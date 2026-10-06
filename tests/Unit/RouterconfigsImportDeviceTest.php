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
	$GLOBALS['__test_messages']  = array();
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

/**
 * Return the raise_message() calls the importer recorded.
 *
 * @return array
 */
function rc_import_messages() {
	return $GLOBALS['__test_messages'];
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
	// Imported disabled: it still has no account/device type (issue #133 review).
	expect($saves[0]['save']['enabled'])->toBe('');
	expect($saves[0]['save'])->not->toHaveKey('id');

	$messages = rc_import_messages();
	expect($messages)->toHaveCount(1);
	expect($messages[0]['level'])->toBe(MESSAGE_LEVEL_INFO);
	expect($messages[0]['text'])->toContain('Core Switch');
	expect($messages[0]['text'])->toContain('added to RouterConfigs');
});

it('rejects a device already linked to the Cacti host without saving', function () {
	$GLOBALS['__stub_overrides']['db_fetch_row_prepared'] = fn ($sql, $params) =>
		['id' => 5, 'description' => 'Core Switch', 'hostname' => '10.0.0.1'];
	$GLOBALS['__stub_overrides']['db_fetch_cell_prepared'] = fn ($sql, $params) => 9;

	$result = plugin_routerconfigs_import_cacti_device(5);

	expect($result)->toBeFalse();
	expect(rc_import_saves())->toBeEmpty();

	$messages = rc_import_messages();
	expect($messages)->toHaveCount(1);
	expect($messages[0]['level'])->toBe(MESSAGE_LEVEL_WARN);
	expect($messages[0]['text'])->toContain('already in RouterConfigs');
});

it('treats a concurrent duplicate insert as a skip, not a failure', function () {
	$GLOBALS['__stub_overrides']['db_fetch_row_prepared'] = fn ($sql, $params) =>
		['id' => 5, 'description' => 'Core Switch', 'hostname' => '10.0.0.1'];

	// Pre-check finds no row; the unique index then rejects the insert and the
	// row is found on the post-insert re-check.
	$calls = 0;
	$GLOBALS['__stub_overrides']['db_fetch_cell_prepared'] = function ($sql, $params) use (&$calls) {
		$calls++;

		return $calls === 1 ? '' : 7;
	};
	$GLOBALS['__stub_overrides']['sql_save'] = fn ($array, $table, $key) => 0;

	$result = plugin_routerconfigs_import_cacti_device(5);

	expect($result)->toBeFalse();

	$messages = rc_import_messages();
	expect($messages)->toHaveCount(1);
	expect($messages[0]['level'])->toBe(MESSAGE_LEVEL_WARN);
	expect($messages[0]['text'])->toContain('already in RouterConfigs');
});

it('strips path separators and traversal from the imported name', function () {
	$GLOBALS['__stub_overrides']['db_fetch_row_prepared'] = fn ($sql, $params) =>
		['id' => 5, 'description' => '../../etc/cron.d/evil', 'hostname' => '10.0.0.1'];
	$GLOBALS['__stub_overrides']['db_fetch_cell_prepared'] = fn ($sql, $params) => '';
	$GLOBALS['__stub_overrides']['sql_save'] = fn ($array, $table, $key) => 42;

	plugin_routerconfigs_import_cacti_device(5);

	$hostname = rc_import_saves()[0]['save']['hostname'];
	expect($hostname)->not->toContain('/');
	expect($hostname)->not->toContain('\\');
	expect($hostname)->not->toContain('..');
});

it('escapes HTML in the description before raising the message', function () {
	$GLOBALS['__stub_overrides']['db_fetch_row_prepared'] = fn ($sql, $params) =>
		['id' => 5, 'description' => '<b>bad', 'hostname' => '10.0.0.1'];
	$GLOBALS['__stub_overrides']['db_fetch_cell_prepared'] = fn ($sql, $params) => '';
	$GLOBALS['__stub_overrides']['sql_save'] = fn ($array, $table, $key) => 42;

	plugin_routerconfigs_import_cacti_device(5);

	$messages = rc_import_messages();
	expect($messages[0]['text'])->not->toContain('<b>');
	expect($messages[0]['text'])->toContain('&lt;b&gt;');
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

	$messages = rc_import_messages();
	expect($messages)->toHaveCount(1);
	expect($messages[0]['level'])->toBe(MESSAGE_LEVEL_ERROR);
	expect($messages[0]['text'])->toContain('was not found');
});

it('falls back to a host_<id> name when description and hostname are both empty', function () {
	$GLOBALS['__stub_overrides']['db_fetch_row_prepared'] = fn ($sql, $params) =>
		['id' => 7, 'description' => '   ', 'hostname' => ''];
	$GLOBALS['__stub_overrides']['db_fetch_cell_prepared'] = fn ($sql, $params) => '';
	$GLOBALS['__stub_overrides']['sql_save'] = fn ($array, $table, $key) => 12;

	$id = plugin_routerconfigs_import_cacti_device(7);

	expect($id)->toBe(12);

	$saves = rc_import_saves();
	expect($saves[0]['save']['hostname'])->toBe('host_7');
});

it('reports a hard failure when the insert fails and no row exists afterwards', function () {
	$GLOBALS['__stub_overrides']['db_fetch_row_prepared'] = fn ($sql, $params) =>
		['id' => 5, 'description' => 'Core Switch', 'hostname' => '10.0.0.1'];

	// Both the pre-check and the post-insert re-check find no row, so the failed
	// sql_save is a genuine hard failure rather than a concurrent duplicate.
	$GLOBALS['__stub_overrides']['db_fetch_cell_prepared'] = fn ($sql, $params) => '';
	$GLOBALS['__stub_overrides']['sql_save'] = fn ($array, $table, $key) => 0;

	$result = plugin_routerconfigs_import_cacti_device(5);

	expect($result)->toBeFalse();

	$messages = rc_import_messages();
	expect($messages)->toHaveCount(1);
	expect($messages[0]['level'])->toBe(MESSAGE_LEVEL_ERROR);
	expect($messages[0]['text'])->toContain('Failed to add');
});
