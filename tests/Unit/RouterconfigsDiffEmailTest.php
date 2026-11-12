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

it('keeps an exclude pattern that already escapes a slash', function () {
	$lines = ['interface Gi1/0/1', 'description uplink', 'interface Gi1/0/2'];

	// '\/' used to become '\\/' and the pattern was silently dropped.
	expect(plugin_routerconfigs_diff_exclude_lines($lines, ['Gi1\\/0\\/1']))->toBe(['description uplink', 'interface Gi1/0/2']);
	expect(plugin_routerconfigs_diff_exclude_lines($lines, ['Gi1/0/2$']))->toBe(['interface Gi1/0/1', 'description uplink']);

	// Unanchored patterns match anywhere in the line.
	expect(plugin_routerconfigs_diff_exclude_lines($lines, ['uplink']))->toBe(['interface Gi1/0/1', 'interface Gi1/0/2']);
});

it('ignores the volatile lines that change on every backup', function () {
	$dir = rc_diff_tmpdir([
		'new.cfg' => "Current configuration : 1234 bytes\n! Last configuration change at 10:00:00 UTC Tue Nov 11 2026 by admin\n! NVRAM config last updated at 10:01:00 UTC Tue Nov 11 2026\nntp clock-period 17179812\n!Time: Tue Nov 11 10:02:00 2026\n!Running configuration last done at: Tue Nov 11 10:00:00 2026\nhostname r1\n",
		'old.cfg' => "Current configuration : 1200 bytes\n! Last configuration change at 09:00:00 UTC Mon Nov 10 2026 by admin\n! NVRAM config last updated at 09:01:00 UTC Mon Nov 10 2026\nntp clock-period 17179870\n!Time: Mon Nov 10 09:02:00 2026\n!Running configuration last done at: Mon Nov 10 09:00:00 2026\nhostname r1\n",
	]);
	rc_diff_stub_backups($dir, 'new.cfg', 'old.cfg');

	expect(plugin_routerconfigs_device_config_diff(5, [], 3))->toBe('');
	expect(plugin_routerconfigs_diff_volatile_patterns())->toContain('^ntp clock-period ');
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
	// Thold creates plugin_notification_lists (plugin_thold includes/database.php).
	$GLOBALS['__stub_overrides']['db_table_exists']        = fn ($t) => $t === 'plugin_notification_lists';
	$GLOBALS['__stub_overrides']['db_fetch_cell_prepared'] = function ($sql, $params) {
		expect($sql)->toContain('FROM plugin_notification_lists');
		expect($params)->toBe([7]);

		return 'neteng@example.com';
	};

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

it('hides secret values to the end of the line but keeps the line', function (string $line, string $expected) {
	expect(plugin_routerconfigs_redact_secret_line($line))->toBe($expected);
})->with([
	'enable secret type 9'      => ['enable secret 9 PLACEHOLDER-HASH', 'enable secret 9 <secret hidden>'],
	'enable password level'     => ['enable password level 7 0 placeholder words', 'enable password <secret hidden>'],
	'username secret type 5'    => ['username admin privilege 15 secret 5 PLACEHOLDER-HASH', 'username admin privilege 15 secret 5 <secret hidden>'],
	'username password type 7'  => ['username ops password 7 PLACEHOLDER', 'username ops password 7 <secret hidden>'],
	'line password'             => [' password 7 PLACEHOLDER', ' password 7 <secret hidden>'],
	'community with spaces'     => ['snmp-server community my placeholder words RO 10', 'snmp-server community <secret hidden>'],
	'snmp trap host community'  => ['snmp-server host 192.0.2.1 version 2c PLACEHOLDER udp-port 162', 'snmp-server host 192.0.2.1 version 2c <secret hidden>'],
	'snmp v3 auth and priv'     => ['snmp-server user v3user v3group v3 auth sha PLACEHOLDER-A priv aes 128 PLACEHOLDER-P', 'snmp-server user v3user v3group v3 auth sha <secret hidden>'],
	'tacacs key with spaces'    => ['tacacs-server key 7 PLACEHOLDER more words', 'tacacs-server key 7 <secret hidden>'],
	'nxos radius quoted key'    => ['radius-server host 192.0.2.5 key 7 "PLACEHOLDER" authentication accounting', 'radius-server host 192.0.2.5 key 7 <secret hidden> authentication accounting'],
	'server block key'          => [' key 7 PLACEHOLDER', ' key 7 <secret hidden>'],
	'radius server-key'         => [' client 192.0.2.9 server-key 7 PLACEHOLDER', ' client 192.0.2.9 server-key 7 <secret hidden>'],
	'key chain key-string'      => ['  key-string 7 PLACEHOLDER', '  key-string 7 <secret hidden>'],
	'ospf md5 key'              => [' ip ospf message-digest-key 1 md5 7 PLACEHOLDER', ' ip ospf message-digest-key 1 <secret hidden>'],
	'ospf authentication-key'   => [' ip ospf authentication-key 7 PLACEHOLDER', ' ip ospf authentication-key 7 <secret hidden>'],
	'bgp neighbor password'     => [' neighbor 192.0.2.2 password 7 PLACEHOLDER', ' neighbor 192.0.2.2 password 7 <secret hidden>'],
	'hsrp md5 key-string'       => [' standby 1 authentication md5 key-string 7 PLACEHOLDER timeout 30', ' standby 1 authentication md5 <secret hidden>'],
	'hsrp plain text'           => [' standby 1 authentication PLACEHOLDER', ' standby 1 authentication <secret hidden>'],
	'vrrp text'                 => [' vrrp 1 authentication text PLACEHOLDER', ' vrrp 1 authentication text <secret hidden>'],
	'isis domain-password'      => [' domain-password PLACEHOLDER authenticate snp validate', ' domain-password <secret hidden>'],
	'isakmp psk'                => ['crypto isakmp key PLACEHOLDER address 192.0.2.7', 'crypto isakmp key <secret hidden>'],
	'ikev2 pre-shared-key'      => ['  pre-shared-key local PLACEHOLDER', '  pre-shared-key <secret hidden>'],
	'ppp chap password'         => [' ppp chap password 7 PLACEHOLDER', ' ppp chap password 7 <secret hidden>'],
	'wpa-psk inside a token'    => [' wpa-psk ascii 7 PLACEHOLDER', ' wpa-psk <secret hidden>'],
	'fortios set passwd'        => ['    set passwd ENC PLACEHOLDER', '    set passwd ENC <secret hidden>'],
	'passphrase'                => ['  set passphrase PLACEHOLDER words', '  set passphrase <secret hidden>'],
	'junos quoted, tail kept'   => ['authentication-key "PLACEHOLDER"; ## SECRET-DATA', 'authentication-key <secret hidden>; ## SECRET-DATA'],
	'certificate hex row'       => ['  30820122 300D0609 2A864886 F70D0101', '  <secret hidden>'],
	'base64 row'                => ['  QUJDREVGR0hJSktMTU5PUFFSU1RVVldYWVowMTIzNDU2Nzg5', '  <secret hidden>'],
	// Accepted over-redaction: the keyword rule cannot tell these apart.
	'over-redacts description'  => ['  description key account', '  description key <secret hidden>'],
	'over-redacts crypto key'   => ['crypto key generate rsa modulus 2048', 'crypto key <secret hidden>'],
	// Nothing follows the keyword, so nothing is hidden.
	'password-encryption kept'  => ['service password-encryption', 'service password-encryption'],
	'ordinary line unchanged'   => ['interface GigabitEthernet0/1', 'interface GigabitEthernet0/1'],
	'snmp v3 host is a user'    => ['snmp-server host 192.0.2.1 version 3 priv v3user', 'snmp-server host 192.0.2.1 version 3 priv v3user'],
	'hsrp md5 does not count'   => [' standby 1 authentication md5', ' standby 1 authentication md5'],
]);

it('hides every line of a multi-line secret', function (array $lines, array $expected) {
	expect(plugin_routerconfigs_redact_lines($lines))->toBe($expected);
})->with([
	'pem private key' => [
		['ssl key', '-----BEGIN RSA PRIVATE KEY-----', 'PLACEHOLDER line one', 'PLACEHOLDER line two', '-----END RSA PRIVATE KEY-----', 'hostname r1'],
		['ssl key', '-----BEGIN RSA PRIVATE KEY-----', '<secret hidden>', '<secret hidden>', '-----END RSA PRIVATE KEY-----', 'hostname r1'],
	],
	'cisco certificate chain' => [
		['crypto pki certificate chain TP-self', ' certificate self-signed 01', '  PLACEHOLDER row one', '  PLACEHOLDER row two', '  quit', 'hostname r1'],
		['crypto pki certificate chain TP-self', ' certificate self-signed 01', '  <secret hidden>', '  <secret hidden>', '  quit', 'hostname r1'],
	],
	'cisco key-string block' => [
		['  key-string', '   PLACEHOLDER row', '  quit', ' hostname r1'],
		['  key-string', '   <secret hidden>', '  quit', ' hostname r1'],
	],
	'fortios quoted multi-line value' => [
		['    set private-key "-----BEGIN ENCRYPTED PRIVATE KEY-----', 'PLACEHOLDER line', '-----END ENCRYPTED PRIVATE KEY-----"', '    next'],
		['    set private-key <secret hidden>', '<secret hidden>', '<secret hidden>', '    next'],
	],
]);

it('hides a multi-line secret inside a diff hunk and keeps the markers', function () {
	$diff = "@@ -1,4 +1,4 @@\n hostname r1\n-    set private-key \"PLACEHOLDER-OLD\n-PLACEHOLDER-OLD-2\"\n+    set private-key \"PLACEHOLDER-NEW\n+PLACEHOLDER-NEW-2\"\n interface Gi1";

	$redacted = plugin_routerconfigs_redact_diff($diff);

	expect($redacted)->not->toContain('PLACEHOLDER');
	expect($redacted)->toBe("@@ -1,4 +1,4 @@\n hostname r1\n-    set private-key <secret hidden>\n-<secret hidden>\n+    set private-key <secret hidden>\n+<secret hidden>\n interface Gi1");
});

it('hides secrets in an emailed diff unless they are included on purpose', function () {
	$dir = rc_diff_tmpdir([
		'new.cfg' => "hostname r1\nenable secret 9 NEWHASH-PLACEHOLDER\nsnmp-server community NEWCOMM-PLACEHOLDER RO\n",
		'old.cfg' => "hostname r1\nenable secret 9 OLDHASH-PLACEHOLDER\nsnmp-server community OLDCOMM-PLACEHOLDER RO\n",
	]);
	rc_diff_stub_backups($dir, 'new.cfg', 'old.cfg');

	$diff = plugin_routerconfigs_device_config_diff(5, [], 3);

	// The change is still visible as a -/+ pair, without either value.
	expect($diff)->toContain('-enable secret 9 <secret hidden>');
	expect($diff)->toContain('+enable secret 9 <secret hidden>');
	expect($diff)->toContain('+snmp-server community <secret hidden>');
	expect($diff)->toContain(' hostname r1');
	expect($diff)->not->toContain('NEWHASH');
	expect($diff)->not->toContain('OLDHASH');
	expect($diff)->not->toContain('COMM-PLACEHOLDER');

	expect(plugin_routerconfigs_device_config_diff(5, [], 3, false))->toContain('NEWHASH');
});
