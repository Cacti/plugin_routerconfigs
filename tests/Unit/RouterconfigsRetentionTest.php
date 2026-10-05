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
 * and return the DELETE calls it issued.
 *
 * @param string $keep_inactive The 'routerconfigs_retention_keep_inactive' value.
 *
 * @return array
 */
function rc_retention_delete_calls($keep_inactive) {
	$dir  = sys_get_temp_dir();
	$file = 'routerconfigs-retention-' . uniqid() . '.cfg';
	touch($dir . '/' . $file);

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

	$GLOBALS['__stub_overrides']['db_fetch_assoc_prepared'] = fn ($sql, $params) => [
		['directory' => $dir, 'filename' => $file],
	];

	plugin_routerconfigs_retention();

	return array_values(array_filter($GLOBALS['__test_db_calls'], function ($call) {
		return $call['fn'] === 'db_execute_prepared'
			&& stripos($call['sql'], 'DELETE FROM plugin_routerconfigs_backups') !== false;
	}));
}

it('scopes retention to enabled devices when keep-inactive is on', function () {
	$deletes = rc_retention_delete_calls('on');

	expect($deletes)->toHaveCount(1);
	expect($deletes[0]['sql'])->toContain('device IN');
});

it('deletes all aged-out backups when keep-inactive is off', function () {
	$deletes = rc_retention_delete_calls('');

	expect($deletes)->toHaveCount(1);
	expect($deletes[0]['sql'])->not->toContain('device IN');
});
