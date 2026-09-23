<?php

declare(strict_types = 1);
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

// do NOT run this script through a web browser
if (!isset($_SERVER['argv'][0]) || isset($_SERVER['REQUEST_METHOD']) || isset($_SERVER['REMOTE_ADDR'])) {
	die('<br><strong>This script is only meant to run at the command line.</strong>');
}

routerconfigs_define_exit('EXIT_UNKNOWN',-1, "ERROR: Failed due to unknown reason\n");
routerconfigs_define_exit('EXIT_NORMAL',  0, '');
routerconfigs_define_exit('EXIT_ARGERR',  1, "ERROR: Invalid Argument (%s)\n\n");
routerconfigs_define_exit('EXIT_NONNUM',  2, "ERROR: Argument is not numeric (%s)\n\n");
routerconfigs_define_exit('EXIT_ARGMIS',  3, "ERROR: Argument requires value (%s)\n\n");

// We are not talking to the browser
$no_http_headers = true;

$dir = __DIR__;
chdir($dir);

if (strpos($dir, 'plugins') !== false) {
	chdir('../../');
}

$shortOpts = 'VvH:h:DdBbRrFf';
$longOpts  = [
	'device:',
	'devices:',
	'debug',
	'debug-buffer',
	'retry',
	'force',
	'version',
	'help',
	'simulate-schedule'
];

$remaining = '';
$options   = routerconfigs_getopts($shortOpts, $longOpts, $remaining);

include('./include/global.php');
include_once(__DIR__ . '/include/functions.php');

error_reporting(E_ALL ^ E_DEPRECATED);

// setup defaults
$retryMode   = false;
$debugBuffer = false;
$debug       = false;
$force       = false;
$devices     = [];
$simulate    = false;

foreach ($options as $arg => $value) {
	switch ($arg) {
		case 'h':
		case 'host':
		case 'hosts':
		case 'device':
		case 'devices':
			if (!is_array($value)) {
				$value = [$value];
			}

			foreach ($value as $deviceId) {
				$deviceIds = explode(',',$deviceId);

				foreach ($deviceIds as $deviceId) {
					if (!is_numeric($deviceId)) {
						routerconfigs_fail(EXIT_NONNUM, $deviceId);
					}
					// echo "Adding device $deviceId\n";
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
			$debug       = true;
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
			$debug       = false;
			$debugBuffer = false;
			$quiet       = true;

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

/**
 * Prints the message registered for a CLI exit code (via
 * routerconfigs_define_exit()), optionally with printf-style arguments
 * substituted in, then terminates the script with that exit code.
 * Called throughout this script's argument parsing to report invalid
 * CLI usage.
 *
 * @param int   $exit_value    The exit code to report and terminate
 *                            with.
 * @param mixed $args          A single value or array of values to
 *                            substitute into the registered message via
 *                            printf(); defaults to an empty array.
 * @param int   $display_help  Currently unused placeholder for
 *                            optionally showing usage help; defaults to
 *                            0.
 *
 * @return void This function always terminates script execution via
 *              exit and therefore never returns.
 *
 * @global bool  $quiet    When true, suppresses the printed message
 *                         (the script still exits with $exit_value).
 * @global array $fail_msg The exit-code => message map populated by
 *                         routerconfigs_define_exit().
 */
function routerconfigs_fail($exit_value,$args = [],$display_help = 0) {
	global $quiet,$fail_msg;

	if (!$quiet) {
		if (!isset($args)) {
			$args = [];
		} elseif (!is_array($args)) {
			$args = [$args];
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

/**
 * Defines a named exit-code constant and registers its associated
 * failure message text (indexed by both name and value) for later use
 * by routerconfigs_fail(). Called at the top of this script to register
 * each of its EXIT_* constants.
 *
 * @param string $name  The constant name to define (e.g. 'EXIT_ARGERR').
 * @param int    $value The exit code value to assign.
 * @param string $text  The printf-style message format associated with
 *                      this exit code.
 *
 * @return void
 *
 * @global array $fail_msg The exit-code => message map being built up.
 */
function routerconfigs_define_exit($name, $value, $text) {
	global $fail_msg;

	$fail_msg ??= [];

	define($name,$value);
	$fail_msg[$name]  = $text;
	$fail_msg[$value] = $text;
}

/**
 * A hand-rolled command-line argument parser: builds the short/long
 * option definitions and walks $_SERVER['argv'], matching each argument
 * against them (via routerconfigs_getopts_find()), collecting repeated
 * options into arrays, and accumulating any unrecognized arguments into
 * $remaining. Called at the top of this script to parse its CLI
 * arguments before Cacti's include/global.php is even loaded.
 *
 * @param string      $short     The short-option specification string
 *                               (getopt()-style, e.g. 'Vv').
 * @param array       $long      The long-option specification array
 *                               (getopt()-style, e.g. array('device:')).
 * @param string|null $remaining Reference, set to any arguments that
 *                               didn't match a known option; defaults
 *                               to null.
 *
 * @return array Map of matched option name to its value (or array of
 *               values, if repeated).
 */
function routerconfigs_getopts($short, $long, &$remaining = null) {
	$remaining = '';
	$argv      = $_SERVER['argv'];
	$argc      = $_SERVER['argc'];
	$result    = [];

	$options = [];
	routerconfigs_getopts_short($options, $short);
	routerconfigs_getopts_long($options, $long);

	$ignoreOptions = false;

	for ($loop = 1; $loop < $argc; $loop++) {
		$name  = null;
		$value = null;

		$arg = $argv[$loop];

		if ($arg == '--') {
			$ignoreOptions = true;
		} else {
			$option = $ignoreOptions ? null : routerconfigs_getopts_find($arg, $options);

			if ($option != null) {
				if ($option['value']) {
					if (!isset($option['result']) && $loop < $argc - 1) {
						$option2 = routerconfigs_getopts_find($argv[$loop + 1], $options);

						if ($option2 == null) {
							$option['result'] = $argv[$loop + 1];
							$loop++;
						}
					}

					if (!$option['optional'] && !isset($option['result'])) {
						routerconfigs_fail(EXIT_ARGMIS,$option['text']);
					}
				}

				$name  = $option['text'];
				$value = $option['result'] ?? '';

				if (array_key_exists($name, $result)) {
					$result_val = $result[$name];

					if (!is_array($result_val)) {
						$result_val = [$result_val];
					}

					$result_val[] = $value;
				} else {
					$result_val = $value;
				}

				$result[$name] = $result_val;
			} else {
				$remaining .= (strlen($remaining) ? ' ' : '') . $arg;
			}
		}
	}

	return $result;
}

/**
 * Parses a getopt()-style long-option specification array into this
 * parser's internal option definitions, appending them to $options via
 * routerconfigs_addopt(). Called from routerconfigs_getopts() to build
 * the long-option half of the option table.
 *
 * @param array $options Reference, the option definitions array being
 *                       built up.
 * @param array $long    The long-option specification array to parse
 *                       (each entry optionally suffixed with ':' for a
 *                       required value or '::' for an optional one).
 *
 * @return void
 */
function routerconfigs_getopts_long(array &$options, array &$long) {
	if (isset($long)) {
		if (!is_array($long)) {
			$long = [$long];
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

				if ($long_opt) {
					$long_text = substr($long_text, 0, -1);
				}

				routerconfigs_addopt($options, count($options), $long_text, $long_val, $long_opt);
			}
		}
	}
}

/**
 * Parses a getopt()-style short-option specification string into this
 * parser's internal option definitions, appending them to $options via
 * routerconfigs_addopt(). Called from routerconfigs_getopts() to build
 * the short-option half of the option table.
 *
 * @param array  $options Reference, the option definitions array being
 *                        built up.
 * @param string $short   The short-option specification string to parse
 *                        (getopt()-style, e.g. 'Vv').
 *
 * @return void
 */
function routerconfigs_getopts_short(array &$options, $short) {
	if (!preg_match('~[A-Za-z0-9:]~', $short)) {
		routerconfigs_fail(EXIT_OPTERR,$short);
	}

	$options = [];

	for ($loop = 0; $loop < strlen($short); $loop++) {
		$short_text = $short[$loop];
		$short_val  = routerconfigs_checkopt('value', $short, $loop);
		$short_opt  = routerconfigs_checkopt('value', $short, $loop);

		routerconfigs_addopt($options, $loop, $short_text, $short_val, $short_opt);
	}
}

/**
 * Finds the option definition matching a single command-line argument
 * (a '-x' or '--long[=value]' style token), extracting an inline
 * '=value' when present. Called from routerconfigs_getopts() for each
 * argument encountered.
 *
 * @param string $arg     The raw command-line argument token to match.
 * @param array  $options The option definitions to match against.
 *
 * @return array|null The matching option definition (with 'result' set
 *                    if an inline value was found), or null if no
 *                    option matched.
 */
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

			// echo sprintf("%3d arg, %3d txt, %15s = %s\n", $length_arg, $length_txt, $option['text'], $substr_txt);
			if ($length_arg > $length_txt && $substr_txt == $option['text']) {
				$separator_pos = strlen($option['text']);
				$separator     = $arg[$separator_pos];

				// echo "$arg $separator found\n";
				if ($separator == '=') {
					$found = $option;
					$separator_pos++;

					if ($separator_pos < strlen($arg)) {
						$substr_txt = substr($arg, $separator_pos);
						// echo "Setting $substr_txt\n";
						$found['result'] = $substr_txt;
					} else {
						// echo "Unsetting result\n";
						unset($found['result']);
					}
				}
			}
		}
	}

	// echo sprintf("routerconfigs_getopts_find('%s', options()) return %s%s\n",
	//	$arg,
	//	$found == null ? '<null>' : str_replace("\n","",var_export($found, true)),
	//	$found == null ? '' : ' '.(isset($found['result']) ? str_replace("\n","",var_export($found,true)) : '<null>'));
	return $found;
}

/**
 * Appends a single option definition to the option definitions array.
 * Called from routerconfigs_getopts_long() and
 * routerconfigs_getopts_short() for each parsed option.
 *
 * @param array  $options Reference, the option definitions array to
 *                        append to.
 * @param mixed  $index   Unused positional index (kept for parity with
 *                        the short-option parser's loop variable).
 * @param string $text    The option's name/flag text.
 * @param bool   $val     Whether this option takes a value.
 * @param bool   $opt     Whether this option's value is optional (only
 *                        meaningful when $val is true).
 *
 * @return void
 */
function routerconfigs_addopt(array &$options, $index, $text, $val, $opt) {
	$option = [
		'text'     => $text,
		'value'    => $val,
		'optional' => $opt
	];

	// echo sprintf("Adding option %2s (%3d%s%s)\n", $text, $index, ($val?' hasValue':''), ($opt?' hasOptional':''));
	$options[] = $option;
}

/**
 * Checks whether a short-option specification character at $index is
 * followed by a ':' (indicating it takes a value), advancing $index past
 * the colon when found. Called from routerconfigs_getopts_short() for
 * each character of the short-option specification string.
 *
 * @param string $label Unused label parameter (kept for parity with
 *                      other checkopt helpers; not used directly here).
 * @param string $short The short-option specification string being
 *                      scanned.
 * @param int    $index Reference, the current position in $short;
 *                      advanced by one if a value-indicator colon is
 *                      found.
 *
 * @return bool True if this option takes a value, false otherwise.
 */
function routerconfigs_checkopt($label, $short, &$index) {
	$result = false;
	$colon  = '<unset>';

	if ($index < strlen($short) - 1) {
		$colon = $short[$index + 1];

		if ($colon == ':') {
			$index++;
			$result = true;
		}
	}

	// echo sprintf("%15s routerconfigs_checkopt('%s', %3d) returned %-5s (%s)\n", $label, $short, $index, $result ? 'Yes' : 'No', $colon);
	return $result;
}

/**
 * Checks whether a long-option specification string ends with a ':'
 * (indicating it takes a value), stripping the trailing colon from the
 * returned text when found. Called from routerconfigs_getopts_long() for
 * each long-option specification entry (once for the value indicator,
 * once for the optional indicator).
 *
 * @param string $label Unused label parameter (kept for parity with
 *                      other checkopt helpers; not used directly here).
 * @param string $text  Reference, the long-option specification text;
 *                      updated in place with any trailing ':' removed.
 *
 * @return bool True if the original text ended with ':', false
 *              otherwise.
 */
function routerconfigs_checkopt_string($label, &$text) {
	$result = false;
	$output = $text;
	$colon  = '<unset>';

	if (strlen($text) > 1) {
		$colon = $text[strlen($text) - 1];

		if ($colon == ':') {
			$result = true;
			$output = substr($text, 0, - 1);
		}
	}
	// echo sprintf("%15s routerconfigs_checkopt_string('%s returned %-5s (%s - %s)\n", $label, $text . '\')', $result ? 'Yes' : 'No', $colon, $output);
	$text = $output;

	return $result;
}
