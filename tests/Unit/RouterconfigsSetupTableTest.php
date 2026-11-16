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

	expect($inserts)->toHaveCount(16);
});

it('seeds exactly the 16 known default device types with matching parameters', function () {
	AddDeviceTypes();

	$names = array_map(function ($call) {
		return $call['params'][0];
	}, array_values(array_filter($GLOBALS['__test_db_calls'], function ($call) {
		return $call['fn'] === 'db_execute_prepared';
	})));

	expect($names)->toBe(['Cisco IOS', 'Cisco CatOS', 'Cisco Nexus', 'HP Comware', 'Dell Switch', 'MikroTik RouterOS',
		'Juniper Junos', 'Arista EOS', 'HPE Aruba CX', 'Extreme EXOS', 'Huawei VRP', 'Fortinet FortiOS',
		'Ruckus ICX', 'Ubiquiti EdgeSwitch', 'Netgear Managed', 'TP-Link JetStream']);
});

it('seeds the SSH-only vendors with a capture command and the SSH connection type', function () {
	AddDeviceTypes();

	$expected = [
		'Juniper Junos'    => 'show configuration',
		'Arista EOS'       => 'show running-config',
		'HPE Aruba CX'     => 'show running-config',
		'Extreme EXOS'     => 'show configuration',
		'Huawei VRP'       => 'display current-configuration',
		'Fortinet FortiOS' => 'show full-configuration',
	];

	foreach ($expected as $name => $command) {
		$rows = array_values(array_filter($GLOBALS['__test_db_calls'], function ($call) use ($name) {
			return $call['fn'] === 'db_execute_prepared' && $call['params'][0] === $name;
		}));

		expect($rows)->toHaveCount(1);
		expect($rows[0]['params'][3])->toBe('');
		expect($rows[0]['params'][10])->toBe('ssh');
		expect($rows[0]['params'][11])->toBe($command);
	}
});

it('seeds the TFTP vendors with a copy command that names the server and file', function () {
	AddDeviceTypes();

	foreach (['Ruckus ICX', 'Ubiquiti EdgeSwitch', 'Netgear Managed', 'TP-Link JetStream'] as $name) {
		$rows = array_values(array_filter($GLOBALS['__test_db_calls'], function ($call) use ($name) {
			return $call['fn'] === 'db_execute_prepared' && $call['params'][0] === $name;
		}));

		expect($rows)->toHaveCount(1);
		expect($rows[0]['params'][3])->toContain('%SERVER%')->toContain('%FILE%');
		expect($rows[0]['params'][11])->toBe('');
	}
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
