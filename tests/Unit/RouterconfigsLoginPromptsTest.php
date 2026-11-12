<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Unit coverage for plugin_routerconfigs_parse_login_prompts(): parsing a
 * device type's Post-Login Prompts text into ordered prompt/response
 * entries (issue #130); plugin_routerconfigs_regex(), which compiles those
 * prompts; and PHPShellConnection::ProcessLoginPrompts(), which must only
 * answer a prompt it has actually seen.
 */

beforeAll(function () {
	require_once __DIR__ . '/../../includes/functions.php';
});

beforeEach(function () {
	$GLOBALS['__stub_overrides'] = array();
	$GLOBALS['__test_db_calls']  = array();
});

/**
 * An interactive shell whose device output is scripted and whose commands
 * and log lines are recorded, so ProcessLoginPrompts() runs without a
 * socket.
 */
class RouterconfigsTestPromptShell extends PHPShellConnection {
	/** @var array<int, string> Device output returned by successive reads. */
	public $test_output = [];

	/** @var array<int, string> Device replies returned by successive commands. */
	public $test_replies = [];

	/** @var array<int, string> Commands sent to the device. */
	public $test_sent = [];

	/** @var array<int, string> Log lines written. */
	public $test_log = [];

	function __construct(string $loginprompts) {
		$this->deviceType = ['loginprompts' => $loginprompts];
	}

	function Connect() {
		return true;
	}

	function GetResponse(&$response, $pass = null) {
		$response .= array_shift($this->test_output) ?? '';

		return 0;
	}

	function DoCommand($cmd, &$response, $pass = null) {
		$this->test_sent[] = $cmd;
		$response          = array_shift($this->test_replies) ?? '';

		return 0;
	}

	function Sleep() {
	}

	function Log($message) {
		$this->test_log[] = $message;
	}
}

it('returns no entries for empty or whitespace input', function () {
	expect(plugin_routerconfigs_parse_login_prompts(''))->toBe([]);
	expect(plugin_routerconfigs_parse_login_prompts(null))->toBe([]);
	expect(plugin_routerconfigs_parse_login_prompts("   \n\n  "))->toBe([]);
});

it('parses prompt|response pairs in order and trims whitespace', function () {
	$text = "Press any key| space \nEnter switch number|1";

	$entries = plugin_routerconfigs_parse_login_prompts($text);

	expect($entries)->toHaveCount(2);
	expect($entries[0])->toBe(['prompt' => 'Press any key', 'response' => 'space']);
	expect($entries[1])->toBe(['prompt' => 'Enter switch number', 'response' => '1']);
});

it('normalizes a <CR> or empty response to an empty string', function () {
	$text = "Press any key|<CR>\nEnter switch number or <CR>:|";

	$entries = plugin_routerconfigs_parse_login_prompts($text);

	expect($entries)->toHaveCount(2);
	expect($entries[0]['response'])->toBe('');
	expect($entries[1]['response'])->toBe('');
});

it('ignores blank lines, lines without a separator, and empty prompts', function () {
	$text = "\nno separator here\n|just a response\nGood prompt|ok\n";

	$entries = plugin_routerconfigs_parse_login_prompts($text);

	expect($entries)->toHaveCount(1);
	expect($entries[0])->toBe(['prompt' => 'Good prompt', 'response' => 'ok']);
});

it('handles CRLF and CR line endings', function () {
	$text = "A|1\r\nB|2\rC|3";

	$entries = plugin_routerconfigs_parse_login_prompts($text);

	expect($entries)->toHaveCount(3);
	expect(array_column($entries, 'prompt'))->toBe(['A', 'B', 'C']);
});

it('keeps only the first separator so responses may contain a pipe', function () {
	$entries = plugin_routerconfigs_parse_login_prompts('prompt|a|b');

	expect($entries)->toHaveCount(1);
	expect($entries[0]['response'])->toBe('a|b');
});

it('compiles operator regular expressions without breaking on a slash', function () {
	expect(plugin_routerconfigs_regex('[y/n]'))->toBe('/[y\\/n]/i');
	expect(preg_match(plugin_routerconfigs_regex('Select member \\(1/2\\)'), 'Select member (1/2):'))->toBe(1);

	// An already escaped slash is left alone rather than double escaped.
	expect(plugin_routerconfigs_regex('a\\/b', ''))->toBe('/a\\/b/');
	expect(preg_match(plugin_routerconfigs_regex('a\\/b', ''), 'a/b'))->toBe(1);

	// The second call for the same pattern comes from the cache.
	expect(plugin_routerconfigs_regex('[y/n]'))->toBe('/[y\\/n]/i');
});

it('keeps compiling correctly after the pattern cache fills and is reset', function () {
	// More distinct patterns than the 256-entry cache holds, so the cache is
	// emptied at least once along the way.
	for ($i = 0; $i < 300; $i++) {
		expect(plugin_routerconfigs_regex("prompt $i/x"))->toBe("/prompt $i\\/x/i");
	}

	expect(plugin_routerconfigs_regex('[y/n]'))->toBe('/[y\\/n]/i');
	expect(plugin_routerconfigs_regex('(broken'))->toBeNull();
});

it('returns null for a pattern that does not compile, without a warning', function (string $pattern) {
	expect(plugin_routerconfigs_regex($pattern))->toBeNull();
})->with([
	'unclosed class'    => ['[abc'],
	'unbalanced paren'  => ['(abc'],
	'trailing escape'   => ['abc\\'],
	'nothing to repeat' => ['*abc'],
]);

it('lists the configured prompts that are not valid regular expressions', function () {
	expect(plugin_routerconfigs_invalid_login_prompts("Press any key|<CR>\n[y/n]|y\n(broken|2\n[x|1"))->toBe(['(broken', '[x']);
	expect(plugin_routerconfigs_invalid_login_prompts(''))->toBe([]);
});

it('answers a prompt containing a slash once it appears', function () {
	$shell = new RouterconfigsTestPromptShell("Save changes \\[y/n\\]|y\nPress any key|<CR>");
	$shell->test_output = ['banner', 'Save changes [y/n]', 'Press any key to continue'];

	$shell->ProcessLoginPrompts();

	expect($shell->test_sent)->toBe(['y', '']);
});

it('answers a prompt printed in reply to the previous response', function () {
	$shell = new RouterconfigsTestPromptShell("Press any key|<CR>\nSelect member|2");
	$shell->test_output  = ['Press any key to continue'];
	$shell->test_replies = ['Select member (1/2):'];

	$shell->ProcessLoginPrompts();

	expect($shell->test_sent)->toBe(['', '2']);
});

it('checks output read while waiting for a missing prompt against the next one', function () {
	$shell = new RouterconfigsTestPromptShell("Enter switch number|2\nPress any key|<CR>");
	$shell->test_output = ['Press any key to continue'];

	$shell->ProcessLoginPrompts();

	expect($shell->test_sent)->toBe(['']);
});

it('sends nothing when the prompt never appears', function () {
	$shell = new RouterconfigsTestPromptShell("Enter switch number|2\nPress any key|<CR>");
	$shell->test_output = ['switch-1>', '', '', '', '', 'Press any key to continue'];

	$shell->ProcessLoginPrompts();

	// The '2' would otherwise land on the CLI prompt and run as a command.
	expect($shell->test_sent)->toBe(['']);
	expect(implode("\n", $shell->test_log))->toContain("WARNING: Post-login prompt 'Enter switch number' was not seen after 5 attempts");
});

it('sends nothing for a prompt that is not a valid regular expression', function () {
	$shell = new RouterconfigsTestPromptShell('(broken|2');
	$shell->test_output = ['(broken'];

	$shell->ProcessLoginPrompts();

	expect($shell->test_sent)->toBe([]);
	expect($shell->test_log[0])->toContain('is not a valid regular expression');
});

it('does nothing for a device type without prompts', function () {
	$shell = new RouterconfigsTestPromptShell('');

	$shell->ProcessLoginPrompts();

	expect($shell->test_sent)->toBe([]);
	expect($shell->test_log)->toBe([]);
});
