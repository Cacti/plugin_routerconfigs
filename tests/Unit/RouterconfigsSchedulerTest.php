<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Unit coverage for the plugin-local Automation Networks style scheduler
 * (includes/scheduler.php): the next-backup calendar math for Manual,
 * Daily (including the 10-day cadence), Weekly, Hourly, and true Monthly
 * schedules, plus the legacy interval-in-days fallback (issue #133).
 */

beforeAll(function () {
	require_once __DIR__ . '/../../includes/functions.php';

	// The legacy next-time helper aligns to a UTC day boundary, so pin the
	// timezone to make the date assertions below deterministic regardless of
	// the runner's configured timezone.
	date_default_timezone_set('UTC');
});

beforeEach(function () {
	$GLOBALS['__stub_overrides'] = array();
	$GLOBALS['__test_db_calls']  = array();
});

/**
 * Pin the configured backup hour so next-start results are deterministic.
 *
 * @param int $hour The hour of day (0-23) to report for routerconfigs_hour.
 *
 * @return void
 */
function rc_sched_set_hour(int $hour): void {
	$GLOBALS['__stub_overrides']['read_config_option'] = function ($name, $force = false) use ($hour) {
		return $name === 'routerconfigs_hour' ? (string) $hour : '';
	};
}

/**
 * Resolve the next backup time for a device at a given hour and reference
 * time, collapsing the repeated set-hour / strtotime / call sequence.
 *
 * @param int    $hour   The configured backup hour.
 * @param array  $device The device row.
 * @param string $after  The reference datetime string.
 *
 * @return int The next backup Unix timestamp.
 */
function rc_sched_next(int $hour, array $device, string $after): int {
	rc_sched_set_hour($hour);

	return plugin_routerconfigs_schedule_next_start($device, strtotime($after));
}

it('computes the next backup time for each schedule type', function (int $hour, array $device, string $after, string $expected) {
	expect(date('Y-m-d H:i', rc_sched_next($hour, $device, $after)))->toBe($expected);
})->with([
	'daily, next day at the configured hour' => [
		2, ['id' => 1, 'sched_type' => SCHEDULE_DAILY, 'recur_every' => 1], '2026-01-15 09:00:00', '2026-01-16 02:00',
	],
	'daily rerun-every-10 gives a 10-day cadence' => [
		0, ['id' => 1, 'sched_type' => SCHEDULE_DAILY, 'recur_every' => 10], '2026-01-15 00:00:01', '2026-01-25 00:00',
	],
	'weekly with no weekday runs ~7 days ahead' => [
		3, ['id' => 1, 'sched_type' => SCHEDULE_WEEKLY, 'recur_every' => 1, 'day_of_week' => ''], '2026-01-15 09:00:00', '2026-01-22 03:00',
	],
	'weekly on the next selected weekday (Monday)' => [
		1, ['id' => 1, 'sched_type' => SCHEDULE_WEEKLY, 'recur_every' => 1, 'day_of_week' => '2'], '2026-01-15 09:00:00', '2026-01-19 01:00',
	],
	'weekly rolls to next week when today slot passed' => [
		1, ['id' => 1, 'sched_type' => SCHEDULE_WEEKLY, 'recur_every' => 1, 'day_of_week' => '5'], '2026-01-15 09:00:00', '2026-01-22 01:00',
	],
	'weekly picks the soonest of several weekdays' => [
		1, ['id' => 1, 'sched_type' => SCHEDULE_WEEKLY, 'recur_every' => 1, 'day_of_week' => '2,7'], '2026-01-15 09:00:00', '2026-01-17 01:00',
	],
	'true monthly on the same day next month' => [
		4, ['id' => 1, 'sched_type' => SCHEDULE_MONTHLY, 'recur_every' => 1], '2026-01-15 09:00:00', '2026-02-15 04:00',
	],
	'monthly with explicit month and day of month' => [
		5, ['id' => 1, 'sched_type' => SCHEDULE_MONTHLY, 'recur_every' => 1, 'month' => '3', 'day_of_month' => '3'], '2026-01-15 09:00:00', '2026-03-03 05:00',
	],
	'hourly rerunning every N hours' => [
		0, ['id' => 1, 'sched_type' => SCHEDULE_HOURLY, 'recur_every' => 2], '2026-01-15 09:15:00', '2026-01-15 11:00',
	],
	'legacy interval-in-days weekly (schedule = 7)' => [
		0, ['id' => 1, 'schedule' => 7], '2026-01-15 09:00:00', '2026-01-22 00:00',
	],
	'legacy fallback defaults to daily' => [
		0, ['id' => 1], '2026-01-15 09:00:00', '2026-01-16 00:00',
	],
]);

it('returns 0 when a device is not scheduled for automatic backup', function (array $device) {
	expect(rc_sched_next(0, $device, '2026-01-15 09:00:00'))->toBe(0);
})->with([
	'manual schedule'   => [['id' => 1, 'sched_type' => SCHEDULE_MANUAL]],
	'unknown schedule'  => [['id' => 1, 'sched_type' => 999]],
]);

it('resolves monthly calendar schedules to the correct date', function (array $schedule, string $after, string $expected) {
	$next = plugin_routerconfigs_schedule_calc_next_start($schedule, strtotime($after));

	expect(date('Y-m-d', $next))->toBe($expected);
})->with([
	// First Monday of February 2026 is 2026-02-02.
	'monthly on day: first Monday of February' => [
		['id' => 1, 'sched_type' => SCHEDULE_MONTHLY_ON_DAY, 'start_at' => '2026-01-01 05:00:00', 'month' => '2', 'monthly_week' => '1', 'monthly_day' => '2'],
		'2026-01-15 09:00:00', '2026-02-02',
	],
	// 2026-01-10 already passed, so the next occurrence rolls to 2027.
	'monthly rolls into next year when the date has passed' => [
		['id' => 1, 'sched_type' => SCHEDULE_MONTHLY, 'start_at' => '2026-01-01 00:00:00', 'month' => '1', 'day_of_month' => '10'],
		'2026-06-01 00:00:00', '2027-01-10',
	],
	// 2026 is not a leap year, so the last day of February is the 28th.
	'monthly on the last day of the month (sentinel 32)' => [
		['id' => 1, 'sched_type' => SCHEDULE_MONTHLY, 'start_at' => '2026-01-01 00:00:00', 'month' => '2', 'day_of_month' => '32'],
		'2026-01-15 09:00:00', '2026-02-28',
	],
]);

it('defaults the reference time to now when none is supplied', function () {
	$schedule = [
		'id'           => 1,
		'sched_type'   => SCHEDULE_MONTHLY,
		'start_at'     => date('Y-m-d H:i:s'),
		'month'        => (string) ((int) date('n')),
		'day_of_month' => (string) ((int) date('j')),
	];

	// Called without an explicit $now, it must still resolve to a future
	// timestamp (this month's day already passed -> next year).
	expect(plugin_routerconfigs_schedule_calc_next_start($schedule))->toBeGreaterThan(time());
});

it('exposes the schedule type options for the device form', function () {
	$types = plugin_routerconfigs_sched_types();

	expect($types)->toHaveKey(SCHEDULE_DAILY);
	expect($types)->toHaveKey(SCHEDULE_WEEKLY);
	expect($types)->toHaveKey(SCHEDULE_MONTHLY);
	// Monthly on Day is not offered until its selectors exist.
	expect($types)->not->toHaveKey(SCHEDULE_MONTHLY_ON_DAY);
});
