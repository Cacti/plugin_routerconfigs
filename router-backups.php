<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2007-2017 The Cacti Group                                 |
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


chdir('../../');

include('./include/auth.php');

include_once($config['base_path'] . '/plugins/routerconfigs/functions.php');

set_default_action();

switch (get_nfilter_request_var('action')) {
	case 'viewconfig':
		view_device_config();
		break;
	default:
		top_header();

		display_tabs ();
		show_devices ();

		bottom_footer();
		break;
}

function view_device_config() {
	/* ================= input validation ================= */
	get_filter_request_var('id');
	get_filter_request_var('device');
	/* ==================================================== */

	$device = array();
	if (!isempty_request_var('id')) {
		$device = db_fetch_row('SELECT prb.*, prd.hostname, prd.ipaddress
			FROM plugin_routerconfigs_devices AS prd
			INNER JOIN plugin_routerconfigs_backups AS prb
			ON prb.device=prd.id
			WHERE prb.id=' . get_request_var('id'), FALSE);
	}

	if (isset($device['id'])) {
		top_header();

		display_tabs ();

		html_start_box('', '100%', '', '4', 'center', '');

		form_alternate_row();

		print '<td><h2>' . __('Router Config for %s (%s)', $device['hostname'], $device['ipaddress'], 'routerconfigs');
		print __('Backup from %s', date('M j Y H:i:s', $device['btime']), 'routerconfigs') . '<br>';
		print __('File: %s/%s', $device['directory'], $device['filename'], 'routerconfigs');
		print '</h1><textarea rows=36 cols=120>';
		print $device['config'];
		print '</textarea></td></tr>';

		html_end_box(false);
	} else {
		header('Location: router-backups.php');
		exit;
	}
}

function show_devices () {
	global $action, $device, $config;

	get_filter_request_var('page');

	load_current_session_value('page', 'sess_routerconfigs_backups_current_page', '1');

	input_validate_input_number(get_request_var('page'));

	$device = '';
	if (isset_request_var('device')) {
		$device = get_filter_request_var('device');

		if (isset($_SESSION['routerconfigs_backups_device']) && $_SESSION['routerconfigs_backups_device'] != $device) {
			$page = 1;
			set_request_var('page', '1');
		}

		$_SESSION['routerconfigs_backups_device'] = $device;
	} else if (isset($_SESSION['routerconfigs_backups_device']) && $_SESSION['routerconfigs_backups_device'] != '') {
		$device = $_SESSION['routerconfigs_backups_device'];
	}

	$num_rows = 30;

	if ($device != '') {
		$sql = 'SELECT prd.hostname, prd.ipaddress, prb.id, prb.username, prb.lastchange,
			prb.btime, prb.device, prb.directory, prb.filename
			FROM plugin_routerconfigs_devices AS prd
			INNER JOIN plugin_routerconfigs_backups AS prb
			ON prd.id = prb.device
			WHERE prb.device = ' . $device . '
			ORDER BY prb.btime DESC
			LIMIT ' . ($num_rows*(get_request_var('page')-1)) . ', ' . $num_rows;

		$result = db_fetch_assoc($sql);

		$total_rows = db_fetch_cell('SELECT COUNT(*) FROM plugin_routerconfigs_backups WHERE device = ' . $device);
	}else{
		$result = array();
		$total_rows = 0;
	}

	html_start_box(__('Router Backups', 'routerconfigs'), '100%', '', '4', 'center', '');

	$nav = html_nav_bar('router-backups.php', MAX_DISPLAY_PAGES, get_request_var('page'), $num_rows, $total_rows, 7, 'Backups', 'page', 'main');

	print $nav;

	html_header(
		array(
			__('Hostname', 'routerconfigs'),
			__('Compare', 'routerconfigs'),
			__('Directory', 'routerconfigs'),
			__('Filename', 'routerconfigs'),
			__('Backup Time', 'routerconfigs'),
			__('Last Change', 'routerconfigs'),
			__('Changed By', 'routerconfigs')
		)
	);

	if (sizeof($result)) {
		$r = db_fetch_assoc('SELECT device, id FROM plugin_routerconfigs_backups ORDER BY btime ASC');
		$latest = array();
		if (count($r)) {
			foreach ($r as $s) {
				$latest[$s['device']] = $s['id'];
			}
		}

		$c = 0;
		if (sizeof($result)) {
			foreach ($result as $row) {
				form_alternate_row();

				print '<td><a class="linkEditMain" href="router-devices.php?&action=edit&id=' . $row['device'] . '">' . $row['hostname'] . '</a></td>';
				print "<td><a class='hyperLink' href='router-backups.php?action=viewconfig&id=" . $row['id'] . "'>" . __('View Config', 'routerconfigs') . "</a> - <a class='hyperLink' href='router-compare.php?device1=" . $row['device'] . '&device2=' . $row['device'] . '&file1=' . $row['id'] . '&file2=' . $latest[$row['device']] . "'>" . __('Compare', 'routerconfigs') . "</a></td>";
				print '<td>' . $row['directory'] . '</td>';
				print '<td>' . $row['filename'] . '</td>';
				print '<td>' . date('M j Y H:i:s', $row['btime']) . '</td>';

				if ($row['lastchange'] > 0) {
					print '<td>' . date('M j Y H:i:s', $row['lastchange']) . '</td>';
				} else {
					print '<td> </td>';
				}
				print '<td>' . $row['username'] . '</td>';

				form_end_row();
			}

			print $nav;
		}
	}else{
		form_alternate_row();
		print '<td colspan="10">' . __('No Router Backups Found', 'routerconfigs') . '</td>';
		form_end_row();
	}

	html_end_box(false);

	bottom_footer();
}
