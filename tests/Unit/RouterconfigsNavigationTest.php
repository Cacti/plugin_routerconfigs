<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Unit coverage for routerconfigs_draw_navigation_text() in setup.php.
 */

beforeAll(function () {
	require_once __DIR__ . '/../../setup.php';
});

it('adds the routerconfigs breadcrumb entries without disturbing existing ones', function () {
	$nav = routerconfigs_draw_navigation_text(['other.php:' => ['title' => 'Other']]);

	expect($nav)->toHaveKey('other.php:');

	foreach ([
		'router-devices.php:',
		'router-backups.php:',
		'router-accounts.php:',
		'router-compare.php:',
	] as $expected) {
		expect($nav)->toHaveKey($expected);
	}

	expect($nav['router-devices.php:']['url'])->toBe('router-devices.php');
});
