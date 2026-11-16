<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Unit coverage for routerconfigs_setup_table_new()/AddDeviceTypes()/
 * AddDeviceType() in setup.php.
 */

beforeAll(function () {
	require_once __DIR__ . '/../../setup.php';
	require_once __DIR__ . '/../../includes/database.php';
});

beforeEach(function () {
	$GLOBALS['__stub_overrides'] = array();
	$GLOBALS['__test_db_calls']  = array();
});

it('creates all 4 routerconfigs tables and seeds the default device types', function () {
	routerconfigs_setup_table_new();

	$inserts = array_filter($GLOBALS['__test_db_calls'], function ($call) {
		return $call['fn'] === 'db_execute_prepared' && stripos($call['sql'], 'INSERT INTO plugin_routerconfigs_devicetypes') !== false;
	});

	expect($inserts)->toHaveCount(6);
});

it('seeds exactly the 6 known default device types with matching parameters', function () {
	AddDeviceTypes();

	$names = array_map(function ($call) {
		return $call['params'][0];
	}, array_values(array_filter($GLOBALS['__test_db_calls'], function ($call) {
		return $call['fn'] === 'db_execute_prepared';
	})));

	expect($names)->toBe(['Cisco IOS', 'Cisco CatOS', 'Cisco Nexus', 'HP Comware', 'Dell Switch', 'MikroTik RouterOS']);
});

it('seeds MikroTik RouterOS as an SSH device type that captures /export', function () {
	AddDeviceTypes();

	$mikrotik = array_values(array_filter($GLOBALS['__test_db_calls'], function ($call) {
		return $call['fn'] === 'db_execute_prepared' && $call['params'][0] === 'MikroTik RouterOS';
	}));

	expect($mikrotik)->toHaveCount(1);
	expect($mikrotik[0]['sql'])->toContain('connecttype')->toContain('capturecmd');

	// name, ..., promptconfirm, connecttype, capturecmd, then name for the NOT EXISTS guard
	expect($mikrotik[0]['params'][10])->toBe('ssh');
	expect($mikrotik[0]['params'][11])->toBe('/export');
	expect($mikrotik[0]['params'][12])->toBe('MikroTik RouterOS');
});

it('leaves the connection type and capture command empty for the TFTP device types', function () {
	AddDeviceTypes();

	$cisco = array_values(array_filter($GLOBALS['__test_db_calls'], function ($call) {
		return $call['fn'] === 'db_execute_prepared' && $call['params'][0] === 'Cisco IOS';
	}));

	expect($cisco[0]['params'][10])->toBe('');
	expect($cisco[0]['params'][11])->toBe('');
});
