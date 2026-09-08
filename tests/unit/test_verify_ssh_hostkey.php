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

foreach (['SSH2_FINGERPRINT_SHA1' => 1, 'SSH2_FINGERPRINT_HEX' => 0] as $k => $v) {
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
	if (is_array($GLOBALS['t_columns'] ?? null)) {
		return $GLOBALS['t_columns'][$column] ?? false;
	}

	return $GLOBALS['t_col'] ?? true;
}

function db_execute($sql) {
	$GLOBALS['t_ddl_calls'][] = $sql;

	if (!($GLOBALS['t_ddl_result'] ?? true)) {
		return false;
	}

	if (preg_match('/ADD COLUMN `([^`]+)`/', $sql, $matches)) {
		$GLOBALS['t_columns'][$matches[1]] = true;
	}

	return true;
}

function db_fetch_row_prepared($sql, $params = []) {
	return $GLOBALS['t_stored'] ?? null;
}

function db_execute_prepared($sql, $params = []) {
	$GLOBALS['t_updates'][] = $params;
	$result                 = $GLOBALS['t_update_result'] ?? true;

	if ($result && count($params) === 3 && is_array($GLOBALS['t_concurrent_pin'])) {
		$GLOBALS['t_stored'] = $GLOBALS['t_concurrent_pin'];
	}

	if ($result && count($params) === 3 &&
		is_array($GLOBALS['t_stored']) &&
		empty($GLOBALS['t_stored']['ssh_hostkey_type']) &&
		empty($GLOBALS['t_stored']['ssh_fingerprint'])) {
		$GLOBALS['t_stored'] = [
			'id'               => $params[2],
			'ssh_hostkey_type' => $params[0],
			'ssh_fingerprint'  => $params[1],
		];
	}

	if ($result && count($params) === 1 && strpos($sql, 'SET ssh_hostkey_type = NULL') !== false) {
		$GLOBALS['t_stored'] = ['id' => $params[0], 'ssh_hostkey_type' => null, 'ssh_fingerprint' => null];
	}

	return $result;
}

function cacti_log($message, $print = false, $type = '', $verbosity = POLLER_VERBOSITY_NONE) {
	if ($verbosity <= ($GLOBALS['t_log_verbosity'] ?? POLLER_VERBOSITY_LOW)) {
		$GLOBALS['t_logs'][] = $message;
	}

	return true;
}

// Loading the production file also loads the plugin settings array, which
// expects a full Cacti bootstrap. Suppress only those bootstrap notices.
$previous_error_reporting = error_reporting(E_ERROR | E_PARSE);
require __DIR__ . '/../../include/functions.php';
require __DIR__ . '/../../setup.php';
error_reporting($previous_error_reporting);

trait RouterconfigsTestSshAdapter {
	protected function sshAvailable() {
		return true;
	}

	protected function sshConnect() {
		return (object) ['connected' => true];
	}

	protected function sshAuthPassword() {
		$GLOBALS['t_auth_calls']++;

		return true;
	}

	protected function sshHostKey() {
		return $GLOBALS['t_presented_hostkey'];
	}

	protected function sshShell() {
		return (object) ['shell' => true];
	}

	protected function sshScpRecv($source, $destination) {
		return true;
	}
}

class RouterconfigsTestPHPSsh extends PHPSsh {
	use RouterconfigsTestSshAdapter;
}

class RouterconfigsTestPHPScp extends PHPScp {
	use RouterconfigsTestSshAdapter;
}

class RouterconfigsTestPHPSftp extends PHPSftp {
	use RouterconfigsTestSshAdapter;
}

class RouterconfigsTestHostKeyReader extends PHPConnection {
	public $test_methods;
	public $test_fingerprint;

	function __construct() {
		parent::__construct('TEST', [], ['ipaddress' => '127.0.0.1'], '', '', '', false, false);
	}

	protected function sshMethodsNegotiated() {
		return $this->test_methods;
	}

	protected function sshFingerprint() {
		return $this->test_fingerprint;
	}

	public function readHostKey() {
		return $this->sshHostKey();
	}
}

$failures = 0;

function check($label, $ok) {
	global $failures;

	print ($ok ? '  ok: ' : '  FAIL: ') . $label . "\n";

	if (!$ok) {
		$failures++;
	}
}

function reset_state() {
	$GLOBALS['config']               = ['is_web' => false];
	$GLOBALS['debug']                = false;
	$GLOBALS['t_opt']                = [];
	$GLOBALS['t_col']                = true;
	$GLOBALS['t_columns']            = null;
	$GLOBALS['t_stored']             = null;
	$GLOBALS['t_updates']            = [];
	$GLOBALS['t_update_result']      = true;
	$GLOBALS['t_auth_calls']         = 0;
	$GLOBALS['t_logs']               = [];
	$GLOBALS['t_presented_hostkey']  = ['type' => 'ssh-ed25519', 'fingerprint' => 'AA:BB:CC'];
	$GLOBALS['t_concurrent_pin']     = null;
	$GLOBALS['t_ddl_calls']          = [];
	$GLOBALS['t_ddl_result']         = true;
	$GLOBALS['t_log_verbosity']      = POLLER_VERBOSITY_LOW;
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

reset_state();
$GLOBALS['t_opt']['routerconfigs_verify_hostkey'] = 'on';
$GLOBALS['t_columns']                             = ['ssh_fingerprint' => true, 'ssh_hostkey_type' => false];
check('missing host-key type column refuses the connection',
	plugin_routerconfigs_verify_ssh_hostkey(1, ['type' => 'ssh-ed25519', 'fingerprint' => 'AA:BB']) === false);

// Option on, nothing stored: trust on first use, record the fingerprint.
reset_state();
$GLOBALS['t_opt']['routerconfigs_verify_hostkey'] = 'on';
$GLOBALS['t_stored']                              = ['id' => 7, 'ssh_hostkey_type' => null, 'ssh_fingerprint' => null];
$ok                                               = plugin_routerconfigs_verify_ssh_hostkey(7, ['type' => 'ssh-ed25519', 'fingerprint' => 'AA:BB:CC']) === true;
check('first use records the fingerprint and proceeds',
	$ok &&
	$GLOBALS['t_updates'] === [['ssh-ed25519', 'AA:BB:CC', 7]] &&
	strpos(implode("\n", $GLOBALS['t_logs']), "Recorded first-use SSH host key for device 7; algorithm 'ssh-ed25519', fingerprint 'AA:BB:CC'") !== false);

// First-use storage must be confirmed before authentication can proceed.
reset_state();
$GLOBALS['t_opt']['routerconfigs_verify_hostkey'] = 'on';
$GLOBALS['t_stored']                              = ['id' => 7, 'ssh_hostkey_type' => null, 'ssh_fingerprint' => null];
$GLOBALS['t_update_result']                       = false;
check('failed first-use write is refused',
	plugin_routerconfigs_verify_ssh_hostkey(7, ['type' => 'ssh-ed25519', 'fingerprint' => 'AA:BB:CC']) === false);

// The guarded first-use update must not replace a pin won by another poller.
reset_state();
$GLOBALS['t_opt']['routerconfigs_verify_hostkey'] = 'on';
$GLOBALS['t_stored']                              = ['id' => 7, 'ssh_hostkey_type' => null, 'ssh_fingerprint' => null];
$GLOBALS['t_concurrent_pin']                      = ['id' => 7, 'ssh_hostkey_type' => 'ssh-ed25519', 'ssh_fingerprint' => 'OTHER:KEY'];
check('concurrent first-use loser refuses without replacing the winning pin',
	plugin_routerconfigs_verify_ssh_hostkey(7, ['type' => 'ssh-ed25519', 'fingerprint' => 'AA:BB:CC']) === false &&
	$GLOBALS['t_stored']['ssh_fingerprint']                                                            === 'OTHER:KEY');

// A failed lookup is not first use and must never replace trust state.
reset_state();
$GLOBALS['t_opt']['routerconfigs_verify_hostkey'] = 'on';
$GLOBALS['t_stored']                              = [];
check('failed host-key lookup is refused without re-pinning',
	plugin_routerconfigs_verify_ssh_hostkey(7, ['type' => 'ssh-ed25519', 'fingerprint' => 'AA:BB:CC']) === false && count($GLOBALS['t_updates']) === 0);

// Option on, stored matches: proceed, no re-store.
reset_state();
$GLOBALS['t_opt']['routerconfigs_verify_hostkey'] = 'on';
$GLOBALS['t_stored']                              = ['id' => 7, 'ssh_hostkey_type' => 'ssh-ed25519', 'ssh_fingerprint' => 'AA:BB:CC'];
check('matching fingerprint proceeds without re-storing',
	plugin_routerconfigs_verify_ssh_hostkey(7, ['type' => 'ssh-ed25519', 'fingerprint' => 'AA:BB:CC']) === true && count($GLOBALS['t_updates']) === 0);

// RSA signature negotiation may change without changing the underlying key.
reset_state();
$GLOBALS['t_opt']['routerconfigs_verify_hostkey'] = 'on';
$GLOBALS['t_stored']                              = ['id' => 7, 'ssh_hostkey_type' => 'ssh-rsa', 'ssh_fingerprint' => 'AA:BB:CC'];
check('algorithm change with the same fingerprint proceeds',
	plugin_routerconfigs_verify_ssh_hostkey(7, ['type' => 'rsa-sha2-512', 'fingerprint' => 'AA:BB:CC']) === true);

// Option on, partially stored identity: fail closed rather than replacing it.
reset_state();
$GLOBALS['t_opt']['routerconfigs_verify_hostkey'] = 'on';
$GLOBALS['t_stored']                              = ['id' => 7, 'ssh_hostkey_type' => 'ssh-ed25519', 'ssh_fingerprint' => null];
check('incomplete stored host key is refused',
	plugin_routerconfigs_verify_ssh_hostkey(7, ['type' => 'ssh-ed25519', 'fingerprint' => 'AA:BB:CC']) === false && count($GLOBALS['t_updates']) === 0);

// Option on, stored differs: refuse (possible MITM).
reset_state();
$GLOBALS['t_opt']['routerconfigs_verify_hostkey'] = 'on';
$GLOBALS['t_stored']                              = ['id' => 7, 'ssh_hostkey_type' => 'ssh-ed25519', 'ssh_fingerprint' => 'AA:BB:CC'];
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

// Execute each transport's rejection path and prove authentication is never
// called after a mismatched host key.
foreach (['RouterconfigsTestPHPSsh' => 'PHPSsh', 'RouterconfigsTestPHPScp' => 'PHPScp', 'RouterconfigsTestPHPSftp' => 'PHPSftp'] as $transport_class => $transport_label) {
	reset_state();
	$GLOBALS['t_opt']['routerconfigs_verify_hostkey'] = 'on';
	$GLOBALS['t_stored']                              = ['id' => 7, 'ssh_hostkey_type' => 'ssh-ed25519', 'ssh_fingerprint' => 'OLD:FINGERPRINT'];
	$transport                                        = new $transport_class([], ['id' => 7, 'ipaddress' => '127.0.0.1'], 'admin', 'secret', '', false, false);
	$result                                           = $transport->Connect();

	check("$transport_label refuses before password authentication",
		$result === RCONFIG_CONNECT_HOSTKEY_FAILED && $GLOBALS['t_auth_calls'] === 0);
}

foreach (['RouterconfigsTestPHPSsh' => 'PHPSsh', 'RouterconfigsTestPHPScp' => 'PHPScp', 'RouterconfigsTestPHPSftp' => 'PHPSftp'] as $transport_class => $transport_label) {
	reset_state();
	$GLOBALS['t_opt']['routerconfigs_verify_hostkey'] = 'on';
	$GLOBALS['t_stored']                              = ['id' => 7, 'ssh_hostkey_type' => 'ssh-ed25519', 'ssh_fingerprint' => 'AA:BB:CC'];
	$transport                                        = new $transport_class([], ['id' => 7, 'ipaddress' => '127.0.0.1'], 'admin', 'secret', '', false, false);

	check("$transport_label authenticates after a matching host key",
		$transport->Connect() === 0 && $GLOBALS['t_auth_calls'] === 1);
}

reset_state();
$reader                   = new RouterconfigsTestHostKeyReader();
$reader->test_methods     = ['hostkey' => 'ssh-ed25519'];
$reader->test_fingerprint = 'AA:BB:CC';
check('host-key reader returns negotiated algorithm and fingerprint',
	$reader->readHostKey() === ['type' => 'ssh-ed25519', 'fingerprint' => 'AA:BB:CC']);

$reader->test_methods = false;
check('host-key reader refuses non-array negotiation metadata', $reader->readHostKey() === false);

$reader->test_methods = [];
check('host-key reader refuses a missing algorithm', $reader->readHostKey() === false);

$reader->test_methods     = ['hostkey' => 'ssh-ed25519'];
$reader->test_fingerprint = false;
check('host-key reader refuses a missing fingerprint', $reader->readHostKey() === false);

reset_state();
$GLOBALS['t_opt']['routerconfigs_verify_hostkey'] = 'on';
$GLOBALS['t_opt']['routerconfigs_scp_path']       = '/usr/bin/scp';
$scp_transport                                    = new RouterconfigsTestPHPScp(
	['configfile' => '/running-config'],
	['id' => 7, 'ipaddress' => '127.0.0.1'],
	'admin',
	'secret',
	'',
	false,
	false
);
check('verified mode refuses the unverified external SCP path',
	$scp_transport->Download('backup.cfg', '/tmp/') === false);

$GLOBALS['t_opt']['routerconfigs_scp_path'] = '';
check('verified mode allows internal SCP on the verified connection',
	$scp_transport->Download('backup.cfg', '/tmp/') === true);

// Resetting trust is centralized, audited, and tied only to connection target
// changes rather than cosmetic description edits.
reset_state();
$GLOBALS['t_stored'] = ['id' => 7, 'ssh_hostkey_type' => 'ssh-ed25519', 'ssh_fingerprint' => 'AA:BB:CC'];
$cleared             = plugin_routerconfigs_clear_ssh_hostkey(7, 'device action');
check('host-key reset clears both columns and emits an audit log',
	$cleared             === true &&
	$GLOBALS['t_stored'] === ['id' => 7, 'ssh_hostkey_type' => null, 'ssh_fingerprint' => null] &&
	strpos(implode("\n", $GLOBALS['t_logs']), "discarded algorithm 'ssh-ed25519', fingerprint 'AA:BB:CC'") !== false);

reset_state();
$GLOBALS['t_stored']        = ['id' => 7, 'ssh_hostkey_type' => 'ssh-ed25519', 'ssh_fingerprint' => 'AA:BB:CC'];
$GLOBALS['t_update_result'] = false;
check('failed host-key reset does not emit a discarded-key audit log',
	plugin_routerconfigs_clear_ssh_hostkey(7, 'device action')       === false &&
	strpos(implode("\n", $GLOBALS['t_logs']), 'discarded algorithm') === false);

check('description-only edits preserve the host-key pin',
	plugin_routerconfigs_connection_target_changed(
		['hostname' => 'old description', 'ipaddress' => '192.0.2.10'],
		['hostname' => 'new description', 'ipaddress' => '192.0.2.10']) === false);
check('IP address edits reset the host-key pin',
	plugin_routerconfigs_connection_target_changed(
		['hostname' => 'router', 'ipaddress' => '192.0.2.10'],
		['hostname' => 'router', 'ipaddress' => '192.0.2.11']) === true);
check('two devices without an IP address do not reset the host-key pin',
	plugin_routerconfigs_connection_target_changed([], []) === false);
check('adding an IP address resets the host-key pin',
	plugin_routerconfigs_connection_target_changed([], ['ipaddress' => '192.0.2.11']) === true);

reset_state();
$GLOBALS['t_columns']    = ['ssh_fingerprint' => false, 'ssh_hostkey_type' => false];
$GLOBALS['t_ddl_result'] = false;
check('failed host-key migration remains incomplete for a later retry',
	routerconfigs_ensure_hostkey_schema() === false && count($GLOBALS['t_ddl_calls']) === 2);

reset_state();
$GLOBALS['t_columns'] = ['ssh_fingerprint' => false, 'ssh_hostkey_type' => false];
check('host-key migration adds and confirms both columns',
	routerconfigs_ensure_hostkey_schema() === true &&
	$GLOBALS['t_columns']                 === ['ssh_fingerprint' => true, 'ssh_hostkey_type' => true]);

$device_source = file_get_contents(__DIR__ . '/../../router-devices.php');
check('device UI can clear stored host keys',
	strpos($device_source, 'case RCONFIG_DEVICE_CLEAR_SSH_HOSTKEY:') !== false &&
	strpos($device_source, "plugin_routerconfigs_clear_ssh_hostkey(\$selected_items[\$i], 'device action by user '") !== false);

$download_source = file_get_contents(__DIR__ . '/../../router-download.php');
check('background downloads fail closed instead of running schema migrations',
	strpos($download_source, 'routerconfigs_check_upgrade();') === false &&
	strpos($download_source, 'requires a completed Router Configs plugin upgrade') !== false);

if ($failures > 0) {
	fwrite(STDERR, "\n$failures check(s) failed\n");

	exit(1);
}

print "\nall checks passed\n";
exit(0);
