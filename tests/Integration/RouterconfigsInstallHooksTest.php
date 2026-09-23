<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Integration coverage for plugin_routerconfigs_install(): verifies every
 * hook and the realm the plugin depends on at runtime are actually
 * registered, together with its full table set, in a single end-to-end
 * pass.
 */

beforeAll(function () {
	require_once __DIR__ . '/../../setup.php';
});

beforeEach(function () {
	$GLOBALS['__test_db_calls']          = array();
	$GLOBALS['__test_registered_hooks']  = array();
	$GLOBALS['__test_registered_realms'] = array();

	$GLOBALS['__stub_overrides'] = array(
		'api_plugin_register_hook' => function ($plugin, $hook, $function, $file, $subtype = '') {
			$GLOBALS['__test_registered_hooks'][] = array(
				'plugin'   => $plugin,
				'hook'     => $hook,
				'function' => $function,
				'file'     => $file,
			);

			return true;
		},
		'api_plugin_register_realm' => function ($plugin, $file, $description, $enabled = 1) {
			$GLOBALS['__test_registered_realms'][] = array(
				'plugin' => $plugin,
				'file'   => $file,
			);

			return true;
		},
	);
});

it('registers every hook routerconfigs depends on, its realm, and provisions its tables', function () {
	plugin_routerconfigs_install();

	$hooks = [];
	foreach ($GLOBALS['__test_registered_hooks'] as $registered) {
		$hooks[$registered['hook']] = $registered;
	}

	foreach ([
		'top_header_tabs',
		'top_graph_header_tabs',
		'config_arrays',
		'draw_navigation_text',
		'config_settings',
		'poller_bottom',
		'page_head',
	] as $expected) {
		expect($hooks)->toHaveKey($expected);
		expect($hooks[$expected]['plugin'])->toBe('routerconfigs');
		expect($hooks[$expected]['file'])->toBe('setup.php');
	}

	expect($GLOBALS['__test_registered_realms'])->toHaveCount(1);
	expect($GLOBALS['__test_registered_realms'][0]['file'])->toBe('router-devices.php,router-accounts.php,router-backups.php,router-compare.php,router-devtypes.php');

	$creates = array_filter($GLOBALS['__test_db_calls'], function ($call) {
		return $call['fn'] === 'db_execute_prepared' && stripos($call['sql'], 'INSERT INTO plugin_routerconfigs_devicetypes') !== false;
	});

	expect($creates)->toHaveCount(5);
});
