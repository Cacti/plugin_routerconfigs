<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

/*
 * Plugin-local replica of Cacti core's Automation Networks scheduler
 * (lib/api_scheduler.php). Core ships that library only on Cacti 1.3+
 * (the develop branch); this plugin supports Cacti 1.2.29+, where the
 * api_scheduler_* functions and SCHEDULE_* constants do not exist. The
 * helpers below reproduce the same schedule field contract and next-run
 * calendar math so the plugin behaves identically on both baselines,
 * delegating the calendar-heavy monthly calculations to core's
 * api_scheduler_calculate_next_start() whenever it is available.
 */

// Schedule type identifiers, matching Cacti core's SCHEDULE_* values so a
// device row is understood identically by this replica and by core. They are
// defined only when core has not already provided them (Cacti 1.3+).
foreach ([
	'SCHEDULE_MANUAL'         => 1,
	'SCHEDULE_DAILY'          => 2,
	'SCHEDULE_WEEKLY'         => 3,
	'SCHEDULE_MONTHLY'        => 4,
	'SCHEDULE_MONTHLY_ON_DAY' => 5,
	'SCHEDULE_HOURLY'         => 6,
] as $rc_sched_const => $rc_sched_value) {
	if (!defined($rc_sched_const)) {
		define($rc_sched_const, $rc_sched_value);
	}
}

unset($rc_sched_const, $rc_sched_value);

/**
 * Returns the Schedule Type options presented on the device edit form,
 * in the order Cacti core's Automation scheduler presents them. Monthly
 * on Day resolves a run date such as "the first Monday of selected
 * months" from its week/day-of-week selectors.
 *
 * @return array<int, string> Map of SCHEDULE_* constant to label.
 */
function plugin_routerconfigs_sched_types(): array {
	return [
		SCHEDULE_MANUAL         => __('Manual', 'routerconfigs'),
		SCHEDULE_DAILY          => __('Daily', 'routerconfigs'),
		SCHEDULE_WEEKLY         => __('Weekly', 'routerconfigs'),
		SCHEDULE_MONTHLY        => __('Monthly', 'routerconfigs'),
		SCHEDULE_MONTHLY_ON_DAY => __('Monthly on Day', 'routerconfigs'),
	];
}

/**
 * Returns the Days of Week options (1=Sunday .. 7=Saturday), matching
 * Cacti core's Automation scheduler ordering, for the Weekly schedule's
 * multi-select.
 *
 * @return array<int, string> Map of day number to localized day name.
 */
function plugin_routerconfigs_sched_days_of_week(): array {
	return [
		1 => __('Sunday', 'routerconfigs'),
		2 => __('Monday', 'routerconfigs'),
		3 => __('Tuesday', 'routerconfigs'),
		4 => __('Wednesday', 'routerconfigs'),
		5 => __('Thursday', 'routerconfigs'),
		6 => __('Friday', 'routerconfigs'),
		7 => __('Saturday', 'routerconfigs'),
	];
}

/**
 * Returns the Months of Year options (1=January .. 12=December) for the
 * Monthly schedules' multi-select.
 *
 * @return array<int, string> Map of month number to localized month name.
 */
function plugin_routerconfigs_sched_months(): array {
	return [
		1  => __('January', 'routerconfigs'),
		2  => __('February', 'routerconfigs'),
		3  => __('March', 'routerconfigs'),
		4  => __('April', 'routerconfigs'),
		5  => __('May', 'routerconfigs'),
		6  => __('June', 'routerconfigs'),
		7  => __('July', 'routerconfigs'),
		8  => __('August', 'routerconfigs'),
		9  => __('September', 'routerconfigs'),
		10 => __('October', 'routerconfigs'),
		11 => __('November', 'routerconfigs'),
		12 => __('December', 'routerconfigs'),
	];
}

/**
 * Returns the Days of Month options (1..31 plus 32 = Last day) for the
 * Monthly schedule's multi-select.
 *
 * @return array<int, string> Map of day number to label.
 */
function plugin_routerconfigs_sched_days_of_month(): array {
	$days = [];

	for ($d = 1; $d <= 31; $d++) {
		$days[$d] = (string) $d;
	}

	$days[32] = __('Last', 'routerconfigs');

	return $days;
}

/**
 * Returns the Week of Month options (first..fourth plus 32 = Last) for
 * the Monthly on Day schedule's multi-select.
 *
 * @return array<int, string> Map of week number to label.
 */
function plugin_routerconfigs_sched_weeks_of_month(): array {
	return [
		1  => __('First', 'routerconfigs'),
		2  => __('Second', 'routerconfigs'),
		3  => __('Third', 'routerconfigs'),
		4  => __('Fourth', 'routerconfigs'),
		32 => __('Last', 'routerconfigs'),
	];
}

/**
 * Returns the supported formats for the optional dated backup
 * subdirectory, keyed by the PHP date() format string used to build the
 * path component. The empty key disables the feature.
 *
 * @return array<string, string> Map of date() format to label.
 */
function plugin_routerconfigs_sched_date_dir_formats(): array {
	return [
		''           => __('Off (no dated subdirectory)', 'routerconfigs'),
		'Y-m-d'      => __('YYYY-MM-DD', 'routerconfigs'),
		'Y/m/d'      => __('YYYY/MM/DD (nested)', 'routerconfigs'),
		'd-m-Y'      => __('DD-MM-YYYY', 'routerconfigs'),
	];
}

/**
 * Builds the dated subdirectory path component for a backup, according to
 * the configured routerconfigs_archive_datedir format. Returns an empty
 * string when the feature is off or the stored format is not recognized,
 * so callers insert nothing into the path. Only the fixed, whitelisted
 * date() formats above are honored.
 *
 * @param string|null $format The configured date() format.
 * @param int|null    $time   The backup time; defaults to now.
 *
 * @return string The dated path component (no leading/trailing slash), or
 *                '' when disabled.
 */
function plugin_routerconfigs_sched_date_dir($format, ?int $time = null): string {
	$format = (string) $format;

	if ($format === '' || !array_key_exists($format, plugin_routerconfigs_sched_date_dir_formats())) {
		return '';
	}

	if ($time === null) {
		$time = time();
	}

	return date($format, $time);
}

/**
 * Parses a comma separated list of integers (the storage format shared
 * with core for day_of_week/month/day_of_month/etc.) into a sorted,
 * de-duplicated list of positive integers.
 *
 * @param string|null $csv The comma separated value list.
 *
 * @return array<int, int> The parsed integer values.
 */
function plugin_routerconfigs_schedule_csv_ints($csv): array {
	$out = [];

	foreach (explode(',', (string) $csv) as $part) {
		$part = trim($part);

		if ($part !== '' && ctype_digit($part)) {
			$out[] = (int) $part;
		}
	}

	$out = array_values(array_unique($out));
	sort($out);

	return $out;
}

/**
 * Computes the next backup Unix timestamp for a device, strictly after
 * the supplied reference time, following the Automation Networks
 * schedule semantics (sched_type + recur_every + day_of_week/month/
 * day_of_month/monthly_week/monthly_day). The configured
 * routerconfigs_hour setting supplies the time of day. Devices that
 * predate the scheduler columns (no sched_type) fall back to the legacy
 * interval-days behaviour via plugin_routerconfigs_nexttime().
 *
 * @param array $device The device row.
 * @param int   $after  The reference Unix timestamp; the result is
 *                      strictly greater than this value.
 *
 * @return int The next backup Unix timestamp, or 0 when scheduling is
 *             disabled (Manual) or cannot be resolved.
 */
function plugin_routerconfigs_schedule_next_start(array $device, int $after): int {
	$hour = (int) read_config_option('routerconfigs_hour');
	$type = isset($device['sched_type']) ? (int) $device['sched_type'] : 0;

	// Back-compatibility: a device still on the legacy schedule column (no
	// sched_type yet) keeps its previous interval-in-days behaviour.
	if ($type <= 0) {
		$legacy = isset($device['schedule']) && $device['schedule'] !== '' && $device['schedule'] !== null
			? (int) $device['schedule']
			: RCONFIG_BACKUP_DAILY;

		return (int) plugin_routerconfigs_nexttime($after, $legacy, 86400, $hour);
	}

	$recur = isset($device['recur_every']) ? max(1, (int) $device['recur_every']) : 1;

	switch ($type) {
		case SCHEDULE_MANUAL:
			return 0;

		case SCHEDULE_HOURLY:
			$next = $after - ($after % 3600);

			while ($next <= $after) {
				$next += $recur * 3600;
			}

			return $next;

		case SCHEDULE_DAILY:
			$next = strtotime(date('Y-m-d', $after)) + $hour * 3600;

			while ($next <= $after) {
				$next += $recur * 86400;
			}

			return $next;

		case SCHEDULE_WEEKLY:
			$days = plugin_routerconfigs_schedule_csv_ints($device['day_of_week'] ?? '');

			// No specific weekday selected: behave like the legacy weekly
			// cadence (every recur_every weeks from today's time of day).
			if (empty($days)) {
				$next = strtotime(date('Y-m-d', $after)) + $hour * 3600;

				while ($next <= $after) {
					$next += $recur * 7 * 86400;
				}

				return $next;
			}

			// Specific weekday(s): find the soonest selected weekday at the
			// configured hour, strictly after the reference time. day_of_week
			// is 1=Sunday .. 7=Saturday.
			$base      = strtotime(date('Y-m-d', $after)) + $hour * 3600;
			$today_dow = (int) date('w', $base) + 1;
			$best      = 0;

			foreach ($days as $day) {
				$candidate = $base + ((($day - $today_dow) + 7) % 7) * 86400;

				if ($candidate <= $after) {
					$candidate += 7 * 86400;
				}

				if ($best === 0 || $candidate < $best) {
					$best = $candidate;
				}
			}

			return $best;

		case SCHEDULE_MONTHLY:
		case SCHEDULE_MONTHLY_ON_DAY:
			$sched = plugin_routerconfigs_schedule_normalize($device, $hour, $after);

			if (function_exists('api_scheduler_calculate_next_start')) {
				// Delegate to Cacti core's scheduler on 1.3+; the CI baseline is
				// 1.2.x where this function does not exist, so this branch is
				// not exercised there.
				// @codeCoverageIgnoreStart
				$ts = api_scheduler_calculate_next_start($sched);
				// @codeCoverageIgnoreEnd
			} else {
				$ts = plugin_routerconfigs_schedule_calc_next_start($sched, $after);
			}

			return $ts === false ? 0 : (int) $ts;
	}

	return 0;
}

/**
 * Builds a core-compatible schedule array for the monthly calculators,
 * supplying sensible defaults (all months, and the reference day of the
 * month) when a true-monthly device has not selected explicit months or
 * days of month.
 *
 * @param array $device The device row.
 * @param int   $hour   The configured hour of day (0-23).
 * @param int   $after  The reference Unix timestamp.
 *
 * @return array<string, mixed> The normalized schedule array.
 */
function plugin_routerconfigs_schedule_normalize(array $device, int $hour, int $after): array {
	$month = isset($device['month']) && $device['month'] !== '' && $device['month'] !== null
		? $device['month']
		: '1,2,3,4,5,6,7,8,9,10,11,12';

	$day_of_month = isset($device['day_of_month']) && $device['day_of_month'] !== '' && $device['day_of_month'] !== null
		? $device['day_of_month']
		: date('j', $after);

	return [
		'id'           => $device['id'] ?? 0,
		'sched_type'   => (int) $device['sched_type'],
		'start_at'     => date('Y-m-d', $after) . sprintf(' %02d:00:00', $hour),
		'next_start'   => '0000-00-00 00:00:00',
		'recur_every'  => isset($device['recur_every']) ? max(1, (int) $device['recur_every']) : 1,
		'day_of_week'  => $device['day_of_week'] ?? '',
		'month'        => $month,
		'day_of_month' => $day_of_month,
		'monthly_week' => $device['monthly_week'] ?? '',
		'monthly_day'  => $device['monthly_day'] ?? '',
	];
}

/**
 * Plugin-local replica of core's api_scheduler_calculate_next_start():
 * resolves the next monthly / monthly-on-day occurrence, trying this
 * year then next so a late-year schedule does not resolve to false for
 * the remainder of the year.
 *
 * @param array    $schedule The normalized schedule array.
 * @param int|null $now      The reference time; defaults to time().
 *
 * @return int|false The next start Unix timestamp, or false if none.
 */
function plugin_routerconfigs_schedule_calc_next_start(array $schedule, ?int $now = null) {
	if ($now === null) {
		$now = time();
	}

	$next = plugin_routerconfigs_schedule_calc_next_start_for_year($schedule, (int) date('Y', $now), $now);

	if ($next === false) {
		$next = plugin_routerconfigs_schedule_calc_next_start_for_year($schedule, (int) date('Y', $now) + 1, $now);
	}

	return $next;
}

/**
 * Plugin-local replica of core's
 * api_scheduler_calculate_next_start_for_year(): resolves the earliest
 * monthly / monthly-on-day occurrence within a single calendar year that
 * falls strictly after the reference time.
 *
 * @param array $schedule The normalized schedule array.
 * @param int   $year     The calendar year to resolve against.
 * @param int   $now      The reference Unix timestamp.
 *
 * @return int|false The next start Unix timestamp, or false if none.
 */
function plugin_routerconfigs_schedule_calc_next_start_for_year(array $schedule, int $year, int $now) {
	$dates  = [];
	$months = [
		1 => 'January',   2 => 'February', 3 => 'March',     4 => 'April',
		5 => 'May',       6 => 'June',     7 => 'July',      8 => 'August',
		9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December',
	];
	$weeks  = [1 => 'first', 2 => 'second', 3 => 'third', 4 => 'fourth', 32 => 'last'];
	$wdays  = [
		1 => 'Sunday',   2 => 'Monday', 3 => 'Tuesday', 4 => 'Wednesday',
		5 => 'Thursday', 6 => 'Friday', 7 => 'Saturday',
	];

	$type = (int) $schedule['sched_type'];
	$time = date('H:i:s', strtotime($schedule['start_at']));

	if ($type === SCHEDULE_MONTHLY) {
		foreach (plugin_routerconfigs_schedule_csv_ints($schedule['month']) as $month) {
			$smonth = $months[$month] ?? 'January';

			foreach (plugin_routerconfigs_schedule_csv_ints($schedule['day_of_month']) as $day) {
				$dates[] = ($day == 32)
					? strtotime("last day of $smonth $year $time")
					: strtotime("$smonth $day $year $time");
			}
		}
	} elseif ($type === SCHEDULE_MONTHLY_ON_DAY) {
		foreach (plugin_routerconfigs_schedule_csv_ints($schedule['month']) as $month) {
			$smonth = $months[$month] ?? 'January';

			foreach (plugin_routerconfigs_schedule_csv_ints($schedule['monthly_week']) as $week) {
				$sweek = $weeks[$week] ?? 'first';

				foreach (plugin_routerconfigs_schedule_csv_ints($schedule['monthly_day']) as $day) {
					$sday    = $wdays[$day] ?? 'Sunday';
					$dates[] = strtotime("$sweek $sday of $smonth $year $time");
				}
			}
		}
	}

	sort($dates);

	foreach ($dates as $date) {
		if ($date !== false && $date > $now) {
			return $date;
		}
	}

	return false;
}

/**
 * Folds the device form's multi-select schedule inputs (day_of_week,
 * month, day_of_month, monthly_week, monthly_day) into the comma
 * separated storage format on the save array. Each input is expected to
 * be an array of integer-like values (or absent, meaning none selected).
 * Invoked from save_devices() in router-devices.php.
 *
 * @param array $save The device save array to augment.
 * @param array $post The submitted multi-select values keyed by field.
 *
 * @return array The save array with the five schedule CSV fields set.
 */
function plugin_routerconfigs_schedule_augment_save(array $save, array $post): array {
	foreach (['day_of_week', 'month', 'day_of_month', 'monthly_week', 'monthly_day'] as $field) {
		if (isset($post[$field]) && is_array($post[$field]) && cacti_sizeof($post[$field])) {
			$save[$field] = implode(',', array_map('intval', $post[$field]));
		} else {
			$save[$field] = '';
		}
	}

	return $save;
}

/**
 * Validates a device's schedule selections against its Schedule Type,
 * mirroring Cacti core's Automation scheduler rules: a Monthly schedule
 * needs at least one Month and Day of Month, and a Monthly on Day
 * schedule needs Months, Weeks of Month, and Days of Week. Weekly without
 * a specific day is allowed (it runs every Rerun-Every weeks). Invoked
 * from save_devices() to reject an unschedulable combination before it is
 * stored.
 *
 * @param array $save The device save array (with sched_type and the CSV
 *                    schedule fields already set).
 *
 * @return string An error message, or '' when the schedule is valid.
 */
function plugin_routerconfigs_schedule_validate(array $save): string {
	$type = (int) ($save['sched_type'] ?? 0);

	if ($type === SCHEDULE_MONTHLY) {
		if (($save['month'] ?? '') === '' || ($save['day_of_month'] ?? '') === '') {
			return __('For a Monthly schedule you must select at least one Month and one Day of Month.', 'routerconfigs');
		}
	} elseif ($type === SCHEDULE_MONTHLY_ON_DAY) {
		if (($save['month'] ?? '') === '' || ($save['monthly_week'] ?? '') === '' || ($save['monthly_day'] ?? '') === '') {
			return __('For a Monthly on Day schedule you must select Months, Weeks of Month, and Days of Week.', 'routerconfigs');
		}
	}

	return '';
}
