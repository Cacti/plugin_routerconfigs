&lt;?php
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
 * @return array&lt;string, mixed&gt; The table definition array.
 */
function routerconfigs_accounts_table_data(): array {
	$data              = [];
	$data['columns'][] = ['name' =&gt; 'id', 'type' =&gt; 'int(11)', 'NULL' =&gt; false, 'auto_increment' =&gt; true];
	$data['columns'][] = ['name' =&gt; 'name', 'type' =&gt; 'varchar(64)', 'NULL' =&gt; true];
	$data['columns'][] = ['name' =&gt; 'username', 'type' =&gt; 'varchar(64)', 'NULL' =&gt; true];
	$data['columns'][] = ['name' =&gt; 'password', 'type' =&gt; 'varchar(256)', 'NULL' =&gt; true];
	$data['columns'][] = ['name' =&gt; 'enablepw', 'type' =&gt; 'varchar(256)', 'NULL' =&gt; true];
	$data['columns'][] = ['name' =&gt; 'elevated', 'type' =&gt; 'varchar(3)', 'NULL' =&gt; true];
	$data['primary']   = 'id';
	$data['type']      = 'InnoDB';
	$data['comment']   = 'Router Config Accounts';

	return $data;
}

/**
 * The plugin_routerconfigs_backups table definition (one row per captured
 * backup), shared by the create and upgrade paths.
 *
 * @return array&lt;string, mixed&gt; The table definition array.
 */
function routerconfigs_backups_table_data(): array {
	$data              = [];
	$data['columns'][] = ['name' =&gt; 'id', 'type' =&gt; 'int(11)', 'NULL' =&gt; false, 'auto_increment' =&gt; true];
	$data['columns'][] = ['name' =&gt; 'btime', 'type' =&gt; 'int(18)', 'NULL' =&gt; true];
	$data['columns'][] = ['name' =&gt; 'device', 'type' =&gt; 'int(11)', 'NULL' =&gt; true];
	$data['columns'][] = ['name' =&gt; 'directory', 'type' =&gt; 'varchar(255)', 'NULL' =&gt; true];
	$data['columns'][] = ['name' =&gt; 'filename', 'type' =&gt; 'varchar(255)', 'NULL' =&gt; true];
	$data['columns'][] = ['name' =&gt; 'lastchange', 'type' =&gt; 'int(24)', 'NULL' =&gt; true];
	$data['columns'][] = ['name' =&gt; 'lastuser', 'type' =&gt; 'varchar(64)', 'NULL' =&gt; true];
	$data['primary']   = 'id';
	$data['keys'][]    = ['name' =&gt; 'btime', 'columns' =&gt; ['btime']];
	$data['keys'][]    = ['name' =&gt; 'device', 'columns' =&gt; ['device']];
	$data['keys'][]    = ['name' =&gt; 'directory', 'columns' =&gt; ['directory']];
	$data['keys'][]    = ['name' =&gt; 'lastchange', 'columns' =&gt; ['lastchange']];
	$data['type']      = 'InnoDB';
	$data['comment']   = 'Router Config Backups';

	return $data;
}

/**
 * The plugin_routerconfigs_devices table definition (monitored devices and
 * their per-device backup settings, including SSH host-key storage),
 * shared by the create and upgrade paths.
 *
 * @return array&lt;string, mixed&gt; The table definition array.
 */
function routerconfigs_devices_table_data(): array {
	$data              = [];
	$data['columns'][] = ['name' =&gt; 'id', 'type' =&gt; 'int(11)', 'NULL' =&gt; false, 'auto_increment' =&gt; true];
	$data['columns'][] = ['name' =&gt; 'enabled', 'type' =&gt; 'varchar(2)', 'NULL' =&gt; true];
	$data['columns'][] = ['name' =&gt; 'ipaddress', 'type' =&gt; 'varchar(128)', 'NULL' =&gt; true];
	$data['columns'][] = ['name' =&gt; 'hostname', 'type' =&gt; 'varchar(255)', 'NULL' =&gt; true];
	$data['columns'][] = ['name' =&gt; 'directory', 'type' =&gt; 'varchar(255)', 'NULL' =&gt; true];
	$data['columns'][] = ['name' =&gt; 'account', 'type' =&gt; 'int(11)', 'NULL' =&gt; true];
	$data['columns'][] = ['name' =&gt; 'lastchange', 'type' =&gt; 'int(24)', 'NULL' =&gt; true];
	$data['columns'][] = ['name' =&gt; 'lastuser', 'type' =&gt; 'varchar(64)', 'NULL' =&gt; true];
	$data['columns'][] = ['name' =&gt; 'device', 'type' =&gt; 'int(11)', 'NULL' =&gt; true];
	$data['columns'][] = ['name' =&gt; 'schedule', 'type' =&gt; 'int(11)', 'NULL' =&gt; true];
	$data['columns'][] = ['name' =&gt; 'lasterror', 'type' =&gt; 'varchar(255)', 'NULL' =&gt; true];
	$data['columns'][] = ['name' =&gt; 'lastbackup', 'type' =&gt; 'int(18)', 'NULL' =&gt; true];
	$data['columns'][] = ['name' =&gt; 'nextbackup', 'type' =&gt; 'int(18)', 'NULL' =&gt; true];
	$data['columns'][] = ['name' =&gt; 'lastattempt', 'type' =&gt; 'int(18)', 'NULL' =&gt; true];
	$data['columns'][] = ['name' =&gt; 'nextattempt', 'type' =&gt; 'int(18)', 'NULL' =&gt; true];
	$data['columns'][] = ['name' =&gt; 'devicetype', 'type' =&gt; 'int(11)', 'NULL' =&gt; true];
	$data['columns'][] = ['name' =&gt; 'connecttype', 'type' =&gt; 'varchar(10)', 'NULL' =&gt; true];
	$data['columns'][] = ['name' =&gt; 'elevated', 'type' =&gt; 'varchar(3)', 'NULL' =&gt; true];
	$data['columns'][] = ['name' =&gt; 'sleep', 'type' =&gt; 'int(11)', 'NULL' =&gt; true];
	$data['columns'][] = ['name' =&gt; 'timeout', 'type' =&gt; 'int(11)', 'NULL' =&gt; true];
	$data['columns'][] = ['name' =&gt; 'debug', 'type' =&gt; 'longblob', 'NULL' =&gt; true];
	$data['columns'][] = ['name' =&gt; 'ssh_fingerprint', 'type' =&gt; 'varchar(255)', 'NULL' =&gt; true];
	$data['columns'][] = ['name' =&gt; 'ssh_hostkey_type', 'type' =&gt; 'varchar(64)', 'NULL' =&gt; true];
	$data['primary']   = 'id';
	$data['keys'][]    = ['name' =&gt; 'enabled', 'columns' =&gt; ['enabled']];
	$data['keys'][]    = ['name' =&gt; 'schedule', 'columns' =&gt; ['schedule']];
	$data['keys'][]    = ['name' =&gt; 'ipaddress', 'columns' =&gt; ['ipaddress']];
	$data['keys'][]    = ['name' =&gt; 'account', 'columns' =&gt; ['account']];
	$data['keys'][]    = ['name' =&gt; 'lastbackup', 'columns' =&gt; ['lastbackup']];
	$data['keys'][]    = ['name' =&gt; 'lastattempt', 'columns' =&gt; ['lastattempt']];
	$data['keys'][]    = ['name' =&gt; 'devicetype', 'columns' =&gt; ['devicetype']];
	$data['type']      = 'InnoDB';
	$data['comment']   = 'Router Config Devices';

	return $data;
}

/**
 * The plugin_routerconfigs_devicetypes table definition (per-vendor
 * connection/prompt profiles), shared by the create and upgrade paths.
 *
 * @return array&lt;string, mixed&gt; The table definition array.
 */
function routerconfigs_devicetypes_table_data(): array {
	$data              = [];
	$data['columns'][] = ['name' =&gt; 'id', 'type' =&gt; 'int(11)', 'NULL' =&gt; false, 'auto_increment' =&gt; true];
	$data['columns'][] = ['name' =&gt; 'name', 'type' =&gt; 'varchar(64)', 'NULL' =&gt; true];
	$data['columns'][] = ['name' =&gt; 'promptuser', 'type' =&gt; 'varchar(64)', 'NULL' =&gt; true];
	$data['columns'][] = ['name' =&gt; 'promptpass', 'type' =&gt; 'varchar(256)', 'NULL' =&gt; true];
	$data['columns'][] = ['name' =&gt; 'connecttype', 'type' =&gt; 'varchar(10)', 'NULL' =&gt; true];
	$data['columns'][] = ['name' =&gt; 'configfile', 'type' =&gt; 'varchar(256)', 'NULL' =&gt; true];
	$data['columns'][] = ['name' =&gt; 'copytftp', 'type' =&gt; 'varchar(64)', 'NULL' =&gt; true];
	$data['columns'][] = ['name' =&gt; 'version', 'type' =&gt; 'varchar(64)', 'NULL' =&gt; true];
	$data['columns'][] = ['name' =&gt; 'promptconfirm', 'type' =&gt; 'varchar(64)', 'NULL' =&gt; true];
	$data['columns'][] = ['name' =&gt; 'confirm', 'type' =&gt; 'varchar(64)', 'NULL' =&gt; true];
	$data['columns'][] = ['name' =&gt; 'sleep', 'type' =&gt; 'int(11)', 'NULL' =&gt; true];
	$data['columns'][] = ['name' =&gt; 'timeout', 'type' =&gt; 'int(11)', 'NULL' =&gt; true];
	$data['columns'][] = ['name' =&gt; 'forceconfirm', 'type' =&gt; 'char(2)', 'NULL' =&gt; true, 'default' =&gt; 'on'];
	$data['columns'][] = ['name' =&gt; 'checkendinconfig', 'type' =&gt; 'char(2)', 'NULL' =&gt; true, 'default' =&gt; 'on'];
	$data['columns'][] = ['name' =&gt; 'anykey', 'type' =&gt; 'varchar(50)', 'NULL' =&gt; true];
	$data['columns'][] = ['name' =&gt; 'elevated', 'type' =&gt; 'varchar(3)', 'NULL' =&gt; true];
	$data['primary']   = 'id';
	$data['type']      = 'InnoDB';
	$data['comment']   = 'Router Config Device Types';

	return $data;
}

/**
 * Returns the plugin's complete table map (table name =&gt; definition), the
 * single source of truth consumed by both the create path
 * (api_plugin_db_table_create()) and the upgrade path (db_update_table()).
 *
 * @return array&lt;string, array&lt;string, mixed&gt;&gt; Table name keyed definitions.
 */
function routerconfigs_table_map(): array {
	return [
		'plugin_routerconfigs_accounts'    =&gt; routerconfigs_accounts_table_data(),
		'plugin_routerconfigs_backups'     =&gt; routerconfigs_backups_table_data(),
		'plugin_routerconfigs_devices'     =&gt; routerconfigs_devices_table_data(),
		'plugin_routerconfigs_devicetypes' =&gt; routerconfigs_devicetypes_table_data(),
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
	foreach (routerconfigs_table_map() as $table =&gt; $data) {
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
	foreach (routerconfigs_table_map() as $table =&gt; $data) {
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
 * @return void
 */
function routerconfigs_upgrade_tables() {
	foreach (routerconfigs_table_map() as $table =&gt; $data) {
		if (db_table_exists($table)) {
			db_update_table($table, $data);
		} else {
			api_plugin_db_table_create('routerconfigs', $table, $data);
		}
	}
}
