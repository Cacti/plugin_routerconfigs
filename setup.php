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
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

include_once(__DIR__ . '/include/arrays.php');

/**
 * Reads this plugin's version/author metadata from its INFO file.
 * Invoked by the Cacti plugin framework to display plugin information,
 * and called from routerconfigs_check_upgrade() to detect a pending
 * schema upgrade.
 *
 * @return array The plugin's INFO file 'info' section (name, version,
 *               author, etc.).
 *
 * @global array $config Cacti global configuration array; used to
 *                       locate the plugin's INFO file.
 */
function plugin_routerconfigs_version() {
	global $config;
	$info = parse_ini_file($config['base_path'] . '/plugins/routerconfigs/INFO', true);

	return $info['info'];
}

/**
 * Registers this plugin's Cacti hooks (tab display, config arrays,
 * navigation breadcrumbs, settings, poller_bottom, page_head) and its
 * router-*.php realm, then creates the plugin's database tables.
 * Invoked by the Cacti plugin framework when the plugin is
 * installed/enabled.
 *
 * @return void
 */
function plugin_routerconfigs_install() {
	api_plugin_register_hook('routerconfigs', 'top_header_tabs',       'routerconfigs_show_tab', 'setup.php');
	api_plugin_register_hook('routerconfigs', 'top_graph_header_tabs', 'routerconfigs_show_tab', 'setup.php');
	api_plugin_register_hook('routerconfigs', 'config_arrays',         'routerconfigs_config_arrays',        'setup.php');
	api_plugin_register_hook('routerconfigs', 'draw_navigation_text',  'routerconfigs_draw_navigation_text', 'setup.php');
	api_plugin_register_hook('routerconfigs', 'config_settings',       'routerconfigs_config_settings',      'setup.php');
	api_plugin_register_hook('routerconfigs', 'poller_bottom',         'routerconfigs_poller_bottom',        'setup.php');
	api_plugin_register_hook('routerconfigs', 'page_head',             'routerconfigs_page_head',            'setup.php');

	api_plugin_register_realm('routerconfigs', 'router-devices.php,router-accounts.php,router-backups.php,router-compare.php,router-devtypes.php', __('Router Configs', 'routerconfigs'), 1);

	routerconfigs_setup_table_new();
}

/**
 * No-op uninstall hook; this plugin does not remove its database tables
 * on uninstall. Invoked by the Cacti plugin framework when the plugin is
 * uninstalled.
 *
 * @return void
 */
function plugin_routerconfigs_uninstall() {
	// Do any extra Uninstall stuff here
}

/**
 * Here we will upgrade to the newest version
 *
 * Runs any pending database schema upgrade for this plugin. Invoked by
 * the Cacti plugin framework when the plugin's installed version
 * differs from its current version.
 *
 * @return bool Always false.
 */
function plugin_routerconfigs_upgrade() {
	// Here we will upgrade to the newest version
	routerconfigs_check_upgrade();

	return false;
}

/**
 * Applies version-gated schema migrations for this plugin (realm
 * updates, column renames/adds/drops across several historical
 * versions, SSH host-key storage columns, seeding built-in device
 * types) based on comparing the installed version recorded in
 * plugin_config against the current INFO file version, then updates the
 * recorded version. Only runs on plugins.php, router-devices.php, or
 * settings.php to avoid the version lookup on every page. Called from
 * plugin_routerconfigs_upgrade() and routerconfigs_config_arrays().
 *
 * @return void
 *
 * @global array  $config           Cacti global configuration array;
 *                                  used to locate database/functions
 *                                  libraries.
 * @global object $database_default Reserved/declared for parity with
 *                                  the included library files; not used
 *                                  directly here.
 */
function routerconfigs_check_upgrade() {
	global $config, $database_default;

	include_once($config['library_path'] . '/database.php');
	include_once($config['library_path'] . '/functions.php');

	// Let's only run this check if we are on a page that actually needs the data
	$files = ['plugins.php', 'router-devices.php', 'settings.php'];

	if (!in_array(get_current_page(), $files, true)) {
		return;
	}

	$current              = plugin_routerconfigs_version();
	$current              = $current['version'];
	$old                  = db_fetch_cell_prepared('SELECT version FROM plugin_config WHERE directory = ?', ['routerconfigs']);
	$hostkey_schema_ready = routerconfigs_ensure_hostkey_schema();

	if ($current != $old) {
		api_plugin_register_hook('routerconfigs', 'top_header_tabs',       'routerconfigs_show_tab', 'setup.php', 1);
		api_plugin_register_hook('routerconfigs', 'top_graph_header_tabs', 'routerconfigs_show_tab', 'setup.php', 1);

		// update realms for old versions
		if (cacti_version_compare($old,'0.2','<')) {
			api_plugin_register_realm('routerconfigs', 'router-devices.php,router-accounts.php,router-backups.php,router-compare.php', 'Plugin -> Router Configs', 1);

			// get the realm id's and change from old to new
			$user  = db_fetch_cell_prepared('SELECT id FROM plugin_realms WHERE file=?', ['router-devices.php']);

			if ($user > 0) {
				$users = db_fetch_assoc_prepared('SELECT user_id FROM user_auth_realm WHERE realm_id = ?', [86]);

				if (sizeof($users)) {
					foreach ($users as $u) {
						db_execute_prepared('INSERT INTO user_auth_realm
							(realm_id, user_id) VALUES (?, ?)
							ON DUPLICATE KEY UPDATE realm_id=VALUES(realm_id)',
							[(int) $user, (int) $u['user_id']]);

						db_execute_prepared('DELETE FROM user_auth_realm
							WHERE user_id = ?
							AND realm_id = ?',
							[(int) $u['user_id'], 86]);
					}
				}
			}
		}

		if (cacti_version_compare($old, '1.4.0', '<')) {
			plugin_routerconfigs_fix_backups_pre14();
		}

		if (cacti_version_compare($old, '1.5.1', '<')) {
			// Remove old columns of backups
			if (db_column_exists('plugin_routerconfigs_backups', 'config')) {
				db_execute('ALTER TABLE plugin_routerconfigs_backups
					DROP COLUMN `config`');
			}

			if (db_column_exists('plugin_routerconfigs_backups','username')) {
				db_execute('ALTER TABLE plugin_routerconfigs_backups
					CHANGE COLUMN `username` `lastuser` varchar(64)');
			}

			if (db_column_exists('plugin_routerconfigs_devices','password')) {
				db_execute('ALTER TABLE plugin_routerconfigs_devices
					DROP COLUMN `password`');
			}

			if (db_column_exists('plugin_routerconfigs_devices', 'anykey')) {
				db_execute('ALTER TABLE plugin_routerconfigs_devices
					DROP COLUMN `anykey`');
			}

			// Rename existing columns of devices
			if (db_column_exists('plugin_routerconfigs_devices','connect_type')) {
				db_execute('ALTER TABLE plugin_routerconfigs_devices
					CHANGE COLUMN `connect_type` `connecttype` varchar(10) DEFAULT \'\'');
			}

			if (db_column_exists('plugin_routerconfigs_devices','username')) {
				db_execute('ALTER TABLE plugin_routerconfigs_devices
					CHANGE COLUMN `username` `lastuser` varchar(64)');
			}

			// Add new/missing columns of devices
			if (!db_column_exists('plugin_routerconfigs_devices','connecttype')) {
				db_execute('ALTER TABLE plugin_routerconfigs_devices
					ADD COLUMN `connecttype` varchar(10) DEFAULT \'\'');
			}

			if (!db_column_exists('plugin_routerconfigs_devices','nextbackup')) {
				db_execute('ALTER TABLE plugin_routerconfigs_devices
					ADD COLUMN `nextbackup` int(18)');
			}

			if (!db_column_exists('plugin_routerconfigs_devices','nextattempt')) {
				db_execute('ALTER TABLE plugin_routerconfigs_devices
					ADD COLUMN `nextattempt` int(18)');
			}

			if (!db_column_exists('plugin_routerconfigs_devices', 'timeout')) {
				db_execute('ALTER TABLE plugin_routerconfigs_devices
					ADD COLUMN `timeout` int(18)');
			}

			if (!db_column_exists('plugin_routerconfigs_devices', 'sleep')) {
				db_execute('ALTER TABLE plugin_routerconfigs_devices
					ADD COLUMN `sleep` int(18)');
			}

			if (!db_column_exists('plugin_routerconfigs_devices', 'elevated')) {
				db_execute('ALTER TABLE plugin_routerconfigs_devices
					ADD COLUMN `elevated` char(3)');
			}

			// Perform tidy up of devices
			db_execute_prepared('UPDATE plugin_routerconfigs_devices SET
				nextbackup = IFNULL(nextbackup,0),
				nextattempt = IFNULL(nextattempt,0)', []);

			// Rename existing columns of device types
			if (db_column_exists('plugin_routerconfigs_devicetypes','connect_type')) {
				db_execute('ALTER TABLE plugin_routerconfigs_devicetypes
					CHANGE COLUMN `connect_type` `connecttype` varchar(10) DEFAULT \'\'');
			}

			if (db_column_exists('plugin_routerconfigs_devicetypes','username')) {
				db_execute('ALTER TABLE plugin_routerconfigs_devicetypes
					CHANGE COLUMN `username` `promptuser` varchar(64)');
			}

			if (db_column_exists('plugin_routerconfigs_devicetypes','password')) {
				db_execute('ALTER TABLE plugin_routerconfigs_devicetypes
					CHANGE COLUMN `password` `promptpass` varchar(256)');
			}

			// Add new/missing columns of device types
			if (!db_column_exists('plugin_routerconfigs_devicetypes', 'anykey')) {
				db_execute('ALTER TABLE plugin_routerconfigs_devicetypes
					ADD COLUMN `anykey` varchar(50) DEFAULT \'\'');
			}

			if (!db_column_exists('plugin_routerconfigs_devicetypes', 'configfile')) {
				db_execute('ALTER TABLE plugin_routerconfigs_devicetypes
					ADD COLUMN `configfile` varchar(256) DEFAULT \'\'');
			}

			if (!db_column_exists('plugin_routerconfigs_devicetypes','connecttype')) {
				db_execute('ALTER TABLE plugin_routerconfigs_devicetypes
					ADD COLUMN `connecttype` varchar(10) DEFAULT \'both\'');
			}

			if (!db_column_exists('plugin_routerconfigs_devicetypes', 'sleep')) {
				db_execute('ALTER TABLE plugin_routerconfigs_devicetypes
					ADD COLUMN `sleep` int(18)');
			}

			if (!db_column_exists('plugin_routerconfigs_devicetypes', 'timeout')) {
				db_execute('ALTER TABLE plugin_routerconfigs_devicetypes
					ADD COLUMN `timeout` int(18)');
			}

			if (!db_column_exists('plugin_routerconfigs_devicetypes', 'elevated')) {
				db_execute('ALTER TABLE plugin_routerconfigs_devicetypes
					ADD COLUMN `elevated` char(3)');
			}
		}

		if (cacti_version_compare($old, '1.5.2', '<')) {
			if (!db_column_exists('plugin_routerconfigs_devicetypes','promptconfirm')) {
				db_execute('ALTER TABLE plugin_routerconfigs_devicetypes
					ADD COLUMN `promptconfirm` varchar(64) DEFAULT \'confirm|to tftp:\'');
			}
		}

		if (cacti_version_compare($old, '1.5.3', '<')) {
			if (!db_column_exists('plugin_routerconfigs_devicetypes','promptconfirm')) {
				db_execute('ALTER TABLE plugin_routerconfigs_devicetypes
					MODIFY COLUMN `promptconfirm` varchar(64) DEFAULT \'confirm|to tftp:\'');
			}
		}

		AddDeviceTypes();

		if (!$hostkey_schema_ready) {
			cacti_log('ERROR: Routerconfigs upgrade incomplete: unable to create SSH host-key storage columns', false, 'RCONFIG');
		}

		db_execute_prepared('UPDATE plugin_config
			SET version = ?
			WHERE directory = ?',
			[$current, 'routerconfigs']);
	}
}

/**
 * Adds the SSH host-key fingerprint/type storage columns to
 * plugin_routerconfigs_devices if they don't already exist. Called from
 * routerconfigs_check_upgrade() during upgrade.
 *
 * @return bool True if both columns exist after this call (whether they
 *              already existed or were just added), false if adding them
 *              failed.
 */
function routerconfigs_ensure_hostkey_schema() {
	if (!db_column_exists('plugin_routerconfigs_devices', 'ssh_fingerprint')) {
		db_execute('ALTER TABLE plugin_routerconfigs_devices
			ADD COLUMN `ssh_fingerprint` varchar(255) DEFAULT NULL');
	}

	if (!db_column_exists('plugin_routerconfigs_devices', 'ssh_hostkey_type')) {
		db_execute('ALTER TABLE plugin_routerconfigs_devices
			ADD COLUMN `ssh_hostkey_type` varchar(64) DEFAULT NULL');
	}

	return db_column_exists('plugin_routerconfigs_devices', 'ssh_fingerprint') &&
		db_column_exists('plugin_routerconfigs_devices', 'ssh_hostkey_type');
}

/**
 * No-op dependency check. Invoked by the Cacti plugin framework to
 * verify this plugin's dependencies are satisfied before
 * installation/upgrade.
 *
 * @return bool Always true.
 *
 * @global array $plugins Reserved/declared for parity with other hook
 *                        implementations; not used directly here.
 * @global array $config  Cacti global configuration array (declared but
 *                        not directly used here).
 */
function routerconfigs_check_dependencies() {
	global $plugins, $config;

	return true;
}

/**
 * Creates all of this plugin's database tables (accounts, backups,
 * devices, device types, including SSH host-key storage columns) and
 * seeds the built-in device types. Called from
 * plugin_routerconfigs_install() during plugin installation.
 *
 * @return void
 */
function routerconfigs_setup_table_new() {
	$data            = [];
	$data['primary'] = 'id';
	$data['type']    = 'InnoDB';
	$data['comment'] = 'Router Config Accounts';

	$data['columns'][] = ['name' => 'id', 'type' => 'int(11)', 'NULL' => false, 'auto_increment' => true];
	$data['columns'][] = ['name' => 'name', 'type' => 'varchar(64)', 'NULL' => true];
	$data['columns'][] = ['name' => 'username', 'type' => 'varchar(64)', 'NULL' => true];
	$data['columns'][] = ['name' => 'password', 'type' => 'varchar(256)', 'NULL' => true];
	$data['columns'][] = ['name' => 'enablepw', 'type' => 'varchar(256)', 'NULL' => true];
	$data['columns'][] = ['name' => 'elevated', 'type' => 'varchar(3)', 'NULL' => true];

	api_plugin_db_table_create('routerconfigs', 'plugin_routerconfigs_accounts', $data);

	$data            = [];
	$data['type']    = 'InnoDB';
	$data['comment'] = 'Router Config Backups';
	$data['primary'] = 'id';

	$data['columns'][] = ['name' => 'id', 'type' => 'int(11)', 'NULL' => false, 'auto_increment' => true];
	$data['columns'][] = ['name' => 'btime', 'type' => 'int(18)', 'NULL' => true];
	$data['columns'][] = ['name' => 'device', 'type' => 'int(11)', 'NULL' => true];
	$data['columns'][] = ['name' => 'directory', 'type' => 'varchar(255)', 'NULL' => true];
	$data['columns'][] = ['name' => 'filename', 'type' => 'varchar(255)', 'NULL' => true];
	$data['columns'][] = ['name' => 'lastchange', 'type' => 'int(24)', 'NULL' => true];
	$data['columns'][] = ['name' => 'lastuser', 'type' => 'varchar(64)', 'NULL' => true];

	$data['keys'][] = ['name' => 'btime', 'columns' => 'btime'];
	$data['keys'][] = ['name' => 'device', 'columns' => 'device'];
	$data['keys'][] = ['name' => 'directory', 'columns' => 'directory'];
	$data['keys'][] = ['name' => 'lastchange', 'columns' => 'lastchange'];

	api_plugin_db_table_create('routerconfigs', 'plugin_routerconfigs_backups', $data);

	$data = [];

	$data['primary'] = 'id';
	$data['type']    = 'InnoDB';
	$data['comment'] = 'Router Config Devices';

	$data['columns'][] = ['name' => 'id', 'type' => 'int(11)', 'NULL' => false, 'auto_increment' => true];
	$data['columns'][] = ['name' => 'enabled', 'type' => 'varchar(2)', 'NULL' => true];
	$data['columns'][] = ['name' => 'ipaddress', 'type' => 'varchar(128)', 'NULL' => true];
	$data['columns'][] = ['name' => 'hostname', 'type' => 'varchar(255)', 'NULL' => true];
	$data['columns'][] = ['name' => 'directory', 'type' => 'varchar(255)', 'NULL' => true];
	$data['columns'][] = ['name' => 'account', 'type' => 'int(11)', 'NULL' => true];
	$data['columns'][] = ['name' => 'lastchange', 'type' => 'int(24)', 'NULL' => true];
	$data['columns'][] = ['name' => 'lastuser', 'type' => 'varchar(64)', 'NULL' => true];
	$data['columns'][] = ['name' => 'device', 'type' => 'int(11)', 'NULL' => true];
	$data['columns'][] = ['name' => 'schedule', 'type' => 'int(11)', 'NULL' => true];
	$data['columns'][] = ['name' => 'lasterror', 'type' => 'varchar(255)', 'NULL' => true];
	$data['columns'][] = ['name' => 'lastbackup', 'type' => 'int(18)', 'NULL' => true];
	$data['columns'][] = ['name' => 'nextbackup', 'type' => 'int(18)', 'NULL' => true];
	$data['columns'][] = ['name' => 'lastattempt', 'type' => 'int(18)', 'NULL' => true];
	$data['columns'][] = ['name' => 'nextattempt', 'type' => 'int(18)', 'NULL' => true];
	$data['columns'][] = ['name' => 'devicetype', 'type' => 'int(11)', 'NULL' => true];
	$data['columns'][] = ['name' => 'connecttype', 'type' => 'varchar(10)', 'NULL' => true];
	$data['columns'][] = ['name' => 'elevated', 'type' => 'varchar(3)', 'NULL' => true];
	$data['columns'][] = ['name' => 'sleep', 'type' => 'int(11)', 'NULL' => true];
	$data['columns'][] = ['name' => 'timeout', 'type' => 'int(11)', 'NULL' => true];
	$data['columns'][] = ['name' => 'debug', 'type' => 'longblob', 'NULL' => true];
	$data['columns'][] = ['name' => 'ssh_fingerprint', 'type' => 'varchar(255)', 'NULL' => true];
	$data['columns'][] = ['name' => 'ssh_hostkey_type', 'type' => 'varchar(64)', 'NULL' => true];

	$data['keys'][] = ['name' => 'enabled', 'columns' => 'enabled'];
	$data['keys'][] = ['name' => 'schedule', 'columns' => 'schedule'];
	$data['keys'][] = ['name' => 'ipaddress', 'columns' => 'ipaddress'];
	$data['keys'][] = ['name' => 'account', 'columns' => 'account'];
	$data['keys'][] = ['name' => 'lastbackup', 'columns' => 'lastbackup'];
	$data['keys'][] = ['name' => 'lastattempt', 'columns' => 'lastattempt'];
	$data['keys'][] = ['name' => 'devicetype', 'columns' => 'devicetype'];

	api_plugin_db_table_create('routerconfigs', 'plugin_routerconfigs_devices', $data);

	$data = [];

	$data['primary'] = 'id';
	$data['type']    = 'InnoDB';
	$data['comment'] = 'Router Config Device Types';

	$data['columns'][] = ['name' => 'id', 'type' => 'int(11)', 'NULL' => false, 'auto_increment' => true];
	$data['columns'][] = ['name' => 'name', 'type' => 'varchar(64)', 'NULL' => true];
	$data['columns'][] = ['name' => 'promptuser', 'type' => 'varchar(64)', 'NULL' => true];
	$data['columns'][] = ['name' => 'promptpass', 'type' => 'varchar(256)', 'NULL' => true];
	$data['columns'][] = ['name' => 'connecttype', 'type' => 'varchar(10)', 'NULL' => true];
	$data['columns'][] = ['name' => 'configfile', 'type' => 'varchar(256)', 'NULL' => true];
	$data['columns'][] = ['name' => 'copytftp', 'type' => 'varchar(64)', 'NULL' => true];
	$data['columns'][] = ['name' => 'version', 'type' => 'varchar(64)', 'NULL' => true];
	$data['columns'][] = ['name' => 'promptconfirm', 'type' => 'varchar(64)', 'NULL' => true];
	$data['columns'][] = ['name' => 'confirm', 'type' => 'varchar(64)', 'NULL' => true];
	$data['columns'][] = ['name' => 'sleep', 'type' => 'int(11)', 'NULL' => true];
	$data['columns'][] = ['name' => 'timeout', 'type' => 'int(11)', 'NULL' => true];
	$data['columns'][] = ['name' => 'forceconfirm', 'type' => 'char(2)', 'NULL' => true, 'default' => 'on'];
	$data['columns'][] = ['name' => 'checkendinconfig', 'type' => 'char(2)', 'NULL' => true, 'default' => 'on'];
	$data['columns'][] = ['name' => 'anykey', 'type' => 'varchar(50)', 'NULL' => true];
	$data['columns'][] = ['name' => 'elevated', 'type' => 'varchar(3)', 'NULL' => true];

	api_plugin_db_table_create('routerconfigs', 'plugin_routerconfigs_devicetypes', $data);

	AddDeviceTypes();
}

/**
 * Seeds the built-in device type definitions (Cisco IOS/CatOS/Nexus, HP
 * Comware, Dell Switch) via AddDeviceType(). Called from
 * routerconfigs_setup_table_new() during install, and from
 * routerconfigs_check_upgrade() during upgrade to add any newly
 * introduced built-in device types.
 *
 * @return void
 */
function AddDeviceTypes() {
	AddDeviceType('Cisco IOS', 'username:', 'password:', 'copy run tftp', 'show version', 'y', '', 'on','');
	AddDeviceType('Cisco CatOS', 'username:', 'password:', 'copy config tftp', '', 'y', 'on', '', '');
	AddDeviceType('Cisco Nexus', 'Username:', 'Password:', 'copy running-config tftp://%SERVER%/%FILE% vrf management', 'show version', '', '', '', '');
	AddDeviceType('HP Comware', 'usernmae:', 'password:', 'copy startup.cfg tftp://%SERVER%/%FILE%', '', '', '', '', 'on');
	AddDeviceType('Dell Switch', 'User', 'Password', 'copy running-config tftp://%SERVER%/%FILE% vrf management', 'show version', 'y', '', '', '', 'Are you sure you want to start');
}

/**
 * Inserts a single built-in device type definition if a device type with
 * the same name doesn't already exist (so re-running this on upgrade
 * doesn't duplicate or overwrite a user-customized entry). Called from
 * AddDeviceTypes() for each built-in device type.
 *
 * @param string $name             The device type's display name.
 * @param string $promptuser       The username prompt pattern to expect.
 * @param string $promptpass       The password prompt pattern to expect.
 * @param string $copytftp         The command used to copy the running
 *                                config to a TFTP server.
 * @param string $version          The command used to query the device's
 *                                version/model.
 * @param string $confirm          The confirmation response text
 *                                expected during backup.
 * @param string $forceconfirm     Whether to force the confirmation
 *                                prompt.
 * @param string $checkendinconfig Whether to check for a recognizable
 *                                end-of-config marker.
 * @param string $elevated         Whether this device type requires an
 *                                elevated/enable password.
 * @param string $promptconfirm    The confirmation prompt pattern to
 *                                expect; defaults to
 *                                'confirm|to tftp:'.
 *
 * @return void
 */
function AddDeviceType($name, $promptuser, $promptpass, $copytftp, $version, $confirm, $forceconfirm, $checkendinconfig, $elevated, $promptconfirm = 'confirm|to tftp:') {
	$params = [ $name, $promptuser, $promptpass, $copytftp, $version, $confirm, $forceconfirm, $checkendinconfig, $elevated, $promptconfirm, $name ];
	db_execute_prepared('INSERT INTO plugin_routerconfigs_devicetypes
		(name, promptuser, promptpass, copytftp, version,
		confirm, forceconfirm, checkendinconfig, elevated,
		promptconfirm)
		SELECT
			? AS name, ? AS promptuser, ? AS promptpass, ? AS copytftp, ? AS version,
			? AS confirm, ? AS forceconfirm, ? AS checkendinconfig, ? AS elevated,
			? AS promptconfirm FROM DUAL
		WHERE NOT EXISTS(SELECT * FROM plugin_routerconfigs_devicetypes
			WHERE name = ? LIMIT 1)', $params);
}

/**
 * Injects this plugin's diff.css stylesheet into the page head when
 * viewing router-compare.php. Invoked by the Cacti plugin framework via
 * the 'page_head' hook.
 *
 * @return void
 *
 * @global array $config Cacti global configuration array; used to build
 *                       the stylesheet URL.
 */
function routerconfigs_page_head() {
	global $config;

	if (strpos(get_current_page(), 'router-compare.php') !== false) {
		print '<link rel="stylesheet" type="text/css" href="' . $config['url_path'] . "plugins/routerconfigs/css/diff.css\">\n";
	}
}

/**
 * Launches a background router-download.php process to run scheduled
 * device backups, once per poller interval near the top of each hour's
 * cycle, adding '--retry' outside the configured daily retry hour so
 * failed backups are retried more aggressively at other times. Invoked
 * by the Cacti plugin framework via the 'poller_bottom' hook at the end
 * of each poller cycle.
 *
 * @return void
 *
 * @global array $config Cacti global configuration array; used to
 *                       resolve the PHP binary and
 *                       router-download.php path.
 */
function routerconfigs_poller_bottom() {
	global $config;

	$h = date('G', time());
	$s = date('i', time()) * 60;

	// Check for the polling interval, only valid with the Multipoller patch
	$poller_interval = read_config_option('poller_interval');

	$poller_interval ??= 300;

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

		cacti_log(__("DEBUG: Executing '%s' with arguments '%s'", $command_string, $extra_args, 'routerconfigs'), true, 'RCONFIG', POLLER_VERBOSITY_DEBUG);

		exec_background($command_string, $extra_args);
	}
}

/**
 * Triggers a schema-upgrade check, then registers this plugin's
 * 'Router Configs' Settings tab and its configuration fields. Invoked by
 * the Cacti plugin framework via the 'config_settings' hook when
 * rendering the Settings page.
 *
 * @return void
 *
 * @global array $tabs        Cacti's registered settings tabs; a
 *                            'routerconfigs' entry is added.
 * @global array $settings    Cacti's registered settings fields; a
 *                            'routerconfigs' entry is added/merged with
 *                            $rc_settings.
 * @global array $config       Cacti global configuration array (declared
 *                            but not directly used here).
 * @global array $rc_settings  This plugin's settings field definitions,
 *                            merged into $settings.
 */
function routerconfigs_config_settings() {
	global $tabs, $settings, $config, $rc_settings;

	routerconfigs_check_upgrade();

	$tabs['routerconfigs'] = __('Router Configs', 'routerconfigs');

	if (isset($settings['routerconfigs'])) {
		$settings['routerconfigs'] = array_merge($settings['routerconfigs'], $rc_settings);
	} else {
		$settings['routerconfigs'] = $rc_settings;
	}
}

/**
 * Triggers a schema-upgrade check, and adds this plugin's 'Router
 * Configs' entry to the Utilities menu when using the 'console'
 * presentation style. Invoked by the Cacti plugin framework via the
 * 'config_arrays' hook.
 *
 * @return void
 *
 * @global array $menu Cacti's registered admin menu; a 'Router Configs'
 *                     entry is added under 'Utilities' when applicable.
 */
function routerconfigs_config_arrays() {
	global $menu;

	plugin_routerconfigs_upgrade();

	if (read_config_option('routerconfigs_presentation') == 'console') {
		$menu[__('Utilities', 'routerconfigs')]['plugins/routerconfigs/router-devices.php'] = __('Router Configs', 'routerconfigs');
	}
}

/**
 * Adds this plugin's page breadcrumb/navigation entries (device list/
 * edit/actions/view-config/view-debug, backup list/edit/actions/view-
 * config, account list/edit/actions, and compare). Invoked by the Cacti
 * plugin framework via the 'draw_navigation_text' hook.
 *
 * @param array $nav Cacti's registered navigation text entries.
 *
 * @return array The $nav array with this plugin's entries added.
 */
function routerconfigs_draw_navigation_text($nav) {
	$nav['router-devices.php:'] = [
		'title'   => __('Router Devices', 'routerconfigs'),
		'mapping' => 'index.php:',
		'url'     => 'router-devices.php',
		'level'   => '1'
	];

	$nav['router-devices.php:edit'] = [
		'title'   => __('(edit)', 'routerconfigs'),
		'mapping' => 'index.php:,router-devices.php:',
		'url'     => 'router-devices.php',
		'level'   => '2'
	];

	$nav['router-devices.php:actions'] = [
		'title'   => __('(actions)', 'routerconfigs'),
		'mapping' => 'index.php:,router-devices.php:',
		'url'     => 'router-devices.php',
		'level'   => '2'
	];

	$nav['router-devices.php:viewconfig'] = [
		'title'   => __('View Config', 'routerconfigs'),
		'mapping' => 'index.php:,router-devices.php:',
		'url'     => 'router-devices.php',
		'level'   => '2'
	];

	$nav['router-devices.php:viewdebug'] = [
		'title'   => __('View Debug', 'routerconfigs'),
		'mapping' => 'index.php:,router-devices.php:',
		'url'     => 'router-devices.php',
		'level'   => '2'
	];

	$nav['router-backups.php:'] = [
		'title'   => __('Router Backups', 'routerconfigs'),
		'mapping' => 'index.php:',
		'url'     => 'router-backups.php',
		'level'   => '1'
	];

	$nav['router-backups.php:edit'] = [
		'title'   => __('(edit)', 'routerconfigs'),
		'mapping' => 'index.php:,router-backups.php:',
		'url'     => 'router-backups.php',
		'level'   => '2'
	];

	$nav['router-backups.php:actions'] = [
		'title'   => __('(actions)', 'routerconfigs'),
		'mapping' => 'index.php:,router-backups.php:',
		'url'     => 'router-backups.php',
		'level'   => '2'
	];

	$nav['router-backups.php:viewconfig'] = [
		'title'   => __('View Config', 'routerconfigs'),
		'mapping' => 'index.php:,router-backups.php:',
		'url'     => 'router-backups.php',
		'level'   => '2'
	];

	$nav['router-accounts.php:'] = [
		'title'   => __('Router Accounts', 'routerconfigs'),
		'mapping' => 'index.php:',
		'url'     => 'router-accounts.php',
		'level'   => '1'
	];

	$nav['router-accounts.php:edit'] = [
		'title'   => __('(edit)', 'routerconfigs'),
		'mapping' => 'index.php:,router-accounts.php:',
		'url'     => 'router-accounts.php',
		'level'   => '2'
	];

	$nav['router-accounts.php:actions'] = [
		'title'   => __('(actions)', 'routerconfigs'),
		'mapping' => 'index.php:,router-accounts.php:',
		'url'     => 'router-accounts.php',
		'level'   => '2'
	];

	$nav['router-compare.php:'] = [
		'title'   => __('Router Compare', 'routerconfigs'),
		'mapping' => 'index.php:',
		'url'     => 'router-compare.php',
		'level'   => '1'
	];

	return $nav;
}

/**
 * Joins two filesystem path fragments with a single separating slash;
 * treats $path2 as absolute (discarding $path1) if it starts with '/'.
 * Called from plugin_routerconfigs_fix_backups_pre14() to rebuild
 * historical backup file paths.
 *
 * @param string $path1 The base path fragment.
 * @param string $path2 The path fragment to append (or, if absolute,
 *                      to use in place of $path1).
 *
 * @return string The combined path, with exactly one slash between the
 *                two fragments.
 */
function plugin_routerconfigs_combinepaths($path1, $path2) {
	if (strlen($path2) < 1 || $path2[0] != '/') {
		if (strlen($path1) && $path1[strlen($path1) - 1] != '/') {
			$path1 = $path1 . '/';
		}
	} else {
		$path1 = '';
	}

	if (strlen($path2) && $path2[strlen($path2) - 1] != '/') {
		$path2 = $path2 . '/';
	}

	return $path1 . $path2;
}

/**
 * Normalizes pre-1.4.0 plugin_routerconfigs_backups rows whose 'filename'
 * column may have included a relative directory path, splitting it back
 * into separate 'directory'/'filename' values rooted at the configured
 * backup path. Called from routerconfigs_check_upgrade() when upgrading
 * from a version older than 1.4.0.
 *
 * @return void
 */
function plugin_routerconfigs_fix_backups_pre14() {
	$backups = db_fetch_assoc_prepared('SELECT id, directory, filename FROM plugin_routerconfigs_backups', []);

	foreach ($backups as $backup) {
		$filename = trim($backup['filename']);
		$path     = $backup['directory'];

		if (strlen($path) && $path[strlen($path) - 1] != '/') {
			$path = $path . '/';
		}

		if (strlen($path) < 1 || $path[0] != '/') {
			$path = plugin_routerconfigs_combinepaths(read_config_option('routerconfigs_backup_path'), $path);
		}

		if (basename($filename) != $filename || $path != $backup['directory']) {
			$dir = trim(dirname($filename));

			if ($dir == '.') {
				$dir = '';
			}

			$dir = plugin_routerconfigs_combinepaths($path, $dir);

			db_execute_prepared('UPDATE plugin_routerconfigs_backups
				SET directory = ?, filename = ?
				WHERE id = ?',
				[$dir, basename($filename), $backup['id']]);
		}
	}
}

/**
 * Renders this plugin's tab icon/link on device and graph header pages,
 * when using the 'toptab' presentation style and the current user is
 * authorized for router-devices.php. Invoked by the Cacti plugin
 * framework via the 'top_header_tabs' and 'top_graph_header_tabs' hooks.
 *
 * @return void Outputs the tab link HTML directly (nothing if the tab
 *              style isn't 'toptab' or the user lacks the
 *              router-devices.php realm).
 *
 * @global array $config Cacti global configuration array; used to build
 *                       the tab link/image URLs.
 */
function routerconfigs_show_tab() {
	global $config;

	$tabstyle       = read_config_option('routerconfigs_presentation');
	$selected_theme = get_selected_theme();

	if (api_plugin_user_realm_auth('router-devices.php') && $tabstyle == 'toptab') {
		if (preg_match('/router-devices.php/', $_SERVER['REQUEST_URI'], $matches)) {
			$down = true;
		} else {
			$down = false;
		}

		print '<a id="routerconfigs"
			href="' . $config['url_path'] . 'plugins/routerconfigs/router-devices.php">
			<img src="' . ($selected_theme == 'classic' ? get_classic_tabimage(__('Routers', 'routerconfig'), $down) : '#') . '" alt="' . __esc('RouterConfigs', 'routerconfigs') . '"></a>';
	}
}
