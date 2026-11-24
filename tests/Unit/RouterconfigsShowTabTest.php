<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Unit coverage for routerconfigs_show_tab() in setup.php and regression
 * check that the routerconfigs text domain is spelled consistently.
 */

beforeAll(function () {
	require_once __DIR__ . '/../../setup.php';
});

beforeEach(function () {
	$GLOBALS['__stub_overrides'] = array();
	$GLOBALS['__test_db_calls']  = array();
	$_SERVER['REQUEST_URI']      = '/cacti/graph_view.php';
});

it('renders the classic tab image when toptab and classic theme are active', function () {
	$captured = null;

	$GLOBALS['__stub_overrides']['read_config_option'] = fn ($name) => $name === 'routerconfigs_presentation' ? 'toptab' : '';
	$GLOBALS['__stub_overrides']['get_selected_theme'] = fn () => 'classic';
	$GLOBALS['__stub_overrides']['get_classic_tabimage'] = function ($text, $down) use (&$captured) {
		$captured = ['text' => $text, 'down' => $down];

		return '/cacti/images/tab_routers_down.gif';
	};
	$_SERVER['REQUEST_URI'] = '/cacti/plugins/routerconfigs/router-devices.php';

	ob_start();
	routerconfigs_show_tab();
	$output = ob_get_clean();

	expect($captured)->toBe(['text' => 'Routers', 'down' => true]);
	expect($output)->toContain('id="routerconfigs"');
	expect($output)->toContain('/cacti/images/tab_routers_down.gif');
	expect($output)->toContain('alt="RouterConfigs"');
});

it('renders a placeholder image src on non-classic themes and omits output outside toptab', function () {
	$GLOBALS['__stub_overrides']['read_config_option'] = fn ($name) => $name === 'routerconfigs_presentation' ? 'toptab' : '';
	$GLOBALS['__stub_overrides']['get_selected_theme'] = fn () => 'modern';

	ob_start();
	routerconfigs_show_tab();
	$output = ob_get_clean();

	expect($output)->toContain('src="#"');

	$GLOBALS['__stub_overrides']['read_config_option'] = fn ($name) => $name === 'routerconfigs_presentation' ? 'console' : '';

	ob_start();
	routerconfigs_show_tab();
	$consoleOutput = ob_get_clean();

	expect($consoleOutput)->toBe('');
});

it('uses the routerconfigs gettext domain in setup.php and router-devices.php', function () {
	$setup   = file_get_contents(__DIR__ . '/../../setup.php');
	$devices = file_get_contents(__DIR__ . '/../../router-devices.php');

	expect($setup)->toContain("__('Routers', 'routerconfigs')");
	expect($setup)->not->toContain("'routerconfig'");
	expect($devices)->toContain("__('Current', 'routerconfigs')");
	expect($devices)->not->toContain("'routerconfig'");
});
