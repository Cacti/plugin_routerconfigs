<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Unit coverage for plugin_routerconfigs_combinepaths() and
 * plugin_routerconfigs_fix_backups_pre14() in setup.php.
 */

beforeAll(function () {
	require_once __DIR__ . '/../../setup.php';
});

beforeEach(function () {
	$GLOBALS['__stub_overrides'] = array();
	$GLOBALS['__test_db_calls']  = array();
});

it('joins two relative path segments with exactly one separating slash', function () {
	expect(plugin_routerconfigs_combinepaths('/backups', 'sub'))->toBe('/backups/sub/');
	expect(plugin_routerconfigs_combinepaths('/backups/', 'sub/'))->toBe('/backups/sub/');
});

it('discards the base path when the second path is already absolute', function () {
	expect(plugin_routerconfigs_combinepaths('/backups', '/sub'))->toBe('/sub/');
});

it('does nothing when every stored backup path is already normalized', function () {
	$GLOBALS['__stub_overrides']['db_fetch_assoc_prepared'] = fn ($sql, $params) => [
		['id' => 1, 'directory' => '/backups/', 'filename' => 'router1.cfg'],
	];

	plugin_routerconfigs_fix_backups_pre14();

	$updates = array_filter($GLOBALS['__test_db_calls'], function ($call) {
		return $call['fn'] === 'db_execute_prepared' && stripos($call['sql'], 'UPDATE plugin_routerconfigs_backups') !== false;
	});

	expect($updates)->toBeEmpty();
});

it('splits a nested legacy filename into directory + basename and rewrites the row', function () {
	$GLOBALS['__stub_overrides']['db_fetch_assoc_prepared'] = fn ($sql, $params) => [
		['id' => 7, 'directory' => '/backups', 'filename' => 'sub/router1.cfg'],
	];
	$GLOBALS['__stub_overrides']['read_config_option'] = fn ($name, $force = false) => '/backups';

	plugin_routerconfigs_fix_backups_pre14();

	$updates = array_values(array_filter($GLOBALS['__test_db_calls'], function ($call) {
		return $call['fn'] === 'db_execute_prepared' && stripos($call['sql'], 'UPDATE plugin_routerconfigs_backups') !== false;
	}));

	expect($updates)->toHaveCount(1);
	expect($updates[0]['params'])->toBe(['/backups/sub/', 'router1.cfg', 7]);
});
