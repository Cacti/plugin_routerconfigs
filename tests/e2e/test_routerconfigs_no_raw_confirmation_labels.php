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
		"'<li>' . db_fetch_cell('SELECT name FROM plugin_routerconfigs_accounts WHERE id=' . \$matches[1]) . '</li>'",
		"name='drp_action' value='\" . get_nfilter_request_var('drp_action') . \"'",
	],
	__DIR__ . '/../../router-devtypes.php' => [
		"'<li>' . db_fetch_cell_prepared('SELECT name FROM plugin_routerconfigs_devicetypes WHERE id = ?', [\$matches[1]]) . '</li>'",
		"name='drp_action' value='\" . get_request_var('drp_action') . \"'",
	],
];

foreach ($checks as $path => $patterns) {
	$contents = file_get_contents($path);

	if ($contents === false) {
		fwrite(STDERR, "Unable to read {$path}\n");
		exit(1);
	}

	foreach ($patterns as $pattern) {
		if (strpos($contents, $pattern) !== false) {
			fwrite(STDERR, "Raw confirmation output remains: {$pattern}\n");
			exit(1);
		}
	}
}

print "OK\n";
