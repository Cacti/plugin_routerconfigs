<?php
require_once(__DIR__ . '/PHPConnection.php');

/*
The following PHPSsh class is based loosely on code by Abdul Pallares
adapted from code found on the PHP website in the public domain but
has been heavily modified to work with this plugin and the cacti base
system.

PHPTelnet now relies on the above PHPConnection class which must be
included to have proper functionality.  Most common connection code is
now within PHPConnection with only SSH specific code in this class
*/
class PHPScp extends PHPConnection implements ShellSsh {
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
		parent::__construct('SCP', $devicetype, $device, $user, $pass, $enablepw, $buffer_debug, $elevated);
	}

	function Connect() {
		$rv = 0;

		if (!function_exists('ssh2_auth_password')) {
			$this->Log("DEBUG: PHP doesn't have the ssh2 module installed");
			$this->Log("DEBUG: Follow the installation instructions in the official manual at http://www.php.net/manual/en/ssh2.installation.php");
			$rv=4;
			return $rv;
		}

		if (strlen($this->ip)) {
			if(!($this->connection = @ssh2_connect($this->server, 22))){
				$rv=1;
			} else {
				// try to authenticate
				if (!@ssh2_auth_password($this->connection, $this->user, $this->pass)) {
					$rv=3;
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
		$scp_path = read_config_option('routerconfigs_scp_path');
		$scp_source = $this->deviceType['configfile'];
		$scp_dest = $backuppath . $filename;
		if (empty($scp_path)) {
			$this->Log("DEBUG: Using PHP Internal 'ssh2_scp_recv' command");
			$this->Log("DEBUG: Attempting to download '$scp_source' to '$scp_dest'");
			return ssh2_scp_recv($this->connection, $scp_source, $scp_dest);
		} else {
			$this->Log("DEBUG: Using external '$scp_path' command");
			$scp_args = "'" . $this->user . "'@'" . $this->server . "':'$scp_source' '$scp_dest'";
			$this->Log("DEBUG: Using external '$scp_path' command with \"$scp_args\"");
			exec_background($scp_path, $extra_args);
		}
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

PHPConnection::AddType('PHPScp','scp');
