<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Unit coverage for the bulk device-edit helpers in includes/functions.php
 * (issue #133): plugin_routerconfigs_bulk_edit_validate() rejects values the
 * form does not offer, and plugin_routerconfigs_bulk_edit_apply() reschedules
 * each device and groups identical updates.
 */

beforeAll(function () {
	require_once __DIR__ . '/../../includes/functions.php';

	date_default_timezone_set('UTC');
});

beforeEach(function () {
	$GLOBALS['__test_db_calls']  = array();
	$GLOBALS['__stub_overrides'] = array(
		// Account 3 and device type 4 exist; nothing else does.
		'db_fetch_cell_prepared' => function ($sql, $params) {
			$id = $params[0] ?? 0;

			if (strpos($sql, 'plugin_routerconfigs_accounts') !== false) {
				return $id === 3 ? '1' : '0';
			}

			return $id === 4 ? '1' : '0';
		},
		'read_config_option' => fn ($name, $force = false) => $name === 'routerconfigs_hour' ? '2' : '',
	);
});

/**
 * Return the UPDATE statements issued against the devices table.
 *
 * @return array<int, array{sql: string, params: array}>
 */
function rc_bulk_updates(): array {
	return array_values(array_filter($GLOBALS['__test_db_calls'], function ($call) {
		return $call['fn'] === 'db_execute_prepared'
			&& stripos($call['sql'], 'UPDATE plugin_routerconfigs_devices') !== false;
	}));
}

it('maps valid bulk values to typed device columns', function () {
	$result = plugin_routerconfigs_bulk_edit_validate([
		'enabled'     => '',
		'sched_type'  => (string) SCHEDULE_WEEKLY,
		'recur_every' => '2',
		'connecttype' => RCONFIG_CONNECT_SSH,
		'account'     => '3',
		'devicetype'  => '4',
	]);

	expect($result['errors'])->toBe([]);
	expect($result['set'])->toBe([
		'enabled'     => '',
		'sched_type'  => SCHEDULE_WEEKLY,
		'recur_every' => 2,
		'connecttype' => RCONFIG_CONNECT_SSH,
		'account'     => 3,
		'devicetype'  => 4,
	]);
});

it('rejects a bulk value the form does not offer', function (string $column, $value) {
	$result = plugin_routerconfigs_bulk_edit_validate([$column => $value]);

	expect($result['set'])->toBe([]);
	expect($result['errors'])->toHaveCount(1);
})->with([
	'unknown schedule type'   => ['sched_type', '999'],
	'hourly is not offered'   => ['sched_type', (string) SCHEDULE_HOURLY],
	'non-numeric schedule'    => ['sched_type', '2abc'],
	'rerun every zero'        => ['recur_every', '0'],
	'rerun every too large'   => ['recur_every', '45'],
	'enabled other than on'   => ['enabled', 'yes'],
	'unknown connection type' => ['connecttype', 'rsh'],
	'missing account'         => ['account', '7'],
	'account none'            => ['account', '0'],
	'missing device type'     => ['devicetype', '9'],
	'array instead of string' => ['devicetype', ['4']],
	'unknown column'          => ['password', 'x'],
]);

it('reschedules each device and shares one UPDATE between identical results', function () {
	$GLOBALS['__stub_overrides']['db_fetch_assoc_prepared'] = function ($sql, $params) {
		expect($params)->toBe([1, 2, 3]);

		return [
			['id' => '1', 'nextbackup' => '1768442400', 'sched_type' => '2', 'recur_every' => '1', 'day_of_week' => null, 'month' => null, 'day_of_month' => null, 'monthly_week' => null, 'monthly_day' => null],
			['id' => '2', 'nextbackup' => '1768442400', 'sched_type' => '2', 'recur_every' => '1', 'day_of_week' => null, 'month' => null, 'day_of_month' => null, 'monthly_week' => null, 'monthly_day' => null],
			['id' => '3', 'nextbackup' => '1768442400', 'sched_type' => '3', 'recur_every' => '1', 'day_of_week' => null, 'month' => null, 'day_of_month' => '5', 'monthly_week' => null, 'monthly_day' => null],
		];
	};

	$updated = plugin_routerconfigs_bulk_edit_apply([1, 2, 3, 2], ['sched_type' => SCHEDULE_MONTHLY], strtotime('2026-01-31 09:00:00'));

	expect($updated)->toBe(3);

	$updates = rc_bulk_updates();
	expect($updates)->toHaveCount(2);

	// Devices 1 and 2 were Daily with no day: both get Last (set up on the
	// 31st) and the same next run, in one statement.
	expect($updates[0]['sql'])->toContain('`day_of_month` = ?');
	expect($updates[0]['sql'])->toContain('`nextbackup` = ?');
	expect($updates[0]['params'])->toBe([SCHEDULE_MONTHLY, '32', strtotime('2026-02-28 02:00:00'), 1, 2]);

	// Device 3 (Weekly, with a day of month left from before) keeps that day.
	expect($updates[1]['sql'])->not->toContain('day_of_month');
	expect($updates[1]['params'])->toBe([SCHEDULE_MONTHLY, strtotime('2026-02-05 02:00:00'), 3]);
});

it('leaves the schedule alone for a bulk change that does not touch it', function () {
	$GLOBALS['__stub_overrides']['db_fetch_assoc_prepared'] = fn ($sql, $params) => [
		['id' => '1', 'nextbackup' => '1768442400', 'sched_type' => '4', 'recur_every' => '1', 'day_of_week' => null, 'month' => null, 'day_of_month' => null, 'monthly_week' => null, 'monthly_day' => null],
		['id' => '2', 'nextbackup' => '1768442400', 'sched_type' => '2', 'recur_every' => '1', 'day_of_week' => null, 'month' => null, 'day_of_month' => null, 'monthly_week' => null, 'monthly_day' => null],
	];

	expect(plugin_routerconfigs_bulk_edit_apply([1, 2], ['enabled' => 'on'], time()))->toBe(2);

	$updates = rc_bulk_updates();
	expect($updates)->toHaveCount(1);
	expect($updates[0]['params'])->toBe(['on', 1, 2]);
});

it('does nothing without devices, settings, or matching rows', function () {
	expect(plugin_routerconfigs_bulk_edit_apply([], ['enabled' => 'on'], time()))->toBe(0);
	expect(plugin_routerconfigs_bulk_edit_apply([1], [], time()))->toBe(0);

	// The default stub returns no rows for the selected ids.
	expect(plugin_routerconfigs_bulk_edit_apply([1], ['enabled' => 'on'], time()))->toBe(0);
	expect(rc_bulk_updates())->toBe([]);
});
