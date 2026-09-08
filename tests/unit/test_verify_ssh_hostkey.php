<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | Standalone: `php tests/unit/test_verify_ssh_hostkey.php`. Stubs the core |
 | data layer and exercises every branch of                                 |
 | plugin_routerconfigs_verify_ssh_hostkey().                               |
 +-------------------------------------------------------------------------+
*/

error_reporting(E_ALL);

foreach (['POLLER_VERBOSITY_NONE' => 0, 'POLLER_VERBOSITY_LOW' => 1, 'POLLER_VERBOSITY_MEDIUM' => 2, 'POLLER_VERBOSITY_HIGH' => 3, 'POLLER_VERBOSITY_DEBUG' => 4, 'POLLER_VERBOSITY_DEVDBG' => 5] as $k => $v) {
	if (!defined($k)) {
		define($k, $v);
	}
}

foreach (['SSH2_FINGERPRINT_SHA1' => 1, 'SSH2_FINGERPRINT_HEX' => 2] as $k => $v) {
	if (!defined($k)) {
		define($k, $v);
	}
}

function __($text, ...$args) {
	return $text;
}

function read_config_option($name, $default = false) {
	return $GLOBALS['t_opt'][$name] ?? $default;
}

function cacti_sizeof($value) {
	return is_countable($value) ? count($value) : 0;
}

function db_column_exists($table, $column) {
	return $GLOBALS['t_col'] ?? true;
}

function db_fetch_row_prepared($sql, $params = []) {
	return $GLOBALS['t_stored'] ?? null;
}

function db_execute_prepared($sql, $params = []) {
	$GLOBALS['t_updates'][] = $params;
	$result                 = $GLOBALS['t_update_result'] ?? true;

	if ($result && count($params) === 3) {
		$GLOBALS['t_stored'] = [
			'ssh_hostkey_type' => $params[0],
			'ssh_fingerprint'  => $params[1],
		];
	}

	return $result;
}

function cacti_log($message, $print = false, $type = '', $verbosity = POLLER_VERBOSITY_NONE) {
	return true;
}

// Loading the production file also loads the plugin settings array, which
// expects a full Cacti bootstrap. Suppress only those bootstrap notices.
$previous_error_reporting = error_reporting(E_ERROR | E_PARSE);
require __DIR__ . '/../../include/functions.php';
error_reporting($previous_error_reporting);

$failures = 0;

function check($label, $ok) {
	global $failures;

	print ($ok ? '  ok: ' : '  FAIL: ') . $label . "\n";

	if (!$ok) {
		$failures++;
	}
}

function reset_state() {
	$GLOBALS['config']    = ['is_web' => false];
	$GLOBALS['debug']     = false;
	$GLOBALS['t_opt']     = [];
	$GLOBALS['t_col']     = true;
	$GLOBALS['t_stored']  = null;
	$GLOBALS['t_updates'] = [];
	$GLOBALS['t_update_result'] = true;
	$GLOBALS['t_connect_calls'] = [];
	$GLOBALS['t_auth_calls']    = 0;
	$GLOBALS['t_methods']       = ['hostkey' => 'ssh-ed25519'];
	$GLOBALS['t_fingerprint']   = 'AA:BB:CC';
	$GLOBALS['routerconfigs_ssh_connect'] = function ($server, $port) {
		$GLOBALS['t_connect_calls'][] = ['server' => $server, 'port' => $port];

		return (object) ['connected' => true];
	};
	$GLOBALS['routerconfigs_ssh_methods_negotiated'] = function ($connection) {
		return $GLOBALS['t_methods'];
	};
	$GLOBALS['routerconfigs_ssh_fingerprint'] = function ($connection, $flags) {
		return $GLOBALS['t_fingerprint'];
	};
	$GLOBALS['routerconfigs_ssh_auth_password'] = function ($connection, $user, $password) {
		$GLOBALS['t_auth_calls']++;

		return true;
	};
}

// Option off: always proceed, no storage touched.
reset_state();
$GLOBALS['t_opt']['routerconfigs_verify_hostkey'] = '';
check('option off proceeds without touching storage',
	plugin_routerconfigs_verify_ssh_hostkey(1, false) === true && count($GLOBALS['t_updates']) === 0);

// Option on, no fingerprint available: refuse.
reset_state();
$GLOBALS['t_opt']['routerconfigs_verify_hostkey'] = 'on';
check('missing fingerprint is refused', plugin_routerconfigs_verify_ssh_hostkey(1, false) === false);

// Option on, storage column absent: fail closed.
reset_state();
$GLOBALS['t_opt']['routerconfigs_verify_hostkey'] = 'on';
$GLOBALS['t_col']                                 = false;
check('absent storage column refuses the connection',
	plugin_routerconfigs_verify_ssh_hostkey(1, ['type' => 'ssh-ed25519', 'fingerprint' => 'AA:BB']) === false);

// Option on, nothing stored: trust on first use, record the fingerprint.
reset_state();
$GLOBALS['t_opt']['routerconfigs_verify_hostkey'] = 'on';
$GLOBALS['t_stored']                              = ['ssh_hostkey_type' => null, 'ssh_fingerprint' => null];
$ok                                               = plugin_routerconfigs_verify_ssh_hostkey(7, ['type' => 'ssh-ed25519', 'fingerprint' => 'AA:BB:CC']) === true;
check('first use records the fingerprint and proceeds',
	$ok && $GLOBALS['t_updates'] === [['ssh-ed25519', 'AA:BB:CC', 7]]);

// First-use storage must be confirmed before authentication can proceed.
reset_state();
$GLOBALS['t_opt']['routerconfigs_verify_hostkey'] = 'on';
$GLOBALS['t_stored']                              = ['ssh_hostkey_type' => null, 'ssh_fingerprint' => null];
$GLOBALS['t_update_result']                       = false;
check('failed first-use write is refused',
	plugin_routerconfigs_verify_ssh_hostkey(7, ['type' => 'ssh-ed25519', 'fingerprint' => 'AA:BB:CC']) === false);

// A failed lookup is not first use and must never replace trust state.
reset_state();
$GLOBALS['t_opt']['routerconfigs_verify_hostkey'] = 'on';
$GLOBALS['t_stored']                              = false;
check('failed host-key lookup is refused without re-pinning',
	plugin_routerconfigs_verify_ssh_hostkey(7, ['type' => 'ssh-ed25519', 'fingerprint' => 'AA:BB:CC']) === false && count($GLOBALS['t_updates']) === 0);

// Option on, stored matches: proceed, no re-store.
reset_state();
$GLOBALS['t_opt']['routerconfigs_verify_hostkey'] = 'on';
$GLOBALS['t_stored']                              = ['ssh_hostkey_type' => 'ssh-ed25519', 'ssh_fingerprint' => 'AA:BB:CC'];
check('matching fingerprint proceeds without re-storing',
	plugin_routerconfigs_verify_ssh_hostkey(7, ['type' => 'ssh-ed25519', 'fingerprint' => 'AA:BB:CC']) === true && count($GLOBALS['t_updates']) === 0);

// RSA signature negotiation may change without changing the underlying key.
reset_state();
$GLOBALS['t_opt']['routerconfigs_verify_hostkey'] = 'on';
$GLOBALS['t_stored']                              = ['ssh_hostkey_type' => 'ssh-rsa', 'ssh_fingerprint' => 'AA:BB:CC'];
check('algorithm change with the same fingerprint proceeds',
	plugin_routerconfigs_verify_ssh_hostkey(7, ['type' => 'rsa-sha2-512', 'fingerprint' => 'AA:BB:CC']) === true);

// Option on, partially stored identity: fail closed rather than replacing it.
reset_state();
$GLOBALS['t_opt']['routerconfigs_verify_hostkey'] = 'on';
$GLOBALS['t_stored']                              = ['ssh_hostkey_type' => 'ssh-ed25519', 'ssh_fingerprint' => null];
check('incomplete stored host key is refused',
	plugin_routerconfigs_verify_ssh_hostkey(7, ['type' => 'ssh-ed25519', 'fingerprint' => 'AA:BB:CC']) === false && count($GLOBALS['t_updates']) === 0);

// Option on, stored differs: refuse (possible MITM).
reset_state();
$GLOBALS['t_opt']['routerconfigs_verify_hostkey'] = 'on';
$GLOBALS['t_stored']                              = ['ssh_hostkey_type' => 'ssh-ed25519', 'ssh_fingerprint' => 'AA:BB:CC'];
check('changed fingerprint is refused',
	plugin_routerconfigs_verify_ssh_hostkey(7, ['type' => 'ssh-ed25519', 'fingerprint' => 'DD:EE:FF']) === false);

// A host-key rejection must abort a multi-transport attempt instead of
// falling through to Telnet. Verification also disables fallback after any
// SSH failure in "both" mode so a failed negotiation cannot force a downgrade.
reset_state();
$GLOBALS['t_opt']['routerconfigs_verify_hostkey'] = 'on';
check('host-key rejection prevents transport fallback',
	plugin_routerconfigs_should_try_next_connection(RCONFIG_CONNECT_BOTH, 'PHPSsh', RCONFIG_CONNECT_HOSTKEY_FAILED) === false);
check('verification prevents SSH-to-Telnet downgrade',
	plugin_routerconfigs_should_try_next_connection(RCONFIG_CONNECT_BOTH, 'PHPSsh', 1) === false);
check('missing SSH extension cannot force a Telnet downgrade',
	plugin_routerconfigs_should_try_next_connection(RCONFIG_CONNECT_BOTH, 'PHPSsh', 4) === false);

reset_state();
check('legacy SSH-to-Telnet fallback remains when verification is off',
	plugin_routerconfigs_should_try_next_connection(RCONFIG_CONNECT_BOTH, 'PHPSsh', 1) === true);

// Verification observes libssh2 negotiation without silently restricting it.
reset_state();
plugin_routerconfigs_ssh_connect('router.example');
check('SSH connector receives only server and port',
	$GLOBALS['t_connect_calls'] === [['server' => 'router.example', 'port' => 22]]);

check('negotiated host-key identity includes algorithm and fingerprint',
	plugin_routerconfigs_get_ssh_hostkey((object) []) === ['type' => 'ssh-ed25519', 'fingerprint' => 'AA:BB:CC']);

$GLOBALS['routerconfigs_ssh_methods_negotiated'] = false;
check('unavailable negotiation metadata is refused',
	plugin_routerconfigs_get_ssh_hostkey((object) []) === false);

reset_state();
$GLOBALS['t_methods'] = [];
check('missing negotiated host-key algorithm is refused',
	plugin_routerconfigs_get_ssh_hostkey((object) []) === false);

$GLOBALS['t_methods']     = ['hostkey' => 'ssh-ed25519'];
$GLOBALS['t_fingerprint'] = false;
check('missing negotiated host-key fingerprint is refused',
	plugin_routerconfigs_get_ssh_hostkey((object) []) === false);

// Execute each transport's rejection path and prove authentication is never
// called after a mismatched host key.
foreach (['PHPSsh', 'PHPScp', 'PHPSftp'] as $transport_class) {
	reset_state();
	$GLOBALS['t_opt']['routerconfigs_verify_hostkey'] = 'on';
	$GLOBALS['t_stored'] = ['ssh_hostkey_type' => 'ssh-ed25519', 'ssh_fingerprint' => 'OLD:FINGERPRINT'];
	$transport = new $transport_class([], ['id' => 7, 'ipaddress' => '127.0.0.1'], 'admin', 'secret', '', false, false);
	$result    = $transport->Connect();

	check("$transport_class refuses before password authentication",
		$result === RCONFIG_CONNECT_HOSTKEY_FAILED && $GLOBALS['t_auth_calls'] === 0);
}

$setup_source = file_get_contents(__DIR__ . '/../../setup.php');
check('upgrade schema stores host-key algorithm and fingerprint',
	strpos($setup_source, 'ADD COLUMN `ssh_hostkey_type`') !== false &&
	strpos($setup_source, 'ADD COLUMN `ssh_fingerprint`') !== false);

$device_source = file_get_contents(__DIR__ . '/../../router-devices.php');
check('device UI can clear stored host keys',
	strpos($device_source, 'case RCONFIG_DEVICE_CLEAR_SSH_HOSTKEY:') !== false &&
	strpos($device_source, 'SET ssh_hostkey_type = NULL, ssh_fingerprint = NULL') !== false &&
	strpos($device_source, "previous_endpoint['hostname']") !== false);

if ($failures > 0) {
	fwrite(STDERR, "\n$failures check(s) failed\n");

	exit(1);
}

print "\nall checks passed\n";
exit(0);
