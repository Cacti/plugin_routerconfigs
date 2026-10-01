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
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

/*
 * Each table's 'primary' is declared as a scalar column name (the legacy
 * string form), not an array: on the plugin's supported Cacti releases
 * api_plugin_db_table_create() interpolates the primary key as a string, so
 * the scalar form is required for backward compatibility on a fresh install
 * (an array would emit PRIMARY KEY (`Array`) and fail). db_update_table()
 * accepts either form, and index 'keys' columns stay arrays because both the
 * create and update paths accept them.
 */

/**
 * The plugin_routerconfigs_accounts table definition (login accounts used
 * to reach devices), shared by the create and upgrade paths.
 *
 * @return array<string, mixed> The table definition array.
 */
function routerconfigs_accounts_table_data(): array {
	$data              = [];
	$data['columns'][] = ['name' => 'id', 'type' => 'int(11)', 'NULL' => false, 'auto_increment' => true];
	$data['columns'][] = ['name' => 'name', 'type' => 'varchar(64)', 'NULL' => true];
	$data['columns'][] = ['name' => 'username', 'type' => 'varchar(64)', 'NULL' => true];
	$data['columns'][] = ['name' => 'password', 'type' => 'varchar(256)', 'NULL' => true];
	$data['columns'][] = ['name' => 'enablepw', 'type' => 'varchar(256)', 'NULL' => true];
	$data['columns'][] = ['name' => 'elevated', 'type' => 'varchar(3)', 'NULL' => true];
	$data['primary']   = 'id';
	$data['type']      = 'InnoDB';
	$data['comment']   = 'Router Config Accounts';

	return $data;
}

/**
 * The plugin_routerconfigs_backups table definition (one row per captured
 * backup), shared by the create and upgrade paths.
 *
 * @return array<string, mixed> The table definition array.
 */
function routerconfigs_backups_table_data(): array {
	$data              = [];
	$data['columns'][] = ['name' => 'id', 'type' => 'int(11)', 'NULL' => false, 'auto_increment' => true];
	$data['columns'][] = ['name' => 'btime', 'type' => 'int(18)', 'NULL' => true];
	$data['columns'][] = ['name' => 'device', 'type' => 'int(11)', 'NULL' => true];
	$data['columns'][] = ['name' => 'directory', 'type' => 'varchar(255)', 'NULL' => true];
	$data['columns'][] = ['name' => 'filename', 'type' => 'varchar(255)', 'NULL' => true];
	$data['columns'][] = ['name' => 'lastchange', 'type' => 'int(24)', 'NULL' => true];
	$data['columns'][] = ['name' => 'lastuser', 'type' => 'varchar(64)', 'NULL' => true];
	$data['primary']   = 'id';
	$data['keys'][]    = ['name' => 'btime', 'columns' => ['btime']];
	$data['keys'][]    = ['name' => 'device', 'columns' => ['device']];
	$data['keys'][]    = ['name' => 'directory', 'columns' => ['directory']];
	$data['keys'][]    = ['name' => 'lastchange', 'columns' => ['lastchange']];
	$data['type']      = 'InnoDB';
	$data['comment']   = 'Router Config Backups';

	return $data;
}

/**
 * The plugin_routerconfigs_devices table definition (monitored devices and
 * their per-device backup settings, including SSH host-key storage),
 * shared by the create and upgrade paths.
 *
 * @return array<string, mixed> The table definition array.
 */
function routerconfigs_devices_table_data(): array {
	$data              = [];
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
	$data['primary']   = 'id';
	$data['keys'][]    = ['name' => 'enabled', 'columns' => ['enabled']];
	$data['keys'][]    = ['name' => 'schedule', 'columns' => ['schedule']];
	$data['keys'][]    = ['name' => 'ipaddress', 'columns' => ['ipaddress']];
	$data['keys'][]    = ['name' => 'account', 'columns' => ['account']];
	$data['keys'][]    = ['name' => 'lastbackup', 'columns' => ['lastbackup']];
	$data['keys'][]    = ['name' => 'lastattempt', 'columns' => ['lastattempt']];
	$data['keys'][]    = ['name' => 'devicetype', 'columns' => ['devicetype']];
	$data['type']      = 'InnoDB';
	$data['comment']   = 'Router Config Devices';

	return $data;
}

/**
 * The plugin_routerconfigs_devicetypes table definition (per-vendor
 * connection/prompt profiles), shared by the create and upgrade paths.
 *
 * @return array<string, mixed> The table definition array.
 */
function routerconfigs_devicetypes_table_data(): array {
	$data              = [];
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
	$data['primary']   = 'id';
	$data['type']      = 'InnoDB';
	$data['comment']   = 'Router Config Device Types';

	return $data;
}

/**
 * Returns the plugin's complete table map (table name => definition), the
 * single source of truth consumed by both the create path
 * (api_plugin_db_table_create()) and the upgrade path (db_update_table()).
 *
 * @return array<string, array<string, mixed>> Table name keyed definitions.
 */
function routerconfigs_table_map(): array {
	return [
		'plugin_routerconfigs_accounts'    => routerconfigs_accounts_table_data(),
		'plugin_routerconfigs_backups'     => routerconfigs_backups_table_data(),
		'plugin_routerconfigs_devices'     => routerconfigs_devices_table_data(),
		'plugin_routerconfigs_devicetypes' => routerconfigs_devicetypes_table_data(),
	];
}

/**
 * Creates all of this plugin's database tables through Cacti's tracked
 * plugin table API and seeds the built-in device types. Called from
 * plugin_routerconfigs_install() during installation, and re-run (safely,
 * as a no-op for already-applied changes) from routerconfigs_upgrade_tables()
 * during upgrades.
 *
 * @return void
 */
function routerconfigs_setup_table_new() {
	foreach (routerconfigs_table_map() as $table => $data) {
		api_plugin_db_table_create('routerconfigs', $table, $data);
	}

	AddDeviceTypes();
}

/**
 * Creates any of this plugin's tables that do not yet exist, leaving
 * already-present tables untouched so the historical column renames/drops
 * in routerconfigs_check_upgrade() can still run against them. Called at
 * the very start of an upgrade, before those guarded pre-steps, the SSH
 * host-key column check, and AddDeviceTypes(), so a missing devices or
 * device-types table can no longer make the migration ALTERs error or seed
 * device types into a not-yet-created table.
 *
 * @return void
 */
function routerconfigs_create_missing_tables() {
	foreach (routerconfigs_table_map() as $table => $data) {
		if (!db_table_exists($table)) {
			api_plugin_db_table_create('routerconfigs', $table, $data);
		}
	}
}

/**
 * Refreshes this plugin's tables to their current definition on upgrade:
 * db_update_table() diffs the live schema against each definition and
 * issues the exact ALTER when the table already exists, otherwise the
 * table is created outright. Historical column renames/drops that
 * db_update_table() can not express are applied first as guarded pre-steps
 * inside routerconfigs_check_upgrade(). Called from that function when the
 * stored version changes.
 *
 * @return bool True when every table was refreshed/created successfully;
 *              false if a db_update_table() refresh reported failure.
 */
function routerconfigs_upgrade_tables(): bool {
	$success = true;

	foreach (routerconfigs_table_map() as $table => $data) {
		if (db_table_exists($table)) {
			if (db_update_table($table, $data) === false) {
				$success = false;
			}
		} else {
			api_plugin_db_table_create('routerconfigs', $table, $data);
		}
	}

	return $success;
}
