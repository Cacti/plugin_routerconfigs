<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

/**
 * Base class for Routerconfigs tests.
 *
 * Pest's functional tests do not need this directly, but any test that
 * prefers a class-based fixture can `uses(TestCase::class)` to get a clean
 * stub-call log and stub-override registry per test.
 */
abstract class TestCase extends PHPUnit\Framework\TestCase {
	/**
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['__test_db_calls']  = array();
		$GLOBALS['__stub_overrides'] = array();
	}
}
