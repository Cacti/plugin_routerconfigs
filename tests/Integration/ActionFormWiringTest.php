<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

describe('routerconfigs action form wiring', function () {
	$checks = [
		'router-accounts.php' => [
			"html_escape(db_fetch_cell_prepared('SELECT name FROM plugin_routerconfigs_accounts WHERE id = ?', [(int) \$matches[1]]))",
			"html_escape(get_nfilter_request_var('drp_action'))",
		],
		'router-devtypes.php' => [
			"html_escape(db_fetch_cell_prepared('SELECT name FROM plugin_routerconfigs_devicetypes WHERE id = ?', [(int) \$matches[1]]))",
			"html_escape(get_request_var('drp_action'))",
		],
	];

	foreach ($checks as $relativeFile => $patterns) {
		foreach ($patterns as $pattern) {
			it("wires escaped output in {$relativeFile} for: {$pattern}", function () use ($relativeFile, $pattern) {
				$path = realpath(__DIR__ . '/../../' . $relativeFile);

				expect($path)->not->toBeFalse("Unable to locate {$relativeFile}");

				$contents = file_get_contents($path);

				expect($contents)->not->toBeFalse("Unable to read {$relativeFile}");
				expect($contents)->toContain($pattern, "Missing expected escaping in {$relativeFile}: {$pattern}");
			});
		}
	}
});
