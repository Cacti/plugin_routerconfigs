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

abstract class PHPShellConnection extends PHPConnection {
	/*
	0 = success
	1 = couldn't open network connection
	2 = unknown host
	3 = login failed
	4 = No ssh2 extension
	5 = Error enabling device
	*/
	function __construct($classtype, $devicetype, $device, $user, $pass, $enablepw, $buffer_debug = false, $elevated = false) {
		parent::__construct($classtype, $devicetype, $device, $user, $pass, $enablepw, $buffer_debug, $elevated);
	}

	function Download($filename, $backuppath) {
		$tftpserver = read_config_option('routerconfigs_tftpserver');
		$command = $this->deviceType['copytftp'];

		if (stristr($command, '%SERVER%')) {
			$command = str_ireplace('%SERVER%', $tftpserver, $command);
		}

		if (stristr($command, '%FILE%')) {
			$command=str_ireplace('%FILE%', $filename, $command);
		}

		if (!$this->EnsureEnabled()) {
			$this->error(9);
			return false;
		}

		$response = '';
		$result = $this->DoCommand($command, $response);

		$lines = explode("\n", preg_replace('/[\r\n]+/',"\n",$response));
		foreach ($lines as $line) {
			$this->Log("DEBUG: Line: $line");
		}
		$this->Log("DEBUG: Result: ($result)");

		//check if there is questions to confirm
		//i.e: in ASA it ask for confirmation of the source file
		//but this also confirm in case that all command is executed
		//in one line
		$x = 0;
		$ret = 0;
		$confirmed = false;
		$sent_srv = false;
		$sent_dst = false;
		$tftpserver = read_config_option('routerconfigs_tftpserver');

		while (($ret == 0 || $ret == 8) && $x<30 &&
			$this->prompt() != LinePrompt::Enabled &&
			$this->prompt() != LinePrompt::Normal) {
			$x++;

			$try_level='DEBUG:';
			$try_command='';
			$try_prompt='';

			if (stristr($response, 'bytes copied') ||
				stristr($response,'successful')) {
				$this->Log("DEBUG: TFTP Transfer successful");
				break;
			} else if (stristr($response, 'error')) {
				$this->Log("DEBUG: TFTP Transfer ERRORED");
				$this->error(5);
				return false;
			} else if ($this->prompt() == LinePrompt::Confirm) {
				$this->Log("DEBUG: Confirmation prompt found");

				if ($try_prompt == '' && !$confirmed) {
					$try_command=$this->deviceType['confirm'];
					$try_prompt='confirmation:';
					$confirmed = true;
				}
			} else if ($this->prompt() == LinePrompt::Question) {
				$this->Log("DEBUG: Question found");

				if (stristr($response, 'address') && !stristr($response, '[' . $this->ip . ']')) {
					if (!$sent_srv) {
						//send tftpserver if necessary
						$try_level='NOTICE:';
						$try_command=$tftpserver;
						$try_prompt='Server:';
						$sent_srv=true;
					}
				} else if (stristr($response, 'filename')) {
					if (!stristr($response, 'source') && !stristr($response, "[$filename]")) {
						if (!$sent_dst) {
							$try_level='NOTICE:';
							//send filename if necessary
							$try_prompt='Filename (Destination):';
							$try_command=$filename;
							$sent_dst=true;
						}
					} else {
						$try_level='NOTICE:';
						$try_prompt='Filename (Source):';
					}
				}

				if ($try_prompt == '' && !$confirmed && (preg_match('/' . $this->deviceType['promptconfirm'] . '/i', $response) || $this->deviceType['forceconfirm'])) {
					$try_command=$this->deviceType['confirm'];
					$try_prompt='confirmation:';
					$confirmed = true;
				}
			}

			if ($try_prompt == '') {
				$try_prompt = 'a return';
			}
			$this->Log("$try_level Sending $try_prompt $try_command");

			$response = '';
			$result = $this->DoCommand($try_command, $response);
			$lines = explode("\n", preg_replace('/[\r\n]+/',"\n",$response));
			foreach ($lines as $line) {
				$this->Log("DEBUG: Line: $line");
			}
			$this->Log("DEBUG: Result: ($result)");
		}

		$this->lastuser   = '';
		$this->lastchange = '';

		if ($this->deviceType['version'] != '') {
			$length='';
			$this->DoCommand('terminal length 0', $length);
			$this->DoCommand('terminal pager 0', $length);

			$version='';
			$this->DoCommand($this->deviceType['version'], $version);

			$t       = time();
			$version = explode("\n", $version);

			if (sizeof($version)) {
				foreach ($version as $v) {
					if (strpos($v, ' uptime is ') !== FALSE) {
						$uptime = 0;
						$up     = trim(substr($v, strpos($v, ' uptime is ') + 11));
						$up     = explode(',', $up);
						$x      = 0;

						foreach ($up as $u) {
							$s = explode(' ', trim($u));
							switch (trim($s[1])) {
								case 'years':
								case 'year':
									$uptime += ($s[0] * 31449600);
									break;
								case 'months':
								case 'month':
									$uptime += ($s[0] * 2419200);
									break;
								case 'weeks':
								case 'week':
									$uptime += ($s[0] * 604800);
									break;
								case 'days':
								case 'day':
									$uptime += ($s[0] * 86400);
									break;
								case 'hours':
								case 'hour':
									$uptime += ($s[0] * 3600);
									break;
								case 'minutes':
								case 'minute':
									$uptime += ($s[0] * 60);
									break;
								case 'seconds':
								case 'second':
									$uptime += $s[0];
									break;
							}
						}

						$this->lastuser   = '-- Reboot --';
						$this->lastchange = $t - $uptime;
						$ver_diff       = $this->lastchange - $this->device['lastchange'];

						if ($ver_diff < 0) {
							$ver_diff = $ver_diff * -1;
						}

						if ($ver_diff > 60) {
							$this->lastchange = $this->device['lastchange'];
						}
					}
				}
			}
		}
		return true;
	}
}
