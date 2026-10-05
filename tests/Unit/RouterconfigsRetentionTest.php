<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Unit coverage for the "keep inactive device backups" retention filter in
 * plugin_routerconfigs_retention() (issue #116).
 */

beforeAll(function () {
	require_once __DIR__ . '/../../includes/functions.php';
});

beforeEach(function () {
	$GLOBALS['__stub_overrides'] = array();
	$GLOBALS['__test_db_calls']  = array();
});

/**
 * Drive plugin_routerconfigs_retention() with a given keep-inactive setting
 * and return the SELECT SQL it issued plus the DELETE calls it recorded.
 *
 * @param string $keep_inactive The 'routerconfigs_retention_keep_inactive' value.
 *
 * @return array
 */
function rc_retention_capture($keep_inactive) {
	$dir  = sys_get_temp_dir();
	$file = 'routerconfigs-retention-' . uniqid() . '.cfg';
	touch($dir . '/' . $file);

	$captured = '';

	$GLOBALS['__stub_overrides']['read_config_option'] = function ($name, $force = false) use ($dir, $keep_inactive) {
		if ($name === 'routerconfigs_backup_path') {
			return $dir;
		}

		if ($name === 'routerconfigs_retention') {
			return 30;
		}

		if ($name === 'routerconfigs_retention_keep_inactive') {
			return $keep_inactive;
		}

		return '';
	};

	$GLOBALS['__stub_overrides']['db_fetch_assoc_prepared'] = function ($sql, $params) use (&$captured, $dir, $file) {
		$captured = $sql;

		return [['id' => 7, 'directory' => $dir, 'filename' => $file]];
	};

	plugin_routerconfigs_retention();

	$deletes = array_values(array_filter($GLOBALS['__test_db_calls'], function ($call) {
		return $call['fn'] === 'db_execute_prepared'
			&& stripos($call['sql'], 'DELETE FROM plugin_routerconfigs_backups') !== false;
	}));

	return ['select' => $captured, 'deletes' => $deletes];
}

it('scopes the retention query to enabled devices when keep-inactive is on', function () {
	$result = rc_retention_capture('on');

	expect($result['select'])->toContain('device IN');
	expect($result['deletes'])->toHaveCount(1);
	expect($result['deletes'][0]['sql'])->toContain('WHERE id IN');
	expect($result['deletes'][0]['params'])->toBe([7]);
});

it('purges all aged-out backups when keep-inactive is off', function () {
	$result = rc_retention_capture('');

	expect($result['select'])->not->toContain('device IN');
	expect($result['deletes'])->toHaveCount(1);
	expect($result['deletes'][0]['params'])->toBe([7]);
});
