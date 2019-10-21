<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2007-2019 The Cacti Group                                 |
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

require_once(__DIR__ . '/constants.php');
require_once(__DIR__ . '/arrays.php');
require_once(__DIR__ . '/../classes/LinePrompt.php');
require_once(__DIR__ . '/../classes/PHPConnection.php');
require_once(__DIR__ . '/../classes/PHPShellConnection.php');
require_once(__DIR__ . '/../classes/PHPScp.php');
require_once(__DIR__ . '/../classes/PHPSftp.php');
require_once(__DIR__ . '/../classes/PHPSsh.php');
require_once(__DIR__ . '/../classes/PHPTelnet.php');

function display_tabs() {
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
	$current_tab = get_nfilter_request_var('tab');
	if (!isset($current_tab) || !strlen($current_tab)) {
		$back_trace = debug_backtrace();
		$file_info = pathinfo($back_trace[0]['file']);
		$file_tab = preg_replace('~router-([a-zA-Z]+).php~','\\1',$file_info['basename']);
		if (array_key_exists($file_tab,$tabs)) {
			$current_tab = $file_tab;
		}
	}
	if (!isset($current_tab) || !strlen($current_tab)) {
		load_current_session_value('tab', 'sess_rc_tabs', 'devices');
		$current_tab = get_nfilter_request_var('tab');
	}
	$header_label = __('Technical Support [ %s ]', $tabs[$current_tab], 'routerconfigs');

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

		api_plugin_hook('routerconfigs_tab');

		print "</ul></nav></div>\n";
	}
}

function plugin_routerconfigs_maskpw($pass) {
	return !isset($pass) ? __('(Not Set)','routerconfigs') : __('(%s chars)',strlen($pass),'routerconfigs');
}

function plugin_routerconfigs_backtrace($skip = 1) {
	$backtrace = debug_backtrace();
	foreach ($backtrace as $trace) {
		if ($skip == 0) {
			plugin_routerconfigs_log('DEBUG: BACKTRACE: '. $trace['function'] . '() at ' . $trace['line'] . ' in ' . $trace['file']);
		} else {
			$skip--;
		}
	}
}

function plugin_routerconfigs_download($retry = false, $force = false, $devices = array(), $buffer_debug = false, $simulate = false) {
	ini_set('max_execution_time', '0');
	ini_set('memory_limit', '256M');

	if (!$buffer_debug) {
		$buffer_debug = (read_config_option('routerconfigs_debug_buffer') == 'on');
	}

	$filter_devices = array();
	if ($devices != null && sizeof($devices)) {
		$filter_devices = $devices;
		plugin_routerconfigs_log(__('NOTICE: Starting manual backup of %s devices',sizeof($filter_devices),'routerconfigs'));
	} else {
		if ($retry) {
			plugin_routerconfigs_log(__('NOTICE: Starting automatic retry','routerconfigs'));
		} else {
			plugin_routerconfigs_log(__('NOTICE: Starting automatic backup','routerconfigs'));
		}
		plugin_routerconfigs_start($force, $simulate);
	}

	$start  = microtime(true);
	$stime  = time();
	$passed = array();
	$success = 0;
	$cfailed = 0;

	$backuppath = read_config_option('routerconfigs_backup_path');
	$tftpserver = read_config_option('routerconfigs_tftpserver');

	if (!is_dir($backuppath) || strlen($backuppath) < 2) {
		plugin_routerconfigs_log(__('FATAL: TFTP Backup Path is not set or is not a directory', 'routerconfigs'));
	} else {
		if (strlen($tftpserver) < 2) {
			plugin_routerconfigs_log(__('FATAL: TFTP Server is not set', 'routerconfigs'));
		} else {
			$sqlwhere = "";

			// If we aren't forcing all backups...
			$scheduled = false;
			$manual = sizeof($filter_devices) > 0;
			if ($manual) {
				$sqlwhere = 'AND id IN (' . implode(',',$filter_devices) .')';
			} else if (!$force) {
				$scheduled = (!$force) || $simulate;
				if ($retry) {
					$sqlwhere = "AND nextattempt > lastbackup AND nextattempt <= $stime";
				} else {
					$sqlwhere = "AND (nextbackup <= $stime OR nextbackup IS NULL)";
				}
			}

			$sql = "SELECT *
				FROM plugin_routerconfigs_devices
				WHERE enabled = 'on'
				$sqlwhere";
			plugin_routerconfigs_log('DEBUG: SQL: ' . preg_replace('/[\r\n]+\s*/m',' ',$sql));
			$devices = db_fetch_assoc($sql, false);

			$failed = array();
			$passed = array();

			if (sizeof($devices)) {
				foreach ($devices as $device) {
					$t = time();
					plugin_routerconfigs_log(__('DEBUG: Attempting download for %s', $device['hostname'], 'routerconfigs'));
					$found = plugin_routerconfigs_download_config($device, $stime, $buffer_debug, $scheduled);
					if ($found) {
						plugin_routerconfigs_log('NOTICE: Download successful for ' . $device['hostname']);
						$passed[] = array ('hostname' => $device['hostname'], 'lastfile' => $device['lastfile']);
					} else {
						plugin_routerconfigs_log('NOTICE: Failed to download for ' . $device['hostname']);
						$fmsg = db_fetch_cell_prepared('SELECT lasterror
							FROM plugin_routerconfigs_devices
					                WHERE id = ?',
					                array($device['id']));
						$failed[] = array('hostname' => $device['hostname'], 'lasterror' => $fmsg);
					}
				}

				$success = count($devices) - count($failed);
				$cfailed = count($failed);
				$disabled = db_fetch_cell('SELECT COUNT(*) FROM plugin_routerconfigs_devices WHERE enabled <> \'on\'');
				$totalsecs = time() - $stime;

				$notice_level = 'NOTICE:';
				if ($cfailed > 0) {
					$notice_level = 'WARNING:';
				}

				plugin_routerconfigs_log("$notice_level $success Devices Backed Up, $cfailed Devices Failed, $disabled Disabled (ignored) in $totalsecs seconds");

				if ($success != 0 || $cfailed != 0 || $retry != true) {
					/* print out failures */
					$message = '<html><head><style>
h3 { border-bottom: 1px solid black; padding-bottom: 5px; }
table { border-style: collapse: border: none; }
th { border-bottom: 1px solid gray; }
tr { border-bottom: 1px solid gray; }
td { margin: 5 10 5 10; }
.red { color: #821509; }
.row0 { background-color: #eee; }
.row1 { background-color: #ddd; }
</style></head><body>';
					plugin_routerconfigs_message_title($message, 'Summary');
					plugin_routerconfigs_message($message, __('%s devices backed up successfully.', $success, 'routerconfigs'));
					plugin_routerconfigs_message($message, __('%s devices failed to backup.', $cfailed, 'routerconfigs'));
					if ($disabled > 0) {
						plugin_routerconfigs_message($message, __('%s devices disabled from backup.', $disabled, 'routerconfigs'));
					}


					if (sizeof($failed)) {
						plugin_routerconfigs_message_devicetable($message, $failed, true);
					}

					if (sizeof($passed)) {
						plugin_routerconfigs_message_devicetable($message, $passed, false);
					}

					$from_email = read_config_option('routerconfigs_from');
					$from_name  = read_config_option('routerconfigs_name');
					$to         = read_config_option('routerconfigs_email');

					if ($to != '' && $from_email != '') {
						if ($from_name == '') {
							$from_name = __('Config Backups', 'routerconfigs');
						}

						$from = array(0 => $from_email, 1 => $from_name);
						$subject = __('Configuration Backups', 'routerconfigs');

						if ($force) {
							$subject .= __(' - Forced', 'routerconfigs');
						}

						if ($manual) {
							$subject .= __(' - Manual', 'routerconfigs');
						}

						if ($simulate) {
							$subject .= __(' - Simulated', 'routerconfigs');
						}

						if ($scheduled) {
							$subject .= __(' - Scheduled', 'routerconfigs');
						}

						if ($retry) {
							$subject .= __(' - Reattempt', 'routerconfigs');
						}

						if ($cfailed && $success) {
							$subject .= __(': Partial', 'routerconfigs');
						} else if ($cfailed) {
							$subject .= __(': FAILED', 'routerconfigs');
						}
						$message .= '</body></html>';
						send_mail($to, $from, $subject, $message, $filename = '', $headers = '', $html = true);
					}
				}
			}
			/* remove old backups */
			plugin_routerconfigs_retention ();
		}
	}

	$end = microtime(true);
	$download_stats = sprintf('Time:%01.2f Downloaded:%s Failed:%s', $end - $start, $success, $cfailed);

	plugin_routerconfigs_log(__('STATS: ','routerconfigs') . $download_stats);

	plugin_routerconfigs_stop(sizeof($filter_devices) == 0);
}

function plugin_routerconfigs_message(&$message, $text) {
	plugin_routerconfigs_log("DEBUG: $text");
	$message .= "<div>$text</div>";
}

function plugin_routerconfigs_message_title(&$message, $title, $class = '') {
	if ($class > '') {
		$class = " class='$class'";
	}
	$message .= "<h3$class>$title</h3>";
}

function plugin_routerconfigs_message_devicetable(&$message, $devices, $failed) {
	$title = 'Devices that ' . ($failed ? 'failed to backup' : 'backed up');
	plugin_routerconfigs_message_title($message, $title, ($failed ? 'red' : ''));
	if ($failed) {
		$message .= "<table><thead><th>Device</th><th>Reason</th></thead>\n";
	} else {
		$message .= "<table><thead><th>Device</th><th>Filename</th></thead>\n";
	}

	$row = 0;
	foreach ($devices as $device) {
		$message .= '<tr class=\'row'.$row.'\'><td>' . $device['hostname'] . '</td>';
		$message .= '<td>' . ($failed ? $device['lasterror'] : $device['lastfile']) .'</td>';
		$message .= "</tr>\n";

		$row = ($row + 1) % 2;
	}

	$message .= '</table>';
}

function plugin_routerconfigs_retention() {
	$backuppath = read_config_option('routerconfigs_backup_path');
	if (!is_dir($backuppath) || strlen($backuppath) < 2) {
		plugin_routerconfigs_log(__('ERROR: Backup Path is not set or is not a directory', 'routerconfigs'));
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
			@unlink("$dir/$filename");
		}
	}

	db_execute_prepared('DELETE FROM plugin_routerconfigs_backups
		WHERE btime < ?',
		array($time));
}

function plugin_routerconfigs_check_config($data) {
	if (preg_match('/\n[^\w]*end[^\w]*$/',$data))
		return true;
	return false;
}

function plugin_routerconfigs_start($force = false, $simulate = false) {
	global $config;

	$running = db_fetch_cell('SELECT value FROM settings WHERE name = \'plugin_routerconfigs_running\'');
	if ($running == 1) {
		$running = time();
		db_execute('REPLACE INTO settings (name, value)
			VALUES (\'plugin_routerconfigs_running\', ' . $running .')');
	}

	if ($running < time() - 7200 || $force || $simulate) {
		$running = time();

		db_execute('REPLACE INTO settings (name, value)
			VALUES (\'plugin_routerconfigs_running\', ' . $running .')');

		$datetime = new DateTime();
		$datetime->setTimestamp($running);
		cacti_log(__('STATS: Backup now running since %s',$datetime->format('Y-m-d H:i:s'),'routerconfigs'), !$config['is_web'],'RCONFIG');
	} else {
		$datetime = new DateTime();
		$datetime->setTimestamp($running);
		cacti_log(__('WARN: Backup already running since %s',$datetime->format('Y-m-d H:i:s'),'routerconfigs'), !$config['is_web'],'RCONFIG');
		exit();
	}
}

function plugin_routerconfigs_stop($force_stop) {
	if ($force_stop) {
		db_execute('REPLACE INTO settings (name, value)
			VALUES (\'plugin_routerconfigs_running\', 0)');
	}
	exit();
}

function plugin_routerconfigs_dir($dir) {
	if (strlen($dir) && $dir[strlen($dir) - 1] != '/') {
		$dir .= '/';
	}
	return $dir;
}

function plugin_routerconfigs_download_config(&$device, $backuptime, $buffer_debug = false, $scheduled = false) {
	$t_last = time();

	$t_next = plugin_routerconfigs_nexttime($t_last, read_config_option('routerconfigs_retry'),3600,0);

	db_execute_prepared('UPDATE plugin_routerconfigs_devices
		SET lastattempt = ?,
		nextattempt = ?
		WHERE id = ?',
		array($t_last, $t_next, $device['id']));

	$info    = plugin_routerconfigs_retrieve_account($device['id']);
	$dir     = trim($device['directory']);
	$ip      = $device['ipaddress'];

	$backuppath = plugin_routerconfigs_dir(trim(read_config_option('routerconfigs_backup_path')));
	$archivepath = plugin_routerconfigs_dir(trim(read_config_option('routerconfigs_archive_path')));
	$tftpserver = read_config_option('routerconfigs_tftpserver');

	$filename = $device['hostname'];

	if (strlen($dir) && $dir[0] == '/') {
		$dir = substr($dir,1);
	}

	if (read_config_option('routerconfigs_archive_separate') == 'on') {
		$archivepath = plugin_routerconfigs_dir($archivepath  . $dir);
	}

	$devicetype = db_fetch_row_prepared('SELECT *
		FROM plugin_routerconfigs_devicetypes
		WHERE id = ?',
		array($device['devicetype']));

	if (empty($devicetype)){
		$devicetype = array('promptuser' => 'username:',
			'promptpass' => 'password:',
			'promptconfirm' => 'confirm|to tftp:',
			'copytftp' => 'copy start tftp',
			'version' => 'show version',
			'sleep' => '125000',
			'timeout' => '1',
			'confirm' => '',
			'forceconfirm' => '',
			'connecttype' => 'both',
			'checkendinconfig' => 'on',
			'elevated' => '',
		);
	}

	$readname = "$backuppath$filename";
	clearstatcache();
	if (file_exists("$readname")) {
		plugin_routerconfigs_log("DEBUG: Attempting to remove pre-existing incoming file: $readname");
		@unlink("$readname");

		clearstatcache();
		if (file_exists("$readname")) {
			$fail_msg = "ERROR: Failed to remove pre-existing incoming file: $readname";
			plugin_routerconfigs_save_error($device['id'],null,$fail_msg);
			plugin_routerconfigs_log($fail_msg);
			return false;
		}
	}

	$timeout  = plugin_routerconfigs_getfirst(array($device['timeout'], $devicetype['timeout'], read_config_option('timeout'), 1));
	$sleep    = plugin_routerconfigs_getfirst(array($device['sleep'], $devicetype['sleep'], read_config_option('sleep'), 125000));
	$type_dev = plugin_routerconfigs_getfirst(array($device['connecttype'], $devicetype['connecttype'], read_config_option('routerconfigs_connecttype'), 'both'), true);
	$elevated = plugin_routerconfigs_getfirst(array($device['elevated'], $devicetype['elevated'], read_config_option('routerconfigs_elevated'), ''), true);

	$classes = PHPConnection::GetTypes($type_dev);
	plugin_routerconfigs_log("$ip -> DEBUG: $type_dev has '" . implode('\', \'', $classes) . "'");

	$result = 1;
	foreach ($classes as $classname) {
		plugin_routerconfigs_log("$ip -> DEBUG: Attempting to use '$classname'");
		if (!class_exists($classname)) {
			plugin_routerconfigs_log("$ip -> DEBUG: Skipped creating '$classname' as classType was not found");
			continue;
		}

		$connection = new $classname($devicetype, $device, $info['username'], $info['password'], $info['enablepw'], $buffer_debug, $elevated);

		$connection->setTimeout($timeout);
		$connection->setSleep($sleep);

		$result = $connection->Connect();
		if (!$result) {
			$connection->Log("DEBUG: Connected via " . $connection->classType);
			break;
		} else {
			$connection = NULL;
		}
	}

	if ($result) {
		$fail_msg = __("ERROR: Failed to connect to Device '%s' using connection type: %s",$device['hostname'],$type_dev,'routerconfigs');
		plugin_routerconfigs_save_error($device['id'],null,$fail_msg);
		plugin_routerconfigs_log($fail_msg);
		return false;
	}

	$ip    = $connection->ip();
	$file  = false;

	if (!$connection->Download($filename, $backuppath)) {
		$fail_msg = __("ERROR: Failed to download '%s' to '%s' via '%s'", $filename, $backuppath . $filename, $type_dev,'routerconfigs');
		plugin_routerconfigs_save_error($device['id'],null,$fail_msg);
		plugin_routerconfigs_log($fail_msg);
	}
	$connection->Disconnect();
	$connection->Sleep();
	$data = '';

	$connection->Log("DEBUG: Checking for valid incoming file at $readname");
	clearstatcache();

	$file = false;
	$data = false;
	if (!file_exists("$readname")) {
		$connection->Log("ERROR: Failed to find file at $readname");
	} else {
		if (filesize("$readname") > 0) {
			$connection->Log("DEBUG: Attempting to open file at $readname");
			$file = @fopen("$readname", 'r');
		}

		if ($file === false) {
			$connection->Log("ERROR: Failed to open file at $readname");
		} else {
			$data = @fread($file, filesize("$readname"));
			@fclose($file);

			if ($data === false) {
				$connection->Log("ERROR: Failed to read file at $readname");
			}
		}
	}

	if ($data === false) {
		$connection->error(7);
		plugin_routerconfigs_save_error($device['id'], $connection);
		plugin_routerconfigs_save_debug($device, $connection);
		return false;
	}

	@unlink("$readname");
	clearstatcache();
	if (file_exists("$readname")) {
		$connection->Log("WARNING: Failed to remove file at $readname");
	}

	if ($devicetype['checkendinconfig'] == 'on' && !plugin_routerconfigs_check_config($data)) {
		$connection->error(5);
		plugin_routerconfigs_save_error($device['id'], $connection);
		plugin_routerconfigs_save_debug($device, $connection);
		$connection->Log("DEBUG: checking end in config");
		return false;
	}

	if ($devicetype['checkendinconfig'] == 'on') {
		$connection->Log("DEBUG: Configuration end check successful");
	} else {
		$connection->Log("DEBUG: Configuration end check was not performed");
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

			if (preg_match('~^(host|set system )name ["]{0,1}([a-zA-Z0-9\._\-]+)["]{0,1}~i',$d,$matches)) {
				$filename = trim($matches[2]);
				if (strlen($filename)) {
					db_execute_prepared('UPDATE plugin_routerconfigs_devices
						SET hostname = ?, ipaddress = ?
						WHERE id = ?',
						array($filename, $connection->ip(), $device['id']));
				}
			}
		}
	}

	if ($lastchange == '') {
		$lastchange = $connection->lastchange;
		$lastuser   = $connection->lastuser;
	}

	if ($lastchange != '' && $lastchange != $device['lastchange']) {
		db_execute_prepared('UPDATE plugin_routerconfigs_devices
			SET lastchange = ?, lastuser = ?
			WHERE id = ?',
			array($lastchange, $lastuser, $device['id']));
	}

	$connection->Log("DEBUG: Configuration Data Length " . strlen($data));
	if (strlen($data) > 100) {
		$connection->Log("DEBUG: Checking backup directory exists: $archivepath");
		if (!is_dir("$archivepath")) {
			$connection->Log("DEBUG: Creating backup directory: $archivepath");
			@mkdir("$archivepath", 0770, true);
		}

		$file = false;
		if (!is_dir("$archivepath")) {
			$connection->Log("ERROR: Failed to create backup directory: $archivepath");
		} else {
			$date = date('Y-m-d-Hi');
			$savename = "$archivepath$filename-$date";
			$connection->Log("DEBUG: Attempting to backup to filename '$savename'");

			clearstatcache();
			if (file_exists($savename)) {
				$connection->Log("WARNING: Overwritting existing file '$savename'");
			}

			$file = @fopen($savename, 'w');

			if ($file === false) {
				$connection->Log("ERROR: Failed to open file '$savename' for writing");
			} else {
				@fwrite($file, $data);
				@fclose($file);

				clearstatcache();
				$filesize = @filesize("$savename");
				if ($filesize < strlen($data)) {
					$connection->Log("WARNING: File '$savename' has size $filesize, expected ".strlen($data));
					$file = false;
				} else {
					$data2 = $data;
					$t_back = time();
					if ($scheduled) {
						$t_next = plugin_routerconfigs_nexttime($backuptime, $device['schedule'], 86400, read_config_option('routerconfigs_hour'));
					} else {
						$t_next = db_fetch_cell_prepared('SELECT nextbackup FROM plugin_routerconfigs_devices WHERE id = ?', array($device['id']));
					}

					if ($lastchange == '') {
						 $lastchange = 0;
					}

					$device['lastfile'] = $savename;
					db_execute_prepared('UPDATE plugin_routerconfigs_devices
						SET lastbackup = ?,
						nextbackup = ?,
						nextattempt = 0
						WHERE id = ?',
						array($t_back, $t_next, $device['id']));

					$backup_dir = dirname($savename);
					$backup_file = basename($savename);

					db_execute_prepared('INSERT INTO plugin_routerconfigs_backups
						(device, btime, directory, filename, lastchange, lastuser)
						VALUES (?, ?, ?, ?, ?, ?)',
						array($device['id'], $t_back, $backup_dir, $backup_file, $lastchange, $lastuser));
				}
			}
		}
	}

	if ($file === false) {
		plugin_routerconfigs_save_error($device['id'], $connection);
		plugin_routerconfigs_save_debug($device, $connection);

		$connection->Log("DEBUG: Exiting download as failed");
		return false;
	}

	plugin_routerconfigs_save_error($device['id'], $connection);
	plugin_routerconfigs_save_debug($device, $connection);
	$connection->Log("DEBUG: Backed up");

	return true;
}

function plugin_routerconfigs_save_debug($device, $connection) {
	$base64 = base64_encode($connection->getDebug());
	//echo "Saving Debug\n";
	db_execute_prepared('UPDATE plugin_routerconfigs_devices
		SET debug = ?
		WHERE id = ?',
		array($base64, $device['id']));
}

function plugin_routerconfigs_save_error($id, $connection, $error='') {
	if ($connection != null && $error == '') {
		$error = $connection->ConnectError($connection->error());
	}

	db_execute_prepared('UPDATE plugin_routerconfigs_devices
		SET lasterror = ?
		WHERE id = ?',
		array($error, $id));
}

function plugin_routerconfigs_retrieve_account ($device) {
	if ($device == '') {
		return false;
	}

	$info = db_fetch_row_prepared('SELECT plugin_routerconfigs_accounts.*
		FROM plugin_routerconfigs_accounts,plugin_routerconfigs_devices
		WHERE plugin_routerconfigs_accounts.id = plugin_routerconfigs_devices.account
		AND plugin_routerconfigs_devices.id = ?',
                array($device));

	if (isset($info['username'])) {
		$info['password'] = plugin_routerconfigs_decode($info['password']);
		$info['enablepw'] = plugin_routerconfigs_decode($info['enablepw']);
		return $info;
	}

	return false;
}

function plugin_routerconfigs_decode($info) {
	$info = base64_decode($info);
	$debug_info = preg_replace('~(;s:\d+:"password";s:(\d+:))"(.*)\"~','\\1"(\\2 chars)"', $info);
	plugin_routerconfigs_log("DEBUG: Base64 decoded: $debug_info");

	$info = unserialize($info);
	return $info['password'];
}

function plugin_routerconfigs_encode($info) {
	$crypt = array(rand(1, time()) => rand(1, time()), 'password' => '', rand(1, time()) => rand(1, time()));
	$crypt['password'] = $info;
	$crypt = serialize($crypt);
	$crypt = base64_encode($crypt);
	return $crypt;
}

function plugin_routerconfigs_messagetype($message) {
	$types = array('ERROR:','FATAL:','STATS:','WARNING:','NOTICE:','DEBUG:');
	$typepos = array();
	foreach ($types as $type) {
		$pos = strpos($message,$type);
		if ($pos !== false) {
			$typepos[$pos] = $type;
		}
	}

	ksort($typepos);
	foreach ($typepos as $pos=>$type) {
		return $type;
	}

	return $message;
}

/*
//Log messages to cacti log or syslog
//This function is the same as thold plugin with a litle changes
//to respect cacti log level settings
*/
function plugin_routerconfigs_log($message, $log_level = POLLER_VERBOSITY_NONE) {
	global $config, $debug;

	$environ = 'RCONFIG';

	if ($log_level == POLLER_VERBOSITY_NONE) {
		$log_level = POLLER_VERBOSITY_HIGH;

		$message_type = plugin_routerconfigs_messagetype($message);
		if (substr_count($message_type,'ERROR:') || substr_count($message_type, 'FATAL:') || substr_count($message_type,'STATS:')) {
			$log_level = POLLER_VERBOSITY_LOW;
		} else if (substr_count($message_type,'WARNING:') || substr_count($message_type,'NOTICE:')) {
			$log_level = POLLER_VERBOSITY_MEDIUM;
		} else if (substr_count($message_type,'DEBUG:')) {
			$log_level = POLLER_VERBOSITY_DEBUG;
		}
	}

	if ($debug) {
		$log_level = POLLER_VERBOSITY_NONE;
	}
	cacti_log($message,!$config['is_web'],$environ, $log_level);
}

function plugin_routerconfigs_date_from_time_with_na($time) {
	return ($time > 0) ? date(CACTI_DATE_TIME_FORMAT, $time) : 'N/A';
}

function plugin_routerconfigs_date_from_time($time) {
	return ($time > 0) ? date(CACTI_DATE_TIME_FORMAT, $time) : '';
}

function plugin_routerconfigs_nexttime($time, $schedule, $multipler, $hour = 0) {
	if ($schedule == 0 || $multipler == 0) {
		return 0;
	} else {
		$next = $time - ($time % $multipler) + ($schedule * $multipler) + ($hour * 3600);
/*
		printf("\n%10d - %4d + %5d (%2d * %5d) + %6d = %10d (%10d)\n\n", $time, ($time % 3600),
			($schedule * $multipler), $schedule, $multipler,
			($hour * 3600), $time - ($time % 3600) + ($schedule * $multipler) + ($hour * 3600), $next);
*/
		return $next;
	}
}

function plugin_routerconfigs_getfirst($array, $debug = false) {
	$count = 0;
	foreach ($array as $item) {
		$count++;
		if (!empty($item))
			return $item;
	}
	return false;
}

function plugin_routerconfigs_view_device_config($backup_id = 0, $device_id = 0, $failure_url = '') {
	$device = array();
	if (!empty($backup_id)) {
		$device = db_fetch_row_prepared('SELECT prb.*, prd.hostname, prd.ipaddress
			FROM plugin_routerconfigs_devices AS prd
			INNER JOIN plugin_routerconfigs_backups AS prb
			ON prb.device=prd.id
			WHERE prb.id=?',
			array(get_request_var('id')));
	} else if (!empty($device_id)) {
		$device = db_fetch_row_prepared('SELECT prb.*, prd.hostname, prd.ipaddress
			FROM plugin_routerconfigs_devices AS prd
			INNER JOIN plugin_routerconfigs_backups AS prb
			ON prb.device=prd.id
			WHERE prd.id=?
			ORDER BY btime DESC',
			array(get_request_var('id')));
	}

	if (isset($device['id'])) {
		ini_set('memory_limit', '256M');

		$filepath = plugin_routerconfigs_dir($device['directory']) . $device['filename'];
		if (file_exists($filepath)) {
			$lines = @file($filepath);
			if ($lines === false) {
				$lines = array(__("File '%s' failed to load", $filepath, 'routerconfigs'));
			}
		} else {
			$lines = array(__("File '%s' was not found", $filepath, 'routerconfigs'));
		}

		top_header();

		display_tabs ();

		html_start_box('', '100%', '', '4', 'center', '');

		form_alternate_row();

		print '<td><h2>' . __('Router Config for %s (%s)', $device['hostname'], $device['ipaddress'], 'routerconfigs') . '<br>';
		print __('Backup from %s', plugin_routerconfigs_date_from_time($device['btime']), 'routerconfigs') . '</h2>';
		print __('File: %s/%s', $device['directory'], $device['filename'], 'routerconfigs');
		print '<br><textarea style="background: white; width:100%; height: auto;" rows=36 cols=120>';
		print implode($lines);
		print '</textarea></td></tr>';

		html_end_box(false);
		bottom_footer();

	} else if (!empty($failure_url)) {
		header('Location: ' . $failure_url);
		exit;
	}
}
