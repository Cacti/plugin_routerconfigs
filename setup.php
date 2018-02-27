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

function plugin_routerconfigs_version () {
	global $config;
	$info = parse_ini_file($config['base_path'] . '/plugins/routerconfigs/INFO', true);
	return $info['info'];
}

function plugin_routerconfigs_install () {
	api_plugin_register_hook('routerconfigs', 'config_arrays',        'routerconfigs_config_arrays',        'setup.php');
	api_plugin_register_hook('routerconfigs', 'draw_navigation_text', 'routerconfigs_draw_navigation_text', 'setup.php');
	api_plugin_register_hook('routerconfigs', 'config_settings',      'routerconfigs_config_settings',      'setup.php');
	api_plugin_register_hook('routerconfigs', 'poller_bottom',        'routerconfigs_poller_bottom',        'setup.php');
	api_plugin_register_hook('routerconfigs', 'page_head',            'routerconfigs_page_head',            'setup.php');

	api_plugin_register_realm('routerconfigs', 'router-devices.php,router-accounts.php,router-backups.php,router-compare.php,router-devtypes.php', __('Router Configs', 'routerconfigs'), 1);

	routerconfigs_setup_table_new();
}

function plugin_routerconfigs_uninstall () {
	/* Do any extra Uninstall stuff here */
}

function plugin_routerconfigs_upgrade() {
	/* Here we will upgrade to the newest version */
	routerconfigs_check_upgrade();

	return false;
}

function routerconfigs_check_upgrade() {
	global $config, $database_default;

	include_once($config['library_path'] . '/database.php');
	include_once($config['library_path'] . '/functions.php');

	// Let's only run this check if we are on a page that actually needs the data
	$files = array('plugins.php','router-devices.php');
	if (!in_array(get_current_page(), $files)) {
		return;
	}

	$current = plugin_routerconfigs_version();
	$current = $current['version'];
	$old     = db_fetch_cell("SELECT version FROM plugin_config WHERE directory='routerconfigs'");

	if ($current != $old) {
		/* update realms for old versions */
		if (cacti_version_compare($old,'0.2','<')) {
			api_plugin_register_realm('routerconfigs', 'router-devices.php,router-accounts.php,router-backups.php,router-compare.php', 'Plugin -> Router Configs', 1);

			/* get the realm id's and change from old to new */
			$user  = db_fetch_cell("SELECT id FROM plugin_realms WHERE file='router-devices.php'");
			if ($user >  0) {
				$users = db_fetch_assoc('SELECT user_id FROM user_auth_realm WHERE realm_id=86');

				if (sizeof($users)) {
					foreach($users as $u) {
						db_execute("INSERT INTO user_auth_realm
							(realm_id, user_id) VALUES ($user, " . $u['user_id'] . ')
							ON DUPLICATE KEY UPDATE realm_id=VALUES(realm_id)');

						db_execute('DELETE FROM user_auth_realm
							WHERE user_id=' . $u['user_id'] . "
							AND realm_id=$user");
					}
				}
			}
		}

		if (cacti_version_compare($old, '1.2', '<')) {
			if (!db_column_exists('connect_type','plugin_routerconfigs_devices')) {
				db_execute('ALTER TABLE plugin_routerconfigs_devices
					ADD COLUMN `connect_type` varchar(10) DEFAULT \'both\'');
			}
		}
		db_execute("UPDATE plugin_config
			SET version='$current'
			WHERE directory='routerconfigs'");
	}
}

function routerconfigs_check_dependencies() {
	global $plugins, $config;
	return true;
}

function routerconfigs_setup_table_new() {
	$data = array();
	$data['primary'] = 'id';
	$data['type'] = 'InnoDB';
	$data['comment'] = 'Router Config Accounts';

	$data['columns'][] = array('name' => 'id', 'type' => 'int(11)', 'NULL' => false, 'auto_increment' => true);
	$data['columns'][] = array('name' => 'name', 'type' => 'varchar(64)', 'NULL' => true);
	$data['columns'][] = array('name' => 'username', 'type' => 'varchar(64)', 'NULL' => true);
	$data['columns'][] = array('name' => 'password', 'type' => 'varchar(256)', 'NULL' => true);
	$data['columns'][] = array('name' => 'enablepw', 'type' => 'varchar(256)', 'NULL' => true);

	api_plugin_db_table_create ('routerconfigs', 'plugin_routerconfigs_accounts', $data);

	$data = array();
	$data['type'] = 'InnoDB';
	$data['comment'] = 'Router Config Backups';
	$data['primary'] = 'id';

	$data['columns'][] = array('name' => 'id', 'type' => 'int(11)', 'NULL' => false, 'auto_increment' => true);
	$data['columns'][] = array('name' => 'btime', 'type' => 'int(18)', 'NULL' => true);
	$data['columns'][] = array('name' => 'device', 'type' => 'int(11)', 'NULL' => true);
	$data['columns'][] = array('name' => 'directory', 'type' => 'varchar(255)', 'NULL' => true);
	$data['columns'][] = array('name' => 'filename', 'type' => 'varchar(255)', 'NULL' => true);
	$data['columns'][] = array('name' => 'config', 'type' => 'longblob', 'NULL' => true);
	$data['columns'][] = array('name' => 'lastchange', 'type' => 'int(24)', 'NULL' => true);
	$data['columns'][] = array('name' => 'username', 'type' => 'varchar(64)', 'NULL' => true);

	$data['keys'][] = array('name' => 'btime', 'columns' => 'btime');
	$data['keys'][] = array('name' => 'device', 'columns' => 'device');
	$data['keys'][] = array('name' => 'directory', 'columns' => 'directory');
	$data['keys'][] = array('name' => 'lastchange', 'columns' => 'lastchange');

	api_plugin_db_table_create ('routerconfigs', 'plugin_routerconfigs_backups', $data);

	$data = array();

	$data['primary'] = 'id';
	$data['type'] = 'InnoDB';
	$data['comment'] = 'Router Config Devices';

	$data['columns'][] = array('name' => 'id', 'type' => 'int(11)', 'NULL' => false, 'auto_increment' => true);
	$data['columns'][] = array('name' => 'enabled', 'type' => 'varchar(2)', 'NULL' => true);
	$data['columns'][] = array('name' => 'ipaddress', 'type' => 'varchar(128)', 'NULL' => true);
	$data['columns'][] = array('name' => 'hostname', 'type' => 'varchar(255)', 'NULL' => true);
	$data['columns'][] = array('name' => 'directory', 'type' => 'varchar(255)', 'NULL' => true);
	$data['columns'][] = array('name' => 'account', 'type' => 'int(11)', 'NULL' => true);
	$data['columns'][] = array('name' => 'lastchange', 'type' => 'int(24)', 'NULL' => true);
	$data['columns'][] = array('name' => 'device', 'type' => 'int(11)', 'NULL' => true);
	$data['columns'][] = array('name' => 'username', 'type' => 'varchar(64)', 'NULL' => true);
	$data['columns'][] = array('name' => 'schedule', 'type' => 'int(11)', 'NULL' => true);
	$data['columns'][] = array('name' => 'lasterror', 'type' => 'varchar(255)', 'NULL' => true);
	$data['columns'][] = array('name' => 'lastbackup', 'type' => 'int(18)', 'NULL' => true);
	$data['columns'][] = array('name' => 'lastattempt', 'type' => 'int(18)', 'NULL' => true);
	$data['columns'][] = array('name' => 'devicetype', 'type' => 'int(11)', 'NULL' => true);
	$data['columns'][] = array('name' => 'connect_type', 'type' => 'varchar(10)', 'NULL' => false, 'default' => 'both');
	$data['columns'][] = array('name' => 'debug', 'type' => 'longblob', 'NULL' => true);

	$data['keys'][] = array('name' => 'enabled', 'columns' => 'enabled');
	$data['keys'][] = array('name' => 'schedule', 'columns' => 'schedule');
	$data['keys'][] = array('name' => 'ipaddress', 'columns' => 'ipaddress');
	$data['keys'][] = array('name' => 'account', 'columns' => 'account');
	$data['keys'][] = array('name' => 'lastbackup', 'columns' => 'lastbackup');
	$data['keys'][] = array('name' => 'lastattempt', 'columns' => 'lastattempt');
	$data['keys'][] = array('name' => 'devicetype', 'columns' => 'devicetype');

	api_plugin_db_table_create ('routerconfigs', 'plugin_routerconfigs_devices', $data);

	$data = array();

	$data['primary'] = 'id';
	$data['type'] = 'InnoDB';
	$data['comment'] = 'Router Config Device Types';

	$data['columns'][] = array('name' => 'id', 'type' => 'int(11)', 'NULL' => false, 'auto_increment' => true);
	$data['columns'][] = array('name' => 'name', 'type' => 'varchar(64)', 'NULL' => true);
	$data['columns'][] = array('name' => 'username', 'type' => 'varchar(64)', 'NULL' => true);
	$data['columns'][] = array('name' => 'password', 'type' => 'varchar(256)', 'NULL' => true);
	$data['columns'][] = array('name' => 'copytftp', 'type' => 'varchar(64)', 'NULL' => true);
	$data['columns'][] = array('name' => 'version', 'type' => 'varchar(64)', 'NULL' => true);
	$data['columns'][] = array('name' => 'confirm', 'type' => 'varchar(64)', 'NULL' => true);
	$data['columns'][] = array('name' => 'forceconfirm', 'type' => 'char(2)', 'NULL' => true, 'default' => 'on');
	$data['columns'][] = array('name' => 'checkendinconfig', 'type' => 'char(2)', 'NULL' => true, 'default' => 'on');

	api_plugin_db_table_create ('routerconfigs', 'plugin_routerconfigs_devicetypes', $data);

	db_execute("REPLACE INTO plugin_routerconfigs_devicetypes
		(id, name, username, password, copytftp, version, confirm, forceconfirm, checkendinconfig)
		VALUES
		(1, 'Cisco IOS', 'username:', 'password:', 'copy run tftp', 'show version', 'y', '', 'on'),
		(2, 'Cisco CatOS', 'username:', 'password:', 'copy config tftp', '', 'y', 'on', '')");
}

function routerconfigs_page_head () {
	global $config;

	if (strpos(get_current_page(), 'router-compare.php')) {
		print '<link rel="stylesheet" type="text/css" href="' . $config['url_path'] . "plugins/routerconfigs/diff.css\">\n";
	}
}

function routerconfigs_poller_bottom () {
	global $config;

	$h = date('G', time());
	$s = date('i', time()) * 60;

	/* Check for the polling interval, only valid with the Multipoller patch */
	$poller_interval = read_config_option('poller_interval');
	if (!isset($poller_interval)) {
		$poller_interval = 300;
	}

	if ($s < $poller_interval) {
		$command_string = trim(read_config_option('path_php_binary'));

		if (trim($command_string) == '') {
			$command_string = 'php';
		}

		$extra_args = ' -q ' . $config['base_path'] . '/plugins/routerconfigs/router-download.php';

		$daily = read_config_option('routerconfigs_hour');
		if ($daily === false || $daily < 0 || $daily > 23) {
			$daily = 0;
		}
		$daily = (int)$daily;

		if ($h != $daily) {
			$extra_args .= ' --retry';
		}

		cacti_log(__("DEBUG: Executing '%s' with arguments '%s'",$command_string,$extra_args,'routerconfigs'),true,'RCONFIG', POLLER_VERBOSITY_NONE);
		exec_background($command_string, $extra_args);
	}
}

function routerconfigs_config_settings () {
	global $tabs, $settings, $config;

	routerconfigs_check_upgrade();

	if (function_exists('gethostname')) {
		$hostname = gethostname();
	}else{
		$hostname = php_uname('n');
	}
	$tabs['routerconfigs'] = __('Router Configs', 'routerconfigs');

	$temp = array(
		'routerconfigs_header' => array(
			'friendly_name' => __('Router Configs', 'routerconfigs'),
			'method' => 'spacer',
		),
		'routerconfigs_debug_buffer' => array(
			'friendly_name' => __('Debug Connection Buffer', 'routerconfigs'),
			'description' => __('Whether to log direct output of device connection', 'routerconfigs'),
			'method' => 'checkbox'
		),
		'routerconfigs_hour' => array(
			'friendly_name' => __('Download Hour', 'routerconfigs'),
			'description' => __('The hour of the day to perform the full downloads.', 'routerconfigs'),
			'method' => 'drop_array',
			'default' => '0',
			'array' => array(
				'0'  => __('00:00 (12am)', 1, 'routerconfigs'),
				'1'  => __('01:00 (1am)', 1, 'routerconfigs'),
				'2'  => __('02:00 (2am)', 1, 'routerconfigs'),
				'3'  => __('03:00 (3am)', 1, 'routerconfigs'),
				'4'  => __('04:00 (4am)', 1, 'routerconfigs'),
				'5'  => __('05:00 (5am)', 1, 'routerconfigs'),
				'6'  => __('06:00 (6am)', 1, 'routerconfigs'),
				'7'  => __('07:00 (7am)', 1, 'routerconfigs'),
				'8'  => __('08:00 (8am)', 1, 'routerconfigs'),
				'9'  => __('09:00 (9am)', 1, 'routerconfigs'),
				'10'  => __('10:00 (12am)', 1, 'routerconfigs'),
				'11'  => __('11:00 (11am)', 1, 'routerconfigs'),
				'12'  => __('12:00 (12pm)', 1, 'routerconfigs'),
				'13'  => __('13:00 (1pm)', 1, 'routerconfigs'),
				'14'  => __('14:00 (2pm)', 1, 'routerconfigs'),
				'15'  => __('15:00 (3pm)', 1, 'routerconfigs'),
				'16'  => __('16:00 (4pm)', 1, 'routerconfigs'),
				'17'  => __('17:00 (5pm)', 1, 'routerconfigs'),
				'18'  => __('18:00 (6pm)', 1, 'routerconfigs'),
				'19'  => __('19:00 (7pm)', 1, 'routerconfigs'),
				'20'  => __('20:00 (8pm)', 1, 'routerconfigs'),
				'21'  => __('21:00 (9pm)', 1, 'routerconfigs'),
				'22'  => __('22:00 (10pm)', 1, 'routerconfigs'),
				'23'  => __('23:00 (11pm)', 1, 'routerconfigs'),
			)
		),
		'routerconfigs_retention' => array(
			'friendly_name' => __('Retention Period', 'routerconfigs'),
			'description' => __('The number of days to retain old backups.', 'routerconfigs'),
			'method' => 'drop_array',
			'default' => '30',
			'array' => array(
				'30'  => __('%d Month', 1, 'routerconfigs'),
				'60'  => __('%d Months', 2, 'routerconfigs'),
				'90'  => __('%d Months', 3, 'routerconfigs'),
				'120' => __('%d Months', 4, 'routerconfigs'),
				'180' => __('%d Months', 6, 'routerconfigs'),
				'365' => __('%d Year', 1, 'routerconfigs')
			)
		),
		'routerconfigs_header_tftp' => array(
			'friendly_name' => __('Router Configs - TFTP', 'routerconfigs'),
			'method' => 'spacer',
		),
		'routerconfigs_tftpserver' => array(
			'friendly_name' => __('TFTP Server IP', 'routerconfigs'),
			'description' => __('Must be an IP pointing to your Cacti server.', 'routerconfigs'),
			'method' => 'textbox',
			'max_length' => 255,
			'default' => gethostbyname($hostname)
		),
		'routerconfigs_backup_path' => array(
			'friendly_name' => __('Backup Directory Path', 'routerconfigs'),
			'description' => __('The path to where your Configs will be backed up, it must be the path that the local TFTP Server writes to.', 'routerconfigs'),
			'method' => 'dirpath',
			'max_length' => 255,
			'size' => '50',
			'default' => $config['base_path'] . '/backups/'
		),
		'routerconfigs_header_email' => array(
			'friendly_name' => __('Router Configs - Email', 'routerconfigs'),
			'method' => 'spacer',
		),
		'routerconfigs_from' => array(
			'friendly_name' => __('From Address', 'routerconfigs'),
			'description' => __('Email address the nightly backup will be sent from.', 'routerconfigs'),
			'method' => 'textbox',
			'size' => 40,
			'max_length' => 255,
			'default' => ''
		),
		'routerconfigs_name' => array(
			'friendly_name' => __('From Name', 'routerconfigs'),
			'description' => __('Name the nightly backup will be sent from.', 'routerconfigs'),
			'method' => 'textbox',
			'size' => 40,
			'max_length' => 255,
			'default' => ''
		),
		'routerconfigs_email' => array(
			'friendly_name' => __('Email Address', 'routerconfigs'),
			'description' => __('A comma delimited list of Email addresses to send the nightly backup Email to.', 'routerconfigs'),
			'method' => 'textarea',
			'class' => 'textAreaNotes',
			'textarea_rows' => '5',
			'textarea_cols' => '40',
			'size' => 40,
			'max_length' => 255,
			'default' => ''
		),
	);

	if (isset($settings['routerconfigs'])) {
		$settings['routerconfigs'] = array_merge($settings['routerconfigs'], $temp);
	} else {
		$settings['routerconfigs'] = $temp;
	}
}

function routerconfigs_config_arrays () {
	global $menu;

	plugin_routerconfigs_upgrade();

	$menu[__('Utilities', 'routerconfigs')]['plugins/routerconfigs/router-devices.php'] = __('Router Configs', 'routerconfigs');
}

function routerconfigs_draw_navigation_text ($nav) {
	$nav['router-devices.php:'] = array(
		'title' => __('Router Devices', 'routerconfigs'),
		'mapping' => 'index.php:',
		'url' => 'router-devices.php',
		'level' => '1'
	);

	$nav['router-devices.php:edit'] = array(
		'title' => __('(edit)', 'routerconfigs'),
		'mapping' => 'index.php:,router-devices.php:',
		'url' => 'router-devices.php',
		'level' => '2'
	);

	$nav['router-devices.php:actions'] = array(
		'title' => __('(actions)', 'routerconfigs'),
		'mapping' => 'index.php:,router-devices.php:',
		'url' => 'router-devices.php',
		'level' => '2'
	);

	$nav['router-devices.php:viewconfig'] = array(
		'title' => __('View Config', 'routerconfigs'),
		'mapping' => 'index.php:,router-devices.php:',
		'url' => 'router-devices.php',
		'level' => '2'
	);

	$nav['router-devices.php:viewdebug'] = array(
		'title' => __('View Debug', 'routerconfigs'),
		'mapping' => 'index.php:,router-devices.php:',
		'url' => 'router-devices.php',
		'level' => '2'
	);

	$nav['router-backups.php:'] = array(
		'title' => __('Router Backups', 'routerconfigs'),
		'mapping' => 'index.php:',
		'url' => 'router-backups.php',
		'level' => '1'
	);

	$nav['router-backups.php:edit'] = array(
		'title' => __('(edit)', 'routerconfigs'),
		'mapping' => 'index.php:,router-backups.php:',
		'url' => 'router-backups.php',
		'level' => '2'
	);

	$nav['router-backups.php:actions'] = array(
		'title' => __('(actions)', 'routerconfigs'),
		'mapping' => 'index.php:,router-backups.php:',
		'url' => 'router-backups.php',
		'level' => '2'
	);

	$nav['router-backups.php:viewconfig'] = array(
		'title' => __('View Config', 'routerconfigs'),
		'mapping' => 'index.php:,router-backups.php:',
		'url' => 'router-backups.php',
		'level' => '2'
	);

	$nav['router-accounts.php:'] = array(
		'title' => __('Router Accounts', 'routerconfigs'),
		'mapping' => 'index.php:',
		'url' => 'router-accounts.php',
		'level' => '1'
	);

	$nav['router-accounts.php:edit'] = array(
		'title' => __('(edit)', 'routerconfigs'),
		'mapping' => 'index.php:,router-accounts.php:',
		'url' => 'router-accounts.php',
		'level' => '2'
	);

	$nav['router-accounts.php:actions'] = array(
		'title' => __('(actions)', 'routerconfigs'),
		'mapping' => 'index.php:,router-accounts.php:',
		'url' => 'router-accounts.php',
		'level' => '2'
	);

	$nav['router-compare.php:'] = array(
		'title' => __('Router Compare', 'routerconfigs'),
		'mapping' => 'index.php:',
		'url' => 'router-compare.php',
		'level' => '1'
	);

	return $nav;
}


