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

/*
 * Test bootstrap.
 *
 * Routerconfigs' sources expect to be included by Cacti, which has already
 * defined the db_*, request-variable, and logging helpers as plain global
 * functions. Nothing here talks to a database or a network: each Cacti
 * function is declared as a stub that records the call in
 * $GLOBALS['__test_db_calls'] and hands back a safe default.
 *
 * Each stub also consults $GLOBALS['__stub_overrides'][$name], a per-test
 * callable set via beforeEach()/it() to script specific return values or
 * side effects without redeclaring the function (PHP cannot redeclare a
 * function once defined, guarded or not).
 *
 * The CI workflow checks out a pinned Cacti release next to this plugin so
 * Pest runs against Cacti's own Composer-managed vendor tree (Pest/PHPUnit)
 * instead of a vendor tree local to this plugin. The version check below
 * makes sure that checkout actually matches what tests/.cacti-version
 * expects before any plugin source is loaded.
 */

$cacti_root = dirname(__DIR__, 3);
$autoload   = $cacti_root . '/include/vendor/autoload.php';
$version    = $cacti_root . '/include/cacti_version';
$expected   = __DIR__ . '/.cacti-version';

if (!is_readable($autoload)) {
	throw new RuntimeException("Cacti Composer autoloader is not readable: $autoload");
}

if (!is_readable($version)) {
	throw new RuntimeException("Cacti version file is not readable: $version");
}

if (!is_readable($expected)) {
	throw new RuntimeException("Expected Cacti version file is not readable: $expected");
}

$cacti_version    = trim((string) file_get_contents($version));
$expected_version = trim((string) file_get_contents($expected));

if ($cacti_version === '') {
	throw new RuntimeException("Cacti version file is empty: $version");
}

if ($expected_version === '') {
	throw new RuntimeException("Expected Cacti version file is empty: $expected");
}

// The CI workflow tracks a moving branch (1.2.x or develop) rather than a pinned release, so any actual version is accepted.
if (!in_array($expected_version, array('1.2.x', 'develop'), true) && $cacti_version !== $expected_version) {
	throw new RuntimeException("Expected Cacti $expected_version, found $cacti_version in $version");
}

require_once $autoload;

/*
 * base_path has to point at the Cacti root two levels above this plugin:
 * routerconfigs' source files build include paths from it at runtime.
 */
/*
 * routerconfigs_check_upgrade() include_once()s $config['library_path'] .
 * '/database.php' and '/functions.php'. Point that at a throwaway
 * directory containing empty stub files: library_path is fully
 * test-controlled (unlike base_path/lib, which is Cacti's real library),
 * so this is safe.
 */
$__stub_library_path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'routerconfigs-test-lib-stub';

if (!is_dir($__stub_library_path)) {
	mkdir($__stub_library_path, 0777, true);
}

file_put_contents($__stub_library_path . '/database.php', "<?php\n");
file_put_contents($__stub_library_path . '/functions.php', "<?php\n");

$GLOBALS['config'] = array(
	'base_path'       => $cacti_root,
	'url_path'        => '/cacti/',
	'cacti_version'   => $cacti_version,
	'cacti_server_os' => 'unix',
	'is_web'          => false,
	'library_path'    => $__stub_library_path,
);

$GLOBALS['debug']             = false;
$GLOBALS['__test_db_calls']   = array();
$GLOBALS['__stub_overrides']  = array();

/**
 * Invoke a per-test override for a stubbed Cacti function if one is set.
 *
 * @param string $name    The stubbed function name.
 * @param array  $args    The arguments the stub was called with.
 * @param mixed  $default The value to return when no override is set.
 *
 * @return mixed
 */
function routerconfigs_test_stub($name, $args, $default) {
	if (isset($GLOBALS['__stub_overrides'][$name]) && is_callable($GLOBALS['__stub_overrides'][$name])) {
		return call_user_func_array($GLOBALS['__stub_overrides'][$name], $args);
	}

	return $default;
}

if (!function_exists('db_execute')) {
	function db_execute($sql) {
		$GLOBALS['__test_db_calls'][] = array('fn' => 'db_execute', 'sql' => $sql, 'params' => array());

		return routerconfigs_test_stub('db_execute', array($sql), true);
	}
}

if (!function_exists('db_execute_prepared')) {
	function db_execute_prepared($sql, $params = array()) {
		$GLOBALS['__test_db_calls'][] = array('fn' => 'db_execute_prepared', 'sql' => $sql, 'params' => $params);

		return routerconfigs_test_stub('db_execute_prepared', array($sql, $params), true);
	}
}

if (!function_exists('db_fetch_assoc')) {
	function db_fetch_assoc($sql) {
		return routerconfigs_test_stub('db_fetch_assoc', array($sql), array());
	}
}

if (!function_exists('db_fetch_assoc_prepared')) {
	function db_fetch_assoc_prepared($sql, $params = array()) {
		return routerconfigs_test_stub('db_fetch_assoc_prepared', array($sql, $params), array());
	}
}

if (!function_exists('db_fetch_row')) {
	function db_fetch_row($sql) {
		return routerconfigs_test_stub('db_fetch_row', array($sql), array());
	}
}

if (!function_exists('db_fetch_row_prepared')) {
	function db_fetch_row_prepared($sql, $params = array()) {
		return routerconfigs_test_stub('db_fetch_row_prepared', array($sql, $params), array());
	}
}

if (!function_exists('db_fetch_cell')) {
	function db_fetch_cell($sql) {
		return routerconfigs_test_stub('db_fetch_cell', array($sql), '');
	}
}

if (!function_exists('db_fetch_cell_prepared')) {
	function db_fetch_cell_prepared($sql, $params = array()) {
		return routerconfigs_test_stub('db_fetch_cell_prepared', array($sql, $params), '');
	}
}

if (!function_exists('db_index_exists')) {
	function db_index_exists($table, $index) {
		return routerconfigs_test_stub('db_index_exists', array($table, $index), false);
	}
}

if (!function_exists('db_column_exists')) {
	function db_column_exists($table, $column) {
		return routerconfigs_test_stub('db_column_exists', array($table, $column), false);
	}
}

if (!function_exists('api_plugin_db_add_column')) {
	function api_plugin_db_add_column($plugin, $table, $data) {
		return routerconfigs_test_stub('api_plugin_db_add_column', array($plugin, $table, $data), true);
	}
}

if (!function_exists('api_plugin_db_table_create')) {
	function api_plugin_db_table_create($plugin, $table, $data) {
		return routerconfigs_test_stub('api_plugin_db_table_create', array($plugin, $table, $data), true);
	}
}

if (!function_exists('api_plugin_register_hook')) {
	function api_plugin_register_hook($plugin, $hook, $function, $file, $subtype = '') {
		return routerconfigs_test_stub('api_plugin_register_hook', array($plugin, $hook, $function, $file, $subtype), true);
	}
}

if (!function_exists('api_plugin_register_realm')) {
	function api_plugin_register_realm($plugin, $file, $description, $enabled = 1) {
		return routerconfigs_test_stub('api_plugin_register_realm', array($plugin, $file, $description, $enabled), true);
	}
}

if (!function_exists('get_current_page')) {
	function get_current_page() {
		return routerconfigs_test_stub('get_current_page', array(), '');
	}
}

if (!function_exists('read_config_option')) {
	function read_config_option($name, $force = false) {
		return routerconfigs_test_stub('read_config_option', array($name, $force), '');
	}
}

if (!function_exists('set_config_option')) {
	function set_config_option($name, $value) {
		routerconfigs_test_stub('set_config_option', array($name, $value), null);
	}
}

if (!function_exists('html_escape')) {
	function html_escape($string) {
		return htmlspecialchars((string) $string, ENT_QUOTES | ENT_HTML5, 'UTF-8');
	}
}

if (!function_exists('__')) {
	function __($text, $domain = '') {
		return $text;
	}
}

if (!function_exists('__esc')) {
	function __esc($text, $domain = '') {
		return htmlspecialchars((string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
	}
}

if (!function_exists('cacti_log')) {
	function cacti_log($message, $also_print = false, $log_type = '', $level = 0) {
		$GLOBALS['__test_log_messages'][] = $message;

		routerconfigs_test_stub('cacti_log', array($message, $also_print, $log_type, $level), null);
	}
}

if (!function_exists('cacti_sizeof')) {
	function cacti_sizeof($array) {
		return is_array($array) ? count($array) : 0;
	}
}

if (!function_exists('cacti_escapeshellarg')) {
	function cacti_escapeshellarg($string) {
		return escapeshellarg((string) $string);
	}
}

if (!function_exists('exec_background')) {
	function exec_background($command, $args = '') {
		return routerconfigs_test_stub('exec_background', array($command, $args), true);
	}
}

if (!function_exists('cacti_version_compare')) {
	function cacti_version_compare($a, $b, $operator) {
		return version_compare((string) $a, (string) $b, $operator);
	}
}

if (!function_exists('is_realm_allowed')) {
	function is_realm_allowed($realm) {
		return true;
	}
}

if (!function_exists('raise_message')) {
	function raise_message($id, $text = '', $level = 0) {
	}
}

if (!function_exists('get_request_var')) {
	function get_request_var($name) {
		return routerconfigs_test_stub('get_request_var', array($name), '');
	}
}

if (!function_exists('get_nfilter_request_var')) {
	function get_nfilter_request_var($name) {
		return routerconfigs_test_stub('get_nfilter_request_var', array($name), '');
	}
}

if (!function_exists('get_filter_request_var')) {
	function get_filter_request_var($name) {
		return routerconfigs_test_stub('get_filter_request_var', array($name), '');
	}
}

if (!function_exists('form_input_validate')) {
	function form_input_validate($value, $name, $regex, $optional, $error) {
		return $value;
	}
}

if (!function_exists('is_error_message')) {
	function is_error_message() {
		return false;
	}
}

if (!function_exists('sql_save')) {
	function sql_save($array, $table, $key = 'id') {
		return isset($array['id']) ? $array['id'] : 1;
	}
}

// Not stubbed: SshHostKeyVerificationTest requires include/functions.php, whose
// unconditional real declaration would fatal on "Cannot redeclare" against a stub here.

if (!defined('CACTI_PATH_BASE')) {
	define('CACTI_PATH_BASE', '/var/www/html/cacti');
}

if (!defined('CACTI_DATE_TIME_FORMAT')) {
	define('CACTI_DATE_TIME_FORMAT', 'Y-m-d H:i:s');
}

if (!defined('POLLER_VERBOSITY_NONE')) {
	define('POLLER_VERBOSITY_NONE', 6);
}

if (!defined('POLLER_VERBOSITY_LOW')) {
	define('POLLER_VERBOSITY_LOW', 2);
}

if (!defined('POLLER_VERBOSITY_MEDIUM')) {
	define('POLLER_VERBOSITY_MEDIUM', 3);
}

if (!defined('POLLER_VERBOSITY_HIGH')) {
	define('POLLER_VERBOSITY_HIGH', 4);
}

if (!defined('POLLER_VERBOSITY_DEBUG')) {
	define('POLLER_VERBOSITY_DEBUG', 5);
}

if (!defined('POLLER_VERBOSITY_DEVDBG')) {
	define('POLLER_VERBOSITY_DEVDBG', 5);
}

if (!defined('MESSAGE_LEVEL_ERROR')) {
	define('MESSAGE_LEVEL_ERROR', 1);
}

// Only defined by ext-ssh2; stub so tests can run on matrix legs without it.
if (!defined('SSH2_FINGERPRINT_SHA1')) {
	define('SSH2_FINGERPRINT_SHA1', 1);
}

if (!defined('SSH2_FINGERPRINT_HEX')) {
	define('SSH2_FINGERPRINT_HEX', 0);
}
