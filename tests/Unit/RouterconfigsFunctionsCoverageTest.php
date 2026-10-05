<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Unit coverage for display_tabs() and plugin_routerconfigs_retention() in
 * includes/functions.php.
 */

beforeAll(function () {
	require_once __DIR__ . '/../../includes/functions.php';
});

beforeEach(function () {
	$GLOBALS['__stub_overrides'] = array();
	$GLOBALS['__test_db_calls']  = array();
});

it('renders the plugin tab bar for a known tab', function () {
	$GLOBALS['__stub_overrides']['get_nfilter_request_var'] = fn ($name) => 'devices';

	ob_start();
	display_tabs();
	$html = (string) ob_get_clean();

	expect($html)->toContain('router-devices.php');
	expect($html)->toContain('router-backups.php');
});

it('deletes aged-out backups and their rows during retention', function () {
	$dir  = sys_get_temp_dir();
	$file = 'routerconfigs-retention-' . uniqid() . '.cfg';
	touch($dir . '/' . $file);

	$GLOBALS['__stub_overrides']['read_config_option'] = function ($name, $force = false) use ($dir) {
		if ($name === 'routerconfigs_backup_path') {
			return $dir;
		}

		if ($name === 'routerconfigs_retention') {
			return 30;
		}

		return '';
	};

	$GLOBALS['__stub_overrides']['db_fetch_assoc_prepared'] = fn ($sql, $params) => [
		['id' => 1, 'directory' => $dir, 'filename' => $file],
	];

	plugin_routerconfigs_retention();

	expect(file_exists($dir . '/' . $file))->toBeFalse();

	$deletes = array_filter($GLOBALS['__test_db_calls'], function ($call) {
		return $call['fn'] === 'db_execute_prepared'
			&& stripos($call['sql'], 'DELETE FROM plugin_routerconfigs_backups') !== false;
	});

	expect($deletes)->not->toBeEmpty();
});
