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
	$GLOBALS['__stub_overrides']['db_table_exists']       = fn ($t) => $t === 'plugin_notification_lists';
	$GLOBALS['__stub_overrides']['db_column_exists']      = fn ($t, $c) => true;
	$GLOBALS['__stub_overrides']['db_fetch_row_prepared'] = function ($sql, $params) {
		expect($sql)->toContain('FROM plugin_notification_lists');
		expect($params)->toBe([7]);

		return ['emails' => 'neteng@example.com', 'bcc_emails' => 'audit@example.com', 'enabled' => 'on'];
	};

	expect(plugin_routerconfigs_notification_recipients())->toBe(['to' => 'ops@example.com,neteng@example.com', 'bcc' => 'audit@example.com']);
});

it('sends nothing to a disabled Thold notification list', function () {
	$GLOBALS['__stub_overrides']['read_config_option'] = function ($name, $force = false) {
		return ['routerconfigs_email' => 'ops@example.com', 'routerconfigs_notification_list' => '7'][$name] ?? '';
	};
	$GLOBALS['__stub_overrides']['db_table_exists']       = fn ($t) => $t === 'plugin_notification_lists';
	$GLOBALS['__stub_overrides']['db_column_exists']      = fn ($t, $c) => true;
	$GLOBALS['__stub_overrides']['db_fetch_row_prepared'] = fn ($sql, $params) => ['emails' => 'neteng@example.com', 'bcc_emails' => 'audit@example.com', 'enabled' => ''];

	expect(plugin_routerconfigs_notification_recipients())->toBe(['to' => 'ops@example.com', 'bcc' => '']);
});

it('reads a Thold notification list that predates the Enabled and Bcc fields', function () {
	$GLOBALS['__stub_overrides']['read_config_option'] = function ($name, $force = false) {
		return $name === 'routerconfigs_notification_list' ? '7' : '';
	};
	$GLOBALS['__stub_overrides']['db_table_exists']       = fn ($t) => $t === 'plugin_notification_lists';
	$GLOBALS['__stub_overrides']['db_column_exists']      = fn ($t, $c) => false;
	$GLOBALS['__stub_overrides']['db_fetch_row_prepared'] = function ($sql, $params) {
		expect($sql)->not->toContain('enabled')->and($sql)->not->toContain('bcc_emails');

		return ['emails' => 'neteng@example.com'];
	};

	expect(plugin_routerconfigs_notification_recipients())->toBe(['to' => 'neteng@example.com', 'bcc' => '']);
});

it('sends to a Bcc-only Thold notification list', function () {
	$GLOBALS['__stub_overrides']['read_config_option'] = function ($name, $force = false) {
		return $name === 'routerconfigs_notification_list' ? '7' : '';
	};
	$GLOBALS['__stub_overrides']['db_table_exists']       = fn ($t) => $t === 'plugin_notification_lists';
	$GLOBALS['__stub_overrides']['db_column_exists']      = fn ($t, $c) => true;
	$GLOBALS['__stub_overrides']['db_fetch_row_prepared'] = fn ($sql, $params) => ['emails' => '', 'bcc_emails' => 'audit@example.com', 'enabled' => 'on'];

	expect(plugin_routerconfigs_notification_recipients())->toBe(['to' => '', 'bcc' => 'audit@example.com']);
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

	expect(plugin_routerconfigs_notification_recipients())->toBe(['to' => 'ops@example.com', 'bcc' => '']);
});

/**
 * Writes two backups and returns the emailed (redacted) diff between them.
 *
 * @param array<int, string> $old The previous configuration lines.
 * @param array<int, string> $new The new configuration lines.
 * @param int                $context The number of context lines.
 *
 * @return string The diff text.
 */
function rc_redacted_diff(array $old, array $new, int $context = 3): string {
	$dir = rc_diff_tmpdir([
		'new.cfg' => implode("\n", $new) . "\n",
		'old.cfg' => implode("\n", $old) . "\n",
	]);
	rc_diff_stub_backups($dir, 'new.cfg', 'old.cfg');

	return plugin_routerconfigs_device_config_diff(5, [], $context);
}

it('shows a changed secret as a changed line without either value', function (array $lines) {
	$old = array_merge(['hostname r1'], str_replace('%V', 'OLD', $lines), ['end']);
	$new = array_merge(['hostname r1'], str_replace('%V', 'NEW', $lines), ['end']);

	$diff = rc_redacted_diff($old, $new);

	// The raw lines differ, so there is a hunk, but no value is printed.
	expect($diff)->toContain('@@');
	expect($diff)->not->toContain('PLACEHOLDER');
	expect($diff)->not->toContain('PLACE\\"HOLDER');
	expect($diff)->toContain('<secret hidden>');
})->with([
	'key=value'               => [['password=PLACEHOLDER-%V']],
	'key:value'               => [['  secret:PLACEHOLDER-%V']],
	'json pair'               => [['  "password":"PLACEHOLDER-%V",']],
	'quoted keyword'          => [['    set "psk" "PLACEHOLDER-%V"']],
	'escaped quote'           => [['    set psk "PLACE\"HOLDER-%V"']],
	'unterminated quote'      => [['    set psk "PLACEHOLDER-%V', 'hostname r2']],
	'value with spaces'       => [['snmp-server community PLACEHOLDER %V words RO 10']],
	'type and value'          => [['username admin privilege 15 secret 9 PLACEHOLDER-%V']],
	'snmp trap host'          => [['snmp-server host 192.0.2.1 version 2c PLACEHOLDER-%V']],
	'snmp v3 user'            => [['snmp-server user v3 g v3 auth sha PLACEHOLDER-%V priv aes 128 PLACEHOLDER-%V']],
	'hpe snmpv3 user'         => [['snmpv3 user "ops" auth sha "PLACEHOLDER-%V" priv aes "PLACEHOLDER-%V"']],
	'hsrp text'               => [[' standby 1 authentication PLACEHOLDER-%V']],
	'vrrp text'               => [[' vrrp 1 authentication text PLACEHOLDER-%V']],
	'nhrp'                    => [[' ip nhrp authentication PLACEHOLDER-%V']],
	'ospf md5'                => [[' ip ospf message-digest-key 1 md5 7 PLACEHOLDER-%V']],
	'tacacs key'              => [[' key 7 PLACEHOLDER-%V']],
	'fortios pwd'             => [['    set pwd PLACEHOLDER-%V']],
	'certificate chain' => [[
		'crypto pki certificate chain TP-self',
		' certificate self-signed 01',
		'  PLACEHOLDER-%V',
		'  AB',
		'  quit',
	]],
	'pubkey key-string' => [[
		'crypto key pubkey-chain rsa',
		' named-key peer',
		'  key-string',
		'   PLACEHOLDER-%V',
		'  quit',
	]],
	'pem block' => [[
		'ssl key',
		'-----BEGIN RSA PRIVATE KEY-----',
		'PLACEHOLDER-%V',
		'-----END RSA PRIVATE KEY-----',
	]],
	'fortios quoted pem' => [[
		'    set private-key "-----BEGIN ENCRYPTED PRIVATE KEY-----',
		'PLACEHOLDER-%V',
		'-----END ENCRYPTED PRIVATE KEY-----"',
		'    next',
	]],
	'quote closed by a later line' => [[
		'    set psk "PLACEHOLDER-%V',
		'PLACEHOLDER-%V" set passwd PLACEHOLDER-%V',
	]],
]);

it('marks a changed password and keeps the rest of the diff readable', function () {
	$diff = rc_redacted_diff(
		['hostname r1', 'username admin secret 9 PLACEHOLDER-OLD', 'interface Gi1'],
		['hostname r2', 'username admin secret 9 PLACEHOLDER-NEW', 'interface Gi1'],
	);

	expect($diff)->toBe("@@ -1,3 +1,3 @@\n-hostname r1\n-username <secret hidden>\n+hostname r2\n+username <secret hidden> (changed)\n interface Gi1\n");
});

it('renders a diff without secrets unchanged', function () {
	$diff = rc_redacted_diff(
		['hostname r1', 'interface Gi1', ' ip address 192.0.2.1 255.255.255.0'],
		['hostname r1', 'interface Gi1', ' ip address 192.0.2.2 255.255.255.0'],
	);

	expect($diff)->toBe("@@ -1,3 +1,3 @@\n hostname r1\n interface Gi1\n- ip address 192.0.2.1 255.255.255.0\n+ ip address 192.0.2.2 255.255.255.0\n");
});

it('still shows changes after a block that never closes, hidden, with a note', function () {
	$diff = rc_redacted_diff(
		['hostname r1', 'interface Gi1', ' ip address 192.0.2.1 255.255.255.0'],
		['hostname r1', ' description "PLACEHOLDER', 'interface Gi1', ' ip address 192.0.2.2 255.255.255.0'],
	);

	expect($diff)->toStartWith("Redaction block on line 2 of the new backup never closed; following lines hidden.\n@@");
	expect($diff)->toContain("+ description <secret hidden>\n");
	expect($diff)->toContain("- ip address 192.0.2.1 255.255.255.0\n");
	expect($diff)->toContain("+ <secret hidden>\n");
	expect($diff)->not->toContain('PLACEHOLDER');
	expect($diff)->not->toContain('192.0.2.2');
});

it('notes an unclosed block in the previous backup too', function () {
	$diff = rc_redacted_diff(
		['hostname r1', '-----BEGIN CERTIFICATE-----', 'PLACEHOLDER'],
		['hostname r2'],
	);

	expect($diff)->toStartWith("Redaction block on line 2 of the previous backup never closed; following lines hidden.\n@@");
	expect($diff)->not->toContain('PLACEHOLDER');
});

it('hides a block whose diff hunk starts in the middle of it', function () {
	$old = ['crypto pki certificate chain TP-self', ' certificate self-signed 01'];

	for ($i = 1; $i <= 30; $i++) {
		$old[] = sprintf('  PLACEHOLDER-ROW-%02d', $i);
	}

	$old[]   = '  quit';
	$old[]   = 'hostname r1';
	$new     = $old;
	$new[20] = '  PLACEHOLDER-ROW-CHANGED';

	$diff = rc_redacted_diff($old, $new);

	// The hunk starts 17 lines into the block and still hides every row.
	expect($diff)->toStartWith('@@ -18,7 +18,7 @@');
	expect($diff)->toContain("+  <secret hidden> (changed)\n");
	expect($diff)->not->toContain('PLACEHOLDER');
});

it('reduces secret-like lines to their first word and opens blocks only in their context', function (array $lines, array $expected) {
	expect(plugin_routerconfigs_redact_lines($lines))->toBe($expected);
})->with([
	// Accepted over-redaction: any secret-like word reduces the line.
	'description mentioning a key' => [['  description key account'], ['  description <secret hidden>']],
	'aaa method list'              => [['aaa authentication login default local'], ['aaa <secret hidden>']],
	'no leading word'              => [['"token": "PLACEHOLDER"'], ['<secret hidden>']],
	'keyword alone opens a block'  => [['  key-string'], ['  key-string']],
	'ordinary line'                => [['interface Gi1', ' no shutdown'], ['interface Gi1', ' no shutdown']],
	'certificate under an unexpected parent' => [
		['interface Gi1', ' certificate self-signed 01', '  PLACEHOLDER', '  quit', 'hostname r1'],
		['interface Gi1', ' certificate <secret hidden>', '  <secret hidden>', '  quit', 'hostname r1'],
	],
	'key-string under an unexpected parent' => [
		['key chain KC', ' key 1', '  key-string', '   PLACEHOLDER', '  quit'],
		['key <secret hidden>', ' key <secret hidden>', '  key-string', '   <secret hidden>', '  quit'],
	],
	'stray hex row'                => [['interface Gi1', '  0A1B2C3D 4E5F6071 8293A4B5'], ['interface Gi1', '  <secret hidden>']],
	'stray base64 row'             => [['  UExBQ0VIT0xERVJQTEFDRUhPTERFUlBMQUNFSE9MREVS'], ['  <secret hidden>']],
	'single-quoted multi-line'     => [["set psk 'PLACEHOLDER-A", "PLACEHOLDER-B'", 'next'], ['set <secret hidden>', '<secret hidden>', 'next']],
	'pem closes and reopens on one line' => [
		['-----BEGIN X-----', 'PLACEHOLDER-A', '-----END X----- -----BEGIN Y-----', 'PLACEHOLDER-B', '-----END Y-----', 'next'],
		['<secret hidden>', '<secret hidden>', '<secret hidden>', '<secret hidden>', '-----END Y-----', 'next'],
	],
	'quote closes and another opens on one line' => [
		['set psk "PLACEHOLDER-A', "PLACEHOLDER-B\" 'start", 'PLACEHOLDER-C', "end'", 'next'],
		['set <secret hidden>', '<secret hidden>', '<secret hidden>', '<secret hidden>', 'next'],
	],
	'quit with other text stays hidden' => [
		[' certificate ca 01', '  PLACEHOLDER', '  quit PLACEHOLDER', '  quit', 'next'],
		[' certificate <secret hidden>', '  <secret hidden>', '  <secret hidden>', '  quit', 'next'],
	],
	'pem on one line'              => [['-----BEGIN X-----PLACEHOLDER-----END X-----', 'hostname r1'], ['<secret hidden>', 'hostname r1']],
]);

it('reports where an unclosed block opened', function () {
	plugin_routerconfigs_redact_lines(['a', 'b', 'set psk "PLACEHOLDER', 'c'], $unclosed);
	expect($unclosed)->toBe(3);

	plugin_routerconfigs_redact_lines(['a', 'set psk "PLACEHOLDER', 'c"'], $unclosed);
	expect($unclosed)->toBe(0);
});

it('groups Horde edits the same way as jfcherng', function (array $old, array $new, int $context) {
	require_once __DIR__ . '/../../includes/HordeText.php';

	$differ = new \Jfcherng\Diff\Differ($old, $new, ['context' => $context]);
	$horde  = new Horde_Text_Diff('Native', [$old, $new]);

	expect(plugin_routerconfigs_horde_hunks($horde->getDiff(), $context))->toBe(array_map('array_values', array_values($differ->getGroupedOpcodesGnu())));
})->with([
	'one change in the middle' => [range(1, 20), array_replace(range(1, 20), [9 => 'x']), 3],
	'two far-apart changes'    => [range(1, 40), array_replace(range(1, 40), [2 => 'x', 30 => 'y']), 3],
	'insert and delete'        => [range(1, 10), array_merge([0], range(1, 4), range(6, 10)), 2],
]);

it('has no Horde hunks without edits', function () {
	expect(plugin_routerconfigs_horde_hunks([], 3))->toBe([]);
});

it('leaves no placeholder secret anywhere in the diff of a realistic configuration', function () {
	$config = static function (string $v): array {
		return [
			'Current configuration : 4242 bytes',
			'! Last configuration change at 10:00:00 UTC ' . $v . ' by admin',
			'hostname core-' . $v,
			'enable secret 9 PLACEHOLDER-ENABLE-' . $v,
			'username admin privilege 15 secret 9 PLACEHOLDER-USER-' . $v,
			'username ops password 7 PLACEHOLDER-OPS-' . $v,
			'aaa authentication login default group tacacs+ local',
			'crypto pki certificate chain TP-self-signed-1',
			' certificate self-signed 01',
			'  PLACEHOLDER-CERT-A-' . $v,
			'  PLACEHOLDER-CERT-B-' . $v,
			'  quit',
			'crypto isakmp key PLACEHOLDER-PSK-' . $v . ' address 192.0.2.7',
			'key chain KC',
			' key 1',
			'  key-string 7 PLACEHOLDER-KC-' . $v,
			'crypto key pubkey-chain rsa',
			' named-key peer',
			'  key-string',
			'   PLACEHOLDER-PUB-' . $v,
			'  quit',
			'interface GigabitEthernet0/1',
			' description uplink ' . $v,
			' ip ospf message-digest-key 1 md5 7 PLACEHOLDER-OSPF-' . $v,
			' ip nhrp authentication PLACEHOLDER-NHRP-' . $v,
			' standby 1 authentication text PLACEHOLDER-HSRP-' . $v,
			'router bgp 65000',
			' neighbor 192.0.2.2 password 7 PLACEHOLDER-BGP-' . $v,
			'snmp-server community PLACEHOLDER-COMM-' . $v . ' RO',
			'snmp-server host 192.0.2.1 version 2c PLACEHOLDER-TRAP-' . $v,
			'snmp-server user v3 g v3 auth sha PLACEHOLDER-V3A-' . $v . ' priv aes 128 PLACEHOLDER-V3P-' . $v,
			'tacacs server T',
			' key 7 PLACEHOLDER-TAC-' . $v,
			'ssl key',
			'-----BEGIN RSA PRIVATE KEY-----',
			'PLACEHOLDER-PEM-' . $v,
			'-----END RSA PRIVATE KEY-----',
			'config system admin',
			'    set password=PLACEHOLDER-FGT-' . $v,
			'    set private-key "-----BEGIN ENCRYPTED PRIVATE KEY-----',
			'PLACEHOLDER-FGT-KEY-' . $v,
			'-----END ENCRYPTED PRIVATE KEY-----"',
			'    set psk "PLACE\"HOLDER-ESC-' . $v . '"',
			'    set "psk" "PLACEHOLDER-QK-' . $v . '"',
			'    set secret:PLACEHOLDER-COLON-' . $v,
			'end',
			'line vty 0 4',
			' password 7 PLACEHOLDER-VTY-' . $v,
			'end',
		];
	};

	// Every line of the configuration is redacted, and nothing is left
	// open at the end.
	$redacted = implode("\n", plugin_routerconfigs_redact_lines($config('A'), $unclosed));
	expect($redacted)->not->toContain('PLACEHOLDER');
	expect($redacted)->not->toContain('PLACE\\"HOLDER');
	expect($unclosed)->toBe(0);

	// Context 100 puts every remaining line in the diff as -, + or context.
	$diff = rc_redacted_diff($config('A'), $config('B'), 100);

	expect($diff)->toContain('-hostname core-A');
	expect($diff)->toContain('+hostname core-B');
	expect($diff)->toContain(' crypto pki certificate chain TP-self-signed-1');
	expect($diff)->toContain('+enable <secret hidden> (changed)');
	expect($diff)->not->toContain('Current configuration');
	expect($diff)->not->toContain('PLACEHOLDER');
	expect($diff)->not->toContain('PLACE\\"HOLDER');
});
