<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2023 The Cacti Group                                 |
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

require_once(__DIR__ . '/PHPConnection.php');

/**
 * The following PHPSsh class is based loosely on code by Abdul Pallares
 * adapted from code found on the PHP website in the public domain but
 * has been heavily modified to work with this plugin and the cacti base
 * system.
 *
 * PHPTelnet now relies on the above PHPConnection class which must be
 * included to have proper functionality.  Most common connection code is
 * now within PHPConnection with only SSH specific code in this class
 */
class PHPSftp extends PHPConnection implements ShellSsh {
	var $show_connect_error = 1;

	/*
	0 = success
	1 = couldn't open network connection
	2 = unknown host
	3 = login failed
	4 = No ssh2 extension
	5 = Error enabling device
	*/
	function __construct($devicetype, $device, $user, $pass, $enablepw, $buffer_debug = false, $elevated = false) {
		parent::__construct('SFTP', $devicetype, $device, $user, $pass, $enablepw, $buffer_debug, $elevated);
	}

	function Connect() {
		$rv = 0;

		if (!function_exists('ssh2_auth_password')) {
			$this->Log("DEBUG: PHP doesn't have the ssh2 module installed");
			$this->Log("DEBUG: Follow the installation instructions in the official manual at http://www.php.net/manual/en/ssh2.installation.php");

			$rv = 4;

			return $rv;
		}

		if (strlen($this->ip)) {
			if (!$this->connection = @ssh2_connect($this->server, 22)) {
				$rv = 1;
			} else {
				// try to authenticate
				if (!ssh2_auth_password($this->connection, $this->user, $this->pass)) {
					$rv = 3;
				} else {
					$this->Log('DEBUG: okay: logged in...');
				}
			}
		}

		if ($rv) {
			$error = $this->ConnectError($rv);

			$this->Log($error);
		}

		return $rv; //everything goes well ;)
	}

	function Download($filename, $backuppath) {
		$sftp_source = $this->deviceType['configfile'];
		$sftp_dest   = "$backuppath$filename";

		$this->Log("DEBUG: Attempting to download '$sftp_source' to '$sftp_dest'");

		$sftp = @ssh2_sftp($this->connection);

		if (!$sftp) {
			$this->Log('DEBUG: Failed to initialise SFTP subsystem');

			return false;
		}

		$sftpfile = "ssh.sftp://$sftp$sftp_source";

		$stream = fopen($sftpfile, 'r');

		if (!is_resource($stream)) {
			$this->Log("DEBUG: Failed to open stream to file '$sftpfile'");

			return false;
		}

		$contents = fread($stream, filesize($sftpfile));
		if (!$contents) {
			$this->Log("DEBUG: Failed to download file '$sftpfile'");

			return false;
		}

		if (!file_put_contents($scp_dest, $contents)) {
			$this->Log("DEBUG: Failed to write to file '$sftp_dest'");

			return false;
		}

		return true;
	}

	function ConnectError($num) {
		if ($this->show_connect_error) {
			$this->error=$num;
			switch ($num) {
			case 1:
				return 'WARNING: Unable to open ssh network connection';
				break;
			case 2:
				return 'ERROR: Unknown host';
				break;
			case 3:
				return 'ERROR: SSH login failed';
				break;
			case 4:
				return "ERROR: PHP doesn't have the ssh2 module installed\nFollow the installation instructions in the official manual: http://www.php.net/manual/en/ssh2.installation.php";
				break;
			case 5:
				return 'ERROR: Bad download of config';
				break;
			case 6:
				return 'ERROR: SSH access not Permitted';
				break;
			case 7:
				return 'ERROR: SSH no Config uploaded from Router';
				break;
			case 8:
				return "NOTICE: SSH Timeout of {$this->timeout} seconds has been reached";
				break;
			case 9:
				return 'ERROR: SSH Enable login failed';
			}
		}
	}
}

PHPConnection::AddType('PHPSftp','sftp');
