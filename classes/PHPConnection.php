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

require_once(__DIR__ . '/LinePrompt.php');
require_once(__DIR__ . '/Interfaces.php');

#[AllowDynamicProperties]
abstract class PHPConnection {
	/** @var bool */
	protected $debugbuffer  = false;
	/** @var bool */
	protected $use_usleep   = false;	// change to 1 for faster execution

	/** @var int */
	protected $sleeptime    = 125000;
	/** @var int */
	protected $timeout      = 1; // Seconds to avoid buggies connections

	/** @var mixed */
	protected $connection   = null; // stores the ssh connection pointer
	/** @var mixed */
	protected $stream       = null; // points to the ssh session stream
	/** @var int */
	protected $errorcode    = 0;
	/** @var mixed */
	protected $error        = 0;

	/** @var string */
	protected $debug        = '';
	/** @var string */
	protected $ip           = '';
	/** @var string */
	protected $server       = '';

	/** @var int */
	private $lastPrompt     = 0;
	/** @var bool */
	private $isEnabled      = false;

	// avoid deprecation warnings
	/** @var string|null */
	public $classType       = null;
	/** @var string|null */
	public $pw1_text        = null;
	/** @var string|null */
	public $pw2_text        = null;
	/** @var array<string,mixed> */
	public $device          = [];
	/** @var string */
	public $user            = '';
	/** @var string */
	public $pass            = '';
	/** @var string */
	public $enablepw        = '';
	/** @var array<string,mixed> */
	public $deviceType      = [];
	/** @var bool */
	public $isAlwaysEnabled = false;

	/** @var string|int */
	public $lastuser = '';
	/** @var string|int */
	public $lastchange = '';

	/** @var array<string,array<int,string>> */
	private static $knownTypes = [];

	/**
	 * Opens the connection to this instance's resolved server, using
	 * whatever transport the concrete subclass implements (SSH/SCP/SFTP/
	 * Telnet). Called from the backup flow before Download().
	 *
	 * @return int 0 on success, or a nonzero result code (also recordable
	 *             via ConnectError()) on failure.
	 */
	abstract function Connect();

	/**
	 * Downloads the device's configured configuration file using
	 * whatever transport the concrete subclass implements. Called from
	 * the backup flow after a successful Connect().
	 *
	 * @param string $filename   The local filename to save the downloaded
	 *                           config as.
	 * @param string $backuppath The local directory to save the downloaded
	 *                           config into.
	 *
	 * @return bool|void True/false result of the transfer, or no return
	 *                   value for a transport that runs in the
	 *                   background.
	 */
	abstract function Download($filename, $backuppath);

	/**
	 * Registers a connection class under a named group (e.g. connection
	 * type category), for later lookup via GetTypes(). Called at class
	 * load time by each concrete connection subclass to register itself.
	 *
	 * @param string $classType The connection class name to register.
	 * @param string $groupName The group name to register it under.
	 *
	 * @return void
	 */
	public static function AddType($classType, $groupName) {
		if (!array_key_exists($groupName, PHPConnection::$knownTypes)) {
			PHPConnection::$knownTypes[$groupName] = [];
		}

		PHPConnection::$knownTypes[$groupName][] = $classType;
	}

	/**
	 * Looks up the connection class names registered under a group name
	 * via AddType(). Called wherever the plugin needs to enumerate
	 * available connection types for a given group.
	 *
	 * @param string $wantedGroup The group name to look up; defaults to
	 *                            ''.
	 *
	 * @return array The registered class names for this group, or an
	 *               empty array if none are registered.
	 */
	public static function GetTypes($wantedGroup = '') {
		$wantedGroup = "$wantedGroup";

		$result = (array_key_exists($wantedGroup, PHPConnection::$knownTypes)) ?
			PHPConnection::$knownTypes[$wantedGroup] :
			[];

		return $result;
	}

	/**
	 * Initializes a connection instance for a single device: stores its
	 * credentials (masking passwords for logging), device type, and
	 * elevated/enable-always flag, then resolves its server IP via
	 * setServerDetails(). Called when a concrete connection subclass
	 * (PHPSsh/PHPTelnet/etc.) is constructed for a backup/download attempt.
	 *
	 * @param string $classtype   The concrete connection class name (for
	 *                            logging).
	 * @param array  $devicetype  The device type row (prompt patterns,
	 *                            commands, etc.) for this device.
	 * @param array  $device      The device row being connected to.
	 * @param string $user        The login username.
	 * @param string $pass        The login password.
	 * @param string $enablepw    The enable/elevated password, if any.
	 * @param bool   $bufferDebug Whether to buffer verbose per-line debug
	 *                            output; defaults to false.
	 * @param bool   $elevated    Whether this device type is always
	 *                            considered enabled/elevated; defaults to
	 *                            false.
	 *
	 * @return void
	 */
	function __construct($classtype, $devicetype, $device, $user, $pass, $enablepw, $bufferDebug = false, $elevated = false) {
		$this->classType  = $classtype;

		$this->pw1_text   = plugin_routerconfigs_maskpw($pass);
		$this->pw2_text   = plugin_routerconfigs_maskpw($enablepw);

		$this->device     = $device;
		$this->user       = $user;
		$this->pass       = $pass;
		$this->enablepw   = $enablepw;
		$this->deviceType = $devicetype;

		$this->debug           = '';
		$this->debugbuffer     = $bufferDebug;
		$this->isEnabled       = false;
		$this->isAlwaysEnabled = $elevated;

		$this->setServerDetails();

		$this->Log("DEBUG: Creating $classtype Server: $this->server, User: $this->user, Password: $this->pw1_text, Enablepw: $this->pw2_text, Elevated: $this->isAlwaysEnabled");
		$this->Log('DEBUG: deviceType: ' . json_encode($this->deviceType));
	}

	/**
	 * Logs a message (splitting on CRLF into separate log lines) prefixed
	 * with this connection's IP and class type. Called throughout this
	 * class and its subclasses to report connection progress.
	 *
	 * @param string $message The message to log.
	 *
	 * @return void
	 */
	function Log($message) {
		$lines = explode("\r\n", $message);

		if (cacti_sizeof($lines)) {
			foreach ($lines as $line) {
				plugin_routerconfigs_log("$this->ip ($this->classType) -> $line");
			}
		}
	}

	/**
	 * Resolves the device's configured IP/hostname into this connection's
	 * target IP (via gethostbyname() when it looks like a hostname),
	 * falling back to localhost when blank. Called from the constructor.
	 *
	 * @return void
	 */
	protected function setServerDetails() {
		$this->server = isset($this->device['ipaddress']) ? $this->device['ipaddress'] : '';

		if (strlen($this->server)) {
			if (preg_match('/[^0-9.]/', $this->server)) {
				$ip = gethostbyname($this->server);

				if ($ip == $this->server) {
					$ip = '';
				}
			} else {
				$ip = $this->server;
			}
		} else {
			$ip = '127.0.0.1';
		}

		$this->ip = $ip;
	}

	/**
	 * Sets the connection timeout (in seconds), falling back to 1 second
	 * for an invalid value. Called by the connection setup flow to apply a
	 * device's or device type's configured timeout.
	 *
	 * @param mixed $timeout The timeout in seconds.
	 *
	 * @return void
	 */
	function setTimeout($timeout) {
		if (!is_numeric($timeout) || $timeout <= 0) {
			$timeout = 1;
		}

		$this->Log("DEBUG: Setting timeout to $timeout second(s)");
		$this->timeout = (int) $timeout;
	}

	/**
	 * Sets the delay used between command send/response reads, treating
	 * values of 10 or less as seconds (sleep()) and larger values as
	 * microseconds (usleep()), falling back to a default for an invalid
	 * value. Called by the connection setup flow to apply a device's or
	 * device type's configured sleep interval.
	 *
	 * @param mixed $sleep The delay value (seconds if <= 10, otherwise
	 *                     microseconds).
	 *
	 * @return void
	 */
	function setSleep($sleep) {
		if (!is_numeric($sleep) || $sleep <= 0) {
			$sleep = 125000;
		}

		$u_sleep = $sleep > 10;

		$this->Log("DEBUG: Setting sleep time to $sleep " . ($u_sleep ? 'micro' : '') . 'second(s)');
		$this->use_usleep = $u_sleep;
		$this->sleeptime  = (int) $sleep;
	}

	/**
	 * Returns the accumulated raw debug transcript for this connection.
	 * Called after a backup attempt to persist the connection's debug log.
	 *
	 * @return string The accumulated debug output.
	 */
	function getDebug() {
		return $this->debug;
	}

	/**
	 * Returns this connection's resolved target IP address.
	 *
	 * @return string The resolved IP address.
	 */
	function ip() {
		return $this->ip;
	}

	/**
	 * Gets (and optionally sets) this connection's last recorded error
	 * value. Called throughout the connection/backup flow to record and
	 * check for a connection-level error.
	 *
	 * @param mixed $value A new error value to set, or null to only read
	 *                     the current value; defaults to null.
	 *
	 * @return mixed The current (possibly just-updated) error value.
	 */
	function error($value = null) {
		if ($value !== null) {
			$this->error = $value;
		}

		return $this->error;
	}

	/**
	 * Returns the last LinePrompt constant detected in the device's
	 * response stream (by GetResponse()). Called throughout the connection
	 * flow to decide how to react to the device's current prompt.
	 *
	 * @return int The last detected LinePrompt value.
	 *
	 * @phpstan-impure
	 */
	function prompt() {
		return $this->lastPrompt;
	}

	/**
	 * Whether this connection is currently in an enabled/elevated
	 * privilege state, either detected from the device's prompt or forced
	 * by the device type's 'always enabled' flag. Called throughout the
	 * connection flow (e.g. EnsureEnabled()) to check elevation status.
	 *
	 * @return bool True if currently enabled/elevated, false otherwise.
	 */
	function IsEnabled() {
		return $this->isEnabled || $this->isAlwaysEnabled;
	}

	/**
	 * Whether the ssh2 PHP extension's password-auth function is
	 * available. Thin wrapper around function_exists() so subclasses/tests
	 * can override connection behavior without depending directly on the
	 * ssh2 extension. Called from PHPSsh before attempting an SSH
	 * connection.
	 *
	 * @return bool True if the ssh2 extension appears available, false
	 *              otherwise.
	 */
	protected function sshAvailable() {
		return function_exists('ssh2_auth_password');
	}

	/**
	 * Opens an SSH connection to this instance's resolved server on port
	 * 22. Thin wrapper around ssh2_connect() so subclasses/tests can
	 * override connection behavior. Called from PHPSsh to establish the
	 * SSH session.
	 *
	 * @return resource|false The ssh2 connection resource, or false on
	 *                        failure.
	 */
	protected function sshConnect() {
		return @ssh2_connect($this->server, 22);
	}

	/**
	 * Authenticates the current SSH connection using this instance's
	 * username/password. Thin wrapper around ssh2_auth_password(). Called
	 * from PHPSsh after establishing the SSH connection.
	 *
	 * @return bool True on successful authentication, false otherwise.
	 */
	protected function sshAuthPassword() {
		return @ssh2_auth_password($this->connection, $this->user, $this->pass);
	}

	/**
	 * Returns the negotiated methods/algorithms (including the host key
	 * type) for the current SSH connection. Thin wrapper around
	 * ssh2_methods_negotiated(). Called from sshHostKey() to determine the
	 * host key algorithm in use.
	 *
	 * @return array|false The negotiated methods, or false on failure.
	 */
	protected function sshMethodsNegotiated() {
		return @ssh2_methods_negotiated($this->connection);
	}

	/**
	 * Returns the current SSH connection's host key fingerprint (SHA1,
	 * hex-encoded). Thin wrapper around ssh2_fingerprint(). Called from
	 * sshHostKey() to build the recorded host key info.
	 *
	 * @return string|false The hex-encoded fingerprint, or false on
	 *                      failure.
	 */
	protected function sshFingerprint() {
		return @ssh2_fingerprint($this->connection, SSH2_FINGERPRINT_SHA1 | SSH2_FINGERPRINT_HEX);
	}

	/**
	 * Opens an interactive xterm shell channel on the current SSH
	 * connection. Thin wrapper around ssh2_shell(). Called from PHPSsh
	 * after successful authentication to obtain the interactive session
	 * stream.
	 *
	 * @return resource|false The shell stream resource, or false on
	 *                        failure.
	 */
	protected function sshShell() {
		return @ssh2_shell($this->connection, 'xterm');
	}

	/**
	 * Receives a remote file over the current SSH connection via SCP. Thin
	 * wrapper around ssh2_scp_recv(). Called from PHPScp to download a
	 * device's configuration file.
	 *
	 * @param string $source      The remote file path to receive.
	 * @param string $destination The local file path to write the
	 *                            received content to.
	 *
	 * @return bool True on success, false on failure.
	 */
	protected function sshScpRecv($source, $destination) {
		return @ssh2_scp_recv($this->connection, $source, $destination);
	}

	/**
	 * Builds this SSH connection's host key info (algorithm type and
	 * fingerprint) from the negotiated methods and fingerprint, for
	 * recording/comparison against a previously trusted key. Called from
	 * PHPSsh after connecting, to detect a changed host key.
	 *
	 * @return array|false An array with 'type' and 'fingerprint' keys, or
	 *                     false if either could not be determined.
	 */
	protected function sshHostKey() {
		$methods     = $this->sshMethodsNegotiated();
		$fingerprint = $this->sshFingerprint();

		if (!is_array($methods) || empty($methods['hostkey']) || empty($fingerprint)) {
			return false;
		}

		return [
			'type'        => $methods['hostkey'],
			'fingerprint' => $fingerprint,
		];
	}

	/**
	 * Ensures the connection reaches an enabled/elevated privilege state,
	 * sending an 'en' command and the enable password if needed and not
	 * already enabled. Called from the backup flow before running commands
	 * that require elevated privileges.
	 *
	 * @return bool True if the connection ends up enabled (or already
	 *              was), false otherwise.
	 */
	function EnsureEnabled() {
		// Get > to show we are at the command prompt and ready to input the en command
		// Get # to show we are already enabled so we don't need to enable
		$is_enabled = $this->IsEnabled();

		if ($is_enabled) {
			$this->Log('NOTICE: Already enabled, continuing');

			return true;
		} else {
			$this->Log('NOTICE: Ensuring process is enabled');
		}

		$res = '';
		$x   = 0;

		while ($x < 10 && $this->prompt() != LinePrompt::Enabled && $this->prompt() != LinePrompt::Normal) {
			$r = '';

			if ($this->prompt() == LinePrompt::AnyKey) {
				$this->Log('DEBUG: AnyKey prompt detected, sending space');
				$this->DoCommand(' ', $r, $this->pass);
			} else {
				$this->DoCommand('', $r, $this->pass);
			}

			$res .= $r;

			$x++;

			$this->Log("DEBUG: Attempt $x of 10 to find prompt");
		}

		if ($x < 10) {
			if ($this->enablepw != '' && !$this->IsEnabled() && is_resource($this->stream)) {
				$this->Log('DEBUG: Sending enable command');

				fputs($this->stream, "en\r");

				// Get the password prompt again to input the enable password
				$x = 0;

				while ($x < 10 && $this->Prompt() != LinePrompt::Enabled) {
					$response = '';

					$this->Sleep();
					$this->GetResponse($response);

					if ($this->prompt() == LinePrompt::Normal) {
						$this->DoCommand('en',$response);
					}

					if ($this->prompt() == LinePrompt::Password) {
						$response = '';

						$result = $this->DoCommand($this->enablepw, $response, $this->enablepw);

						if ($result != 0) {
							$this->Log('DEBUG: Enable login failed (' . $result . ')');
							$this->Disconnect();

							break;
						}

						if ($this->IsEnabled()) {
							$this->Log('DEBUG: Ok we are in enabled mode');
						}
					}

					$x++;
				}
			} elseif (empty($this->enablepw)) {
				$this->Log('DEBUG: No enable command set, unable to elevate');
			}
		}

		$this->Log('Process is now ' . ($this->IsEnabled() ? '' : 'NOT ') . 'enabled');

		return $this->IsEnabled();
	}

	/**
	 * Closes the connection's shell stream, optionally sending an 'exit'
	 * command first (unless the 'routerconfigs_exit' setting disables
	 * this). Called at the end of a backup attempt to clean up the
	 * connection.
	 *
	 * @return void
	 */
	function Disconnect() {
		if (is_resource($this->stream)) {
			$exit = read_config_option('routerconfigs_exit') != 'on';

			if ($exit) {
				$this->DoCommand('exit', $junk);
			}

			fclose($this->stream);

			$this->stream = null;
		}
	}

	/**
	 * Checks whether $haystack begins with $needle. Called throughout the
	 * connection flow for simple prefix matching.
	 *
	 * @param string $haystack The string to check.
	 * @param string $needle   The prefix to look for.
	 *
	 * @return bool True if $haystack starts with $needle, false otherwise.
	 */
	function startsWith($haystack, $needle) {
		$length = strlen($needle);

		return (substr($haystack, 0, $length) === $needle);
	}

	/**
	 * Checks whether $haystack ends with $needle. Called throughout the
	 * connection flow for simple suffix matching.
	 *
	 * @param string $haystack The string to check.
	 * @param string $needle   The suffix to look for.
	 *
	 * @return bool True if $haystack ends with $needle (or $needle is
	 *              empty), false otherwise.
	 */
	function endsWith($haystack, $needle) {
		$length = strlen($needle);

		return $length === 0 || (substr($haystack, -$length) === $needle);
	}

	/**
	 * Pauses for this connection's configured delay (usleep() or sleep(),
	 * depending on setSleep()'s unit detection) between sending a command
	 * and reading its response. Called from DoCommand() and EnsureEnabled().
	 *
	 * @return void
	 */
	function Sleep() {
		if ($this->use_usleep) {
			usleep($this->sleeptime);
		} else {
			sleep($this->sleeptime);
		}
	}

	/**
	 * Sends a command line to the device (masking any password value in
	 * the logged debug output) and reads back its response, trimming the
	 * echoed command and prompt from the result. Called throughout the
	 * backup flow to interact with the device's shell.
	 *
	 * @param string $cmd      The command to send.
	 * @param string $response Reference, set to the device's raw response.
	 * @param string $pass     A password value to mask in logged output
	 *                         and in the response, if present; defaults to
	 *                         null.
	 *
	 * @return int The result of GetResponse() (0 on success), or 0 if the
	 *             stream isn't open.
	 */
	function DoCommand($cmd, &$response, $pass = null) {
		$result = 0;

		if (is_resource($this->stream)) {
			$lines = $cmd;

			if ($pass != null) {
				$pass_text = plugin_routerconfigs_maskpw($pass);
				$lines     = str_replace($pass,$pass_text,$lines);
			}

			$lines = explode("\n",$lines);

			foreach ($lines as $line) {
				$this->Log("DEBUG: --> $line");
			}

			fwrite($this->stream, $cmd . PHP_EOL);

			$this->Sleep();

			$result = $this->GetResponse($response, $pass);

			if ($response != '') {
				$response = preg_replace("/^.*?\n(.*)\n([^\n]*)$/", '$2', $response) ?? $response;
			}
		}

		return $result;
	}

	/**
	 * Reads from the connection's stream until a recognized prompt
	 * (normal, enabled, password, username, or confirm, based on the
	 * device type's configured patterns) is found, updating
	 * $this->lastPrompt/$this->isEnabled accordingly and masking any
	 * password value in the accumulated debug output. Called from
	 * DoCommand() and EnsureEnabled() after sending a command.
	 *
	 * @param string $response Reference, appended with the raw data read
	 *                         from the stream.
	 * @param string $pass     A password value to mask in the read data;
	 *                         defaults to null.
	 *
	 * @return int 0 once a recognized prompt is found or the stream
	 *             isn't open (loops otherwise), or 8 if the read exceeds
	 *             the configured timeout.
	 *
	 * @phpstan-impure
	 */
	function GetResponse(&$response, $pass = null) {
		$time_start = microtime(true);

		$data = '';

		if (!is_resource($this->stream)) {
			return 0;
		}

		stream_set_timeout($this->stream, 0, 500000);

		$this->lastPrompt = LinePrompt::None;

		while (true) {
			if (!feof($this->stream)) {
				$buf = fgets($this->stream);
			} else {
				$buf = false;
			}

			if ($buf !== false) {
				if ($pass != null) {
					$buf = str_replace($pass,'__password__',$buf);
				}

				$data .= $buf;
				$response .= $buf;

				$this->debug .= $buf;

				$line_buf = explode("\n", str_replace("\r", '', $buf));

				if ($this->debugbuffer) {
					if (!is_array($line_buf)) {
						$line_buf = [$line_buf];
					}

					foreach ($line_buf as $line) {
						$line      = str_replace("`\r",'',str_replace("\n",'',$line));
						$buf_line  = 'DEBUG: <-- ';
						$buf_line .= $line;

						$this->Log($buf_line);
					}
				}

				$trim_buf = trim($buf);

				if (preg_match("|[a-z0-9\-_ ]+>[ ]*$|i", $buf) === 1) {
					$this->Log('DEBUG: Found Prompt (Normal)');
					$this->isEnabled  = false;
					$this->lastPrompt = LinePrompt::Normal;

					return 0;
				}

				if (preg_match("|[a-z0-9\-_ ]+#[ ]*$|i", $buf) === 1) {
					$this->Log('DEBUG: Found Prompt (Enabled)');
					$this->isEnabled  = true;
					$this->lastPrompt = LinePrompt::Enabled;

					return 0;
				}

				if (!empty($this->deviceType['promptpass']) &&
					preg_match('/' . $this->deviceType['promptpass'] . '/i', $buf) === 1) {
					$this->Log('DEBUG: Found Prompt (Password)');
					$this->lastPrompt = LinePrompt::Password;

					return 0;
				}

				if (!empty($this->deviceType['promptuser']) &&
					preg_match('/' . $this->deviceType['promptuser'] . '/i', $buf) === 1) {
					$this->Log('DEBUG: Found Prompt (Username)');
					$this->lastPrompt = LinePrompt::Username;

					return 0;
				}

				if (!empty($this->deviceType['promptconfirm']) &&
					preg_match('/' . $this->deviceType['promptconfirm'] . '/i', $buf) === 1) {
					$this->Log('DEBUG: Found Prompt (Confirm)');
					$this->lastPrompt = LinePrompt::Confirm;

					return 0;
				}

				if (!empty($this->deviceType['anykey']) &&
					preg_match('/' . $this->deviceType['anykey'] . '/i', $buf) === 1) {
					$this->Log('DEBUG: Found Prompt (AnyKey)');
					$this->lastPrompt = LinePrompt::AnyKey;

					return 0;
				}

				if (stripos($buf, 'Access not permitted.') !== false) {
					$this->Log('DEBUG: Found Prompt (Access Denied)');
					$this->lastPrompt = LinePrompt::AccessDenied;

					return 0;
				}

				if (preg_match('/[\d\w\[]\]\?[^\w]*$/i',$buf) === 1) {
					$this->Log('DEBUG: Found Prompt (Question)');
					$this->lastPrompt = LinePrompt::Question;

					return 0;
				}

				if (preg_match("|[a-z0-9\-_]:[ ]*$|i", $buf) === 1) {
					$this->Log('DEBUG: Found Prompt (Colon)');
					$this->lastPrompt = LinePrompt::Colon;

					return 0;
				}
			}

			$s = socket_get_status($this->stream);

			if ((microtime(true) - $time_start) > $this->timeout) {
				$this->Log("DEBUG: Timeout of {$this->timeout} seconds has been reached");

				return 8;
			}
		}
	}
}
