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

$device_actions = array(
	1 => __('Backup', 'routerconfigs'),
	2 => __('Delete', 'routerconfigs'),
	3 => __('Enable', 'routerconfigs'),
	4 => __('Disable', 'routerconfigs')
);

set_default_action();

set_request_var('tab', 'devices');

$acc = array('None');
$accounts = db_fetch_assoc('SELECT id, name FROM plugin_routerconfigs_accounts ORDER BY name', false);
if (!empty($accounts)) {
	foreach ($accounts as $a) {
		$acc[$a['id']] = $a['name'];
	}
}

$dtypes = array('Auto-Detect');
$dtypesarr = db_fetch_assoc('SELECT id, name FROM plugin_routerconfigs_devicetypes ORDER BY name', false);
if (!empty($dtypes)) {
	if (sizeof($dtypesarr)) {
		foreach ($dtypesarr as $a) {
			$dtypes[$a['id']] = $a['name'];
		}
	}
}

$device_edit = array(
	'enabled' => array(
		'method' => 'checkbox',
		'friendly_name' => __('Enable Device', 'routerconfigs'),
		'description' => __('Uncheck this box to disabled this device from being backed up.', 'routerconfigs'),
		'value' => '|arg1:enabled|',
		'default' => '',
		'form_id' => false
	),
	'hostname' => array(
		'method' => 'textbox',
		'friendly_name' => __('Description', 'routerconfigs'),
		'description' => __('Name of this device (will be used for config saving and SVN if no hostname is present in config).', 'routerconfigs'),
		'value' => '|arg1:hostname|',
		'max_length' => '128',
	),
	'ipaddress' => array(
		'method' => 'textbox',
		'friendly_name' => __('IP Address', 'routerconfigs'),
		'description' => __('This is the IP Address used to communicate with the device.', 'routerconfigs'),
		'value' => '|arg1:ipaddress|',
		'max_length' => '128',
	),
	'directory' => array(
		'method' => 'dirpath',
		'friendly_name' => __('Directory', 'routerconfigs'),
		'description' => __('This is the relative directory structure used to store the configs.', 'routerconfigs'),
		'value' => '|arg1:directory|',
		'max_length' => '255',
	),
	'schedule' => array(
		'method' => 'drop_array',
		'friendly_name' => __('Schedule', 'routerconfigs'),
		'description' => __('How often to Backup this device.', 'routerconfigs'),
		'value' => '|arg1:schedule|',
		'default' => 1,
		'array' => array(
			1  => __('Daily', 'routerconfigs'),
			7  => __('Weekly', 'routerconfigs'),
			10 => __('Monthly', 'routerconfigs')
		),
	),
	'devicetype' => array(
		'method' => 'drop_array',
		'friendly_name' => __('Device Type', 'routerconfigs'),
		'description' => __('Choose the type of device that the router is.', 'routerconfigs'),
		'value' => '|arg1:devicetype|',
		'default' => 0,
		'array' => $dtypes,
	),
	'account' => array(
		'method' => 'drop_array',
		'friendly_name' => __('Authentication Account', 'routerconfigs'),
		'description' => __('Choose an account to use to Login to the router', 'routerconfigs'),
		'value' => '|arg1:account|',
		'default' => 0,
		'array' => $acc,
	),
	'id' => array(
		'method' => 'hidden_zero',
		'value' => '|arg1:id|'
	)
);

switch (get_request_var('action')) {
	case 'viewdebug':
		plugin_routerconfigs_view_device_debug();

		break;
	case 'viewconfig':
		view_device_config();

		break;
	case 'actions':
		actions_devices();

		break;
	case 'save':
		save_devices();

		break;
	case 'edit':
		top_header();

		display_tabs ();
		edit_devices();

		bottom_footer();

		break;
	default:
		top_header();

		display_tabs ();
		show_devices ();

		bottom_footer();

		break;
}

function plugin_routerconfigs_view_device_debug () {
	/* ================= input validation ================= */
	get_filter_request_var('id');
	/* ==================================================== */

	$device = array();
	if (!isempty_request_var('id')) {
		$device = db_fetch_row_prepared('SELECT *
			FROM plugin_routerconfigs_devices
			WHERE id = ?',
			array(get_request_var('id')));
	}

	if (isset($device['id'])) {
		top_header();

		display_tabs ();

		html_start_box('', '100%', '', '4', 'center', '');

		form_alternate_row();
		print '<td><h2>' . __('Debug for %s (%s)<br><br>', $device['hostname'], $device['ipaddress'], 'routerconfigs');
		print '</h1><textarea rows=36 cols=120>';
		print base64_decode($device['debug']);
		print '</textarea></td></tr>';

		html_end_box(false);
	} else {
		header('Location: router-devices.php?header=false');
		exit;
	}
}

function view_device_config() {
	/* ================= input validation ================= */
	get_filter_request_var('id');
	/* ==================================================== */

	$device = array();
	if (!empty($_GET['id'])) {
		$device = db_fetch_row_prepared('SELECT prb.*, prd.hostname, prd.ipaddress
			FROM plugin_routerconfigs_devices AS prd
			INNER JOIN plugin_routerconfigs_backups AS prb
			ON prb.device = prd.id
			WHERE prb.device = ?
			ORDER BY btime DESC',
			array(get_request_var('id')));
	}

	if (isset($device['id'])) {
		top_header();

		display_tabs ();

		html_start_box('', '100%', '', '4', 'center', '');

		form_alternate_row();
		print '<td><h2>' . __('Router Config for %s (%s)<br><br>', $device['hostname'], $device['ipaddress'], 'routerconfigs');
		print __('Backup from %s', date('M j Y H:i:s', $device['btime']), 'routerconfigs') . '<br>';
		print __('File: %s/%s', $device['directory'], $device['filename'], 'routerconfigs');
		print '</h1><textarea rows=36 cols=120>';
		print $device['config'];
		print '</textarea></td></tr>';

		html_end_box(false);

		bottom_footer();
	} else {
		header('Location: router-devices.php?header=false');
		exit;
	}
}

function actions_devices() {
	global $device_actions, $config;
	if (isset_request_var('selected_items')) {
		$selected_items = sanitize_unserialize_selected_items(get_nfilter_request_var('selected_items'));

		if ($selected_items != false) {
			switch(get_nfilter_request_var('drp_action')) {
			case '2':
				for ($i=0; $i<count($selected_items); $i++) {
					db_execute_prepared('DELETE FROM plugin_routerconfigs_devices
						WHERE id = ?',
						array($selected_items[$i]));
				}

				break;
			case '3':
				for ($i=0; $i<count($selected_items); $i++) {
					db_execute_prepared('UPDATE plugin_routerconfigs_devices
						SET enabled="on"
						WHERE id = ?',
						array($selected_items[$i]));
				}

				break;
			case '4':
				for ($i=0; $i<count($selected_items); $i++) {
					db_execute_prepared('UPDATE plugin_routerconfigs_devices
						SET enabled=""
						WHERE id = ?',
						array($selected_items[$i]));
				}

				break;
			}
		}

		header('Location: router-devices.php?header=false');
		exit;
	}

	/* setup some variables */
	$device_list  = '';
	$device_array = array();

	/* loop through each of the devices selected on the previous page and get more info about them */
	while (list($var,$val) = each($_POST)) {
		if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
			/* ================= input validation ================= */
			input_validate_input_number($matches[1]);
			/* ==================================================== */

			$device_list .= '<li>' . db_fetch_cell_prepared('SELECT hostname
				FROM plugin_routerconfigs_devices
				WHERE id = ?',
				array($matches[1])) . '</li>';

			$device_array[] = $matches[1];
		}
	}

	if (sizeof($device_array)) {
		if (get_nfilter_request_var('drp_action') == '1') { /* Backup */
			ini_set('max_execution_time', 0);
			ini_set('memory_limit', '256M');
			foreach ($device_array as $id) {
				$device = db_fetch_assoc_prepared('SELECT *
					FROM plugin_routerconfigs_devices
					WHERE id = ?',
					array($id));

				plugin_routerconfigs_download_config($device[0]);
			}

			header('Location: router-devices.php?header=false');
			exit;
		}
	}

	top_header();

	form_start('router-devices.php');

	if (get_nfilter_request_var('drp_action') > 0) {
		html_start_box($device_actions{get_nfilter_request_var('drp_action')}, '60%', '', '3', 'center', '');
	}else{
		html_start_box('', '60%', '', '3', 'center', '');
	}

	if (sizeof($device_array)) {
		switch (get_nfilter_request_var('drp_action')) {
		case '2':
			print "<tr>
				<td colspan='2' class='textArea'>
					<p>" . __('Click \'Continue\' to Delete the following device(s).', 'routerconfigs') . "</p>
					<p><ul>$device_list</ul></p>
				</td>
			</tr>";
			$save_html = "<input type='button' value='" . __esc('Cancel', 'routerconfigs') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' value='" . __esc('Continue', 'routerconfigs') . "' title='" . __esc('Delete Device(s)', 'routerconfigs') . "'>";
			break;
		case '3':
			print "<tr>
				<td colspan='2' class='textArea'>
					<p>" . __('Click \'Continue\' to Enable the following device(s).', 'routerconfigs') . "</p>
					<p><ul>$device_list</ul></p>
				</td>
			</tr>";
			$save_html = "<input type='button' value='" . __esc('Cancel', 'routerconfigs') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' value='" . __esc('Continue', 'routerconfigs') . "' title='" . __esc('Enable Device(s)', 'routerconfigs') . "'>";
			break;
		case '4':
			print "<tr>
				<td colspan='2' class='textArea'>
					<p>" . __('Click \'Continue\' to Disable the following device(s).', 'routerconfigs') . "</p>
					<p><ul>$device_list</ul></p>
				</td>
			</tr>";
			$save_html = "<input type='button' value='" . __esc('Cancel', 'routerconfigs') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' value='" . __esc('Continue', 'routerconfigs') . "' title='" . __esc('Disable Device(s)', 'routerconfigs') . "'>";
			break;
		}
	}else{
		print "<tr><td class='even'><span class='textError'>" . __('You must select at least Router Device.', 'routerconfigs') . "</span></td></tr>\n";

		$save_html = "<input type='button' value='" . __esc('Return', 'routerconfigs') . "' onClick='cactiReturnTo()'>";
	}

	print "<tr>
		<td class='saveRow'>
			<input type='hidden' name='action' value='actions'>
			<input type='hidden' name='selected_items' value='" . (isset($device_array) ? serialize($device_array) : '') . "'>
			<input type='hidden' name='drp_action' value='" . get_request_var('drp_action') . "'>
			$save_html
		</td>
	</tr>";

	html_end_box();

	form_end();

	bottom_footer();
}

function save_devices () {
	/* ================= input validation ================= */
	get_filter_request_var('id');
	get_filter_request_var('devicetype');
	get_filter_request_var('schedule');
	/* ==================================================== */

	if (isset_request_var('id')) {
		$save['id'] = get_request_var('id');
	} else {
		$save['id'] = '';
	}


	if (isset_request_var('enabled')) {
		$save['enabled'] = 'on';
	} else {
		$save['enabled'] = '';
	}

	$save['hostname']   = get_nfilter_request_var('hostname');
	$save['ipaddress']  = get_nfilter_request_var('ipaddress');
	$save['directory']  = get_nfilter_request_var('directory');
	$save['account']    = get_nfilter_request_var('account');
	$save['devicetype'] = get_nfilter_request_var('devicetype');
	$save['schedule']   = get_nfilter_request_var('schedule');

	$id = sql_save($save, 'plugin_routerconfigs_devices', 'id');

	if (is_error_message()) {
		header('Location: router-devices.php?header=false&action=edit&id=' . (empty($id) ? get_request_var('id') : $id));
		exit;
	}

	header('Location: router-devices.php?header=false');
	exit;

}

function edit_devices () {
	global $device_edit;

	/* ================= input validation ================= */
	get_filter_request_var('id');
	/* ==================================================== */

	$account = array();
	if (!isempty_request_var('id')) {
		$account = db_fetch_row('SELECT * FROM plugin_routerconfigs_devices WHERE id=' . get_request_var('id'), FALSE);
		$account['password'] = '';
		$header_label = __('Router: [edit: %s]', $account['hostname'], 'routerconfigs');
	}else{
		$header_label = __('Router: [new]', 'routerconfigs');
	}

	form_start('router-devices.php', 'chk');

	html_start_box($header_label, '100%', '', '3', 'center', '');

	draw_edit_form(
		array(
			'config' => array('no_form_tag' => true, 'form_name' => 'chk'),
			'fields' => inject_form_variables($device_edit, $account)
		)
	);

	html_end_box();

	form_save_button('router-devices.php');
}

function show_devices() {
	global $host, $username, $password, $command;
	global $config, $device_actions, $acc;

	get_filter_request_var('account');
	get_filter_request_var('page');

	$account = '';
	if (isset_request_var('account')) {
		$account = get_request_var('account');
	}

	if (isset_request_var('page')) {
		$page = get_request_var('page');
	}else{
		$page = 1;
	}

	load_current_session_value('page', 'sess_routerconfigs_devices_current_page', '1');
	$num_rows = 30;

	$sql = 'SELECT * FROM plugin_routerconfigs_devices ';
	if ($account != '') {
		$sql .= ' WHERE account = ' . $account;
	}
	$sql   .= 'ORDER BY hostname LIMIT ' . ($num_rows*($page-1)) . ', ' . $num_rows;
	$result = db_fetch_assoc($sql);

	$total_rows = db_fetch_cell('SELECT COUNT(*) FROM plugin_routerconfigs_devices' . ($account != '' ? ' WHERE account = ' . $account:''));

	$url_page_select = get_page_list(get_request_var('page'), MAX_DISPLAY_PAGES, $num_rows, $total_rows, 'router-devices.php?' . ($account != '' ? 'account=' . $account:''));

	$nav = html_nav_bar('router-devices.php', MAX_DISPLAY_PAGES, get_request_var('page'), '20', $total_rows, 10, 'Devices', 'page', 'main');

	form_start('router-devices.php', 'chk');

	html_start_box(__('Router Device Management', 'routerconfigs'), '100%', '', '4', 'center', 'router-devices.php?action=edit');

	print $nav;

	html_header_checkbox(
		array(
			__('Actions', 'routerconfigs'),
			__('Hostname', 'routerconfigs'),
			__('Device Type', 'routerconfigs'),
			__('Configs', 'routerconfigs'),
			__('IP Address', 'routerconfigs'),
			__('Directory', 'routerconfigs'),
			__('Last Backup', 'routerconfigs'),
			__('Last Change', 'routerconfigs'),
			__('Changed By', 'routerconfigs'),
			__('Enabled', 'routerconfigs')
		)
	);

	if (sizeof($result)) {
		foreach ($result as $row) {
			form_alternate_row('line' . $row['id'], false);

			$total = db_fetch_cell_prepared('SELECT count(device)
				FROM plugin_routerconfigs_backups
				WHERE device = ?',
				array($row['id']));

			$dtype = db_fetch_cell_prepared('SELECT name
				FROM plugin_routerconfigs_devicetypes
				WHERE id = ?',
				array($row['devicetype']));

			if (empty($dtype)) $dtype = __('Auto-Detect', 'routerconfigs');

			$cell = '<a class="hyperLink" href="telnet://' . $row['ipaddress'] .'"><img src="' . $config['url_path'] . 'plugins/routerconfigs/images/telnet.jpeg" style="height:14px;" alt="" title="' . __esc('Telnet', 'routerconfigs') . '"></a>';
			if (file_exists($config['base_path'] . '/plugins/traceroute/tracenow.php')) {
				$cell .= '<a class="hyperLink" href="' . htmlspecialchars($config['url_path'] . 'plugins/traceroute/tracenow.php?ip=' . $row['ipaddress']) .'"><img src="' . $config['url_path'] . 'plugins/routerconfigs/images/reddot.png" height=10 alt="" title="' . __esc('Trace Route', 'routerconfigs') . '"></a>';
			}
			$cell .= '<a class="linkEditMain" href="router-devices.php?action=viewdebug&id=' . $row['id'] . '"><img src="' . $config['url_path'] . 'plugins/routerconfigs/images/feedback.jpg" height=10 alt="" title="' . __esc('Router Debug Info', 'routerconfigs') . '"></a>';

			form_selectable_cell($cell, $row['id'], '', 'width:1%;');
			form_selectable_cell('<a class="linkEditMain" href="router-devices.php?&action=edit&id=' . $row['id'] . '">' . $row['hostname'] . '</a>', $row['id']);
			form_selectable_cell('<a class="linkEditMain" href="router-devtypes.php?&action=edit&id=' . $row['devicetype'] . '">' . $dtype . '</a>', $row['id']);
			form_selectable_cell("<a class='linkEditMain' href='router-devices.php?action=viewconfig&id=" . $row['id'] . "'>" . __('Current', 'routerconfigs') . "</a> - <a class='linkEditMain' href='router-backups.php?device=" . $row['id'] . "'>" . __('Backups (%s)', $total) . "</a>", $row['id'], 'routerconfigs');
			form_selectable_cell($row['ipaddress'], $row['id']);
			form_selectable_cell($row['directory'], $row['id']);
			form_selectable_cell(($row['lastbackup'] < 1 ? '' : date('M j Y H:i:s', $row['lastbackup'])), $row['id']);
			form_selectable_cell(($row['lastchange'] < 1 ? '' : date('M j Y H:i:s', $row['lastchange'])), $row['id']);
			form_selectable_cell($row['username'], $row['id']);
			form_selectable_cell(($row['enabled'] == 'on' ? '<span class="deviceUp">' . __('Yes', 'routerconfigs') . '</span>' : '<span class="deviceDown">' . __('No', 'routerconfigs') . '</span>'), $row['id']);
			form_checkbox_cell($row['hostname'], $row['id']);
			form_end_row();
		}
	}else{
		print "<tr class='even'><td colspan='10'>" . __('No Router Devices Found', 'routerconfigs') . "</td></tr>\n";
	}

	html_end_box(false);

	draw_actions_dropdown($device_actions);

	form_end();
}

