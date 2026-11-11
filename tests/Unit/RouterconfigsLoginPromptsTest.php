<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Unit coverage for plugin_routerconfigs_parse_login_prompts(): parsing a
 * device type's Post-Login Prompts text into ordered prompt/response
 * entries (issue #130).
 */

beforeAll(function () {
	require_once __DIR__ . '/../../includes/functions.php';
});

beforeEach(function () {
	$GLOBALS['__stub_overrides'] = array();
	$GLOBALS['__test_db_calls']  = array();
});

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
