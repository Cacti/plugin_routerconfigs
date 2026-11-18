<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * The device hostname is used as a backup file name, so it must be a single
 * plain path component, and it is printed in HTML, so it must be escaped.
 */

require_once __DIR__ . '/../../includes/functions.php';

beforeEach(function () {
	$GLOBALS['__stub_overrides'] = array();
	$GLOBALS['__test_db_calls']  = array();
});

it('accepts ordinary hostnames and addresses', function (string $name) {
	expect(plugin_routerconfigs_valid_hostname($name))->toBeTrue();
	expect(plugin_routerconfigs_backup_filename($name))->toBe($name);
})->with(['edge1', 'core-sw.example.net', '192.0.2.10', 'fe80::1', 'Core Router_1']);

it('rejects path separators, traversal, NUL and other characters', function (string $name) {
	expect(plugin_routerconfigs_valid_hostname($name))->toBeFalse();
})->with(['../victim', 'a/b', 'a\\b', '..', '.hidden', '', "a\0b", 'a..b', "a\nb", '<b>x</b>', "x'y"]);

it('refuses to use a stored hostname that leaves the backup directory', function (string $name) {
	expect(plugin_routerconfigs_backup_filename($name))->toBeFalse();
})->with(['../victim', 'sub/victim', '..', '.', "a\0b", 'a\\b', '']);

it('does not delete a file outside the incoming directory for a traversal hostname', function () {
	$base = sys_get_temp_dir() . '/rc_host_' . uniqid();
	mkdir($base . '/incoming', 0770, true);
	mkdir($base . '/archive', 0770, true);
	file_put_contents($base . '/victim', 'keep me');

	$GLOBALS['__stub_overrides']['read_config_option'] = fn ($name) => [
		'routerconfigs_backup_path'  => $base . '/incoming',
		'routerconfigs_archive_path' => $base . '/archive',
		'routerconfigs_retry'        => 1,
	][$name] ?? '';
	$GLOBALS['__stub_overrides']['db_fetch_row_prepared'] = fn ($sql) => str_contains($sql, 'plugin_routerconfigs_accounts') ? ['username' => 'fixture'] : [];

	$device = ['id' => 1, 'hostname' => '../victim', 'directory' => '', 'ipaddress' => '192.0.2.1', 'devicetype' => 1,
		'timeout' => 1, 'sleep' => 1, 'connecttype' => 'ssh', 'elevated' => '', 'debug' => ''];

	try {
		expect(plugin_routerconfigs_download_config($device, time()))->toBeFalse();
		expect(file_exists($base . '/victim'))->toBeTrue();
	} finally {
		@unlink($base . '/victim');
		@rmdir($base . '/incoming');
		@rmdir($base . '/archive');
		@rmdir($base);
	}
});

it('escapes hostname, error and file name in the backup report email', function () {
	$message = '';
	plugin_routerconfigs_message_devicetable($message, [
		['hostname' => '<i>h</i>', 'lasterror' => '<b>e</b>', 'lastfile' => '<u>f</u>'],
	], true);

	expect($message)->toContain('&lt;i&gt;h&lt;/i&gt;', '&lt;b&gt;e&lt;/b&gt;');
	expect($message)->not->toContain('<i>h</i>', '<b>e</b>');
});

it('validates the hostname on save and escapes it in the edit header', function () {
	$src = file_get_contents(__DIR__ . '/../../router-devices.php');

	expect($src)->toContain("plugin_routerconfigs_valid_hostname(\$save['hostname'])");
	expect($src)->toContain("html_escape(\$account['hostname'])");
	expect($src)->toContain('html_escape(db_fetch_cell_prepared(\'SELECT hostname');
});
