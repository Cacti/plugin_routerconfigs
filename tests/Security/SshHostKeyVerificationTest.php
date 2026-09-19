<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | Exercises every branch of plugin_routerconfigs_verify_ssh_hostkey() and  |
 | its supporting helpers: first-use pinning, MITM refusal, transport       |
 | fallback rules, schema migration, and audit logging.                    |
 +-------------------------------------------------------------------------+
*/

$previous_error_reporting = error_reporting(E_ERROR | E_PARSE);
require_once __DIR__ . '/../../include/functions.php';
require_once __DIR__ . '/../../setup.php';
error_reporting($previous_error_reporting);

trait RouterconfigsTestSshAdapter {
	protected function sshAvailable() {
		return true;
	}

	protected function sshConnect() {
		return (object) ['connected' => true];
	}

	protected function sshAuthPassword() {
		$GLOBALS['__t_auth_calls']++;

		return true;
	}

	protected function sshHostKey() {
		return $GLOBALS['__t_presented_hostkey'];
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

/**
 * Reset the fixture globals and (re)install the stub overrides that give
 * the shared Cacti db_* and read_config_option stubs the behaviour this
 * suite needs, without redeclaring any of them.
 */
function resetRouterconfigsSshFixture() {
	$GLOBALS['__t_opt']               = [];
	$GLOBALS['__t_col']               = true;
	$GLOBALS['__t_columns']           = null;
	$GLOBALS['__t_stored']            = null;
	$GLOBALS['__t_updates']           = [];
	$GLOBALS['__t_update_result']     = true;
	$GLOBALS['__t_auth_calls']        = 0;
	$GLOBALS['__t_presented_hostkey'] = ['type' => 'ssh-ed25519', 'fingerprint' => 'AA:BB:CC'];
	$GLOBALS['__t_concurrent_pin']    = null;
	$GLOBALS['__t_ddl_calls']         = [];
	$GLOBALS['__t_ddl_result']        = true;

	$GLOBALS['__stub_overrides']['read_config_option'] = function ($name, $force = false) {
		return $GLOBALS['__t_opt'][$name] ?? false;
	};

	$GLOBALS['__stub_overrides']['db_column_exists'] = function ($table, $column) {
		if (is_array($GLOBALS['__t_columns'])) {
			return $GLOBALS['__t_columns'][$column] ?? false;
		}

		return $GLOBALS['__t_col'];
	};

	$GLOBALS['__stub_overrides']['db_fetch_row_prepared'] = function ($sql, $params = []) {
		return $GLOBALS['__t_stored'];
	};

	$GLOBALS['__stub_overrides']['db_execute'] = function ($sql) {
		$GLOBALS['__t_ddl_calls'][] = $sql;

		if (!$GLOBALS['__t_ddl_result']) {
			return false;
		}

		if (preg_match('/ADD COLUMN `([^`]+)`/', $sql, $matches)) {
			if (!is_array($GLOBALS['__t_columns'])) {
				$GLOBALS['__t_columns'] = [];
			}

			$GLOBALS['__t_columns'][$matches[1]] = true;
		}

		return true;
	};

	$GLOBALS['__stub_overrides']['db_execute_prepared'] = function ($sql, $params = []) {
		$GLOBALS['__t_updates'][] = $params;
		$result                   = $GLOBALS['__t_update_result'];

		if ($result && strpos($sql, 'SET ssh_hostkey_type = ?') !== false &&
			is_array($GLOBALS['__t_stored']) &&
			(string) $GLOBALS['__t_stored']['id']              === (string) $params[1] &&
			(string) $GLOBALS['__t_stored']['ssh_fingerprint'] === (string) $params[2]) {
			$GLOBALS['__t_stored']['ssh_hostkey_type'] = $params[0];
		}

		if ($result && count($params) === 3 && is_array($GLOBALS['__t_concurrent_pin'])) {
			$GLOBALS['__t_stored'] = $GLOBALS['__t_concurrent_pin'];
		}

		if ($result && count($params) === 3 &&
			is_array($GLOBALS['__t_stored']) &&
			empty($GLOBALS['__t_stored']['ssh_hostkey_type']) &&
			empty($GLOBALS['__t_stored']['ssh_fingerprint'])) {
			$GLOBALS['__t_stored'] = [
				'id'               => $params[2],
				'ssh_hostkey_type' => $params[0],
				'ssh_fingerprint'  => $params[1],
			];
		}

		if ($result && count($params) === 1 && strpos($sql, 'SET ssh_hostkey_type = NULL') !== false) {
			$GLOBALS['__t_stored'] = ['id' => $params[0], 'ssh_hostkey_type' => null, 'ssh_fingerprint' => null];
		}

		return $result;
	};
}

beforeEach(function () {
	resetRouterconfigsSshFixture();
});

describe('plugin_routerconfigs_verify_ssh_hostkey: option handling', function () {
	it('proceeds without touching storage when the option is off', function () {
		$GLOBALS['__t_opt']['routerconfigs_verify_hostkey'] = '';

		expect(plugin_routerconfigs_verify_ssh_hostkey(1, false))->toBeTrue();
		expect($GLOBALS['__t_updates'])->toBeEmpty();
	});

	it('refuses when no fingerprint is available', function () {
		$GLOBALS['__t_opt']['routerconfigs_verify_hostkey'] = 'on';

		expect(plugin_routerconfigs_verify_ssh_hostkey(1, false))->toBeFalse();
	});
});

describe('plugin_routerconfigs_verify_ssh_hostkey: storage prerequisites', function () {
	it('fails closed when the storage column is absent', function () {
		$GLOBALS['__t_opt']['routerconfigs_verify_hostkey'] = 'on';
		$GLOBALS['__t_col']                                 = false;

		expect(plugin_routerconfigs_verify_ssh_hostkey(1, ['type' => 'ssh-ed25519', 'fingerprint' => 'AA:BB']))->toBeFalse();
	});

	it('fails closed when the host-key type column is missing', function () {
		$GLOBALS['__t_opt']['routerconfigs_verify_hostkey'] = 'on';
		$GLOBALS['__t_columns']                             = ['ssh_fingerprint' => true, 'ssh_hostkey_type' => false];

		expect(plugin_routerconfigs_verify_ssh_hostkey(1, ['type' => 'ssh-ed25519', 'fingerprint' => 'AA:BB']))->toBeFalse();
	});
});

describe('plugin_routerconfigs_verify_ssh_hostkey: first use', function () {
	it('records the fingerprint and proceeds on first use', function () {
		$GLOBALS['__t_opt']['routerconfigs_verify_hostkey'] = 'on';
		$GLOBALS['__t_stored']                              = ['id' => 7, 'ssh_hostkey_type' => null, 'ssh_fingerprint' => null];

		$ok = plugin_routerconfigs_verify_ssh_hostkey(7, ['type' => 'ssh-ed25519', 'fingerprint' => 'AA:BB:CC']);

		expect($ok)->toBeTrue();
		expect($GLOBALS['__t_updates'])->toBe([['ssh-ed25519', 'AA:BB:CC', 7]]);
		expect(implode("\n", $GLOBALS['__test_log_messages']))->toContain(
			"Recorded first-use SSH host key for device 7; algorithm 'ssh-ed25519', fingerprint 'AA:BB:CC'"
		);
	});

	it('refuses when the first-use write fails', function () {
		$GLOBALS['__t_opt']['routerconfigs_verify_hostkey'] = 'on';
		$GLOBALS['__t_stored']                              = ['id' => 7, 'ssh_hostkey_type' => null, 'ssh_fingerprint' => null];
		$GLOBALS['__t_update_result']                       = false;

		expect(plugin_routerconfigs_verify_ssh_hostkey(7, ['type' => 'ssh-ed25519', 'fingerprint' => 'AA:BB:CC']))->toBeFalse();
	});

	it('refuses a concurrent first-use loser without replacing the winning pin', function () {
		$GLOBALS['__t_opt']['routerconfigs_verify_hostkey'] = 'on';
		$GLOBALS['__t_stored']                              = ['id' => 7, 'ssh_hostkey_type' => null, 'ssh_fingerprint' => null];
		$GLOBALS['__t_concurrent_pin']                      = ['id' => 7, 'ssh_hostkey_type' => 'ssh-ed25519', 'ssh_fingerprint' => 'OTHER:KEY'];

		expect(plugin_routerconfigs_verify_ssh_hostkey(7, ['type' => 'ssh-ed25519', 'fingerprint' => 'AA:BB:CC']))->toBeFalse();
		expect($GLOBALS['__t_stored']['ssh_fingerprint'])->toBe('OTHER:KEY');
	});

	it('refuses a failed host-key lookup without re-pinning', function () {
		$GLOBALS['__t_opt']['routerconfigs_verify_hostkey'] = 'on';
		$GLOBALS['__t_stored']                              = [];

		expect(plugin_routerconfigs_verify_ssh_hostkey(7, ['type' => 'ssh-ed25519', 'fingerprint' => 'AA:BB:CC']))->toBeFalse();
		expect($GLOBALS['__t_updates'])->toBeEmpty();
	});
});

describe('plugin_routerconfigs_verify_ssh_hostkey: established trust', function () {
	it('proceeds without re-storing when the fingerprint matches', function () {
		$GLOBALS['__t_opt']['routerconfigs_verify_hostkey'] = 'on';
		$GLOBALS['__t_stored']                              = ['id' => 7, 'ssh_hostkey_type' => 'ssh-ed25519', 'ssh_fingerprint' => 'AA:BB:CC'];

		expect(plugin_routerconfigs_verify_ssh_hostkey(7, ['type' => 'ssh-ed25519', 'fingerprint' => 'AA:BB:CC']))->toBeTrue();
		expect($GLOBALS['__t_updates'])->toBeEmpty();
	});

	it('proceeds and updates the algorithm when negotiation changes but the fingerprint does not', function () {
		$GLOBALS['__t_opt']['routerconfigs_verify_hostkey'] = 'on';
		$GLOBALS['__t_stored']                              = ['id' => 7, 'ssh_hostkey_type' => 'ssh-rsa', 'ssh_fingerprint' => 'AA:BB:CC'];

		expect(plugin_routerconfigs_verify_ssh_hostkey(7, ['type' => 'rsa-sha2-512', 'fingerprint' => 'AA:BB:CC']))->toBeTrue();
		expect($GLOBALS['__t_stored']['ssh_hostkey_type'])->toBe('rsa-sha2-512');
	});

	it('refuses an incomplete stored host key rather than replacing it', function () {
		$GLOBALS['__t_opt']['routerconfigs_verify_hostkey'] = 'on';
		$GLOBALS['__t_stored']                              = ['id' => 7, 'ssh_hostkey_type' => 'ssh-ed25519', 'ssh_fingerprint' => null];

		expect(plugin_routerconfigs_verify_ssh_hostkey(7, ['type' => 'ssh-ed25519', 'fingerprint' => 'AA:BB:CC']))->toBeFalse();
		expect($GLOBALS['__t_updates'])->toBeEmpty();
	});

	it('refuses a changed fingerprint as a possible MITM', function () {
		$GLOBALS['__t_opt']['routerconfigs_verify_hostkey'] = 'on';
		$GLOBALS['__t_stored']                              = ['id' => 7, 'ssh_hostkey_type' => 'ssh-ed25519', 'ssh_fingerprint' => 'AA:BB:CC'];

		expect(plugin_routerconfigs_verify_ssh_hostkey(7, ['type' => 'ssh-ed25519', 'fingerprint' => 'DD:EE:FF']))->toBeFalse();
	});
});

describe('plugin_routerconfigs_should_try_next_connection: transport fallback', function () {
	it('prevents transport fallback after a host-key rejection', function () {
		$GLOBALS['__t_opt']['routerconfigs_verify_hostkey'] = 'on';

		expect(plugin_routerconfigs_should_try_next_connection(RCONFIG_CONNECT_BOTH, 'PHPSsh', RCONFIG_CONNECT_HOSTKEY_FAILED))->toBeFalse();
	});

	it('prevents an SSH-to-Telnet downgrade when verification is enabled', function () {
		$GLOBALS['__t_opt']['routerconfigs_verify_hostkey'] = 'on';

		expect(plugin_routerconfigs_should_try_next_connection(RCONFIG_CONNECT_BOTH, 'PHPSsh', 1))->toBeFalse();
	});

	it('cannot be forced into a Telnet downgrade by a missing SSH extension', function () {
		$GLOBALS['__t_opt']['routerconfigs_verify_hostkey'] = 'on';

		expect(plugin_routerconfigs_should_try_next_connection(RCONFIG_CONNECT_BOTH, 'PHPSsh', 4))->toBeFalse();
	});

	it('retains the legacy SSH-to-Telnet fallback when verification is off', function () {
		expect(plugin_routerconfigs_should_try_next_connection(RCONFIG_CONNECT_BOTH, 'PHPSsh', 1))->toBeTrue();
	});

	it('retains normal transport handling for SCP-only failures', function () {
		$GLOBALS['__t_opt']['routerconfigs_verify_hostkey'] = 'on';

		expect(plugin_routerconfigs_should_try_next_connection(RCONFIG_CONNECT_SCP, 'PHPScp', 1))->toBeTrue();
	});

	it('retains normal transport handling for SFTP-only failures', function () {
		$GLOBALS['__t_opt']['routerconfigs_verify_hostkey'] = 'on';

		expect(plugin_routerconfigs_should_try_next_connection(RCONFIG_CONNECT_SFTP, 'PHPSftp', 1))->toBeTrue();
	});
});

describe('SSH/SCP/SFTP transports: host-key enforcement before authentication', function () {
	$transports = [
		'RouterconfigsTestPHPSsh'  => 'PHPSsh',
		'RouterconfigsTestPHPScp'  => 'PHPScp',
		'RouterconfigsTestPHPSftp' => 'PHPSftp',
	];

	foreach ($transports as $transportClass => $transportLabel) {
		it("{$transportLabel} refuses before password authentication on a mismatched host key", function () use ($transportClass, $transportLabel) {
			$GLOBALS['__t_opt']['routerconfigs_verify_hostkey'] = 'on';
			$GLOBALS['__t_stored']                              = ['id' => 7, 'ssh_hostkey_type' => 'ssh-ed25519', 'ssh_fingerprint' => 'OLD:FINGERPRINT'];

			$transport = new $transportClass([], ['id' => 7, 'ipaddress' => '127.0.0.1'], 'admin', 'secret', '', false, false);
			$result    = $transport->Connect();

			expect($result)->toBe(RCONFIG_CONNECT_HOSTKEY_FAILED);
			expect($GLOBALS['__t_auth_calls'])->toBe(0);
		});

		it("{$transportLabel} authenticates after a matching host key", function () use ($transportClass, $transportLabel) {
			$GLOBALS['__t_opt']['routerconfigs_verify_hostkey'] = 'on';
			$GLOBALS['__t_stored']                              = ['id' => 7, 'ssh_hostkey_type' => 'ssh-ed25519', 'ssh_fingerprint' => 'AA:BB:CC'];

			$transport = new $transportClass([], ['id' => 7, 'ipaddress' => '127.0.0.1'], 'admin', 'secret', '', false, false);

			expect($transport->Connect())->toBe(0);
			expect($GLOBALS['__t_auth_calls'])->toBe(1);
		});
	}
});

describe('host-key reader', function () {
	it('returns the negotiated algorithm and fingerprint', function () {
		$reader                   = new RouterconfigsTestHostKeyReader();
		$reader->test_methods     = ['hostkey' => 'ssh-ed25519'];
		$reader->test_fingerprint = 'AA:BB:CC';

		expect($reader->readHostKey())->toBe(['type' => 'ssh-ed25519', 'fingerprint' => 'AA:BB:CC']);
	});

	it('refuses non-array negotiation metadata', function () {
		$reader               = new RouterconfigsTestHostKeyReader();
		$reader->test_methods = false;

		expect($reader->readHostKey())->toBeFalse();
	});

	it('refuses a missing algorithm', function () {
		$reader               = new RouterconfigsTestHostKeyReader();
		$reader->test_methods = [];

		expect($reader->readHostKey())->toBeFalse();
	});

	it('refuses a missing fingerprint', function () {
		$reader                   = new RouterconfigsTestHostKeyReader();
		$reader->test_methods     = ['hostkey' => 'ssh-ed25519'];
		$reader->test_fingerprint = false;

		expect($reader->readHostKey())->toBeFalse();
	});
});

describe('PHPScp::Download: verified mode disables the external SCP path', function () {
	it('refuses the unverified external SCP path', function () {
		$GLOBALS['__t_opt']['routerconfigs_verify_hostkey'] = 'on';
		$GLOBALS['__t_opt']['routerconfigs_scp_path']       = '/usr/bin/scp';

		$scpTransport = new RouterconfigsTestPHPScp(
			['configfile' => '/running-config'],
			['id' => 7, 'ipaddress' => '127.0.0.1'],
			'admin',
			'secret',
			'',
			false,
			false
		);

		expect($scpTransport->Download('backup.cfg', '/tmp/'))->toBeFalse();
	});

	it('allows internal SCP on the verified connection', function () {
		$GLOBALS['__t_opt']['routerconfigs_verify_hostkey'] = 'on';
		$GLOBALS['__t_opt']['routerconfigs_scp_path']       = '';

		$scpTransport = new RouterconfigsTestPHPScp(
			['configfile' => '/running-config'],
			['id' => 7, 'ipaddress' => '127.0.0.1'],
			'admin',
			'secret',
			'',
			false,
			false
		);

		expect($scpTransport->Download('backup.cfg', '/tmp/'))->toBeTrue();
	});
});

describe('plugin_routerconfigs_clear_ssh_hostkey: audited resets', function () {
	it('clears both columns and emits an audit log', function () {
		$GLOBALS['__t_stored'] = ['id' => 7, 'ssh_hostkey_type' => 'ssh-ed25519', 'ssh_fingerprint' => 'AA:BB:CC'];

		$cleared = plugin_routerconfigs_clear_ssh_hostkey(7, 'device action');

		expect($cleared)->toBeTrue();
		expect($GLOBALS['__t_stored'])->toBe(['id' => 7, 'ssh_hostkey_type' => null, 'ssh_fingerprint' => null]);
		expect(implode("\n", $GLOBALS['__test_log_messages']))->toContain("discarded algorithm 'ssh-ed25519', fingerprint 'AA:BB:CC'");
	});

	it('does not emit a discarded-key audit log when the reset fails', function () {
		$GLOBALS['__t_stored']        = ['id' => 7, 'ssh_hostkey_type' => 'ssh-ed25519', 'ssh_fingerprint' => 'AA:BB:CC'];
		$GLOBALS['__t_update_result'] = false;

		expect(plugin_routerconfigs_clear_ssh_hostkey(7, 'device action'))->toBeFalse();
		expect(implode("\n", $GLOBALS['__test_log_messages']))->not->toContain('discarded algorithm');
	});
});

describe('plugin_routerconfigs_connection_target_changed', function () {
	it('preserves the host-key pin for description-only edits', function () {
		expect(plugin_routerconfigs_connection_target_changed(
			['hostname' => 'old description', 'ipaddress' => '192.0.2.10'],
			['hostname' => 'new description', 'ipaddress' => '192.0.2.10']
		))->toBeFalse();
	});

	it('resets the host-key pin when the IP address changes', function () {
		expect(plugin_routerconfigs_connection_target_changed(
			['hostname' => 'router', 'ipaddress' => '192.0.2.10'],
			['hostname' => 'router', 'ipaddress' => '192.0.2.11']
		))->toBeTrue();
	});

	it('does not reset the pin for two devices without an IP address', function () {
		expect(plugin_routerconfigs_connection_target_changed([], []))->toBeFalse();
	});

	it('resets the pin when an IP address is added', function () {
		expect(plugin_routerconfigs_connection_target_changed([], ['ipaddress' => '192.0.2.11']))->toBeTrue();
	});
});

describe('routerconfigs_ensure_hostkey_schema', function () {
	it('remains incomplete for a later retry when the migration fails', function () {
		$GLOBALS['__t_columns']    = ['ssh_fingerprint' => false, 'ssh_hostkey_type' => false];
		$GLOBALS['__t_ddl_result'] = false;

		expect(routerconfigs_ensure_hostkey_schema())->toBeFalse();
		expect($GLOBALS['__t_ddl_calls'])->toHaveCount(2);
	});

	it('adds and confirms both columns on success', function () {
		$GLOBALS['__t_columns'] = ['ssh_fingerprint' => false, 'ssh_hostkey_type' => false];

		expect(routerconfigs_ensure_hostkey_schema())->toBeTrue();
		expect($GLOBALS['__t_columns'])->toBe(['ssh_fingerprint' => true, 'ssh_hostkey_type' => true]);
	});
});

describe('source wiring for host-key reset and background downloads', function () {
	it('lets the device UI clear stored host keys', function () {
		$source = file_get_contents(__DIR__ . '/../../router-devices.php');

		expect($source)->toContain('case RCONFIG_DEVICE_CLEAR_SSH_HOSTKEY:');
		expect($source)->toContain("plugin_routerconfigs_clear_ssh_hostkey(\$selected_items[\$i], 'device action by user '");
	});

	it('does not run schema migrations from background downloads', function () {
		$source = file_get_contents(__DIR__ . '/../../router-download.php');

		expect($source)->not->toContain('routerconfigs_check_upgrade();');
	});
});
