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
 * Unit coverage for plugin_routerconfigs_device_tftpserver(): the per-device
 * TFTP server resolver with global fallback (issue #133).
 */

beforeAll(function () {
	require_once __DIR__ . '/../../includes/functions.php';
});

beforeEach(function () {
	$GLOBALS['__stub_overrides'] = array();
	$GLOBALS['__test_db_calls']  = array();
});

it('uses the device tftpserver when it is set', function () {
	$GLOBALS['__stub_overrides']['read_config_option'] = fn ($name, $force = false) => '10.0.0.1';

	$result = plugin_routerconfigs_device_tftpserver(['tftpserver' => '192.168.1.5']);

	expect($result)->toBe('192.168.1.5');
});

it('trims the device tftpserver value', function () {
	$GLOBALS['__stub_overrides']['read_config_option'] = fn ($name, $force = false) => '10.0.0.1';

	$result = plugin_routerconfigs_device_tftpserver(['tftpserver' => "  192.168.1.5\t"]);

	expect($result)->toBe('192.168.1.5');
});

it('falls back to the global setting when the device value is blank', function () {
	$GLOBALS['__stub_overrides']['read_config_option'] = function ($name, $force = false) {
		return $name === 'routerconfigs_tftpserver' ? '10.0.0.1' : '';
	};

	expect(plugin_routerconfigs_device_tftpserver(['tftpserver' => '']))->toBe('10.0.0.1');
	expect(plugin_routerconfigs_device_tftpserver(['tftpserver' => '   ']))->toBe('10.0.0.1');
	expect(plugin_routerconfigs_device_tftpserver([]))->toBe('10.0.0.1');
});

it('prefers an explicit default over reading the global setting', function () {
	$GLOBALS['__stub_overrides']['read_config_option'] = function ($name, $force = false) {
		throw new RuntimeException('read_config_option should not be called when a default is supplied');
	};

	expect(plugin_routerconfigs_device_tftpserver([], '172.16.0.9'))->toBe('172.16.0.9');
});

it('returns an empty string when neither the device nor the global is set', function () {
	$GLOBALS['__stub_overrides']['read_config_option'] = fn ($name, $force = false) => '';

	expect(plugin_routerconfigs_device_tftpserver([]))->toBe('');
});
