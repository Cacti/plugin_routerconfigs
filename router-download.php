<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2008-2019 The Cacti Group                                 |
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

routerconfigs_define_exit('EXIT_UNKNOWN',-1, "ERROR: Failed due to unknown reason\n");
routerconfigs_define_exit('EXIT_NORMAL',  0, "");
routerconfigs_define_exit('EXIT_ARGERR',  1, "ERROR: Invalid Argument (%s)\n\n");
routerconfigs_define_exit('EXIT_NONNUM',  2, "ERROR: Argument is not numeric (%s)\n\n");
routerconfigs_define_exit('EXIT_ARGMIS',  3, "ERROR: Argument requires value (%s)\n\n");

/* We are not talking to the browser */
$no_http_headers = true;

$dir = dirname(__FILE__);
chdir($dir);

if (strpos($dir, 'plugins') !== false) {
	chdir('../../');
}

$shortOpts = 'VvH:h:DdBbRrFf';
$longOpts  = array(
	'device:',
	'devices:',
	'debug',
	'debug-buffer',
	'retry',
	'force',
	'version',
	'help',
	'simulate-schedule'
);

$remaing = '';
$options = routerconfigs_getopts($shortOpts, $longOpts, $remaining);

include('./include/global.php');
include_once(__DIR__ . '/include/functions.php');

error_reporting(E_ALL ^ E_DEPRECATED);

/* setup defaults */
$retryMode = false;
$debugBuffer = false;
$debug = false;
$force = false;
$devices = array();
$simulate = false;

foreach($options as $arg => $value) {
	switch ($arg) {
		case 'h':
		case 'host':
		case 'hosts':
		case 'device':
		case 'devices':
			if (!is_array($value)) {
				$value = array($value);
			}

			foreach ($value as $deviceId) {
				$deviceIds = explode(',',$deviceId);
				foreach ($deviceIds as $deviceId) {
					if (!is_numeric($deviceId)) {
						routerconfigs_fail(EXIT_NONNUM, $deviceId);
					}
					//echo "Adding device $deviceId\n";
					$devices[] = $deviceId;
				}
			}
			break;
		case 'd':
		case 'debug':
			$debug = true;
			break;
		case 'b':
		case 'debug-buffer':
			$debug = true;
			$debugBuffer = true;
			break;
		case 'simulate-schedule':
			$simulate = true;
			break;
		case 'f':
		case 'force':
			$force = true;
			break;
		case 'q':
		case 'quiet':
			$debug = false;
			$debugBuffer = false;
			$quiet = true;
			break;
		case 'r':
		case 'retry':
			$retryMode = true;
			break;
		default:
			routerconfigs_fail(EXIT_ARGERR, $arg, true);
	}
}

if (strlen($remaining)) {
	routerconfigs_fail(EXIT_ARGERR, $remaining, true);
}

$devices = array_unique($devices);
plugin_routerconfigs_download($retryMode, $force, $devices, $debugBuffer, $simulate);
exit(EXIT_NORMAL);

function routerconfigs_fail($exit_value,$args = array(),$display_help = 0) {
	global $quiet,$fail_msg;

	if (!$quiet) {
		if (!isset($args)) {
			$args = array();
		} else if (!is_array($args)) {
			$args = array($args);
		}

		if (!array_key_exists($exit_value,$fail_msg)) {
			$format = $fail_msg[EXIT_UNKNOWN];
		} else {
			$format = $fail_msg[$exit_value];
		}
		call_user_func_array('printf', array_merge((array)$format, $args));

		if ($display_help) {
//			display_help();
		}
	}

	exit($exit_value);
}

function routerconfigs_define_exit($name, $value, $text) {
	global $fail_msg;

	if (!isset($fail_msg)) {
		$fail_msg = array();
	}

	define($name,$value);
	$fail_msg[$name] = $text;
	$fail_msg[$value] = $text;
}

function routerconfigs_getopts($short, $long, &$remaining = null) {
	$remaining = '';
	$argv = $_SERVER['argv'];
	$argc = $_SERVER['argc'];
	$result = array();

	$options = array();
	routerconfigs_getopts_short($options, $short);
	routerconfigs_getopts_long($options, $long);

	$ignoreOptions = false;
	for ($loop = 1; $loop < $argc; $loop++) {
		$name = null;
		$value = null;

		$arg = $argv[$loop];
		if ($arg == '--') {
			$ignoreOptions = true;
		} else {
			$option = $ignoreOptions ? null : routerconfigs_getopts_find($arg, $options);
			if ($option != null)
			{
				if ($option['value']) {
					if (!isset($option['result']) && $loop < $argc -1) {
						$option2 = routerconfigs_getopts_find($argv[$loop+1], $options);
						if ($option2 == null ) {
							$option['result'] = $argv[$loop+1];
							$loop++;
						}
					}

					if (!$option['optional'] && !isset($option['result'])) {
						routerconfigs_fail(EXIT_ARGMIS,$option['text']);
					}
				}

				$name = $option['text'];
				$value = isset($option['result']) ? $option['result'] : '';
				if (array_key_exists($name, $result)) {
					$result_val = $result[$name];
					if (!is_array($result_val)) {
						$result_val = array($result_val);
					}

					$result_val[] = $value;
				} else {
					$result_val = $value;
				}

				$result[$name] = $result_val;
			} else {
				$remaining .= (strlen($remaining)?' ':'') . $arg;
			}
		}
	}

	return $result;
}

function routerconfigs_getopts_long(array &$options, array &$long) {
	if (isset($long)) {
		if (!is_array($long)) {
			$long = array($long);
		}

		if (sizeof($long)) {
			$index = 0;
			foreach ($long as $long_text) {
				$long_text = trim($long_text);
				if (strlen($long_text) == 0 || !preg_match("~[A-Za-z0-9:\-]~", $long_text)) {
					routerconfigs_fail(EXIT_OPTERR,$long_text);
				}

				$long_val  = routerconfigs_checkopt_string('value',$long_text);
				$long_opt  = routerconfigs_checkopt_string('optional',$long_text);


				if ($long_opt) $long_text = substr($long_text, 0, -1);

				routerconfigs_addopt($options, count($options), $long_text, $long_val, $long_opt);
			}
		}
	}
}

function routerconfigs_getopts_short(array &$options, $short) {
	if (!preg_match("~[A-Za-z0-9:]~", $short)) {
		routerconfigs_fail(EXIT_OPTERR,$short);
	}

	$options = array();
	for ($loop = 0; $loop < strlen($short); $loop++) {
		$short_text = $short[$loop];
		$short_val = routerconfigs_checkopt('value', $short, $loop);
		$short_opt = routerconfigs_checkopt('value', $short, $loop);

		routerconfigs_addopt($options, $loop, $short_text, $short_val, $short_opt);
	}
}

function routerconfigs_getopts_find($arg, array $options) {
	$found = null;

	if (strlen($arg) && $arg[0] == '-') {
		while (strlen($arg) && $arg[0] == '-') {
			$arg = substr($arg,1);
		}

		foreach ($options as $option) {
			if ($arg == $option['text']) {
				$found = $option;
				unset($found['result']);
			}

			$length_arg = strlen($arg);
			$length_txt = strlen($option['text']);
			$substr_txt = substr($arg,0,$length_txt);
			//echo sprintf("%3d arg, %3d txt, %15s = %s\n", $length_arg, $length_txt, $option['text'], $substr_txt);
			if ($length_arg > $length_txt && $substr_txt == $option['text']) {
				$separator_pos = strlen($option['text']);
				$separator = $arg[$separator_pos];
				//echo "$arg $separator found\n";
				if ($separator == '=') {
					$found = $option;
					$separator_pos++;
					if ($separator_pos < strlen($arg)) {
						$substr_txt = substr($arg, $separator_pos);
						//echo "Setting $substr_txt\n";
						$found['result'] = $substr_txt;
					} else {
						//echo "Unsetting result\n";
						unset($found['result']);
					}
				}
			}
		}
	}
	//echo sprintf("routerconfigs_getopts_find('%s', options()) return %s%s\n",
	//	$arg,
	//	$found == null ? '<null>' : str_replace("\n","",var_export($found, true)),
	//	$found == null ? '' : ' '.(isset($found['result']) ? str_replace("\n","",var_export($found,true)) : '<null>'));
	return $found;
}

function routerconfigs_addopt(array &$options, $index, $text, $val, $opt) {
	$option = array(
		'text' => $text,
		'value' => $val,
		'optional' => $opt
	);

	//echo sprintf("Adding option %2s (%3d%s%s)\n", $text, $index, ($val?' hasValue':''), ($opt?' hasOptional':''));
	$options[] = $option;
}

function routerconfigs_checkopt($label, $short, &$index) {
	$result = false;
	$colon = '<unset>';

	if ($index < strlen($short) - 1) {
		$colon = $short[$index+1];
		if ($colon == ':') {
			$index++;
			$result = true;
		}
	}
	//echo sprintf("%15s routerconfigs_checkopt('%s', %3d) returned %-5s (%s)\n", $label, $short, $index, $result ? 'Yes' : 'No', $colon);
	return $result;
}

function routerconfigs_checkopt_string($label, &$text) {
	$result = false;
	$output = $text;
	$colon = '<unset>';

	if (strlen($text) > 1) {
		$colon = $text[strlen($text) - 1];
		if ($colon == ':') {
			$result = true;
			$output = substr($text, 0, - 1);
		}
	}
	//echo sprintf("%15s routerconfigs_checkopt_string('%s returned %-5s (%s - %s)\n", $label, $text . '\')', $result ? 'Yes' : 'No', $colon, $output);
	$text = $output;
	return $result;
}
