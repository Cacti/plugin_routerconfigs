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
	// Define routerconfigs_upgrade_tables()/*_table_data() from the real
	// checkout so check_upgrade() runs while base_path is sandboxed below.
	require_once __DIR__ . '/../../includes/database.php';
});

beforeEach(function () {
	$GLOBALS['__stub_overrides'] = array();
	$GLOBALS['__test_db_calls']  = array();

	// Sandbox base_path so the upgrade-path tests run
	// routerconfigs_prune_files() against a throwaway tree with no
	// manifest.json (prune no-ops), never the real checkout. The temp tree
	// carries a copy of the real INFO (so plugin_routerconfigs_version()
	// still matches) and an empty includes/database.php the top-level
	// require_once in check_upgrade() can load harmlessly.
	$GLOBALS['__rc_base_restore'] = $GLOBALS['config']['base_path'];
	$base = sys_get_temp_dir() . '/routerconfigs-test-' . uniqid();
	mkdir($base . '/plugins/routerconfigs/includes', 0777, true);
	copy(__DIR__ . '/../../INFO', $base . '/plugins/routerconfigs/INFO');
	file_put_contents($base . '/plugins/routerconfigs/includes/database.php', "<?php\n");
	$GLOBALS['config']['base_path'] = $base;
});

afterEach(function () {
	if (isset($GLOBALS['__rc_base_restore'])) {
		$GLOBALS['config']['base_path'] = $GLOBALS['__rc_base_restore'];
	}
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
	expect($finalUpdate[0]['params'])->toBe([$info['version'], $info['longname'], $info['author'], $info['homepage'], 'routerconfigs']);
});

it('refreshes already-present tables in place via db_update_table on upgrade', function () {
	$updated = array();

	$GLOBALS['__stub_overrides']['get_current_page']        = fn () => 'plugins.php';
	$GLOBALS['__stub_overrides']['db_fetch_cell_prepared']  = fn ($sql, $params) =>
		stripos($sql, 'plugin_config') !== false ? '0.1' : '0';
	$GLOBALS['__stub_overrides']['db_fetch_assoc_prepared'] = fn ($sql, $params) => [];
	// Every table already exists, so routerconfigs_create_missing_tables()
	// skips creation and routerconfigs_upgrade_tables() takes the
	// db_update_table() refresh branch for each one.
	$GLOBALS['__stub_overrides']['db_table_exists']         = fn ($table) => true;
	$GLOBALS['__stub_overrides']['db_column_exists']        = fn ($table, $column) => true;
	$GLOBALS['__stub_overrides']['db_update_table']         = function ($table, $data) use (&$updated) {
		$updated[] = $table;

		return true;
	};

	routerconfigs_check_upgrade();

	expect($updated)->toHaveCount(4);
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
