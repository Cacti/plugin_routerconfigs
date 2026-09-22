<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Unit coverage for routerconfigs_page_head() and routerconfigs_poller_bottom()
 * in setup.php.
 */

beforeAll(function () {
	require_once __DIR__ . '/../../setup.php';
});

beforeEach(function () {
	$GLOBALS['__stub_overrides'] = array();
	$GLOBALS['__test_db_calls']  = array();
});

it('prints the diff stylesheet only on router-compare.php', function () {
	$GLOBALS['__stub_overrides']['get_current_page'] = fn () => 'router-compare.php';

	ob_start();
	routerconfigs_page_head();
	$output = ob_get_clean();

	expect($output)->toContain('diff.css');
});

it('prints nothing on other pages', function () {
	$GLOBALS['__stub_overrides']['get_current_page'] = fn () => 'router-devices.php';

	ob_start();
	routerconfigs_page_head();
	$output = ob_get_clean();

	expect($output)->toBe('');
});

it('dispatches the background download when within the polling window', function () {
	$calls = [];

	$GLOBALS['__stub_overrides']['read_config_option'] = function ($name, $force = false) {
		if ($name === 'poller_interval') {
			// Larger than the maximum possible "$s" (59 * 60 = 3540) so this
			// test is deterministic regardless of the wall-clock minute it
			// happens to run in.
			return 4000;
		}

		if ($name === 'path_php_binary') {
			return '/usr/bin/php';
		}

		if ($name === 'routerconfigs_hour') {
			return 0;
		}

		return '';
	};

	$GLOBALS['__stub_overrides']['exec_background'] = function ($command, $args = '') use (&$calls) {
		$calls[] = ['command' => $command, 'args' => $args];

		return true;
	};

	routerconfigs_poller_bottom();

	expect($calls)->toHaveCount(1);
	expect($calls[0]['command'])->toBe('/usr/bin/php');
	expect($calls[0]['args'])->toContain('router-download.php');
});
