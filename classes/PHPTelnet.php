<?php
require_once(__DIR__ . '/PHPConnection.php');

/*
The following PHPTelnet is based loosely on code by Antone Roundy
adapted from code found on the PHP website in the public domain
but has been heavily modified to work with this plugin and the
cacti base system.

PHPTelnet now relies on the above PHPConnection class which must
be included to have proper functionality.  Most common connection
code is now within PHPConnection with only Telnet specific code
in this class
*/
class PHPTelnet extends PHPShellConnection implements ShellTelnet {
	var $show_connect_error=1;

	var $loginsleeptime=1000000;

	var $conn1;
	var $conn2;

	/*
	0 = success
	1 = couldn't open network connection
	2 = unknown host
	3 = login failed
	4 = PHP version too low
	*/
	function __construct($devicetype, $device, $user, $pass, $enablepw, $buffer_debug = false, $elevated = false) {
		parent::__construct('Telnet', $devicetype, $device, $user, $pass, $enablepw, $buffer_debug, $elevated);

		$this->conn1=chr(0xFF).chr(0xFB).chr(0x1F).chr(0xFF).chr(0xFB).
			chr(0x20).chr(0xFF).chr(0xFB).chr(0x18).chr(0xFF).chr(0xFB).
			chr(0x27).chr(0xFF).chr(0xFD).chr(0x01).chr(0xFF).chr(0xFB).
			chr(0x03).chr(0xFF).chr(0xFD).chr(0x03).chr(0xFF).chr(0xFC).
			chr(0x23).chr(0xFF).chr(0xFC).chr(0x24).chr(0xFF).chr(0xFA).
			chr(0x1F).chr(0x00).chr(0x50).chr(0x00).chr(0x18).chr(0xFF).
			chr(0xF0).chr(0xFF).chr(0xFA).chr(0x20).chr(0x00).chr(0x33).
			chr(0x38).chr(0x34).chr(0x30).chr(0x30).chr(0x2C).chr(0x33).
			chr(0x38).chr(0x34).chr(0x30).chr(0x30).chr(0xFF).chr(0xF0).
			chr(0xFF).chr(0xFA).chr(0x27).chr(0x00).chr(0xFF).chr(0xF0).
			chr(0xFF).chr(0xFA).chr(0x18).chr(0x00).chr(0x58).chr(0x54).
			chr(0x45).chr(0x52).chr(0x4D).chr(0xFF).chr(0xF0);

		$this->conn2=chr(0xFF).chr(0xFC).chr(0x01).chr(0xFF).chr(0xFC).
			chr(0x22).chr(0xFF).chr(0xFE).chr(0x05).chr(0xFF).chr(0xFC).chr(0x21);
	}

	function Connect() {
		$rv=0;
		$vers=explode('.',PHP_VERSION);
		$needvers=array(4,3,0);
		$j=count($vers);
		$k=count($needvers);
		if ($k<$j) $j=$k;
		for ($i=0;$i<$j;$i++) {
			if (($vers[$i]+0)>$needvers[$i]) break;
			if (($vers[$i]+0)<$needvers[$i]) {
				$error = $this->ConnectError(4);
				$this->Log("Connect 4 error");
				$this->Log($error);
				return 4;
			}
		}

		$this->Log("Disconnect()");
		$this->Disconnect();

		if (strlen($this->ip)) {
			$this->Log("Attempting to open socket to $this->ip:23");
			if (@$this->stream = fsockopen($this->ip, 23)) {
				@fputs($this->stream, $this->conn1);
				$this->Sleep();

				@fputs($this->stream, $this->conn2);

				$this->Log("Looking for ".$this->deviceType['promptuser']);

				// Get Username Prompt
				$r = '';

				$this->Sleep();
				$this->GetResponse($r);

				$x = 0;
				while ($x < 10 &&
					$this->prompt() != LinePrompt::Username &&
					$this->prompt() != LinePrompt::AccessDenied) {

					$x++;

					$this->Log("DEBUG: No Prompt received (" . $this->prompt() . ": $x : " . LinePrompt::Username. ")");
					@fputs($this->stream, "\r");

					$this->Sleep();
					$this->GetResponse($r);
				}

				if ($x == 10) {
					return 8;
				}

				if (!$this->stream ||
					$this->prompt() != LinePrompt::Username ||
					$this->prompt() == LinePrompt::AccessDenied) {

					if ($this->prompt() != LinePrompt::Username) {
						$this->Log("ERROR: Failed to find username prompt");
					}
					if ($this->prompt() == LinePrompt::AccessDenied) {
						$this->Log("ERROR: Access not permitted");
					}
					return 6;
				}

				// Send Username, wait for Password Prompt
				$this->Log("DEBUG: Sending username: $this->user");
				@fputs($this->stream, "$this->user\r");

				$x = 0;
				while ($x < 10 && $this->prompt() !== LinePrompt::Password) {
					$this->Sleep();
					$this->GetResponse($r);

					$x++;
				}

				if ($x == 10) {
					return 3;
				}

				$this->Log("DEBUG: Sending password: $this->pw1_text");
				@fputs($this->stream, "$this->pass\r");

				$r='';

				$this->Sleep();
				$this->GetResponse($r);

				if ($this->prompt() != LinePrompt::Normal && $this->Prompt() != LinePrompt::Enabled) {
					$this->Log('DEBUG: Failed, disconnecting');
					$rv = 3;
					$this->Disconnect();
				}
			} else $rv = 1;
		}

		if ($rv) {
			$this->Log($this->ConnectError($rv));
		}
		return $rv;
	}

	function ConnectError($num) {
		$this->error = $num;
		switch ($num) {
			case 1:
				return 'ERROR: Unable to open telnet network connection';
				break;
			case 2:
				return 'ERROR: Unknown host';
				break;
			case 3:
				return 'ERROR: TELNET login failed';
				break;
			case 4:
				return 'ERROR: Connect failed: Your servers PHP version is too low for PHP Telnet';
				break;
			case 5:
				return 'ERROR: Bad download of config';
				break;
			case 6:
				return 'ERROR: TELNET access not Permitted';
				break;
			case 7:
				return 'ERROR: TELNET no Config uploaded from Router';
				break;
			case 9:
				return 'ERROR: TELNET Enable login failed';
		}

		return '';
	}
}

PHPConnection::AddType('PHPTelnet', 'telnet');
PHPConnection::AddType('PHPTelnet', 'both');
