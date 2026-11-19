<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

/*
 * A device backup names its file and folder from device-controlled values:
 * the device 'directory' field, the device hostname, and a name parsed from
 * the returned configuration. plugin_routerconfigs_download_config() runs
 * these through plugin_routerconfigs_safe_name() and
 * plugin_routerconfigs_safe_subdir() before building the read, write, and
 * removal paths, so none of them can escape the configured backup or archive
 * root. These two helpers are the containment contract.
 */

beforeAll(function () {
	require_once __DIR__ . '/../../includes/functions.php';
});

beforeEach(function () {
	$GLOBALS['__stub_overrides'] = array();
	$GLOBALS['__test_db_calls']  = array();
});

it('accepts an ordinary hostname as a backup filename', function (string $name) {
	expect(plugin_routerconfigs_safe_name($name))->toBe($name);
})->with([
	'simple'         => 'sw-core-1',
	'dotted host'    => 'router1.example.com',
	'underscores'    => 'edge_router_2',
]);

it('rejects a backup filename that is not a single safe component', function ($name) {
	expect(plugin_routerconfigs_safe_name($name))->toBeFalse();
})->with([
	'forward traversal' => '../../etc/cron.d/pwn',
	'backslash'         => '..\\evil',
	'absolute'          => '/etc/passwd',
	'parent'            => '..',
	'current'           => '.',
	'empty'             => '',
	'nul byte'          => "host\0",
	'separator inside'  => 'a/b',
]);

it('keeps a backup subdirectory relative to the root', function (string $dir, string $expected) {
	expect(plugin_routerconfigs_safe_subdir($dir))->toBe($expected);
})->with([
	'nested'              => ['routers/edge', 'routers/edge'],
	'leading slash dropped' => ['/routers', 'routers'],
	'all slashes dropped' => ['///routers', 'routers'],
	'backslash normalised' => ['a\\b', 'a/b'],
	'empty stays empty'   => ['', ''],
]);

it('rejects a backup subdirectory that escapes the root', function ($dir) {
	expect(plugin_routerconfigs_safe_subdir($dir))->toBeFalse();
})->with([
	'leading traversal' => '../../var/www',
	'inner traversal'   => 'ok/../../escape',
	'backslash traversal' => '..\\win',
	'nul byte'          => "a\0/b",
]);
