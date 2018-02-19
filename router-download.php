<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2008-2017 The Cacti Group                                 |
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

/* do NOT run this script through a web browser */
if (!isset($_SERVER['argv'][0]) || isset($_SERVER['REQUEST_METHOD'])  || isset($_SERVER['REMOTE_ADDR'])) {
	die('<br><strong>This script is only meant to run at the command line.</strong>');
}

/* We are not talking to the browser */
$no_http_headers = true;

$dir = dirname(__FILE__);
if (strpos($dir, 'plugins') !== false) {
	chdir('../../');
}

include('./include/global.php');
include_once($config['base_path'] . '/plugins/routerconfigs/functions.php');

error_reporting(E_ALL ^ E_DEPRECATED);
$parms = $_SERVER['argv'];
array_shift($parms);

/* setup defaults */
$retryMode = false;
$debug = false;
$force = false;
$devices = array();

if (sizeof($parms)) {

	foreach($parms as $parameter) {
		if (strpos($parameter, '=')) {
			list($arg, $value) = explode('=', $parameter);
		} else {
			$arg = $parameter;
			$value = '';
		}

		switch ($arg) {
		case '-d':
		case '--debug':
			$debug = true;
			break;
		case '--devices':
			$devices = explode(",",$value);
			break;
		case '--force':
			$force = true;
			break;
		case '--retry':
			$retryMode = true;
			break;
		default:
			plugin_routerconfigs_log('ERROR: Invalid Argument: (' . $arg . ')');
			exit(1);
		}
	}
}

if (!is_array($devices)) {
	$devices = array($devices);
}

plugin_routerconfigs_download($retryMode, $force, $devices);

