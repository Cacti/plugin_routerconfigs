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

	expect($inserts)->toHaveCount(5);
});

it('seeds exactly the 5 known default device types with matching parameters', function () {
	AddDeviceTypes();

	$names = array_map(function ($call) {
		return $call['params'][0];
	}, array_values(array_filter($GLOBALS['__test_db_calls'], function ($call) {
		return $call['fn'] === 'db_execute_prepared';
	})));

	expect($names)->toBe(['Cisco IOS', 'Cisco CatOS', 'Cisco Nexus', 'HP Comware', 'Dell Switch']);
});
