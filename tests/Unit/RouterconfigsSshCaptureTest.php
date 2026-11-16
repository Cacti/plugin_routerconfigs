<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Unit coverage for the SSH capture command (issue #206): PHPSsh runs a
 * device type's capture command on an exec channel and saves its output as
 * the configuration, which is how MikroTik RouterOS is backed up since it
 * cannot copy its configuration to a TFTP server.
 */

beforeAll(function () {
	require_once __DIR__ . '/../../includes/functions.php';
});

beforeEach(function () {
	$GLOBALS['__stub_overrides'] = [];
	$GLOBALS['__test_db_calls']  = [];
});

/**
 * An SSH connection whose transport is scripted: the exec channel is one
 * end of a local socket pair, and the commands run and log lines are
 * recorded.
 */
class RouterconfigsTestCaptureSsh extends PHPSsh {
	/** @var resource|false The channel handed back by sshExec(). */
	public $test_channel = false;

	/** @var array<int, string> Commands run on an exec channel. */
	public $test_commands = [];

	/** @var bool Whether an interactive shell was opened. */
	public $test_shell = false;

	/** @var array<int, string> Log lines written. */
	public $test_log = [];

	function __construct(array $deviceType) {
		$this->deviceType = $deviceType;
		$this->device     = ['id' => 1, 'hostname' => 'router'];
		$this->ip         = '192.0.2.1';
		$this->server     = '192.0.2.1';
	}

	protected function sshAvailable() {
		return true;
	}

	protected function sshConnect() {
		return (object) ['connected' => true];
	}

	protected function sshAuthPassword() {
		return true;
	}

	protected function sshHostKey() {
		return ['type' => 'ssh-ed25519', 'fingerprint' => 'AA:BB:CC'];
	}

	protected function sshShell() {
		$this->test_shell = true;

		return fopen('php://memory', 'r+');
	}

	protected function sshExec($command) {
		$this->test_commands[] = $command;

		return $this->test_channel;
	}

	function Sleep() {
	}

	function Log($message) {
		$this->test_log[] = $message;
	}

	function setCaptureLimit(int $seconds) {
		$this->captureLimit = $seconds;
	}
}

/**
 * Returns a channel the device has already written $output to and closed,
 * so reading it ends with the device's end-of-file.
 */
function routerconfigs_test_channel(string $output) {
	[$channel, $device] = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);

	fwrite($device, $output);
	fclose($device);

	return $channel;
}

function routerconfigs_test_capture_dir(): string {
	$dir = sys_get_temp_dir() . '/rc-capture-' . bin2hex(random_bytes(4)) . '/';

	mkdir($dir);

	return $dir;
}

function routerconfigs_test_capture_cleanup(string $dir): void {
	foreach (array_merge(glob($dir . '*') ?: [], glob($dir . '.capture-*') ?: []) as $file) {
		is_dir($file) ? rmdir($file) : unlink($file);
	}

	rmdir($dir);
}

/**
 * A channel that always has more output and only ends after 1000 reads, so
 * a capture that ignores its deadline finishes (and the test fails) instead
 * of running forever.
 */
class RouterconfigsTestEndlessStream {
	/** @var resource|null Set by PHP for a stream wrapper. */
	public $context;

	/** @var int Reads served so far. */
	public static $reads = 0;

	function stream_open($path, $mode, $options, &$opened_path) {
		self::$reads = 0;

		return true;
	}

	function stream_read($count) {
		self::$reads++;

		return str_repeat('x', $count);
	}

	function stream_eof() {
		return self::$reads >= 1000;
	}

	function stream_set_option($option, $arg1, $arg2) {
		return false;
	}

	function stream_close() {
	}
}

it('saves the capture command output as the incoming file', function () {
	$dir = routerconfigs_test_capture_dir();
	$ssh = new RouterconfigsTestCaptureSsh(['capturecmd' => '/export']);

	$ssh->test_channel = routerconfigs_test_channel("# by RouterOS 7.15\n/system identity\nset name=edge1\n");

	expect($ssh->Download('edge1', $dir))->toBeTrue();
	expect($ssh->test_commands)->toBe(['/export']);
	expect(file_get_contents($dir . 'edge1'))->toBe("# by RouterOS 7.15\n/system identity\nset name=edge1\n");
	expect(glob($dir . '.capture-*'))->toBe([]);

	routerconfigs_test_capture_cleanup($dir);
});

it('stops a command that keeps producing output once the capture limit passes', function () {
	stream_wrapper_register('rcendless', 'RouterconfigsTestEndlessStream');

	$dir = routerconfigs_test_capture_dir();
	$ssh = new RouterconfigsTestCaptureSsh(['capturecmd' => '/export']);

	$ssh->setCaptureLimit(0);
	$ssh->test_channel = fopen('rcendless://device', 'r');

	expect($ssh->Download('edge1', $dir))->toBeFalse();
	expect(RouterconfigsTestEndlessStream::$reads)->toBeLessThan(1000);
	expect(file_exists($dir . 'edge1'))->toBeFalse();

	stream_wrapper_unregister('rcendless');
	routerconfigs_test_capture_cleanup($dir);
});

it('leaves no incoming file or temporary file behind when the file cannot be put in place', function () {
	$dir = routerconfigs_test_capture_dir();
	$ssh = new RouterconfigsTestCaptureSsh(['capturecmd' => '/export']);

	// A directory already at the incoming name makes the final rename fail
	// after the temporary file has been written.
	mkdir($dir . 'edge1');

	$ssh->test_channel = routerconfigs_test_channel("# by RouterOS 7.15\n/system identity\nset name=edge1\n");

	// The failing rename is the point of the test; keep its PHP warning from
	// being reported as a test warning.
	set_error_handler(fn () => true);

	try {
		$result = $ssh->Download('edge1', $dir);
	} finally {
		restore_error_handler();
	}

	expect($result)->toBeFalse();
	expect(is_dir($dir . 'edge1'))->toBeTrue();
	expect(glob($dir . '.capture-*'))->toBe([]);

	routerconfigs_test_capture_cleanup($dir);
});

it('needs no TFTP server for a device type with a capture command', function () {
	$GLOBALS['__stub_overrides']['read_config_option'] = fn ($name, $force = false) => '';

	expect(plugin_routerconfigs_tftp_missing(['tftpserver' => ''], ['capturecmd' => '/export']))->toBeFalse();
});

it('needs a TFTP server for a device type without a capture command', function () {
	$GLOBALS['__stub_overrides']['read_config_option'] = fn ($name, $force = false) => '';

	foreach ([[], ['capturecmd' => ''], ['capturecmd' => '  ']] as $deviceType) {
		expect(plugin_routerconfigs_tftp_missing(['tftpserver' => ''], $deviceType))->toBeTrue();
	}
});

it('is satisfied by the device or the global TFTP server', function () {
	$GLOBALS['__stub_overrides']['read_config_option'] = fn ($name, $force = false) => '';

	expect(plugin_routerconfigs_tftp_missing(['tftpserver' => '192.0.2.10'], []))->toBeFalse();

	$GLOBALS['__stub_overrides']['read_config_option'] = fn ($name, $force = false) => $name === 'routerconfigs_tftpserver' ? '192.0.2.20' : '';

	expect(plugin_routerconfigs_tftp_missing(['tftpserver' => ''], []))->toBeFalse();
});

it('reads the trimmed capture command from a device type', function () {
	expect(plugin_routerconfigs_capture_command(['capturecmd' => " /export \n"]))->toBe('/export');
	expect(plugin_routerconfigs_capture_command(['capturecmd' => null]))->toBe('');
	expect(plugin_routerconfigs_capture_command([]))->toBe('');
	expect(plugin_routerconfigs_capture_command(false))->toBe('');
});

it('converts the device CRLF line endings so the backup flow does not double them', function () {
	$dir = routerconfigs_test_capture_dir();
	$ssh = new RouterconfigsTestCaptureSsh(['capturecmd' => '/export']);

	$ssh->test_channel = routerconfigs_test_channel("line one\r\nline two\r\nline three\r");

	expect($ssh->Download('edge1', $dir))->toBeTrue();
	expect(file_get_contents($dir . 'edge1'))->toBe("line one\nline two\nline three\n");

	routerconfigs_test_capture_cleanup($dir);
});

it('trims the capture command before running it', function () {
	$dir = routerconfigs_test_capture_dir();
	$ssh = new RouterconfigsTestCaptureSsh(['capturecmd' => "  /export show-sensitive \n"]);

	$ssh->test_channel = routerconfigs_test_channel('config');

	expect($ssh->Download('edge1', $dir))->toBeTrue();
	expect($ssh->test_commands)->toBe(['/export show-sensitive']);

	routerconfigs_test_capture_cleanup($dir);
});

it('reports no last change or user, as the capture has no TFTP version probe', function () {
	$dir = routerconfigs_test_capture_dir();
	$ssh = new RouterconfigsTestCaptureSsh(['capturecmd' => '/export']);

	$ssh->lastuser         = 'someone';
	$ssh->lastchange       = 123;
	$ssh->test_channel     = routerconfigs_test_channel('config');

	$ssh->Download('edge1', $dir);

	expect($ssh->lastuser)->toBe('');
	expect($ssh->lastchange)->toBe('');

	routerconfigs_test_capture_cleanup($dir);
});

it('fails and writes nothing when the command prints nothing', function () {
	$dir = routerconfigs_test_capture_dir();
	$ssh = new RouterconfigsTestCaptureSsh(['capturecmd' => '/export']);

	$ssh->test_channel = routerconfigs_test_channel(" \r\n\r\n");

	expect($ssh->Download('edge1', $dir))->toBeFalse();
	expect(file_exists($dir . 'edge1'))->toBeFalse();

	routerconfigs_test_capture_cleanup($dir);
});

it('fails when the exec channel cannot be opened', function () {
	$dir = routerconfigs_test_capture_dir();
	$ssh = new RouterconfigsTestCaptureSsh(['capturecmd' => '/export']);

	$ssh->test_channel = false;

	expect($ssh->Download('edge1', $dir))->toBeFalse();
	expect(file_exists($dir . 'edge1'))->toBeFalse();

	routerconfigs_test_capture_cleanup($dir);
});

it('fails when the incoming file cannot be written', function () {
	$ssh = new RouterconfigsTestCaptureSsh(['capturecmd' => '/export']);

	$ssh->test_channel = routerconfigs_test_channel('config');

	expect($ssh->Download('edge1', '/nonexistent-rc-dir/'))->toBeFalse();
});

it('does not save a partial configuration when the device stops responding', function () {
	[$channel, $device] = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);

	$dir = routerconfigs_test_capture_dir();
	$ssh = new RouterconfigsTestCaptureSsh(['capturecmd' => '/export']);

	// The device sends part of the configuration and never closes the channel.
	fwrite($device, "# by RouterOS 7.15\n/ip address\n");

	$ssh->setCaptureLimit(0);
	$ssh->test_channel = $channel;

	expect($ssh->Download('edge1', $dir))->toBeFalse();
	expect(file_exists($dir . 'edge1'))->toBeFalse();

	fclose($device);
	routerconfigs_test_capture_cleanup($dir);
});

it('runs the capture over an exec channel without opening an interactive shell', function () {
	$ssh = new RouterconfigsTestCaptureSsh(['capturecmd' => '/export']);

	expect($ssh->Connect())->toBe(0);
	expect($ssh->test_shell)->toBeFalse();
});

it('opens the interactive shell when the device type has no capture command', function () {
	foreach ([[], ['capturecmd' => ''], ['capturecmd' => '   ']] as $deviceType) {
		$ssh = new RouterconfigsTestCaptureSsh($deviceType);

		expect($ssh->Connect())->toBe(0);
		expect($ssh->test_shell)->toBeTrue();
	}
});
