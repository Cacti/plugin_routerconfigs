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
	$GLOBALS['__stub_overrides'] = array();
	$GLOBALS['__test_db_calls']  = array();
});

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
	$base    = sys_get_temp_dir() . '/rc-esc-' . uniqid();
	$outside = sys_get_temp_dir() . '/rc-out-' . uniqid();
	mkdir($base, 0777, true);
	mkdir($outside, 0777, true);
	file_put_contents($outside . '/secret.cfg', "secret\n");

	if (!@symlink($outside . '/secret.cfg', $base . '/link.cfg')) {
		unlink($outside . '/secret.cfg');
		rmdir($base);
		rmdir($outside);
		$this->markTestSkipped('symlink() is not available');

		return;
	}

	// realpath() follows the symlink out of $base, so the containment check
	// rejects it.
	expect(plugin_routerconfigs_read_backup_file(['directory' => $base, 'filename' => 'link.cfg']))->toBeFalse();

	unlink($base . '/link.cfg');
	unlink($outside . '/secret.cfg');
	rmdir($base);
	rmdir($outside);
});

it('reads a backup file and rejects a path escaping its directory', function () {
	$dir  = sys_get_temp_dir() . '/rc-diff-' . uniqid();
	mkdir($dir, 0777, true);
	file_put_contents($dir . '/config.cfg', "line1\nline2\n");

	expect(plugin_routerconfigs_read_backup_file(['directory' => $dir, 'filename' => 'config.cfg']))
		->toBe("line1\nline2\n");

	// Missing keys.
	expect(plugin_routerconfigs_read_backup_file(['directory' => $dir]))->toBeFalse();

	// basename() strips traversal, so a traversal filename resolves to a
	// non-existent file inside the directory and returns false.
	expect(plugin_routerconfigs_read_backup_file(['directory' => $dir, 'filename' => '../../etc/passwd']))->toBeFalse();

	unlink($dir . '/config.cfg');
	rmdir($dir);
});

it('diffs the two most recent backups, skipping when fewer than two exist', function () {
	$dir = sys_get_temp_dir() . '/rc-diff2-' . uniqid();
	mkdir($dir, 0777, true);
	file_put_contents($dir . '/new.cfg', "hostname r1\nip address 10.0.0.2\n");
	file_put_contents($dir . '/old.cfg', "hostname r1\nip address 10.0.0.1\n");

	$GLOBALS['__stub_overrides']['db_fetch_assoc_prepared'] = fn ($sql, $params) => [
		['directory' => $dir, 'filename' => 'new.cfg'],
		['directory' => $dir, 'filename' => 'old.cfg'],
	];

	$diff = plugin_routerconfigs_device_config_diff(5, [], 3);
	expect($diff)->toContain('10.0.0.2');

	// Fewer than two backups -> no diff.
	$GLOBALS['__stub_overrides']['db_fetch_assoc_prepared'] = fn ($sql, $params) => [
		['directory' => $dir, 'filename' => 'new.cfg'],
	];
	expect(plugin_routerconfigs_device_config_diff(5, [], 3))->toBe('');

	unlink($dir . '/new.cfg');
	unlink($dir . '/old.cfg');
	rmdir($dir);
});

it('returns no diff when a backup is binary', function () {
	$dir = sys_get_temp_dir() . '/rc-diff3-' . uniqid();
	mkdir($dir, 0777, true);
	file_put_contents($dir . '/new.cfg', "text\x00binary\n");
	file_put_contents($dir . '/old.cfg', "hostname r1\n");

	$GLOBALS['__stub_overrides']['db_fetch_assoc_prepared'] = fn ($sql, $params) => [
		['directory' => $dir, 'filename' => 'new.cfg'],
		['directory' => $dir, 'filename' => 'old.cfg'],
	];

	expect(plugin_routerconfigs_device_config_diff(5, [], 3))->toBe('');

	unlink($dir . '/new.cfg');
	unlink($dir . '/old.cfg');
	rmdir($dir);
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
	$dir = sys_get_temp_dir() . '/rc-diff5-' . uniqid();
	mkdir($dir, 0777, true);

	// Both backup rows point at files that do not exist on disk, so
	// plugin_routerconfigs_read_backup_file() returns false.
	$GLOBALS['__stub_overrides']['db_fetch_assoc_prepared'] = fn ($sql, $params) => [
		['directory' => $dir, 'filename' => 'missing-new.cfg'],
		['directory' => $dir, 'filename' => 'missing-old.cfg'],
	];

	expect(plugin_routerconfigs_device_config_diff(5, [], 3))->toBe('');

	rmdir($dir);
});

it('returns just the email list when no Thold list is selected', function () {
	$GLOBALS['__stub_overrides']['read_config_option'] = function ($name, $force = false) {
		return $name === 'routerconfigs_email' ? 'ops@example.com' : '';
	};
	$GLOBALS['__stub_overrides']['db_table_exists'] = fn ($t) => false;

	expect(plugin_routerconfigs_notification_recipients())->toBe('ops@example.com');
});
