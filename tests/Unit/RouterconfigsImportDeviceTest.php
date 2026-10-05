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

/*
 * Unit coverage for plugin_routerconfigs_import_cacti_device(): the
 * add-device-from-the-Cacti-Devices-page importer (issue #133, closes #115).
 */

beforeAll(function () {
	require_once __DIR__ . '/../../includes/functions.php';
});

beforeEach(function () {
	$GLOBALS['__stub_overrides'] = array();
	$GLOBALS['__test_db_calls']  = array();
});

/**
 * Record the sql_save calls the importer made against the devices table.
 *
 * @return array
 */
function rc_import_saves() {
	return array_values(array_filter($GLOBALS['__test_db_calls'], function ($call) {
		return $call['fn'] === 'sql_save' && $call['table'] === 'plugin_routerconfigs_devices';
	}));
}

it('adds a new device seeded from the Cacti device and returns its id', function () {
	$GLOBALS['__stub_overrides']['db_fetch_row_prepared'] = fn ($sql, $params) =>
		['id' => 5, 'description' => 'Core Switch', 'hostname' => '10.0.0.1'];
	$GLOBALS['__stub_overrides']['db_fetch_cell_prepared'] = fn ($sql, $params) => '';
	$GLOBALS['__stub_overrides']['sql_save'] = fn ($array, $table, $key) => 42;

	$id = plugin_routerconfigs_import_cacti_device(5);

	expect($id)->toBe(42);

	$saves = rc_import_saves();
	expect($saves)->toHaveCount(1);
	expect($saves[0]['save']['host_id'])->toBe(5);
	expect($saves[0]['save']['hostname'])->toBe('Core Switch');
	expect($saves[0]['save']['ipaddress'])->toBe('10.0.0.1');
	expect($saves[0]['save']['enabled'])->toBe('on');
	expect($saves[0]['save'])->not->toHaveKey('id');
});

it('rejects a device already linked to the Cacti host without saving', function () {
	$GLOBALS['__stub_overrides']['db_fetch_row_prepared'] = fn ($sql, $params) =>
		['id' => 5, 'description' => 'Core Switch', 'hostname' => '10.0.0.1'];
	$GLOBALS['__stub_overrides']['db_fetch_cell_prepared'] = fn ($sql, $params) => 9;

	$result = plugin_routerconfigs_import_cacti_device(5);

	expect($result)->toBeFalse();
	expect(rc_import_saves())->toBeEmpty();
});

it('falls back to the Cacti hostname when the description is empty', function () {
	$GLOBALS['__stub_overrides']['db_fetch_row_prepared'] = fn ($sql, $params) =>
		['id' => 8, 'description' => '   ', 'hostname' => 'router8.example.net'];
	$GLOBALS['__stub_overrides']['db_fetch_cell_prepared'] = fn ($sql, $params) => '';
	$GLOBALS['__stub_overrides']['sql_save'] = fn ($array, $table, $key) => 11;

	$id = plugin_routerconfigs_import_cacti_device(8);

	expect($id)->toBe(11);

	$saves = rc_import_saves();
	expect($saves[0]['save']['hostname'])->toBe('router8.example.net');
});

it('returns false and does not save when the Cacti host is not found', function () {
	$GLOBALS['__stub_overrides']['db_fetch_row_prepared'] = fn ($sql, $params) => [];

	$result = plugin_routerconfigs_import_cacti_device(999);

	expect($result)->toBeFalse();
	expect(rc_import_saves())->toBeEmpty();
});
