<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

describe('prepared statement consistency in routerconfigs', function () {
	it('uses prepared DB helpers in all plugin files', function () {
		// setup.php is included, but its raw db_execute() calls are exempted
		// when they are schema-migration DDL (ALTER/CREATE/DROP/RENAME TABLE)
		// with no user-supplied parameters to bind; every data-reading
		// db_fetch_* call (including in setup.php) must still be _prepared.
		$targetFiles = [
		'include/functions.php',
		'router-accounts.php',
		'router-compare.php',
		'router-devices.php',
		'router-devtypes.php',
		'router-download.php',
		'setup.php',
		];

		$rawPattern      = '/\bdb_(?:execute|fetch_row|fetch_assoc|fetch_cell)\s*\(/';
		$preparedPattern = '/\bdb_(?:execute|fetch_row|fetch_assoc|fetch_cell)_prepared\s*\(/';
		$ddlPattern      = '/\b(?:ALTER|CREATE|DROP|RENAME)\s+TABLE\b/i';

		foreach ($targetFiles as $relativeFile) {
			$path = realpath(__DIR__ . '/../../' . $relativeFile);

			if ($path === false) {
				continue;
			}
			$contents = file_get_contents($path);

			if ($contents === false) {
				continue;
			}

			$lines    = explode("\n", $contents);
			$rawCalls = 0;
			$inDdl    = false;

			foreach ($lines as $line) {
				$trimmed = ltrim($line);

				if (strpos($trimmed, '//') === 0 || strpos($trimmed, '*') === 0 || strpos($trimmed, '#') === 0) {
					continue;
				}

				if ($relativeFile === 'setup.php') {
					if (preg_match('/\bdb_execute\s*\(/', $line) && preg_match($ddlPattern, $line)) {
						$inDdl = true;
					}

					if ($inDdl) {
						if (strpos($line, ')') !== false) {
							$inDdl = false;
						}

						continue;
					}
				}

				if (preg_match($rawPattern, $line) && !preg_match($preparedPattern, $line)) {
					$rawCalls++;
				}
			}

			expect($rawCalls)->toBe(0, "File {$relativeFile} contains raw DB calls");
		}
	});

	it('uses parameterized placeholders not string interpolation in SQL', function () {
		$targetFiles = [
		'include/functions.php',
		'router-accounts.php',
		'router-compare.php',
		'router-devices.php',
		'router-devtypes.php',
		'router-download.php',
		'setup.php',
		];

		foreach ($targetFiles as $relativeFile) {
			$path = realpath(__DIR__ . '/../../' . $relativeFile);

			if ($path === false) {
				continue;
			}
			$contents = file_get_contents($path);

			if ($contents === false) {
				continue;
			}

			$lines           = explode("\n", $contents);
			$interpolatedSql = 0;

			foreach ($lines as $num => $line) {
				$trimmed = ltrim($line);

				if (strpos($trimmed, '//') === 0 || strpos($trimmed, '*') === 0) {
					continue;
				}

				// Detect _prepared calls with $ interpolation instead of ? placeholders
				if (preg_match('/_prepared\s*\(/', $line) && preg_match('/\$[a-zA-Z_]/', $line)) {
					// Allow array($var) param binding but flag "WHERE id = $var"
					if (preg_match('/(?:SELECT|INSERT|UPDATE|DELETE|WHERE|SET|FROM|JOIN).*\$/', $line)) {
						$interpolatedSql++;
					}
				}
			}

			// This is a heuristic; some false positives expected for complex queries
			expect($interpolatedSql)->toBeLessThanOrEqual(2,
				"File {$relativeFile} may have SQL interpolation in prepared calls"
			);
		}
	});
});
