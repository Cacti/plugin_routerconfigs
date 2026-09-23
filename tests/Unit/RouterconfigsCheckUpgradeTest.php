<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Unit coverage for routerconfigs_check_upgrade() and
 * routerconfigs_ensure_hostkey_schema() in setup.php.
 */

beforeAll(function () {
	require_once __DIR__ . '/../../setup.php';
});

beforeEach(function () {
	$GLOBALS['__stub_overrides'] = array();
	$GLOBALS['__test_db_calls']  = array();
});

it('does nothing on a page that does not need the version check', function () {
	$GLOBALS['__stub_overrides']['get_current_page'] = fn () => 'graphs.php';

	routerconfigs_check_upgrade();

	expect($GLOBALS['__test_db_calls'])->toBeEmpty();
});

it('does nothing further when the stored version already matches', function () {
	$info = plugin_routerconfigs_version();

	$GLOBALS['__stub_overrides']['get_current_page']       = fn () => 'plugins.php';
	$GLOBALS['__stub_overrides']['db_fetch_cell_prepared']  = fn ($sql, $params) =>
		stripos($sql, 'plugin_config') !== false ? $info['version'] : '';
	// Already-provisioned SSH host-key columns, so
	// routerconfigs_ensure_hostkey_schema()'s unconditional pre-check
	// doesn't itself issue any ALTER TABLE writes in this scenario.
	$GLOBALS['__stub_overrides']['db_column_exists']       = fn ($table, $column) => true;

	routerconfigs_check_upgrade();

	$writes = array_filter($GLOBALS['__test_db_calls'], function ($call) {
		return in_array($call['fn'], ['db_execute', 'db_execute_prepared'], true);
	});

	expect($writes)->toBeEmpty();
});

it('runs every migration step and updates plugin_config when upgrading from a very old version', function () {
	$info = plugin_routerconfigs_version();

	$GLOBALS['__stub_overrides']['get_current_page']      = fn () => 'plugins.php';
	$GLOBALS['__stub_overrides']['db_fetch_cell_prepared'] = fn ($sql, $params) =>
		stripos($sql, 'plugin_config') !== false ? '0.1' : '0';
	$GLOBALS['__stub_overrides']['db_fetch_assoc_prepared'] = fn ($sql, $params) => [];
	$GLOBALS['__stub_overrides']['db_column_exists']        = fn ($table, $column) => false;

	routerconfigs_check_upgrade();

	$deviceTypeInserts = array_filter($GLOBALS['__test_db_calls'], function ($call) {
		return $call['fn'] === 'db_execute_prepared' && stripos($call['sql'], 'INSERT INTO plugin_routerconfigs_devicetypes') !== false;
	});

	expect($deviceTypeInserts)->toHaveCount(5);

	$finalUpdate = array_values(array_filter($GLOBALS['__test_db_calls'], function ($call) {
		return $call['fn'] === 'db_execute_prepared' && stripos($call['sql'], 'UPDATE plugin_config') !== false;
	}));

	expect($finalUpdate)->toHaveCount(1);
	expect($finalUpdate[0]['params'])->toBe([$info['version'], 'routerconfigs']);
});

it('adds the SSH host-key columns when they are missing and reports readiness', function () {
	$GLOBALS['__stub_overrides']['db_column_exists'] = fn ($table, $column) => false;

	// db_column_exists is stubbed to always report "missing", so
	// routerconfigs_ensure_hostkey_schema() takes the ALTER branch for both
	// columns, then its own final readiness check also reports false.
	expect(routerconfigs_ensure_hostkey_schema())->toBeFalse();

	$alters = array_filter($GLOBALS['__test_db_calls'], function ($call) {
		return $call['fn'] === 'db_execute' && stripos($call['sql'], 'ADD COLUMN') !== false;
	});

	expect($alters)->toHaveCount(2);
});

it('reports readiness without altering anything when the columns already exist', function () {
	$GLOBALS['__stub_overrides']['db_column_exists'] = fn ($table, $column) => true;

	expect(routerconfigs_ensure_hostkey_schema())->toBeTrue();

	$alters = array_filter($GLOBALS['__test_db_calls'], function ($call) {
		return $call['fn'] === 'db_execute' && stripos($call['sql'], 'ADD COLUMN') !== false;
	});

	expect($alters)->toBeEmpty();
});
