<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Unit coverage for routerconfigs_config_settings() and
 * routerconfigs_config_arrays() in setup.php.
 */

beforeAll(function () {
	require_once __DIR__ . '/../../setup.php';
});

beforeEach(function () {
	$GLOBALS['__stub_overrides']                     = array();
	$GLOBALS['__test_db_calls']                      = array();
	$GLOBALS['__stub_overrides']['get_current_page'] = fn () => 'graphs.php';
});

it('adds the routerconfigs tab and settings when no settings exist yet', function () {
	global $tabs, $settings, $rc_settings;

	$tabs     = [];
	$settings = [];

	routerconfigs_config_settings();

	expect($tabs['routerconfigs'])->toBe('Router Configs');
	expect($settings['routerconfigs'])->toBe($rc_settings);
});

it('merges into an existing settings array without clobbering it', function () {
	global $tabs, $settings, $rc_settings;

	$tabs     = [];
	$settings = ['routerconfigs' => ['other_setting' => ['friendly_name' => 'Other']]];

	routerconfigs_config_settings();

	expect($settings['routerconfigs'])->toHaveKey('other_setting');

	foreach (array_keys($rc_settings) as $key) {
		expect($settings['routerconfigs'])->toHaveKey($key);
	}
});

it('adds the Utilities menu entry only when the console presentation is selected', function () {
	global $menu;

	$menu = [__('Utilities', 'routerconfigs') => []];

	$GLOBALS['__stub_overrides']['read_config_option'] = fn ($name, $force = false) => $name === 'routerconfigs_presentation' ? 'toptab' : '';
	routerconfigs_config_arrays();
	expect($menu[__('Utilities', 'routerconfigs')])->not->toHaveKey('plugins/routerconfigs/router-devices.php');

	$menu = [__('Utilities', 'routerconfigs') => []];

	$GLOBALS['__stub_overrides']['read_config_option'] = fn ($name, $force = false) => $name === 'routerconfigs_presentation' ? 'console' : '';
	routerconfigs_config_arrays();
	expect($menu[__('Utilities', 'routerconfigs')])->toHaveKey('plugins/routerconfigs/router-devices.php');
});
