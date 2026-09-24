<?php
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

/**
 * Renders this plugin's tabbed interface (Devices/Device Types/
 * Authentication/Backups/Compare), auto-detecting the current tab from
 * the calling script's filename when not explicitly set, and
 * highlighting the currently active tab. Called from each of this
 * plugin's admin pages before rendering their content.
 *
 * @return void Outputs the tab bar HTML directly.
 *
 * @global array $config Cacti global configuration array; used to build
 *                       the tab link URLs.
 */
function display_tabs() {
	global $config;

	// ================= input validation =================
	get_filter_request_var('tab', FILTER_VALIDATE_REGEXP, ['options' => ['regexp' => '/^([a-zA-Z]+)$/']]);
	// ====================================================

	$tabs = [
		'devices'  => __('Devices', 'routerconfigs'),
		'devtypes' => __('Device Types', 'routerconfigs'),
		'accounts' => __('Authentication', 'routerconfigs'),
		'backups'  => __('Backups', 'routerconfigs'),
		'compare'  => __('Compare', 'routerconfigs')
	];

	// set the default tab
	$current_tab = get_nfilter_request_var('tab');

	if (!isset($current_tab) || !strlen($current_tab)) {
		$back_trace = debug_backtrace();
		$file_info  = pathinfo($back_trace[0]['file']);
		$file_tab   = preg_replace('~router-([a-zA-Z]+).php~','\\1',$file_info['basename']);

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
		// draw the tabs
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

/**
 * Produces a display-safe placeholder for a password value (its
 * character count, or '(Not Set)' when unset) for use in debug logging,
 * so the real password is never written to the log. Called from
 * PHPConnection's constructor and throughout the connection classes.
 *
 * @param string|null $pass The password value to mask.
 *
 * @return string The masked placeholder text.
 */
function plugin_routerconfigs_maskpw($pass) {
	return !isset($pass) ? __('(Not Set)','routerconfigs') : __('(%s chars)',strlen($pass),'routerconfigs');
}

/**
 * Logs the current call stack (function name, line, and file for each
 * frame beyond $skip), for diagnosing where a debug message originated.
 * Currently unused/dead code: not called from anywhere else in this
 * file.
 *
 * @param int $skip The number of innermost stack frames to skip;
 *                  defaults to 1 (skipping this function's own frame).
 *
 * @return void
 */
function plugin_routerconfigs_backtrace($skip = 1) {
	$backtrace = debug_backtrace();

	foreach ($backtrace as $trace) {
		if ($skip == 0) {
			plugin_routerconfigs_log('DEBUG: BACKTRACE: ' . $trace['function'] . '() at ' . $trace['line'] . ' in ' . $trace['file']);
		} else {
			$skip--;
		}
	}
}

/**
 * Runs a full backup cycle: determines the set of devices due for backup
 * (either an explicit device list for a manual run, or scheduled/retry
 * candidates from the database), downloads each device's configuration
 * via plugin_routerconfigs_download_config(), logs a summary, emails a
 * formatted HTML report of successes/failures when configured, and
 * purges old backups per the retention policy. Called from
 * router-download.php's main flow (the standalone CLI backup process)
 * and from router-devices.php's actions_devices() for a manual on-demand
 * backup.
 *
 * @param bool  $retry        Whether this is an automatic retry run
 *                            (only devices past their retry window are
 *                            selected); defaults to false.
 * @param bool  $force        Whether to force backup of every enabled
 *                            device regardless of schedule; defaults to
 *                            false.
 * @param array $devices      An explicit list of device ids to back up
 *                            (manual backup), overriding schedule-based
 *                            selection; defaults to an empty array.
 * @param bool  $buffer_debug Whether to buffer verbose per-line debug
 *                            output for each connection; defaults to
 *                            false (falls back to the
 *                            'routerconfigs_debug_buffer' setting).
 * @param bool  $simulate     Whether to simulate a scheduled run without
 *                            actually connecting to devices; defaults to
 *                            false.
 *
 * @return void
 */
function plugin_routerconfigs_download($retry = false, $force = false, $devices = [], $buffer_debug = false, $simulate = false) {
	ini_set('max_execution_time', '0');
	ini_set('memory_limit', '256M');

	if (!$buffer_debug) {
		$buffer_debug = (read_config_option('routerconfigs_debug_buffer') == 'on');
	}

	$filter_devices = [];

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

	$start   = microtime(true);
	$stime   = time();
	$passed  = [];
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
			$sqlwhere  = '';
			$sqlparams = [];

			// If we aren't forcing all backups...
			$scheduled = false;
			$manual    = sizeof($filter_devices) > 0;

			if ($manual) {
				$filter_devices = array_map('intval', $filter_devices);
				$sqlwhere       = 'AND id IN (' . implode(',', array_fill(0, count($filter_devices), '?')) . ')';
				$sqlparams      = $filter_devices;
			} elseif (!$force) {
				$scheduled = (!$force) || $simulate;

				if ($retry) {
					$sqlwhere  = 'AND nextattempt > lastbackup AND nextattempt <= ?';
					$sqlparams = [$stime];
				} else {
					$sqlwhere  = 'AND (nextbackup <= ? OR nextbackup IS NULL)';
					$sqlparams = [$stime];
				}
			}

			$sql = "SELECT *
				FROM plugin_routerconfigs_devices
				WHERE enabled = 'on'
				$sqlwhere";
			plugin_routerconfigs_log('DEBUG: SQL: ' . preg_replace('/[\r\n]+\s*/m',' ',$sql));
			$devices = db_fetch_assoc_prepared($sql, $sqlparams, false);

			$failed = [];
			$passed = [];

			if (sizeof($devices)) {
				foreach ($devices as $device) {
					$t = time();
					plugin_routerconfigs_log(__('DEBUG: Attempting download for %s', $device['hostname'], 'routerconfigs'));
					$found = plugin_routerconfigs_download_config($device, $stime, $buffer_debug, $scheduled);

					if ($found) {
						plugin_routerconfigs_log('NOTICE: Download successful for ' . $device['hostname']);
						$passed[] =  ['hostname' => $device['hostname'], 'lastfile' => $device['lastfile']];
					} else {
						plugin_routerconfigs_log('NOTICE: Failed to download for ' . $device['hostname']);
						$fmsg = db_fetch_cell_prepared('SELECT lasterror
							FROM plugin_routerconfigs_devices
					                WHERE id = ?',
							[$device['id']]);
						$failed[] = ['hostname' => $device['hostname'], 'lasterror' => $fmsg];
					}
				}

				$success   = count($devices) - count($failed);
				$cfailed   = count($failed);
				$disabled  = db_fetch_cell_prepared('SELECT COUNT(*) FROM plugin_routerconfigs_devices WHERE enabled <> \'on\'', []);
				$totalsecs = time() - $stime;

				$notice_level = 'NOTICE:';

				if ($cfailed > 0) {
					$notice_level = 'WARNING:';
				}

				plugin_routerconfigs_log("$notice_level $success Devices Backed Up, $cfailed Devices Failed, $disabled Disabled (ignored) in $totalsecs seconds");

				if ($success != 0 || $cfailed != 0 || $retry != true) {
					// print out failures
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

						$from    = [0 => $from_email, 1 => $from_name];
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
						} elseif ($cfailed) {
							$subject .= __(': FAILED', 'routerconfigs');
						}
						$message .= '</body></html>';
						send_mail($to, $from, $subject, $message, $filename = '', $headers = '', $html = true);
					}
				}
			}
			// remove old backups
			plugin_routerconfigs_retention();
		}
	}

	$end            = microtime(true);
	$download_stats = sprintf('Time:%01.2f Downloaded:%s Failed:%s', $end - $start, $success, $cfailed);

	plugin_routerconfigs_log(__('STATS: ','routerconfigs') . $download_stats);

	plugin_routerconfigs_stop(sizeof($filter_devices) == 0);
}

/**
 * Logs a debug line and appends a wrapped HTML paragraph to the running
 * backup-summary email body. Called from plugin_routerconfigs_download()
 * to build up its summary email message.
 *
 * @param string $message Reference, the HTML email body being built up.
 * @param string $text    The message text to log and append.
 *
 * @return void
 */
function plugin_routerconfigs_message(&$message, $text) {
	plugin_routerconfigs_log("DEBUG: $text");
	$message .= "<div>$text</div>";
}

/**
 * Appends an HTML section heading to the running backup-summary email
 * body, optionally with a CSS class (e.g. to highlight a failures
 * section in red). Called from plugin_routerconfigs_download() and
 * plugin_routerconfigs_message_devicetable() to build up the summary
 * email message.
 *
 * @param string $message Reference, the HTML email body being built up.
 * @param string $title   The heading text.
 * @param string $class   An optional CSS class to apply to the heading;
 *                        defaults to ''.
 *
 * @return void
 */
function plugin_routerconfigs_message_title(&$message, $title, $class = '') {
	if ($class > '') {
		$class = " class='$class'";
	}
	$message .= "<h3$class>$title</h3>";
}

/**
 * Appends an HTML table listing devices that succeeded or failed their
 * backup (hostname plus either the last error or last saved filename)
 * to the running backup-summary email body. Called from
 * plugin_routerconfigs_download() once for the failed devices and once
 * for the successful ones.
 *
 * @param string $message Reference, the HTML email body being built up.
 * @param array  $devices The devices to list, each with 'hostname' and
 *                        either 'lasterror' or 'lastfile'.
 * @param bool   $failed  Whether $devices represents failed backups
 *                        (true) or successful ones (false).
 *
 * @return void
 */
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
		$message .= '<tr class=\'row' . $row . '\'><td>' . $device['hostname'] . '</td>';
		$message .= '<td>' . ($failed ? $device['lasterror'] : $device['lastfile']) . '</td>';
		$message .= "</tr>\n";

		$row = ($row + 1) % 2;
	}

	$message .= '</table>';
}

/**
 * Deletes backup files (and their plugin_routerconfigs_backups rows)
 * older than the configured retention period (clamped to the plugin's
 * supported min/max range, defaulting to 30 days if out of range).
 * Called from plugin_routerconfigs_download() after each backup cycle
 * completes.
 *
 * @return void
 *
 * @global array $rc_schedules_retention The supported retention period
 *                                       options (in days), used to
 *                                       clamp/validate the configured
 *                                       value.
 */
function plugin_routerconfigs_retention() {
	global $rc_schedules_retention;

	$backuppath = read_config_option('routerconfigs_backup_path');

	if (!is_dir($backuppath) || strlen($backuppath) < 2) {
		plugin_routerconfigs_log(__('ERROR: Backup Path is not set or is not a directory', 'routerconfigs'));
		exit;
	}

	$min_days = min(array_keys($rc_schedules_retention));
	$max_days = max(array_keys($rc_schedules_retention));
	$days     = read_config_option('routerconfigs_retention');

	if ($days < $min_days || $days > $max_days) {
		plugin_routerconfigs_log(__('WARNING: Retention period \'%s\' is invalid, defaulting to 30 days', $days, 'routerconfigs'));
		$days = 30;
	}

	$time    = time() - ($days * 24 * 60 * 60);
	$backups = db_fetch_assoc_prepared('SELECT *
		FROM plugin_routerconfigs_backups
		WHERE btime < ?',
		[$time]);

	if (sizeof($backups)) {
		foreach ($backups as $backup) {
			$dir      = $backup['directory'];
			$filename = $backup['filename'];
			@unlink("$dir/$filename");
		}
	}

	db_execute_prepared('DELETE FROM plugin_routerconfigs_backups
		WHERE btime < ?',
		[$time]);
}

/**
 * Checks whether a downloaded configuration file's content ends with a
 * recognizable 'end' marker line, used to sanity-check that a backup
 * wasn't truncated. Called from
 * plugin_routerconfigs_download_config() when the device type's
 * 'checkendinconfig' option is enabled.
 *
 * @param string $data The downloaded configuration file content to
 *                     check.
 *
 * @return bool True if the content appears to end with an 'end' marker,
 *              false otherwise.
 */
function plugin_routerconfigs_check_config($data) {
	if (preg_match('/\n[^\w]*end[^\w]*$/',$data)) {
		return true;
	}

	return false;
}

/**
 * Acquires a simple database-backed lock (via the 'settings' table) to
 * prevent overlapping backup runs, treating a lock older than 2 hours as
 * stale and reclaimable. Exits immediately if another run is already in
 * progress and this run isn't forced/simulated. Called from
 * plugin_routerconfigs_download() at the start of a backup cycle.
 *
 * @param bool $force    Whether to acquire the lock even if another run
 *                       appears to be in progress; defaults to false.
 * @param bool $simulate Whether this is a simulated run, which also
 *                       bypasses the lock check; defaults to false.
 *
 * @return void This function either returns normally after acquiring
 *              the lock, or terminates script execution via exit() if
 *              another run holds it.
 *
 * @global array $config Cacti global configuration array; used to
 *                       determine whether to also print (vs. only log)
 *                       status messages.
 */
function plugin_routerconfigs_start($force = false, $simulate = false) {
	global $config;

	$running = db_fetch_cell_prepared('SELECT value FROM settings WHERE name = ?', ['plugin_routerconfigs_running']);

	if ($running == 1) {
		$running = time();
		db_execute_prepared('REPLACE INTO settings (name, value) VALUES (?, ?)', ['plugin_routerconfigs_running', $running]);
	}

	if ($running < time() - 7200 || $force || $simulate) {
		$running = time();

		db_execute_prepared('REPLACE INTO settings (name, value) VALUES (?, ?)', ['plugin_routerconfigs_running', $running]);

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

/**
 * Releases the backup run lock acquired by plugin_routerconfigs_start()
 * when $force_stop is true, then terminates the script. Called from
 * plugin_routerconfigs_download() at the end of a backup cycle.
 *
 * @param bool $force_stop Whether to actually clear the run lock before
 *                         exiting.
 *
 * @return void This function always terminates script execution via
 *              exit() and therefore never returns.
 */
function plugin_routerconfigs_stop($force_stop) {
	if ($force_stop) {
		db_execute_prepared('REPLACE INTO settings (name, value) VALUES (?, ?)', ['plugin_routerconfigs_running', 0]);
	}
	exit();
}

/**
 * Ensures a directory path string ends with a trailing slash. Called
 * throughout the backup flow when building file paths from a configured
 * directory.
 *
 * @param string $dir The directory path to normalize.
 *
 * @return string The path with a trailing slash appended, if it didn't
 *                already have one.
 */
function plugin_routerconfigs_dir($dir) {
	if (strlen($dir) && $dir[strlen($dir) - 1] != '/') {
		$dir .= '/';
	}

	return $dir;
}

/**
 * Downloads a single device's configuration: resolves its effective
 * connection settings (timeout/sleep/connection type/elevated flag,
 * falling back through device -> device type -> global setting
 * defaults), tries each configured connection class for the device's
 * connection type in turn (SCP/SFTP/SSH/Telnet, stopping early on an SSH
 * host-key verification failure to avoid falling back to insecure
 * Telnet), downloads the config file once connected, validates and
 * archives the resulting file, and records the device's last/next
 * attempt and backup timestamps. Called from
 * plugin_routerconfigs_download() for each device due for backup.
 *
 * @param array  $device      Reference, the device row to back up;
 *                            updated with its new backup/attempt
 *                            timestamps and status as a side effect.
 * @param int    $backuptime  The Unix timestamp this overall backup
 *                            cycle started at, used to compute the next
 *                            scheduled backup time.
 * @param bool   $buffer_debug Whether to buffer verbose per-line debug
 *                            output for the connection; defaults to
 *                            false.
 * @param bool   $scheduled    Whether this download is part of a
 *                            regularly scheduled run (vs. manual/retry);
 *                            defaults to false.
 *
 * @return bool True if the configuration was successfully downloaded and
 *              validated, false otherwise.
 */
function plugin_routerconfigs_download_config(&$device, $backuptime, $buffer_debug = false, $scheduled = false) {
	$t_last = time();

	$t_next = plugin_routerconfigs_nexttime($t_last, read_config_option('routerconfigs_retry'),3600,0);

	db_execute_prepared('UPDATE plugin_routerconfigs_devices
		SET lastattempt = ?,
		nextattempt = ?
		WHERE id = ?',
		[$t_last, $t_next, $device['id']]);

	$info    = plugin_routerconfigs_retrieve_account($device['id']);
	$dir     = trim($device['directory']);
	$ip      = $device['ipaddress'];

	$backuppath  = plugin_routerconfigs_dir(trim(read_config_option('routerconfigs_backup_path')));
	$archivepath = plugin_routerconfigs_dir(trim(read_config_option('routerconfigs_archive_path')));
	$tftpserver  = read_config_option('routerconfigs_tftpserver');

	$filename = $device['hostname'];

	if (strlen($dir) && $dir[0] == '/') {
		$dir = substr($dir,1);
	}

	if (read_config_option('routerconfigs_archive_separate') == 'on') {
		$archivepath = plugin_routerconfigs_dir($archivepath . $dir);
	}

	$devicetype = db_fetch_row_prepared('SELECT *
		FROM plugin_routerconfigs_devicetypes
		WHERE id = ?',
		[$device['devicetype']]);

	if (empty($devicetype)) {
		$devicetype = ['promptuser' => 'username:',
			'promptpass'               => 'password:',
			'promptconfirm'            => 'confirm|to tftp:',
			'copytftp'                 => 'copy start tftp',
			'version'                  => 'show version',
			'sleep'                    => '125000',
			'timeout'                  => '1',
			'confirm'                  => '',
			'forceconfirm'             => '',
			'connecttype'              => 'both',
			'checkendinconfig'         => 'on',
			'elevated'                 => '',
		];
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

	$timeout  = plugin_routerconfigs_getfirst([$device['timeout'], $devicetype['timeout'], read_config_option('timeout'), 1]);
	$sleep    = plugin_routerconfigs_getfirst([$device['sleep'], $devicetype['sleep'], read_config_option('sleep'), 125000]);
	$type_dev = plugin_routerconfigs_getfirst([$device['connecttype'], $devicetype['connecttype'], read_config_option('routerconfigs_connecttype'), 'both'], true);
	$elevated = plugin_routerconfigs_getfirst([$device['elevated'], $devicetype['elevated'], read_config_option('routerconfigs_elevated'), ''], true);

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
			$connection->Log('DEBUG: Connected via ' . $connection->classType);

			break;
		}

		if (!plugin_routerconfigs_should_try_next_connection($type_dev, $classname, $result)) {
			if ($result === RCONFIG_CONNECT_HOSTKEY_FAILED) {
				$fail_msg = __("ERROR: SSH host key verification failed for Device '%s'; refusing to send credentials or fall back to Telnet", $device['hostname'], 'routerconfigs');
			} else {
				$fail_msg = __("ERROR: SSH connection failed for Device '%s'; Telnet fallback is disabled while SSH host key verification is enabled", $device['hostname'], 'routerconfigs');
			}

			plugin_routerconfigs_save_error($device['id'], null, $fail_msg);
			plugin_routerconfigs_log($fail_msg);

			return false;
		}

		$connection = null;
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
		$connection->Log('DEBUG: checking end in config');

		return false;
	}

	if ($devicetype['checkendinconfig'] == 'on') {
		$connection->Log('DEBUG: Configuration end check successful');
	} else {
		$connection->Log('DEBUG: Configuration end check was not performed');
	}

	$data       = str_replace("\n", "\r\n", $data);
	$data2      = explode("\r\n", $data);
	$lastchange = '';
	$lastuser   = '';

	if (sizeof($data2)) {
		foreach ($data2 as $d) {
			if (strpos($d, 'Last configuration change at') !== false) {
				$lastchange = substr($d, strpos($d, 'change at') + 10, strpos($d, ' by ') - (strpos($d, 'change at') + 10));

				$t = explode(' ', $lastchange);

				if (isset($t[5])) {
					$t          = [$t[3], $t[4], $t[5], $t[0], $t[1]];
					$t          = implode(' ', $t);
					$lastchange = strtotime($t);

					if (substr($d, strpos($d, ' by ')) !== false) {
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
						[$filename, $connection->ip(), $device['id']]);
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
			[$lastchange, $lastuser, $device['id']]);
	}

	$connection->Log('DEBUG: Configuration Data Length ' . strlen($data));

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
			$date     = date('Y-m-d-Hi');
			$savename = "$archivepath$filename-$date";
			$connection->Log("DEBUG: Attempting to backup to filename '$savename'");

			clearstatcache();

			if (file_exists($savename)) {
				$connection->Log("WARNING: Overwriting existing file '$savename'");
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
					$connection->Log("WARNING: File '$savename' has size $filesize, expected " . strlen($data));
					$file = false;
				} else {
					$data2  = $data;
					$t_back = time();

					if ($scheduled) {
						$t_next = plugin_routerconfigs_nexttime($backuptime, $device['schedule'], 86400, read_config_option('routerconfigs_hour'));
					} else {
						$t_next = db_fetch_cell_prepared('SELECT nextbackup FROM plugin_routerconfigs_devices WHERE id = ?', [$device['id']]);
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
						[$t_back, $t_next, $device['id']]);

					$backup_dir  = dirname($savename);
					$backup_file = basename($savename);

					db_execute_prepared('INSERT INTO plugin_routerconfigs_backups
						(device, btime, directory, filename, lastchange, lastuser)
						VALUES (?, ?, ?, ?, ?, ?)',
						[$device['id'], $t_back, $backup_dir, $backup_file, $lastchange, $lastuser]);
				}
			}
		}
	}

	if ($file === false) {
		plugin_routerconfigs_save_error($device['id'], $connection);
		plugin_routerconfigs_save_debug($device, $connection);

		$connection->Log('DEBUG: Exiting download as failed');

		return false;
	}

	plugin_routerconfigs_save_error($device['id'], $connection);
	plugin_routerconfigs_save_debug($device, $connection);
	$connection->Log('DEBUG: Backed up');

	return true;
}

/**
 * Persists a connection's accumulated (base64-encoded) debug transcript
 * to the device's row, for later viewing via the 'View Debug' action.
 * Called from plugin_routerconfigs_download_config() after a backup
 * attempt (successful or failed) completes.
 *
 * @param array $device     The device row whose debug column to update.
 * @param mixed $connection The connection instance to read debug output
 *                          from via getDebug().
 *
 * @return void
 */
function plugin_routerconfigs_save_debug($device, $connection) {
	$base64 = base64_encode($connection->getDebug());
	// echo "Saving Debug\n";
	db_execute_prepared('UPDATE plugin_routerconfigs_devices
		SET debug = ?
		WHERE id = ?',
		[$base64, $device['id']]);
}

/**
 * Records the most recent error message for a device, either an
 * explicitly supplied message or one derived from the connection's own
 * ConnectError()/error() state. Called from
 * plugin_routerconfigs_download_config() whenever a connection/download
 * step fails.
 *
 * @param int   $id         The device id to record the error against.
 * @param mixed $connection The connection instance to derive an error
 *                          message from when $error is empty, or null
 *                          to only use $error.
 * @param string $error     An explicit error message to record; defaults
 *                          to '' (derive from $connection).
 *
 * @return void
 */
function plugin_routerconfigs_save_error($id, $connection, $error = '') {
	if ($connection != null && $error == '') {
		$error = $connection->ConnectError($connection->error());
	}

	db_execute_prepared('UPDATE plugin_routerconfigs_devices
		SET lasterror = ?
		WHERE id = ?',
		[$error, $id]);
}

/**
 * Looks up and decodes the login credentials (username, decoded
 * password, decoded enable password) configured for a device's assigned
 * account. Called from plugin_routerconfigs_download_config() before
 * connecting to a device.
 *
 * @param int|string $device The device id to look up the account for.
 *
 * @return array|false The account row with decoded 'password'/
 *                     'enablepw' fields, or false if $device is empty or
 *                     no account/username is configured.
 */
function plugin_routerconfigs_retrieve_account($device) {
	if ($device == '') {
		return false;
	}

	$info = db_fetch_row_prepared('SELECT plugin_routerconfigs_accounts.*
		FROM plugin_routerconfigs_accounts,plugin_routerconfigs_devices
		WHERE plugin_routerconfigs_accounts.id = plugin_routerconfigs_devices.account
		AND plugin_routerconfigs_devices.id = ?',
		[$device]);

	if (isset($info['username'])) {
		if (isset($info['password']) && strlen($info['password']) > 0) {
			$info['password'] = plugin_routerconfigs_decode($info['password']);
		} else {
			$info['password'] = '';
		}

		if (isset($info['enablepw']) && strlen($info['enablepw']) > 0) {
			$info['enablepw'] = plugin_routerconfigs_decode($info['enablepw']);
		} else {
			$info['enablepw'] = '';
		}

		return $info;
	}

	return false;
}

/**
 * Decodes a stored account credential (base64-encoded serialized array
 * with the real value under a randomized-key wrapper, as produced by
 * plugin_routerconfigs_encode()), failing safely to an empty string on
 * malformed input rather than throwing. Called from
 * plugin_routerconfigs_retrieve_account() to decode a device account's
 * password/enable password.
 *
 * @param string $info The base64-encoded, serialized credential to
 *                     decode.
 *
 * @return string The decoded password value, or '' if decoding failed.
 */
function plugin_routerconfigs_decode($info) {
	$info = base64_decode($info, true);

	if ($info === false) {
		plugin_routerconfigs_log('ERROR: Base64 decode failed for stored credential');

		return '';
	}

	$info = unserialize($info, ['allowed_classes' => false]);

	if (!is_array($info) || !isset($info['password'])) {
		plugin_routerconfigs_log('ERROR: Credential decode produced unexpected structure');

		return '';
	}

	return $info['password'];
}

/**
 * Encodes an account credential for storage: wraps it in an array with
 * randomized decoy keys/values around the real 'password' key, then
 * serializes and base64-encodes it. Called from router-accounts.php's
 * save_accounts() when saving a new/changed password or enable
 * password.
 *
 * @param string $info The plain-text credential value to encode.
 *
 * @return string The base64-encoded, serialized, obfuscated credential.
 */
function plugin_routerconfigs_encode($info) {
	$crypt             = [rand(1, time()) => rand(1, time()), 'password' => '', rand(1, time()) => rand(1, time())];
	$crypt['password'] = $info;
	$crypt             = serialize($crypt);
	$crypt             = base64_encode($crypt);

	return $crypt;
}

/**
 * Verify a device's SSH host key against the fingerprint recorded on first use.
 *
 * When the routerconfigs_verify_hostkey option is enabled, the first successful
 * connection records the device's key fingerprint; a later change is treated as
 * a possible man-in-the-middle and the connection is refused before any
 * credential is sent. A legitimate key change (for example a device reinstall)
 * is resolved by clearing the stored fingerprint for that device. The check is
 * skipped when the option is off. When verification is enabled, missing
 * storage fails closed so credentials are never sent without an enforceable
 * host-key check.
 *
 * @param int|string  $device_id The routerconfigs device id.
 * @param array|false $hostkey   The negotiated algorithm and fingerprint.
 *
 * @return bool True to proceed with authentication, false to refuse.
 */
function plugin_routerconfigs_verify_ssh_hostkey($device_id, $hostkey) {
	if (read_config_option('routerconfigs_verify_hostkey') != 'on') {
		return true;
	}

	if (!is_array($hostkey) || empty($hostkey['type']) || empty($hostkey['fingerprint'])) {
		plugin_routerconfigs_log('ERROR: No SSH host key fingerprint available; refusing to send credentials');

		return false;
	}

	if (!db_column_exists('plugin_routerconfigs_devices', 'ssh_fingerprint') ||
		!db_column_exists('plugin_routerconfigs_devices', 'ssh_hostkey_type')) {
		plugin_routerconfigs_log('ERROR: SSH host key storage columns are missing; refusing to send credentials. Complete the routerconfigs upgrade first.');

		return false;
	}

	$stored = db_fetch_row_prepared('SELECT id, ssh_hostkey_type, ssh_fingerprint
		FROM plugin_routerconfigs_devices
		WHERE id = ?',
		[$device_id]);

	if (!is_array($stored) || empty($stored['id'])) {
		plugin_routerconfigs_log("ERROR: Unable to read the stored SSH host key for device $device_id; refusing to send credentials.");

		return false;
	}

	if (empty($stored['ssh_hostkey_type']) && empty($stored['ssh_fingerprint'])) {
		$updated = db_execute_prepared('UPDATE plugin_routerconfigs_devices
			SET ssh_hostkey_type = ?, ssh_fingerprint = ?
			WHERE id = ?
			AND COALESCE(ssh_hostkey_type, \'\') = \'\'
			AND COALESCE(ssh_fingerprint, \'\') = \'\'',
			[$hostkey['type'], $hostkey['fingerprint'], $device_id]);

		if (!$updated) {
			plugin_routerconfigs_log("ERROR: Unable to store the SSH host key for device $device_id; refusing to send credentials.");

			return false;
		}

		$stored = db_fetch_row_prepared('SELECT id, ssh_hostkey_type, ssh_fingerprint
			FROM plugin_routerconfigs_devices
			WHERE id = ?',
			[$device_id]);

		if (!is_array($stored) || empty($stored['id']) ||
			!hash_equals((string) ($stored['ssh_hostkey_type'] ?? ''), (string) $hostkey['type']) ||
			!hash_equals((string) ($stored['ssh_fingerprint'] ?? ''), (string) $hostkey['fingerprint'])) {
			plugin_routerconfigs_log("ERROR: Unable to confirm the stored SSH host key for device $device_id; refusing to send credentials.");

			return false;
		}

		plugin_routerconfigs_log("NOTICE: Recorded first-use SSH host key for device $device_id; algorithm '{$hostkey['type']}', fingerprint '{$hostkey['fingerprint']}'", POLLER_VERBOSITY_LOW);

		return true;
	}

	if (empty($stored['ssh_hostkey_type']) || empty($stored['ssh_fingerprint'])) {
		plugin_routerconfigs_log("ERROR: Stored SSH host key for device $device_id is incomplete; refusing to send credentials. Clear it with the device action.");

		return false;
	}

	if (hash_equals((string) $stored['ssh_fingerprint'], (string) $hostkey['fingerprint'])) {
		if (!hash_equals((string) $stored['ssh_hostkey_type'], (string) $hostkey['type'])) {
			$updated = db_execute_prepared('UPDATE plugin_routerconfigs_devices
				SET ssh_hostkey_type = ?
				WHERE id = ? AND ssh_fingerprint = ?',
				[$hostkey['type'], $device_id, $hostkey['fingerprint']]);

			if ($updated) {
				plugin_routerconfigs_log("NOTICE: SSH host-key negotiation for device $device_id changed from {$stored['ssh_hostkey_type']} to {$hostkey['type']}, but the key fingerprint is unchanged; updated the stored algorithm.");
			}
		}

		return true;
	}

	plugin_routerconfigs_log("ERROR: SSH host key for device $device_id changed (possible MITM); refusing. Use the device action to clear the stored host key after a legitimate change.");

	return false;
}

/**
 * Resets a device's stored SSH host key (algorithm/fingerprint) so the
 * next connection will trust and record whatever key it receives,
 * logging the discarded key and the reason for clearing it. Called from
 * router-devices.php's device actions handler (manual 'Clear SSH Host
 * Key' action) and from save_devices() when the device's connection
 * target changes.
 *
 * @param int    $device_id The device id whose host key to clear.
 * @param string $reason    A short description of why the key is being
 *                          cleared, included in the log message.
 *
 * @return bool True if the key was successfully cleared, false if the
 *              device couldn't be read or the update failed.
 */
function plugin_routerconfigs_clear_ssh_hostkey($device_id, $reason) {
	$stored = db_fetch_row_prepared('SELECT id, ssh_hostkey_type, ssh_fingerprint
		FROM plugin_routerconfigs_devices
		WHERE id = ?',
		[$device_id]);

	if (!is_array($stored) || empty($stored['id'])) {
		plugin_routerconfigs_log("ERROR: Unable to read SSH host key before reset for device $device_id");

		return false;
	}

	$cleared = db_execute_prepared('UPDATE plugin_routerconfigs_devices
		SET ssh_hostkey_type = NULL, ssh_fingerprint = NULL
		WHERE id = ?',
		[$device_id]);

	if (!$cleared) {
		plugin_routerconfigs_log("ERROR: Unable to clear SSH host key for device $device_id");

		return false;
	}

	$type        = str_replace(["\r", "\n"], '', (string) ($stored['ssh_hostkey_type'] ?? ''));
	$fingerprint = str_replace(["\r", "\n"], '', (string) ($stored['ssh_fingerprint'] ?? ''));
	$reason      = str_replace(["\r", "\n"], '', (string) $reason);

	plugin_routerconfigs_log("NOTICE: Cleared SSH host key for device $device_id ($reason); discarded algorithm '$type', fingerprint '$fingerprint'", POLLER_VERBOSITY_LOW);

	return true;
}

/**
 * Return whether the network target changed and its host-key pin must reset.
 *
 * Called from router-devices.php's save_devices() after saving a
 * device, to decide whether to clear its previously recorded SSH host
 * key.
 *
 * @param array $previous_device The device's row before the save (only
 *                               'ipaddress' is consulted).
 * @param array $new_device      The device's row/submitted values after
 *                               the save (only 'ipaddress' is
 *                               consulted).
 *
 * @return bool True if the IP address changed, false otherwise.
 */
function plugin_routerconfigs_connection_target_changed($previous_device, $new_device) {
	return (string) ($previous_device['ipaddress'] ?? '') !== (string) ($new_device['ipaddress'] ?? '');
}

/**
 * Decide whether a failed transport may fall through to the next candidate.
 *
 * Refuses to fall back from a failed SSH attempt to Telnet when SSH host
 * key verification is enabled (since Telnet sends credentials
 * unencrypted with no equivalent verification), and never continues
 * after an explicit host-key mismatch regardless of connection type.
 * Called from plugin_routerconfigs_download_config() after each
 * connection class attempt fails.
 *
 * @param string $connection_type The device's configured connection
 *                                type (e.g. RCONFIG_CONNECT_BOTH).
 * @param string $classname       The connection class that just failed
 *                                (e.g. 'PHPSsh').
 * @param mixed  $result          The failed connection's result code.
 *
 * @return bool True if the next candidate connection class should be
 *              tried, false if attempts should stop here.
 */
function plugin_routerconfigs_should_try_next_connection($connection_type, $classname, $result) {
	if ($result === RCONFIG_CONNECT_HOSTKEY_FAILED) {
		return false;
	}

	if ($connection_type === RCONFIG_CONNECT_BOTH && $classname === 'PHPSsh' &&
		read_config_option('routerconfigs_verify_hostkey') == 'on') {
		return false;
	}

	return true;
}

/**
 * Determines a log message's severity prefix (the earliest-occurring of
 * 'ERROR:', 'FATAL:', 'STATS:', 'WARNING:', 'NOTICE:', 'DEBUG:' found in
 * the message text). Called from plugin_routerconfigs_log() to map a
 * message to its appropriate Cacti poller verbosity level.
 *
 * @param string $message The log message to inspect.
 *
 * @return string The detected severity prefix, or the original $message
 *                unchanged if none of the recognized prefixes are
 *                found.
 */
function plugin_routerconfigs_messagetype($message) {
	$types   = ['ERROR:', 'FATAL:', 'STATS:', 'WARNING:', 'NOTICE:', 'DEBUG:'];
	$typepos = [];

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

/**
 * Log messages to cacti log or syslog
 * This function is the same as thold plugin with a little change
 * to respect cacti log level settings
 *
 * Logs a message to Cacti's log, auto-detecting an appropriate
 * verbosity level from the message's severity prefix (via
 * plugin_routerconfigs_messagetype()) unless one is explicitly supplied,
 * and forcing full verbosity when global debug mode is enabled. Called
 * throughout this plugin to report backup progress/errors.
 *
 * @param string $message   The message to log.
 * @param int    $log_level The Cacti poller verbosity level to log at;
 *                         defaults to POLLER_VERBOSITY_NONE, which
 *                         triggers auto-detection from the message text.
 *
 * @return void
 *
 * @global array $config Cacti global configuration array; used to
 *                       decide whether to also print (vs. only log) the
 *                       message, based on whether this is a web request.
 * @global bool  $debug  When true, forces full log verbosity regardless
 *                       of the detected/supplied level.
 */
function plugin_routerconfigs_log($message, $log_level = POLLER_VERBOSITY_NONE) {
	global $config, $debug;

	$environ = 'RCONFIG';

	if ($log_level == POLLER_VERBOSITY_NONE) {
		$log_level = POLLER_VERBOSITY_HIGH;

		$message_type = plugin_routerconfigs_messagetype($message);

		if (substr_count($message_type,'ERROR:') || substr_count($message_type, 'FATAL:') || substr_count($message_type,'STATS:')) {
			$log_level = POLLER_VERBOSITY_LOW;
		} elseif (substr_count($message_type,'WARNING:') || substr_count($message_type,'NOTICE:')) {
			$log_level = POLLER_VERBOSITY_MEDIUM;
		} elseif (substr_count($message_type,'DEBUG:')) {
			$log_level = POLLER_VERBOSITY_DEBUG;
		}
	}

	if ($debug) {
		$log_level = POLLER_VERBOSITY_NONE;
	}
	cacti_log($message,!$config['is_web'],$environ, $log_level);
}

/**
 * Formats a Unix timestamp using Cacti's configured date/time format, or
 * 'N/A' for an unset (zero or negative) timestamp. Called throughout the
 * admin UI to display last-backup/attempt timestamps.
 *
 * @param int $time The Unix timestamp to format.
 *
 * @return string The formatted date/time, or 'N/A'.
 */
function plugin_routerconfigs_date_from_time_with_na($time) {
	return ($time > 0) ? date(CACTI_DATE_TIME_FORMAT, $time) : 'N/A';
}

/**
 * Formats a Unix timestamp using Cacti's configured date/time format, or
 * an empty string for an unset (zero or negative) timestamp. Called from
 * plugin_routerconfigs_view_device_config() to display a backup's
 * timestamp.
 *
 * @param int $time The Unix timestamp to format.
 *
 * @return string The formatted date/time, or ''.
 */
function plugin_routerconfigs_date_from_time($time) {
	return ($time > 0) ? date(CACTI_DATE_TIME_FORMAT, $time) : '';
}

/**
 * Computes the next scheduled run time aligned to a repeating interval
 * (e.g. the next hour/day boundary plus a configured offset), or 0 if
 * scheduling is disabled. Called from
 * plugin_routerconfigs_download_config() to compute a device's next
 * retry attempt time.
 *
 * @param int $time       The reference Unix timestamp to compute the
 *                        next run relative to.
 * @param int $schedule   The number of interval units to offset by (0
 *                        disables scheduling).
 * @param int $multipler  The interval size in seconds (e.g. 3600 for
 *                        hourly); 0 disables scheduling.
 * @param int $hour       An additional hour offset to add; defaults to
 *                        0.
 *
 * @return int The computed next run Unix timestamp, or 0 if scheduling
 *             is disabled.
 */
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

/**
 * Returns the first non-empty value from a list of candidates, used to
 * implement a device -> device type -> global setting fallback chain.
 * Called from plugin_routerconfigs_download_config() to resolve a
 * device's effective timeout/sleep/connection-type/elevated settings.
 *
 * @param array $array The candidate values to check in order.
 * @param bool  $debug Unused debug flag (kept for interface parity);
 *                     defaults to false.
 *
 * @return mixed The first non-empty candidate value, or false if all are
 *               empty.
 */
function plugin_routerconfigs_getfirst($array, $debug = false) {
	$count = 0;

	foreach ($array as $item) {
		$count++;

		if (!empty($item)) {
			return $item;
		}
	}

	return false;
}

/**
 * Displays the raw content of a device's stored backup configuration
 * file (looked up by a specific backup id, or the most recent backup for
 * a device id), validating that the resolved file path stays within the
 * device's configured backup directory before reading it. Called from
 * router-backups.php's and router-devices.php's view_device_config()
 * dispatch handlers.
 *
 * @param int    $backup_id   A specific plugin_routerconfigs_backups.id
 *                            to display; defaults to 0 (use $device_id
 *                            instead).
 * @param int    $device_id   A device id to display the most recent
 *                            backup for, when $backup_id is not
 *                            supplied; defaults to 0.
 * @param string $failure_url A URL to redirect to if no matching backup
 *                            is found; defaults to '' (no redirect).
 *
 * @return void Outputs the backup file content page directly, or
 *              redirects to $failure_url if no backup is found.
 */
function plugin_routerconfigs_view_device_config($backup_id = 0, $device_id = 0, $failure_url = '') {
	$device = [];

	if (!empty($backup_id)) {
		$device = db_fetch_row_prepared('SELECT prb.*, prd.hostname, prd.ipaddress
			FROM plugin_routerconfigs_devices AS prd
			INNER JOIN plugin_routerconfigs_backups AS prb
			ON prb.device=prd.id
			WHERE prb.id=?',
			[get_request_var('id')]);
	} elseif (!empty($device_id)) {
		$device = db_fetch_row_prepared('SELECT prb.*, prd.hostname, prd.ipaddress
			FROM plugin_routerconfigs_devices AS prd
			INNER JOIN plugin_routerconfigs_backups AS prb
			ON prb.device=prd.id
			WHERE prd.id=?
			ORDER BY btime DESC',
			[get_request_var('id')]);
	}

	if (isset($device['id'])) {
		ini_set('memory_limit', '256M');

		$filepath = plugin_routerconfigs_dir($device['directory']) . basename($device['filename']);
		$resolved = realpath($filepath);
		$basedir  = realpath(plugin_routerconfigs_dir($device['directory']));

		if ($basedir === false
			|| ($resolved !== false
				&& strpos($resolved, rtrim($basedir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR) !== 0)
		) {
			$lines = [__('File path validation failed', 'routerconfigs')];
		} elseif ($resolved !== false && file_exists($resolved)) {
			$lines = @file($resolved);

			if ($lines === false) {
				$lines = [__("File '%s' failed to load", $filepath, 'routerconfigs')];
			}
		} else {
			$lines = [__("File '%s' was not found", $filepath, 'routerconfigs')];
		}

		top_header();

		display_tabs();

		html_start_box('', '100%', '', '4', 'center', '');

		form_alternate_row();

		print '<td><h2>' . __('Router Config for %s (%s)', html_escape($device['hostname']), html_escape($device['ipaddress']), 'routerconfigs') . '<br>';
		print __('Backup from %s', plugin_routerconfigs_date_from_time($device['btime']), 'routerconfigs') . '</h2>';
		print __('File: %s/%s', html_escape($device['directory']), html_escape($device['filename']), 'routerconfigs');
		print '<br><textarea style="background: white; width:100%; height: auto;" rows=36 cols=120>';
		print html_escape(implode($lines));
		print '</textarea></td></tr>';

		html_end_box(false);
		bottom_footer();
	} elseif (!empty($failure_url)) {
		header('Location: ' . $failure_url);
		exit;
	}
}
