<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

describe('html_escape confirmation payload escaping', function () {
	it('escapes script tags out of confirmation-style output', function () {
		$payload = '<script>alert(1)</script>';
		$escaped = htmlspecialchars($payload, ENT_QUOTES, 'UTF-8');

		expect($escaped)->not->toContain('<script>');
		expect($escaped)->toContain('&lt;script&gt;');
	});
});
