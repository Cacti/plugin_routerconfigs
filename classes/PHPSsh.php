<?php

declare(strict_types = 1);
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

require_once(__DIR__ . '/PHPShellConnection.php');

/*
The following PHPSsh class is based loosely on code by Abdul Pallares
adapted from code found on the PHP website in the public domain but
has been heavily modified to work with this plugin and the cacti base
system.

PHPTelnet now relies on the above PHPConnection class which must be
included to have proper functionality.  Most common connection code is
now within PHPConnection with only SSH specific code in this class
*/
class PHPSsh extends PHPShellConnection implements ShellSsh {
	/** @var int Seconds to wait for a capture command to finish. */
	protected $captureLimit = 120;

	/** @var int */
	var $show_connect_error = 1;

	/*
	0 = success
	1 = couldn't open network connection
	2 = unknown host
	3 = login failed
	4 = No ssh2 extension
	5 = Error enabling device
	*/
	/**
	 * Initializes an SSH interactive-shell connection for a device. Called
	 * when a new PHPSsh object is constructed for a backup attempt.
	 *
	 * @param array  $devicetype   The device type row (prompt patterns,
	 *                             commands, etc.) for this device.
	 * @param array  $device       The device row being connected to.
	 * @param string $user         The login username.
	 * @param string $pass         The login password.
	 * @param string $enablepw     The enable/elevated password, if any.
	 * @param bool   $buffer_debug Whether to buffer verbose per-line debug
	 *                             output; defaults to false.
	 * @param bool   $elevated     Whether this device type is always
	 *                             considered enabled/elevated; defaults to
	 *                             false.
	 *
	 * @return void
	 */
	function __construct($devicetype, $device, $user, $pass, $enablepw, $buffer_debug = false, $elevated = false) {
		parent::__construct('SSH', $devicetype, $device, $user, $pass, $enablepw, $buffer_debug, $elevated);
	}

	/**
	 * Opens and authenticates an SSH connection and opens an interactive
	 * shell channel, verifying the remote host's SSH key against any
	 * previously recorded fingerprint. Called from the backup flow before
	 * running commands via DoCommand()/Download().
	 *
	 * @return int 0 on success, or a nonzero result code (also recordable
	 *             via ConnectError()) on failure.
	 */
	function Connect() {
		$rv = 0;

		if (!$this->sshAvailable()) {
			$this->Log("DEBUG: PHP doesn't have the ssh2 module installed");
			$this->Log('DEBUG: Follow the installation instructions in the official manual at http://www.php.net/manual/en/ssh2.installation.php');
			$rv = 4;

			return $rv;
		}

		if (strlen($this->ip)) {
			if (!($this->connection = $this->sshConnect())) {
				$rv = 1;
			} elseif (!plugin_routerconfigs_verify_ssh_hostkey($this->device['id'], $this->sshHostKey())) {
				$this->Log('ERROR: SSH host key verification failed for ' . $this->server);

				return RCONFIG_CONNECT_HOSTKEY_FAILED;
			} else {
				// try to authenticate
				if (!$this->sshAuthPassword()) {
					$rv = 3;
				} else {
					// A capture command runs on its own exec channel, so no
					// interactive shell is needed.
					if (plugin_routerconfigs_capture_command($this->deviceType) !== '' || ($this->stream = $this->sshShell())) {
						$this->Log('DEBUG: okay: logged in...');
					}
				}
			}
		}

		if ($rv) {
			$error = $this->ConnectError($rv);
			$this->Log($error ?? '');
		}

		return $rv; // everything goes well ;)
	}

	/**
	 * Downloads the device's configuration. A device type with a capture
	 * command has that command run on an exec channel and its output saved
	 * to the incoming file the backup flow reads; any other device type
	 * uses the interactive shell's copy-to-TFTP. Called from the backup
	 * flow after a successful Connect().
	 *
	 * @param string $filename   The incoming file name the backup flow
	 *                           reads the configuration from.
	 * @param string $backuppath The directory the incoming file is in.
	 *
	 * @return bool True if the configuration was obtained, false on error.
	 */
	function Download($filename, $backuppath) {
		$command = plugin_routerconfigs_capture_command($this->deviceType);

		if ($command === '') {
			return parent::Download($filename, $backuppath);
		}

		$this->lastuser   = '';
		$this->lastchange = '';

		return $this->captureConfig($command, $backuppath . $filename);
	}

	/**
	 * Runs $command on an exec channel, reads its output until the device
	 * closes the channel, and writes it to $destination with Unix line
	 * endings, since the backup flow adds its own CRLFs. The capture limit
	 * bounds the whole read, so a command that never finishes, or keeps
	 * producing output, fails rather than running on. The file is written
	 * under a temporary name and renamed into place only once it is
	 * complete, so a failed write never leaves a partial file for the backup
	 * flow to archive.
	 *
	 * @param string $command     The command whose output is the
	 *                            configuration.
	 * @param string $destination The incoming file to write.
	 *
	 * @return bool True if a non-empty configuration was written.
	 */
	protected function captureConfig($command, $destination) {
		$this->Log("DEBUG: Capturing configuration with '$command'");

		$channel = $this->sshExec($command);

		if (!is_resource($channel)) {
			$this->Log("ERROR: Unable to run '$command' on the device");

			return false;
		}

		stream_set_blocking($channel, false);

		$output   = '';
		$timedout = false;
		$deadline = time() + $this->captureLimit;

		while (!feof($channel)) {
			if (time() >= $deadline) {
				$timedout = true;

				break;
			}

			$chunk = fread($channel, 8192);

			if ($chunk !== false && $chunk !== '') {
				$output .= $chunk;

				continue;
			}

			$this->Sleep();
		}

		fclose($channel);

		if ($timedout) {
			// A partial configuration must not be saved as a good backup.
			$this->Log("ERROR: Timed out after {$this->captureLimit} seconds reading the output of '$command'");

			return false;
		}

		$output = preg_replace('/\r\n?/', "\n", $output) ?? $output;

		$this->Log('DEBUG: Captured ' . strlen($output) . ' bytes');

		if (trim($output) === '') {
			$this->Log("ERROR: '$command' returned no output");

			return false;
		}

		if (!$this->writeCapture($output, $destination)) {
			$this->Log("ERROR: Failed to write the captured configuration to '$destination'");

			return false;
		}

		return true;
	}

	/**
	 * Writes $data to $destination through a temporary file in the same
	 * directory, so the destination only ever holds the whole of $data. The
	 * temporary file is removed if anything fails.
	 *
	 * @param string $data        The captured configuration.
	 * @param string $destination The incoming file to create.
	 *
	 * @return bool True if $destination now holds all of $data.
	 */
	protected function writeCapture($data, $destination) {
		$dir = dirname($destination);

		if (!is_dir($dir) || !is_writable($dir)) {
			return false;
		}

		$temp = @tempnam($dir, '.capture-');

		if ($temp === false) {
			return false;
		}

		if (@file_put_contents($temp, $data) !== strlen($data) || !@rename($temp, $destination)) {
			@unlink($temp);

			return false;
		}

		return true;
	}

	/**
	 * Translates a Connect()/Download() numeric result code into a
	 * human-readable error message, recording the code on $this->error.
	 * Called from Connect() when a nonzero result is returned.
	 *
	 * @param int $num The result code to translate.
	 *
	 * @return string|null The corresponding error message, or null if
	 *                     $show_connect_error is disabled or the code is
	 *                     unrecognized.
	 */
	function ConnectError($num) {
		if ($this->show_connect_error) {
			$this->error = $num;

			switch ($num) {
				case 1:
					return 'WARNING: Unable to open ssh network connection';
				case 2:
					return 'ERROR: Unknown host';
				case 3:
					return 'ERROR: SSH login failed';
				case 4:
					return "ERROR: PHP doesn't have the ssh2 module installed\nFollow the installation instructions in the official manual: http://www.php.net/manual/en/ssh2.installation.php";
				case 5:
					return 'ERROR: Bad download of config';
				case 6:
					return 'ERROR: SSH access not Permitted';
				case 7:
					return 'ERROR: SSH no Config uploaded from Router';
				case 8:
					return "NOTICE: SSH Timeout of {$this->timeout} seconds has been reached";
				case 9:
					return 'ERROR: SSH Enable login failed';
			}

			return null;
		}

		return null;
	}
}

PHPConnection::AddType('PHPSsh', 'ssh');
PHPConnection::AddType('PHPSsh', 'both');
