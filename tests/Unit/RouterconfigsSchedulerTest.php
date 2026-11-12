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
	'true monthly with no stored day falls back to the 1st' => [
		4, ['id' => 1, 'sched_type' => SCHEDULE_MONTHLY, 'recur_every' => 1], '2026-01-15 09:00:00', '2026-02-01 04:00',
	],
	// The old fallback used the run's own day: a Jan 31 run asked for
	// "February 31", which strtotime() turned into March 3.
	'true monthly with no stored day does not follow a Jan 31 run' => [
		4, ['id' => 1, 'sched_type' => SCHEDULE_MONTHLY, 'recur_every' => 1], '2026-01-31 09:00:00', '2026-02-01 04:00',
	],
	'monthly skips a month that lacks the selected day' => [
		0, ['id' => 1, 'sched_type' => SCHEDULE_MONTHLY, 'month' => '2,3', 'day_of_month' => '30'], '2026-01-15 09:00:00', '2026-03-30 00:00',
	],
	'monthly on the 31st skips April' => [
		0, ['id' => 1, 'sched_type' => SCHEDULE_MONTHLY, 'month' => '1,2,3,4,5,6,7,8,9,10,11,12', 'day_of_month' => '31'], '2026-03-31 09:00:00', '2026-05-31 00:00',
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
	// February 29 alone is skipped in 2026 and 2027 and runs in 2028.
	'monthly on February 29 waits for the next leap year' => [
		['id' => 1, 'sched_type' => SCHEDULE_MONTHLY, 'start_at' => '2026-01-01 00:00:00', 'month' => '2', 'day_of_month' => '29'],
		'2026-01-15 09:00:00', '2028-02-29',
	],
	'monthly on February 29 bridges the 2100 non-leap year' => [
		['id' => 1, 'sched_type' => SCHEDULE_MONTHLY, 'start_at' => '2026-01-01 00:00:00', 'month' => '2', 'day_of_month' => '29'],
		'2096-03-01 00:00:00', '2104-02-29',
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
	expect($types)->toHaveKey(SCHEDULE_MONTHLY_ON_DAY);
});

it('exposes day/month option lists for the schedule selectors', function () {
	expect(plugin_routerconfigs_sched_days_of_week())->toHaveKey(1);
	expect(plugin_routerconfigs_sched_days_of_week())->toHaveCount(7);
	expect(plugin_routerconfigs_sched_months())->toHaveCount(12);
	expect(plugin_routerconfigs_sched_days_of_month())->toHaveKey(32);
	expect(plugin_routerconfigs_sched_weeks_of_month())->toHaveKey(32);
});

it('builds the dated subdirectory only for recognized formats', function () {
	$time = strtotime('2026-03-09 10:00:00');

	expect(plugin_routerconfigs_sched_date_dir('', $time))->toBe('');
	expect(plugin_routerconfigs_sched_date_dir('bogus', $time))->toBe('');
	expect(plugin_routerconfigs_sched_date_dir('Y-m-d', $time))->toBe('2026-03-09');
	expect(plugin_routerconfigs_sched_date_dir('Y/m/d', $time))->toBe('2026/03/09');
	expect(plugin_routerconfigs_sched_date_dir('d-m-Y', $time))->toBe('09-03-2026');
});

it('defaults the dated subdirectory time to now', function () {
	expect(plugin_routerconfigs_sched_date_dir('Y-m-d'))->toBe(date('Y-m-d'));
	expect(plugin_routerconfigs_sched_date_dir_formats())->toHaveKey('Y-m-d');
});

it('folds the multi-select schedule inputs into comma separated storage', function () {
	$save = plugin_routerconfigs_schedule_augment_save(
		['sched_type' => SCHEDULE_WEEKLY],
		[
			'day_of_week'  => ['2', '4', '6'],
			'month'        => [],
			'day_of_month' => ['15'],
			'monthly_day'  => ['3'],
		]
	);

	expect($save['day_of_week'])->toBe('2,4,6');
	expect($save['month'])->toBe('');
	expect($save['day_of_month'])->toBe('15');
	expect($save['monthly_week'])->toBe('');
	expect($save['monthly_day'])->toBe('3');
});

it('validates monthly and monthly-on-day schedules require their day fields', function () {
	// Weekly without a weekday is allowed.
	expect(plugin_routerconfigs_schedule_validate(['sched_type' => SCHEDULE_WEEKLY, 'day_of_week' => '']))->toBe('');

	// Daily needs nothing extra.
	expect(plugin_routerconfigs_schedule_validate(['sched_type' => SCHEDULE_DAILY]))->toBe('');

	// Monthly requires month and day of month.
	expect(plugin_routerconfigs_schedule_validate(['sched_type' => SCHEDULE_MONTHLY, 'month' => '', 'day_of_month' => '']))->not->toBe('');
	expect(plugin_routerconfigs_schedule_validate(['sched_type' => SCHEDULE_MONTHLY, 'month' => '3', 'day_of_month' => '1']))->toBe('');

	// Monthly on Day requires month, weeks, and weekdays.
	expect(plugin_routerconfigs_schedule_validate(['sched_type' => SCHEDULE_MONTHLY_ON_DAY, 'month' => '3', 'monthly_week' => '', 'monthly_day' => '2']))->not->toBe('');
	expect(plugin_routerconfigs_schedule_validate(['sched_type' => SCHEDULE_MONTHLY_ON_DAY, 'month' => '3', 'monthly_week' => '1', 'monthly_day' => '2']))->toBe('');
});

it('only accepts the offered Schedule Type and Rerun Every values', function () {
	expect(plugin_routerconfigs_schedule_valid_type((string) SCHEDULE_DAILY))->toBeTrue();
	expect(plugin_routerconfigs_schedule_valid_type(SCHEDULE_MONTHLY))->toBeTrue();

	foreach (['', '0', '999', (string) SCHEDULE_HOURLY, '2.5', '-2', 'daily'] as $value) {
		expect(plugin_routerconfigs_schedule_valid_type($value))->toBeFalse();
	}

	expect(plugin_routerconfigs_schedule_valid_recur('1'))->toBeTrue();
	expect(plugin_routerconfigs_schedule_valid_recur('31'))->toBeTrue();

	foreach (['', '0', '32', '1e1', ' 5'] as $value) {
		expect(plugin_routerconfigs_schedule_valid_recur($value))->toBeFalse();
	}

	expect(plugin_routerconfigs_sched_recur_options())->toHaveCount(31);
});

it('stores the set-up day for Monthly, using Last after the 28th', function (string $when, string $expected) {
	expect(plugin_routerconfigs_schedule_default_day(strtotime($when)))->toBe($expected);
})->with([
	'mid month'    => ['2026-01-15 10:00:00', '15'],
	'the 28th'     => ['2026-02-28 10:00:00', '28'],
	'the 29th'     => ['2026-01-29 10:00:00', '32'],
	'the 31st'     => ['2026-01-31 10:00:00', '32'],
]);

it('fills a fixed day of month when a device is saved as Monthly without one', function () {
	$save = plugin_routerconfigs_schedule_prepare_save([], ['sched_type' => (string) SCHEDULE_MONTHLY], strtotime('2026-01-31 10:00:00'));

	expect($save['day_of_month'])->toBe('32');
	// A new device keeps a NULL nextbackup so its first backup runs next.
	expect($save)->not->toHaveKey('nextbackup');

	$kept = plugin_routerconfigs_schedule_prepare_save(['day_of_month' => '5', 'sched_type' => '2'], ['sched_type' => (string) SCHEDULE_MONTHLY], strtotime('2026-01-31 10:00:00'));

	expect($kept)->not->toHaveKey('day_of_month');
});

it('recomputes nextbackup only when the schedule changes', function () {
	rc_sched_set_hour(3);

	$now      = strtotime('2026-01-15 09:00:00');
	$previous = [
		'nextbackup'  => (string) strtotime('2026-01-16 03:00:00'),
		'sched_type'  => (string) SCHEDULE_DAILY,
		'recur_every' => '1',
		'day_of_week' => null,
		'month'       => null,
	];

	// Unchanged schedule: the pending run stays.
	$same = plugin_routerconfigs_schedule_prepare_save($previous, ['sched_type' => '2', 'recur_every' => '1', 'hostname' => 'r1'], $now);
	expect($same)->not->toHaveKey('nextbackup');

	// Daily -> Monthly: the old Daily run is replaced by the Monthly one.
	$monthly = plugin_routerconfigs_schedule_prepare_save($previous, ['sched_type' => (string) SCHEDULE_MONTHLY, 'recur_every' => '1'], $now);
	expect($monthly['day_of_month'])->toBe('15');
	expect(date('Y-m-d H:i', $monthly['nextbackup']))->toBe('2026-02-15 03:00');

	// Daily -> Manual: 0, which the download query never selects.
	$manual = plugin_routerconfigs_schedule_prepare_save($previous, ['sched_type' => (string) SCHEDULE_MANUAL, 'recur_every' => '1'], $now);
	expect($manual['nextbackup'])->toBe(0);

	// Never scheduled (NULL nextbackup): left for the next run to pick up.
	$fresh = plugin_routerconfigs_schedule_prepare_save(['nextbackup' => null] + $previous, ['sched_type' => (string) SCHEDULE_WEEKLY], $now);
	expect($fresh)->not->toHaveKey('nextbackup');
});

it('resolves to no date when the selected day never exists', function () {
	$schedule = ['sched_type' => SCHEDULE_MONTHLY, 'start_at' => '2026-01-01 00:00:00', 'month' => '2', 'day_of_month' => '30,31'];

	expect(plugin_routerconfigs_schedule_calc_next_start($schedule, strtotime('2026-01-15 09:00:00')))->toBeFalse();
});

it('keeps Monthly on Day out of the bulk Schedule Type options', function () {
	expect(plugin_routerconfigs_sched_bulk_types())->not->toHaveKey(SCHEDULE_MONTHLY_ON_DAY);
	expect(plugin_routerconfigs_sched_bulk_types())->toHaveKey(SCHEDULE_MONTHLY);
	expect(plugin_routerconfigs_schedule_valid_type((string) SCHEDULE_MONTHLY_ON_DAY))->toBeTrue();
	expect(plugin_routerconfigs_schedule_valid_type((string) SCHEDULE_MONTHLY_ON_DAY, plugin_routerconfigs_sched_bulk_types()))->toBeFalse();
});

it('skips missing days when the 31st is selected for every month on the form', function () {
	rc_sched_set_hour(1);

	$months = ['1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12'];
	$save   = plugin_routerconfigs_schedule_augment_save(['sched_type' => SCHEDULE_MONTHLY], ['month' => $months, 'day_of_month' => ['31']]);

	expect(plugin_routerconfigs_schedule_validate($save, strtotime('2026-01-15 09:00:00')))->toBe('');

	// April, June, September and November have no 31st: no stray May 1,
	// July 1, October 1 or December 1 runs.
	$runs  = [];
	$after = strtotime('2026-01-15 09:00:00');

	for ($i = 0; $i < 7; $i++) {
		$after  = plugin_routerconfigs_schedule_next_start($save, $after);
		$runs[] = date('Y-m-d', $after);
	}

	expect($runs)->toBe(['2026-01-31', '2026-03-31', '2026-05-31', '2026-07-31', '2026-08-31', '2026-10-31', '2026-12-31']);

	// 31 together with Last still runs on the last day of short months.
	$with_last = plugin_routerconfigs_schedule_augment_save(['sched_type' => SCHEDULE_MONTHLY], ['month' => ['4'], 'day_of_month' => ['31', '32']]);
	expect(date('Y-m-d', plugin_routerconfigs_schedule_next_start($with_last, strtotime('2026-01-15 09:00:00'))))->toBe('2026-04-30');
});

it('rejects a monthly selection that never occurs', function () {
	$now = strtotime('2026-01-15 09:00:00');

	$never = plugin_routerconfigs_schedule_augment_save(['sched_type' => SCHEDULE_MONTHLY], ['month' => ['2'], 'day_of_month' => ['30', '31']]);
	expect(plugin_routerconfigs_schedule_validate($never, $now))->not->toBe('');

	// February 29 alone is valid: it runs in leap years.
	$leap = plugin_routerconfigs_schedule_augment_save(['sched_type' => SCHEDULE_MONTHLY], ['month' => ['2'], 'day_of_month' => ['29']]);
	expect(plugin_routerconfigs_schedule_validate($leap, $now))->toBe('');
	expect(plugin_routerconfigs_schedule_validate($leap))->toBe('');
});
