<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Unit coverage for plugin_routerconfigs_version() and the plugin lifecycle
 * contract wrappers (plugin_routerconfigs_uninstall/upgrade,
 * routerconfigs_check_dependencies) in setup.php.
 */

beforeAll(function () {
	require_once __DIR__ . '/../../setup.php';
});

beforeEach(function () {
	$GLOBALS['__stub_overrides'] = array();
	$GLOBALS['__test_db_calls']  = array();
});

it('parses the plugin INFO file into an info array', function () {
	$info = plugin_routerconfigs_version();

	expect($info)->toBeArray();
	expect($info)->toHaveKey('name');
	expect($info)->toHaveKey('version');
	expect($info['name'])->toBe('routerconfigs');
});

it('does not error on uninstall', function () {
	expect(fn () => plugin_routerconfigs_uninstall())->not->toThrow(Throwable::class);
});

it('runs the upgrade check and always reports false', function () {
	$GLOBALS['__stub_overrides']['get_current_page'] = fn () => 'graphs.php';

	expect(plugin_routerconfigs_upgrade())->toBeFalse();
});

it('reports its dependencies as always satisfied', function () {
	expect(routerconfigs_check_dependencies())->toBeTrue();
});
