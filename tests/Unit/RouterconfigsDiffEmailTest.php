<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Unit coverage for the config diff Email helpers (issue #109): binary
 * detection, exclude-pattern parsing/filtering, unified diff rendering,
 * safe backup-file reading, the two-most-recent-backups diff, and the
 * notification recipient resolution (including an optional Thold list).
 */

beforeAll(function () {
	require_once __DIR__ . '/../../includes/functions.php';
});

beforeEach(function () {
	$GLOBALS['__stub_overrides']  = array();
	$GLOBALS['__test_db_calls']   = array();
	$GLOBALS['__rc_diff_tmpdirs'] = array();
});

// Remove every temporary directory a test created, even when an assertion
// failed before the test reached its own cleanup.
afterEach(function () {
	foreach ($GLOBALS['__rc_diff_tmpdirs'] as $dir) {
		foreach (glob($dir . '/{,.}*', GLOB_BRACE) ?: array() as $path) {
			if (is_link($path) || is_file($path)) {
				unlink($path);
			}
		}

		if (is_dir($dir)) {
			rmdir($dir);
		}
	}
});

/**
 * Creates a temporary directory holding the given files, registered for
 * removal after the test.
 *
 * @param array<string, string> $files Map of file name to contents.
 *
 * @return string The directory path.
 */
function rc_diff_tmpdir(array $files = array()): string {
	$dir = sys_get_temp_dir() . '/rc-diff-' . uniqid();
	mkdir($dir, 0777, true);
	$GLOBALS['__rc_diff_tmpdirs'][] = $dir;

	foreach ($files as $name => $contents) {
		file_put_contents($dir . '/' . $name, $contents);
	}

	return $dir;
}

/**
 * Makes the backups query return one row per given file name, newest
 * first, all stored in the given directory.
 *
 * @param string $dir      The backup directory.
 * @param string ...$names The backup file names, newest first.
 *
 * @return void
 */
function rc_diff_stub_backups(string $dir, string ...$names): void {
	$rows = array_map(fn ($name) => ['directory' => $dir, 'filename' => $name], $names);

	$GLOBALS['__stub_overrides']['db_fetch_assoc_prepared'] = fn ($sql, $params) => $rows;
}

it('treats a NUL byte as binary and everything else as text', function () {
	expect(plugin_routerconfigs_is_text_config("hostname router\ninterface eth0"))->toBeTrue();
	expect(plugin_routerconfigs_is_text_config("binary\x00data"))->toBeFalse();
	expect(plugin_routerconfigs_is_text_config(null))->toBeTrue();
});

it('parses exclude patterns one per line, dropping blanks', function () {
	$patterns = plugin_routerconfigs_parse_exclude_patterns("^! Time:.*\n\n  serial .*  \n");

	expect($patterns)->toBe(['^! Time:.*', 'serial .*']);
});

it('filters out lines matching exclude patterns and ignores bad patterns', function () {
	$lines = ['hostname r1', '! Time: 10:00:00', 'serial ABC123', 'interface eth0'];

	$filtered = plugin_routerconfigs_diff_exclude_lines($lines, ['^! Time:', '^serial ']);
	expect($filtered)->toBe(['hostname r1', 'interface eth0']);

	// Empty patterns leave the lines untouched (re-indexed).
	expect(plugin_routerconfigs_diff_exclude_lines($lines, []))->toBe($lines);

	// An invalid regex is ignored rather than fatal.
	$safe = plugin_routerconfigs_diff_exclude_lines(['a', 'b'], ['(']);
	expect($safe)->toBe(['a', 'b']);
});

it('renders a unified diff only when the configs differ', function () {
	$a = ['hostname r1', 'interface eth0', 'ip address 10.0.0.1'];
	$b = ['hostname r1', 'interface eth0', 'ip address 10.0.0.2'];

	expect(plugin_routerconfigs_config_unified_diff($a, $a))->toBe('');

	// A context below 1 is clamped to the default of 3.
	$diff = plugin_routerconfigs_config_unified_diff($a, $b, 0);
	expect($diff)->not->toBe('');
	expect($diff)->toContain('10.0.0.2');
});

it('rejects a backup file that symlinks outside its directory', function () {
	$base    = rc_diff_tmpdir();
	$outside = rc_diff_tmpdir(['secret.cfg' => "secret\n"]);

	if (!@symlink($outside . '/secret.cfg', $base . '/link.cfg')) {
		$this->markTestSkipped('symlink() is not available');

		return;
	}

	// realpath() follows the symlink out of $base, so the containment check
	// rejects it.
	expect(plugin_routerconfigs_read_backup_file(['directory' => $base, 'filename' => 'link.cfg']))->toBeFalse();
});

it('reads a backup file and rejects a path escaping its directory', function () {
	$dir = rc_diff_tmpdir(['config.cfg' => "line1\nline2\n"]);

	expect(plugin_routerconfigs_read_backup_file(['directory' => $dir, 'filename' => 'config.cfg']))
		->toBe("line1\nline2\n");

	// Missing keys.
	expect(plugin_routerconfigs_read_backup_file(['directory' => $dir]))->toBeFalse();

	// basename() strips traversal, so a traversal filename resolves to a
	// non-existent file inside the directory and returns false.
	expect(plugin_routerconfigs_read_backup_file(['directory' => $dir, 'filename' => '../../etc/passwd']))->toBeFalse();
});

it('diffs the two most recent backups, skipping when fewer than two exist', function () {
	$dir = rc_diff_tmpdir([
		'new.cfg' => "hostname r1\nip address 10.0.0.2\n",
		'old.cfg' => "hostname r1\nip address 10.0.0.1\n",
	]);
	rc_diff_stub_backups($dir, 'new.cfg', 'old.cfg');

	$diff = plugin_routerconfigs_device_config_diff(5, [], 3);
	expect($diff)->toContain('10.0.0.2');

	// Fewer than two backups -> no diff.
	rc_diff_stub_backups($dir, 'new.cfg');
	expect(plugin_routerconfigs_device_config_diff(5, [], 3))->toBe('');
});

it('returns no diff when a backup is binary', function () {
	$dir = rc_diff_tmpdir([
		'new.cfg' => "text\x00binary\n",
		'old.cfg' => "hostname r1\n",
	]);
	rc_diff_stub_backups($dir, 'new.cfg', 'old.cfg');

	expect(plugin_routerconfigs_device_config_diff(5, [], 3))->toBe('');
});

it('combines the configured email list with a Thold notification list', function () {
	$GLOBALS['__stub_overrides']['read_config_option'] = function ($name, $force = false) {
		if ($name === 'routerconfigs_email') {
			return 'ops@example.com';
		}

		if ($name === 'routerconfigs_notification_list') {
			return '7';
		}

		return '';
	};
	$GLOBALS['__stub_overrides']['db_table_exists']      = fn ($t) => true;
	$GLOBALS['__stub_overrides']['db_fetch_cell_prepared'] = fn ($sql, $params) => 'neteng@example.com';

	expect(plugin_routerconfigs_notification_recipients())->toBe('ops@example.com,neteng@example.com');
});

it('returns no diff when a backup file cannot be read', function () {
	// Both backup rows point at files that do not exist on disk, so
	// plugin_routerconfigs_read_backup_file() returns false.
	rc_diff_stub_backups(rc_diff_tmpdir(), 'missing-new.cfg', 'missing-old.cfg');

	expect(plugin_routerconfigs_device_config_diff(5, [], 3))->toBe('');
});

it('returns just the email list when no Thold list is selected', function () {
	$GLOBALS['__stub_overrides']['read_config_option'] = function ($name, $force = false) {
		return $name === 'routerconfigs_email' ? 'ops@example.com' : '';
	};
	$GLOBALS['__stub_overrides']['db_table_exists'] = fn ($t) => false;

	expect(plugin_routerconfigs_notification_recipients())->toBe('ops@example.com');
});
