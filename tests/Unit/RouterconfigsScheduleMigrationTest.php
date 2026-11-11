<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Unit coverage for routerconfigs_migrate_schedule_types(): the idempotent
 * conversion of the legacy interval-in-days `schedule` column to the
 * Automation Networks style sched_type/recur_every fields (issue #133).
 */

beforeAll(function () {
	require_once __DIR__ . '/../../includes/functions.php';
	require_once __DIR__ . '/../../includes/database.php';
});

beforeEach(function () {
	$GLOBALS['__stub_overrides'] = array();
	$GLOBALS['__test_db_calls']  = array();
});

/**
 * Return the UPDATE statements the migration issued against the devices
 * table, each as [sql, params].
 *
 * @return array
 */
function rc_migrate_updates() {
	return array_values(array_filter($GLOBALS['__test_db_calls'], function ($call) {
		return $call['fn'] === 'db_execute_prepared'
			&& stripos($call['sql'], 'UPDATE plugin_routerconfigs_devices') !== false;
	}));
}

it('no-ops when the sched_type column does not yet exist', function () {
	$GLOBALS['__stub_overrides']['db_column_exists'] = fn ($table, $column) => false;

	routerconfigs_migrate_schedule_types();

	expect(rc_migrate_updates())->toBeEmpty();
});

it('maps legacy weekly to a true Weekly and every other interval to Daily/rerun-N', function () {
	$GLOBALS['__stub_overrides']['db_column_exists'] = fn ($table, $column) => true;

	routerconfigs_migrate_schedule_types();

	$updates = rc_migrate_updates();
	expect($updates)->toHaveCount(2);

	// First: legacy weekly (schedule = 7) -> SCHEDULE_WEEKLY.
	expect($updates[0]['params'][0])->toBe(SCHEDULE_WEEKLY);
	expect(stripos($updates[0]['sql'], 'sched_type = 0 AND schedule = ?'))->not->toBeFalse();
	expect($updates[0]['params'][1])->toBe(RCONFIG_BACKUP_WEEKLY);

	// Second: every remaining interval -> SCHEDULE_DAILY with recur_every = N.
	expect($updates[1]['params'][0])->toBe(SCHEDULE_DAILY);
	expect(stripos($updates[1]['sql'], 'recur_every = GREATEST'))->not->toBeFalse();
	expect(stripos($updates[1]['sql'], 'WHERE sched_type = 0'))->not->toBeFalse();
});
