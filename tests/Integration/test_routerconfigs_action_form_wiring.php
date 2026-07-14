<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

$checks = [
	__DIR__ . '/../../router-accounts.php' => [
		"html_escape(db_fetch_cell('SELECT name FROM plugin_routerconfigs_accounts WHERE id=' . \$matches[1]))",
		"html_escape(get_nfilter_request_var('drp_action'))",
	],
	__DIR__ . '/../../router-devtypes.php' => [
		"html_escape(db_fetch_cell_prepared('SELECT name FROM plugin_routerconfigs_devicetypes WHERE id = ?', [\$matches[1]]))",
		"html_escape(get_request_var('drp_action'))",
	],
];

foreach ($checks as $path => $patterns) {
	$contents = file_get_contents($path);

	if ($contents === false) {
		fwrite(STDERR, "Unable to read {$path}\n");
		exit(1);
	}

	foreach ($patterns as $pattern) {
		if (strpos($contents, $pattern) === false) {
			fwrite(STDERR, "Missing expected escaping: {$pattern}\n");
			exit(1);
		}
	}
}

print "OK\n";
