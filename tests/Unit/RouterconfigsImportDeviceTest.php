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
 * Return the INSERT IGNORE calls the importer made against the devices table,
 * each with its positional [host_id, enabled, hostname, ipaddress] params.
 *
 * @return array
 */
function rc_import_inserts() {
	return array_values(array_filter($GLOBALS['__test_db_calls'], function ($call) {
		return $call['fn'] === 'db_execute_prepared'
			&& stripos($call['sql'], 'INSERT IGNORE INTO plugin_routerconfigs_devices') !== false;
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
	$GLOBALS['__stub_overrides']['db_affected_rows']       = fn () => 1;
	$GLOBALS['__stub_overrides']['db_fetch_insert_id']     = fn () => 42;

	$id = plugin_routerconfigs_import_cacti_device(5);

	expect($id)->toBe(42);

	$inserts = rc_import_inserts();
	expect($inserts)->toHaveCount(1);
	// A plain INSERT IGNORE, never sql_save()'s INSERT ... ON DUPLICATE KEY
	// UPDATE, so a concurrent duplicate is rejected rather than overwritten.
	expect(stripos($inserts[0]['sql'], 'ON DUPLICATE KEY'))->toBeFalse();
	// Positional params: host_id, enabled, hostname, ipaddress.
	expect($inserts[0]['params'][0])->toBe(5);
	expect($inserts[0]['params'][1])->toBe('');
	expect($inserts[0]['params'][2])->toBe('Core Switch');
	expect($inserts[0]['params'][3])->toBe('10.0.0.1');

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
	expect(rc_import_inserts())->toBeEmpty();

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
	$GLOBALS['__stub_overrides']['db_affected_rows'] = fn () => 0;

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
	$GLOBALS['__stub_overrides']['db_affected_rows']       = fn () => 1;
	$GLOBALS['__stub_overrides']['db_fetch_insert_id']     = fn () => 42;

	plugin_routerconfigs_import_cacti_device(5);

	$hostname = rc_import_inserts()[0]['params'][2];
	expect($hostname)->not->toContain('/');
	expect($hostname)->not->toContain('\\');
	expect($hostname)->not->toContain('..');
});

it('escapes HTML in the description before raising the message', function () {
	$GLOBALS['__stub_overrides']['db_fetch_row_prepared'] = fn ($sql, $params) =>
		['id' => 5, 'description' => '<b>bad', 'hostname' => '10.0.0.1'];
	$GLOBALS['__stub_overrides']['db_fetch_cell_prepared'] = fn ($sql, $params) => '';
	$GLOBALS['__stub_overrides']['db_affected_rows']       = fn () => 1;
	$GLOBALS['__stub_overrides']['db_fetch_insert_id']     = fn () => 42;

	plugin_routerconfigs_import_cacti_device(5);

	$messages = rc_import_messages();
	expect($messages[0]['text'])->not->toContain('<b>');
	expect($messages[0]['text'])->toContain('&lt;b&gt;');
});

it('falls back to the Cacti hostname when the description is empty', function () {
	$GLOBALS['__stub_overrides']['db_fetch_row_prepared'] = fn ($sql, $params) =>
		['id' => 8, 'description' => '   ', 'hostname' => 'router8.example.net'];
	$GLOBALS['__stub_overrides']['db_fetch_cell_prepared'] = fn ($sql, $params) => '';
	$GLOBALS['__stub_overrides']['db_affected_rows']       = fn () => 1;
	$GLOBALS['__stub_overrides']['db_fetch_insert_id']     = fn () => 11;

	$id = plugin_routerconfigs_import_cacti_device(8);

	expect($id)->toBe(11);

	$inserts = rc_import_inserts();
	expect($inserts[0]['params'][2])->toBe('router8.example.net');
});

it('returns false and does not save when the Cacti host is not found', function () {
	$GLOBALS['__stub_overrides']['db_fetch_row_prepared'] = fn ($sql, $params) => [];

	$result = plugin_routerconfigs_import_cacti_device(999);

	expect($result)->toBeFalse();
	expect(rc_import_inserts())->toBeEmpty();

	$messages = rc_import_messages();
	expect($messages)->toHaveCount(1);
	expect($messages[0]['level'])->toBe(MESSAGE_LEVEL_ERROR);
	expect($messages[0]['text'])->toContain('was not found');
});

it('falls back to a host_<id> name when description and hostname are both empty', function () {
	$GLOBALS['__stub_overrides']['db_fetch_row_prepared'] = fn ($sql, $params) =>
		['id' => 7, 'description' => '   ', 'hostname' => ''];
	$GLOBALS['__stub_overrides']['db_fetch_cell_prepared'] = fn ($sql, $params) => '';
	$GLOBALS['__stub_overrides']['db_affected_rows']       = fn () => 1;
	$GLOBALS['__stub_overrides']['db_fetch_insert_id']     = fn () => 12;

	$id = plugin_routerconfigs_import_cacti_device(7);

	expect($id)->toBe(12);

	$inserts = rc_import_inserts();
	expect($inserts[0]['params'][2])->toBe('host_7');
});

it('reports a hard failure when the insert fails and no row exists afterwards', function () {
	$GLOBALS['__stub_overrides']['db_fetch_row_prepared'] = fn ($sql, $params) =>
		['id' => 5, 'description' => 'Core Switch', 'hostname' => '10.0.0.1'];

	// Both the pre-check and the post-insert re-check find no row, so the
	// ignored INSERT is a genuine hard failure rather than a concurrent
	// duplicate.
	$GLOBALS['__stub_overrides']['db_fetch_cell_prepared'] = fn ($sql, $params) => '';
	$GLOBALS['__stub_overrides']['db_affected_rows']       = fn () => 0;

	$result = plugin_routerconfigs_import_cacti_device(5);

	expect($result)->toBeFalse();

	$messages = rc_import_messages();
	expect($messages)->toHaveCount(1);
	expect($messages[0]['level'])->toBe(MESSAGE_LEVEL_ERROR);
	expect($messages[0]['text'])->toContain('Failed to add');
});
