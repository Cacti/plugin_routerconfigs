<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

describe('routerconfigs confirmation labels contain no raw output', function () {
	$checks = [
		'router-accounts.php' => [
			"'<li>' . db_fetch_cell('SELECT name FROM plugin_routerconfigs_accounts WHERE id=' . \$matches[1]) . '</li>'",
			"name='drp_action' value='\" . get_nfilter_request_var('drp_action') . \"'",
		],
		'router-devtypes.php' => [
			"'<li>' . db_fetch_cell_prepared('SELECT name FROM plugin_routerconfigs_devicetypes WHERE id = ?', [\$matches[1]]) . '</li>'",
			"name='drp_action' value='\" . get_request_var('drp_action') . \"'",
		],
	];

	foreach ($checks as $relativeFile => $patterns) {
		foreach ($patterns as $index => $pattern) {
			it("does not leave raw confirmation output in {$relativeFile} (pattern " . ($index + 1) . ')', function () use ($relativeFile, $pattern) {
				$path = realpath(__DIR__ . '/../../' . $relativeFile);

				expect($path)->not->toBeFalse("Unable to locate {$relativeFile}");

				$contents = file_get_contents($path);

				expect($contents)->not->toBeFalse("Unable to read {$relativeFile}");
				expect($contents)->not->toContain($pattern);
			});
		}
	}
});
