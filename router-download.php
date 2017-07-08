<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2008-2017 The Cacti Group                                 |
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

/* do NOT run this script through a web browser */
if (!isset($_SERVER['argv'][0]) || isset($_SERVER['REQUEST_METHOD'])  || isset($_SERVER['REMOTE_ADDR'])) {
	die('<br><strong>This script is only meant to run at the command line.</strong>');
}

/* We are not talking to the browser */
$no_http_headers = true;

$dir = dirname(__FILE__);
if (strpos($dir, 'plugins') !== false) {
	chdir('../../');
}

include('./include/global.php');
include_once($config['base_path'] . '/plugins/routerconfigs/functions.php');

error_reporting(E_ALL ^ E_DEPRECATED);

/* let PHP run just as long as it has to */
ini_set('max_execution_time', '0');
ini_set('memory_limit', '256M');

db_execute("REPLACE INTO settings
	(name, value) VALUES
	('plugin_routerconfigs_running', 1)");

$t = $stime = time();
$devices = db_fetch_assoc("SELECT *
	FROM plugin_routerconfigs_devices
	WHERE enabled = 'on'
	AND ((($t - lastbackup) - (schedule * 86400)) > -3600)");

$failed = array();
if (sizeof($devices)) {
	foreach ($devices as $device) {
		cacti_log("NOTE: Backing up config at " . time() . ' for device ' . $device['hostname'] , false, 'RCONFIG');
		echo 'Processing ' . $device['hostname'] . "\n";

		plugin_routerconfigs_download_config($device);

		// Check for failed Backup
		$t = time() - 120;
		$f = db_fetch_assoc_prepared('SELECT *
			FROM plugin_routerconfigs_backups
			WHERE btime > ?
			AND device = ?',
			array($t, $device['id']));

		if (empty($f)) {
			$device = db_fetch_row_prepared('SELECT *
				FROM plugin_routerconfigs_devices
				WHERE id = ?',
				array($device['id']));

			$failed[] = array ('hostname' => $device['hostname'], 'lasterror' => $device['lasterror']);
		}

		sleep(10);
	}
} else {
	db_execute("REPLACE INTO settings
		(name, value) VALUES
		('plugin_routerconfigs_running', 0)");

	return;
}

$success = count($devices) - count($failed);
$cfailed = count($failed);

cacti_log("NOTE: $success Devices Backed Up and $cfailed Devices Failed in " . time() - $stime . ' seconds', true, 'RCONFIG');

/* print out failures */
$message  = __('%s devices backed up successfully.<br>', $success, 'routerconfigs');
$message .= __('%s devices failed to backup.<br>', $cfailed, 'routerconfigs');

if (!empty($failed)) {
	$message .= __('These devices failed to backup<br>', 'routerconfigs');
	$message .= __('--------------------------------<br>', 'routerconfigs');
	foreach ($failed as $f) {
		$message .= $f['hostname'] . ' - ' . $f['lasterror'] . '<br>';
	}
}

echo $message;

$from_email = read_config_option('settings_from_email');
$from_name  = read_config_option('settings_from_name');
$to         = read_config_option('routerconfigs_email');

if ($to != '' && $from_email != '') {
	if ($from_name == '') {
		$from_name = 'Config Backups';
	}

	$from[0] = $from_email;
	$from[1] = $from_name;
	$subject = __('Network Device Configuration Backups', 'routerconfigs');

	 mailer($from, $to, $cc, '', '', $subject, $message);
}

/* remove old backups */
plugin_routerconfigs_retention ();

db_execute("REPLACE INTO settings
	(name, value) VALUES
	('plugin_routerconfigs_running', 0)");

