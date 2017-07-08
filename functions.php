<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2007-2017 The Cacti Group                                 |
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
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

function display_tabs () {
	global $config;

	/* ================= input validation ================= */
	get_filter_request_var('tab', FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '/^([a-zA-Z]+)$/')));
	/* ==================================================== */

	$tabs = array(
		'devices'  => __('Devices', 'routerconfigs'),
		'devtypes' => __('Device Types', 'routerconfigs'),
		'accounts' => __('Authentication', 'routerconfigs'),
		'backups'  => __('Backups', 'routerconfigs'),
		'compare'  => __('Compare', 'routerconfigs')
	);

   /* set the default tab */
    load_current_session_value('tab', 'sess_rc_tabs', 'devices');
    $current_tab = get_nfilter_request_var('tab');

    $header_label = __('Technical Support [ %s ]', $tabs[get_request_var('tab')], 'routerconfigs');

	if (sizeof($tabs)) {
		/* draw the tabs */
		print "<div class='tabs'><nav><ul>\n";

		foreach (array_keys($tabs) as $tab_short_name) {
			print "<li class='subTab'><a class='tab" . (($tab_short_name == $current_tab) ? " selected'" : "'") .
				" href='" . htmlspecialchars($config['url_path'] .
				'plugins/routerconfigs/router-' . $tab_short_name . '.php' .
				'?tab=' . $tab_short_name) .
				"'>" . $tabs[$tab_short_name] . "</a></li>\n";
		}

		api_plugin_hook('user_admin_tab');

		print "</ul></nav></div>\n";
	}
}

function plugin_routerconfigs_redownload_failed () {
	$t      = time();
	$passed = array();

	// Get device that have not backed up in 24 hours + 30 minutes and that haven't been tried in the last 30 minutes
	$devices = db_fetch_assoc("SELECT *
		FROM plugin_routerconfigs_devices
		WHERE enabled = 'on'
		AND ($t - (schedule * 86400)) - 3600 > lastbackup
		AND $t - lastattempt > 1800", false);

	if (!empty($devices)) {
		db_execute("UPDATE plugin_routerconfigs_devices
			SET lastattempt = $t
			WHERE $t - lastbackup > 88200
			AND $t - lastattempt > 1800");

		foreach ($devices as $device) {
			print $device['hostname'] . "\n";

			plugin_routerconfigs_download_config($device);
			$t = time() - 120;
			$f = db_fetch_assoc_prepared('SELECT *
				FROM plugin_routerconfigs_backups
				WHERE btime > ?
				AND device = ?',
				array($t, $device['id']));

			if (!empty($f)) {
				$passed[] = array ('hostname' => $device['hostname']);
			}
			sleep(10);
		}
	}

	if (!empty($passed)) {
		$message = __("A successful backup has now been completed on these devices\n--------------------------------\n", 'routerconfigs');
		foreach ($passed as $f) {
			$message .= $f['hostname'] . "\n";
		}
		echo $message;

		$email = read_config_option('routerconfigs_email');
		$from = read_config_option('routerconfigs_from');
		if (strlen($email) > 1) {
			if (strlen($from) < 2) {
				$from = 'ConfigBackups@reyrey.com';
			}
		}
		send_mail($email, $from, __('Network Device Configuration Backups - Reattempt', 'routerconfigs'), $message, $filename = '', $headers = '', $fromname = __('Config Backups', 'routerconfigs'));
	}
}

function plugin_routerconfigs_retention () {
	$backuppath = read_config_option('routerconfigs_backup_path');
	if (!is_dir($backuppath) || strlen($backuppath) < 2) {
		print __('Backup Path is not set or is not a directory', 'routerconfigs');
		exit;
	}

	$days = read_config_option('routerconfigs_retention');
	if ($days < 1 || $days > 365) {
		$days = 30;
	}
	$time = time() - ($days * 24 * 60 * 60);
	$backups = db_fetch_assoc_prepared('SELECT *
		FROM plugin_routerconfigs_backups
		WHERE btime < ?',
		array($time));

	if (sizeof($backups)) {
		foreach ($backups as $backup) {
			$dir = $backup['directory'];
			$filename = $backup['filename'];
			@unlink("$backuppath/$dir/$filename");
		}
	}

	db_execute_prepared('DELETE FROM plugin_routerconfigs_backups
		WHERE btime < ?',
		array($time));
}

function plugin_routerconfigs_check_config ($data) {
	if (preg_match('/\n[^\w]*end[^\w]*$/',$data))
		return TRUE;
	return FALSE;
}

function plugin_routerconfigs_download_config ($device) {
	$info = plugin_routerconfigs_retrieve_account($device['id']);
	$dir  = $device['directory'];
	$ip   = $device['ipaddress'];

	$backuppath = read_config_option('routerconfigs_backup_path');
	if (!is_dir($backuppath) || strlen($backuppath) < 2) {
		print __('FATAL: Backup Path is not set or is not a directory', 'routerconfigs');
		exit;
	}

	$tftpserver = read_config_option('routerconfigs_tftpserver');
	if (strlen($tftpserver) < 2) {
		print __('FATAL: TFTP Server is not set', 'routerconfigs');
		exit;
	}

	$tftpfilename = $device['hostname'];
	$filename     = $tftpfilename;

	$devicetype = db_fetch_row_prepared('SELECT *
		FROM plugin_routerconfigs_devicetypes
		WHERE id = ?',
		array($device['devicetype']));

	if (empty($devicetype)){
		$devicetype = array('username' => 'username:',
			'password' => 'password:',
			'copytftp' => 'copy start tftp',
			'version' => 'show version',
			'forceconfirm' => '',
			'checkendinconfig' => 'on'
		);
	}

	$connection = new PHPSsh();
	if (($result = $connection->Connect($device['ipaddress'], $info['username'], $info['password'], $info['enablepw'], $devicetype))) {
		$connection = NULL;
		$connection = new PHPTelnet();
		$result = $connection->Connect($device['ipaddress'], $info['username'], $info['password'], $info['enablepw'], $devicetype);
		plugin_routerconfigs_log($device['ipaddress'] . '-> DEBUG: telnet');
	}
	$debug = $connection->debug;

	db_execute_prepared('UPDATE plugin_routerconfigs_devices
		SET lastattempt = ? WHERE id = ?',
		array(time(), $device['id']));

	if ($result == 0) {
		$command = $devicetype['copytftp'];
		if (stristr($command, '%SERVER%')) {
			$command = str_replace('%SERVER%', $tftpserver, $command);
		}

		if (stristr($command, '%FILE%')) {
			$command=str_replace('%FILE%', $filename, $command);
		}

		plugin_routerconfigs_log($ip . "-> DEBUG: command to execute $command");
		$connection->DoCommand($command, $result);
		$debug .= $result;
		plugin_routerconfigs_log($ip . "-> DEBUG: copy tftp result=$result");

		//check if there is questions to confirm
		//i.e: in ASA it ask for confirmation of the source file
		//but this also confirm in case that all command is executed
		//in one line
		while (preg_match('/[\d\w]\]\?[^\w]*$/',$result)) {
			$connection->DoCommand('', $result); //Just send an enter to confirm a question
			$debug .= $result;
			plugin_routerconfigs_log($ip . "-> DEBUG: confirm data result=$result");
		}

		//send tftpserver if necessary
		if (stristr($debug, 'address') && !stristr($debug, "[$ip]")) {
			$connection->DoCommand($tftpserver, $result);
			$debug .= $result;
			$connection->GetResponse($result);
			plugin_routerconfigs_log($ip . "-> DEBUG: send serverip result=$debug");
		}

		//send filename if necessary
		if (stristr($debug, 'filename') && !stristr($debug, "[$filename]")) {
			$connection->DoCommand($filename, $result);
			plugin_routerconfigs_log($ip . "-> DEBUG: send filename result=$result");
			$debug .= $result;
		}

		if (strpos($result, 'confirm') !== FALSE || strpos($result, 'to tftp:') !== FALSE || $devicetype['forceconfirm']) {
			$connection->DoCommand('y', $result);
			$debug .= $result;
			plugin_routerconfigs_log($ip . "-> DEBUG: confirm result=$result");
		}

		if (stristr($result, 'bytes copied')) {
			plugin_routerconfigs_log($ip . "-> SUCCESSFUL TFTP TRANSFER!!!");
		}

		$x = 0;
		while (!stristr($result, 'bytes copied') && !stristr($result,'successfully') && !stristr($result, 'error') && $x<30) {
			$connection->GetResponse($result);
			$debug .= $result;
			$x++;
		}

		$data = '';

		plugin_routerconfigs_log($ip . "-> CHECKING FOR valid file at $backuppath/$tftpfilename");

		if (file_exists("$backuppath/$tftpfilename")) {
			clearstatcache();
			$file = fopen("$backuppath/$tftpfilename", 'r');
			if (filesize("$backuppath/$tftpfilename") > 0) {
				$data = fread($file, filesize("$backuppath/$tftpfilename"));
			}
			fclose($file);
			@unlink("$backuppath/$tftpfilename");
		}else{
			$connection->error = 7;
			plugin_routerconfigs_save_error ($device['id'], $connection);
			plugin_routerconfigs_save_debug($device, $debug);
			$connection->Disconnect();
			return false;
		}

		if (!plugin_routerconfigs_check_config ($data) && $devicetype['checkendinconfig'] == 'on') {
			$connection->error = 5;
			plugin_routerconfigs_save_error ($device['id'], $connection);
			plugin_routerconfigs_save_debug($device, $debug);
			plugin_routerconfigs_log($device['ipaddress'] . '-> DEBUG: checking end in config');
			$connection->Disconnect();
			return false;
		}

		if ($devicetype['checkendinconfig'] == 'on') {
			plugin_routerconfigs_log($device['ipaddress'].'-> DEBUG: config checked');
		} else {
			plugin_routerconfigs_log($device['ipaddress'].'-> DEBUG: config not checked');
		}

		$data       = str_replace ("\n", "\r\n", $data);
		$data2      = explode("\r\n", $data);
		$lastchange = '';
		$lastuser   = '';
		if (sizeof($data2)) {
			foreach ($data2 as $d) {
				if (strpos($d, 'Last configuration change at') !== FALSE) {
					$lastchange = substr($d, strpos($d, 'change at') + 10, strpos($d, ' by ') - (strpos($d, 'change at') + 10));

					$t = explode(' ', $lastchange);
					if (isset($t[5])) {
						$t = array($t[3], $t[4], $t[5], $t[0], $t[1]);
						$t = implode(' ', $t);
						$lastchange = strtotime($t);
						if (substr($d, strpos($d, ' by ')) !== FALSE) {
							$lastuser = substr($d, strpos($d, ' by ') + 4);
						}
					}
				}

				if (substr($d, 0, 9) == 'hostname ') {
					$filename = trim(substr($d, 9));
					if ($device['hostname'] != $filename) {
						db_execute_prepared('UPDATE plugin_routerconfigs_devices
							SET hostname = ?
							WHERE id = ?',
							array($filename, $device['id']));
					}
				}

				if (substr($d, 0, 17) == 'set system name ') {
					$filename = trim(substr($d, 17));
					if ($device['hostname'] != $filename) {
						db_execute_prepared('UPDATE plugin_routerconfigs_devices
							SET hostname = ?
							WHERE id = ?',
							array($filename, $device['id']));
					}
				}
			}
		}

		if ($lastchange != '' && $lastchange != $device['lastchange']) {
			db_execute_prepared('UPDATE plugin_routerconfigs_devices
				SET lastchange = ?, username = ?
				WHERE id = ?',
				array($lastchange, $lastuser, $device['id']));
		} elseif ($lastchange == '' && $devicetype['version'] != '') {
			$connection->DoCommand('terminal length 0', $version);
			$connection->DoCommand('terminal pager 0', $version);
			$connection->DoCommand($devicetype['version'], $version);

			$t       = time();
			$debug  .= $version;
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

						$lastuser   = '-- Reboot --';
						$lastchange = $t - $uptime;
						$diff       = $lastchange - $device['lastchange'];

						if ($diff < 0) {
							$diff = $diff * -1;
						}

						if ($diff > 60) {
							db_execute_prepared('UPDATE plugin_routerconfigs_devices
								SET lastchange = ?, username = ?
								WHERE id = ?',
								array($lastchange, $lastuser, $device['id']));
						} else {
							$lastchange = $device['lastchange'];
						}
					}
				}
			}
		}

		$connection->Disconnect();

		plugin_routerconfigs_log($ip . '-> DEBUG: datalen' . strlen($data));

		if (strlen($data) > 100) {
			if (!is_dir("$backuppath/$dir")) {
				mkdir("$backuppath/$dir", 0777, true);
			}

			db_execute_prepared('UPDATE plugin_routerconfigs_devices
				SET lastbackup = ?
				WHERE id = ?',
				array(time(), $device['id']));

			$date = date('Y-m-d-Hi');
			$file = fopen("$backuppath/$dir/$filename-$date", 'w');

			plugin_routerconfigs_log($ip . '-> DEBUG: Attempting to backup to filename ' . $backuppath . '/' . $dir . '/' . $filename . '-' . $date);

			fwrite($file, $data);
			fclose($file);

			$data2 = $data;
			$t     = time();
			if ($lastchange == '') {
				 $lastchange = 0;
			}

			db_execute_prepared('INSERT INTO plugin_routerconfigs_backups
				(device, btime, directory, filename, config, lastchange, username)
				VALUES (?, ?, ?, ?, ?, ?, ?)',
				array($device['id'], $t, $dir, "$filename-$date", $data2, $lastchange, $lastuser));
		} else {
			plugin_routerconfigs_save_error($device['id'], $connection);
			plugin_routerconfigs_save_debug($device, $debug);

			return false;
		}
	} else {
		plugin_routerconfigs_save_error($device['id'], $connection);
		plugin_routerconfigs_save_debug($device, $debug);
		plugin_routerconfigs_log($ip . '-> DEBUG: Connection failed');

		return false;
	}

	plugin_routerconfigs_save_error($device['id'], $connection);
	plugin_routerconfigs_save_debug($device, $debug);
	plugin_routerconfigs_log($device['ipaddress'] . '-> NOTICE: Backed up');

	return true;
}

function plugin_routerconfigs_save_debug($device, $debug) {
	$debug = base64_encode($debug);
	//echo "Saving Debug\n";
	db_execute_prepared('UPDATE plugin_routerconfigs_devices
		SET debug = ?
		WHERE id = ?',
		array($debug, $device['id']));
}

function plugin_routerconfigs_save_error($id, $telnet) {
	$error = $telnet->ConnectError($telnet->error);
	db_execute_prepared('UPDATE plugin_routerconfigs_devices
		SET lasterror = ?
		WHERE id = ?',
		array($error, $id));
}

function plugin_routerconfigs_retrieve_account ($device) {
	if ($device == '') {
		return false;
	}

	$info = db_fetch_row_prepared('SELECT *
		FROM plugin_routerconfigs_accounts AS pra
		INNER JOIN plugin_routerconfigs_devices AS prd
		ON pra.id=prd.account
		WHERE prd.id = ?',
		array($device));

	if (isset($info['username'])) {
		$info['password'] = plugin_routerconfigs_decode($info['password']);
		$info['enablepw'] = plugin_routerconfigs_decode($info['enablepw']);
		return $info;
	}

	return false;
}

function plugin_routerconfigs_decode($info) {
	plugin_routerconfigs_log("Info passed to decode: $info");
	$info = base64_decode($info);
	plugin_routerconfigs_log("Info Base64 decoded: $info");

	$info = unserialize($info);
	plugin_routerconfigs_log("Info Unserialized");

	return $info['password'];
}

function plugin_routerconfigs_encode($info) {
	$crypt = array(rand(1, time()) => rand(1, time()), 'password' => '', rand(1, time()) => rand(1, time()));
	$crypt['password'] = $info;
	$crypt = serialize($crypt);
	$crypt = base64_encode($crypt);
	return $crypt;
}

/*
//Log messages to cacti log or syslog
//This function is the same as thold plugin with a litle changes
//to respect cacti log level settings
*/
function plugin_routerconfigs_log($string) {
	global $config;

	$environ = 'ROUTERCONFIGS';
	/* fill in the current date for printing in the log */
	$date = date('m/d/Y h:i:s A');

	/* determine how to log data */
	$logdestination = read_config_option('log_destination');
	$logfile        = read_config_option('path_cactilog');

	/* format the message */
	$message = "$date - " . $environ . ': ' . $string . "\n";

	$log_level = 1;

	if (substr_count($string,'ERROR:') || substr_count($string,'STATS:')) {
		$log_level = 2;
	} else if (substr_count($string,'WARNING:') || substr_count($string,'NOTICE:')) {
		$log_level = 3;
	} else if (substr_count($string,'DEBUG:')) {
		$log_level = 5;
	}

	/* Log to Logfile */
	if ((($logdestination == 1) || ($logdestination == 2)) && read_config_option('log_verbosity') >= $log_level) {
		$log_type = 'note';

		if ($logfile == '') {
			$logfile = $config['base_path'] . '/log/cacti.log';
		}

		/* echo the data to the log (append) */
		$fp = @fopen($logfile, 'a');

		if ($fp) {
			@fwrite($fp, $message);
			fclose($fp);
		}
	}

	/* Log to Syslog/Eventlog */
	/* Syslog is currently Unstable in Win32 */
	if (($logdestination == 2) || ($logdestination == 3)) {
		$string   = strip_tags($string);
		$log_type = '';

		if (substr_count($string,'ERROR:')) {
			$log_type = 'err';
		} else if (substr_count($string,'WARNING:')) {
			$log_type = 'warn';
		} else if (substr_count($string,'STATS:')) {
			$log_type = 'stat';
		} else if (substr_count($string,'NOTICE:')) {
			$log_type = 'note';
		}

		if (strlen($log_type)) {
			define_syslog_variables();

			if ($config['cacti_server_os'] == 'win32') {
				openlog('Cacti', LOG_NDELAY | LOG_PID, LOG_USER);
			} else {
				openlog('Cacti', LOG_NDELAY | LOG_PID, LOG_SYSLOG);
			}

			if (($log_type == 'err') && (read_config_option('log_perror'))) {
				syslog(LOG_CRIT, $environ . ': ' . $string);
			}

			if (($log_type == 'warn') && (read_config_option('log_pwarn'))) {
				syslog(LOG_WARNING, $environ . ': ' . $string);
			}

			if ((($log_type == 'stat') || ($log_type == 'note')) && (read_config_option('log_pstats'))) {
				syslog(LOG_INFO, $environ . ': ' . $string);
			}

			closelog();
		}
	}
}

/*
PHPSsh
by Abdul Pallares: abdul(at)riereta.net
following the PHPTelnet structure
and looking arround on Internet
*/
class PHPSsh {
	var $show_connect_error = 1;
	var $timeout    = 1; //Seconds to avoid buggies connections
	var $connection = NULL; //stores the ssh connection pointer
	var $stream     = NULL; //points to the ssh session stream
	var $errorcode  = 0;
	var $use_usleep = 1;	// change to 1 for faster execution
		// don't change to 1 on Windows servers unless you have PHP 5
	var $debug = '';
	var $error = 0;

	/*
	0 = success
	1 = couldn't open network connection
	2 = unknown host
	3 = login failed
	4 = No ssh2 extension
	5 = Error enabling device
	*/
	function Connect($server, $user, $pass, $enablepw, $devicetype) {
		echo "\nServer: $server User: $user Password: $pass Enablepw: $enablepw Devicetype: $devicetype\n";

		$this->debug = '';
		$rv = 0;

		if (!function_exists('ssh2_auth_password')) {
			plugin_routerconfigs_log($server . "-> DEBUG: PHP doesn't have the ssh2 module installed\nFollow the installation instructions in the official manual at http://www.php.net/manual/en/ssh2.installation.php");
			$rv=4;
			return $rv;
		}

		if (strlen($server)) {
			if (preg_match('/[^0-9.]/', $server)) {
				$ip = gethostbyname($server);
				if ($ip == $server) {
					$ip = '';
					$rv = 2;
				}
			} else {
				$ip = $server;
			}
		} else {
			$ip = '127.0.0.1';
		}

		if (strlen($ip)) {
			if(!($this->connection = ssh2_connect($server, 22))){
				$rv=1;
			} else {
				// try to authenticate
				if (!@ssh2_auth_password($this->connection, $user, $pass)) {
					$rv=3;
				} else {
					if ($this->stream = @ssh2_shell($this->connection,'xterm')) {
						$this->GetResponse($response);
						plugin_routerconfigs_log($ip . '-> DEBUG: okay: logged in...');
					}

					if (strstr($response,'>')) {
						$this->DoCommand('en',$response);

						if (stristr($response,$devicetype['password'])) {
							if($this->DoCommand($enablepw, $response)){
								plugin_routerconfigs_log($ip . '-> DEBUG: Enable login failed');
								$this->Disconnect();
								$rv=5;
							}

							if (strstr($response,'#')) {
								plugin_routerconfigs_log($ip . '-> DEBUG: Ok we are in enabled mode');
							}
						}
					}
				}
			}
		}

		if ($rv) {
			$error = $this->ConnectError($rv);
			if (strstr($error,'ERROR')) {
				echo $ip . '-> ' . $error . '<br>';
			}
			plugin_routerconfigs_log($ip . '-> ' . $error);
		}

		return $rv; //everything goes well ;)
	}

	function Disconnect($exit=1) {
		if ($this->stream) {
			if ($exit) {
				$this->DoCommand('exit', $junk);
			}

			fclose($this->stream);

			$this->stream = NULL;
		}
	}

	function DoCommand($cmd, &$response) {
		$result = 0;
		if ($this->connection) {
			fwrite($this->stream,$cmd.PHP_EOL);
			$this->Sleep();
			$result = $this->GetResponse($response);
			$response = preg_replace("/^.*?\n(.*)\n([^\n]*)$/", "$2", $response);
		}

		return $result;
	}

	function Sleep() {
		if ($this->use_usleep) {
			usleep($this->sleeptime);
		} else {
			sleep(1);
		}
	}

	function GetResponse(&$response) {
		global $devicetype;
		$time_start = time();
		$data = '';

		while (true && isset($this->stream)) {
			while ($buf = fgets($this->stream)) {
				$response .= $buf;
				plugin_routerconfigs_log("DEBUG: SSH buffer=$buf");
				if (strstr($buf,'#') || strstr($buf,'>') || strstr($buf,']?') || strstr($buf, $devicetype['password'])) {
					return 0;
				}
			}

			if ((time()-$time_start) > $this->timeout) {
				plugin_routerconfigs_log("DEBUG: SSH timeout of {$this->timeout} seconds has been reached");

				return 8;
			}
		}

		return 0;
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

/*
PHPTelnet 1.1
by Antone Roundy
adapted from code found on the PHP website
public domain
*/
class PHPTelnet {
	var $show_connect_error=1;

	var $use_usleep=1;	// change to 1 for faster execution
		// don't change to 1 on Windows servers unless you have PHP 5
	var $sleeptime=125000;
	var $loginsleeptime=1000000;

	var $fp=NULL;
	var $loginprompt;
	var $error = 0;

	var $conn1;
	var $conn2;

	var $debug;

	/*
	0 = success
	1 = couldn't open network connection
	2 = unknown host
	3 = login failed
	4 = PHP version too low
	*/
	function Connect($server, $user, $pass, $enablepw, $devicetype) {
		$this->debug = '';
		$rv=0;
		$vers=explode('.',PHP_VERSION);
		$needvers=array(4,3,0);
		$j=count($vers);
		$k=count($needvers);
		if ($k<$j) $j=$k;
		for ($i=0;$i<$j;$i++) {
			if (($vers[$i]+0)>$needvers[$i]) break;
			if (($vers[$i]+0)<$needvers[$i]) {
				echo $this->ConnectError(4);
				return 4;
			}
		}

		$this->Disconnect();

		if (strlen($server)) {
			if (preg_match('/[^0-9.]/',$server)) {
				$ip=gethostbyname($server);
				if ($ip==$server) {
					$ip='';
					$rv=2;
				}
			} else $ip=$server;
		} else $ip='127.0.0.1';

		if (strlen($ip)) {
			if (@$this->fp = fsockopen($ip, 23)) {
				@fputs($this->fp, $this->conn1);
				$this->Sleep();

				@fputs($this->fp, $this->conn2);
				$this->Sleep();
				$this->GetResponse($r);
				if (strpos($r, $devicetype['username']) === FALSE && strpos($r, 'Access not permitted.') !== FALSE || !$this->fp) {
					echo $r;
					return 6;
				}
				echo "Initial response: ";
				echo $r;

// Get Username Prompt
				$res = $r;

				$x = 0;
				while ($x < 10) {
					$this->GetResponse($r);
					echo $r;
					$res .= $r;

					echo "\nChecking response: $res\n";

					if (strpos($res, $devicetype['username']) !== FALSE) {
						echo "\nSending username: $user\n";
						fputs($this->fp, "$user\r");
						sleep(1);
						break;
					}else{
						plugin_routerconfigs_log("DEBUG: No Prompt received");
						echo "\nNo Prompt received\n";
						fputs($this->fp, "\r\n");
						$this->GetResponse($r);
						echo "\nResponse: $r\n";
					}

					$x++;
					sleep(1);
				}

				if ($x == 10) {
					return 8;
				}

				// Get Password Prompt
				$res = '';
				$x = 0;
				while ($x < 10) {
					sleep(1);
					$this->GetResponse($r);
					plugin_routerconfigs_log($ip."-> DEBUG: $r");
					$res .= $r;
					echo $r;
					if (strpos($res, $devicetype['password']) !== FALSE) {
						$r=explode("\n", $r);
						$this->loginprompt = trim(substr($r[count($r) - 1], 0, strpos($r[count($r) - 1], ' ')));
						echo "\nSending password: $pass\n";
						@fputs($this->fp, "$pass\r");
						break;
					}
					$x++;
				}
				if ($x == 10) {
					return 9;
				}

				if ($enablepw != '') {

					# Get > to show we are at the command prompt and ready to input the en command
					$res = '';
					$x = 0;
					while ($x < 10) {
						sleep(1);
						$this->GetResponse($r);
						echo $r;
						$res .= $r;
						if (strpos($r, '>') !== FALSE) {
							break;
						}
						$x++;
					}
					@fputs($this->fp, "en\r");

					# Get the password prompt again to input the enable password
					$res = '';
					$x = 0;
					while ($x < 10) {
						sleep(1);
						$this->GetResponse($r);
						echo $r;
						$res .= $r;
						if (strpos($res, $devicetype['password']) !== FALSE) {
							break;
						}
						$x++;
					}
					//$r=explode("\n", $r);
					@fputs($this->fp, "$enablepw\r");
				}


				$res = $r;
				$x = 0;
				while ($x < 10) {
					sleep(1);
					$this->GetResponse($r);
					$res .= $r;
					echo $r;
					if (strpos($res, '#') !== FALSE || strpos($r, '> (') !== FALSE) {
						break;
					}
					$x++;
				}

				$r=explode("\n", $r);
				if (($r[count($r) - 1] == '') || ($this->loginprompt == trim($r[count($r) - 1]))) {
					$rv = 3;
					$this->Disconnect();
				}
			} else $rv = 1;
		}

		if ($rv) {
			echo $ip . '-> ' . $this->ConnectError($rv) . '<br>';
			plugin_routerconfigs_log($ip . '-> ' . $this->ConnectError($rv));
		}
		return $rv;
	}

	function Disconnect($exit = 1) {
		if ($this->fp) {
			if ($exit)
				$this->DoCommand('exit', $junk);
			fclose($this->fp);
			$this->fp = NULL;
		}
	}

	function DoCommand($c, &$r) {
		if ($this->fp) {
			@fputs($this->fp, "$c" . PHP_EOL);
			$this->Sleep();
			$this->GetResponse($r);
			plugin_routerconfigs_log("DEBUG: DoCommand result=$r");
			$r = preg_replace("/^.*?\n(.*)\n[^\n]*$/", "$1", $r);
		}
		return $this->fp ? 1 : 0;
	}

	function GetResponse(&$r) {
		$r = '';
		stream_set_timeout($this->fp, 1);
		do {
			$r .= fread($this->fp, 2048);
			plugin_routerconfigs_log("DEBUG: Telnet response:$r");
			$this->debug .= $r;
			$s = socket_get_status($this->fp);
		} while ($s['unread_bytes']);
	}

	function Sleep() {
		if ($this->use_usleep) usleep($this->sleeptime);
		else sleep(1);
	}

	function PHPTelnet() {
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

	function ConnectError($num) {
		if ($this->show_connect_error) {
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
		}

		return '';
	}
}
