<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2007-2016 The Cacti Group                                 |
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

$ds_actions = array(
	1 => 'Backup', 
	2 => 'Delete'
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

$account_edit = array(
	'enabled' => array(
		'method' => 'checkbox',
		'friendly_name' => 'Enable Device',
		'description' => 'Uncheck this box to disabled this device from being backed up.',
		'value' => '|arg1:enabled|',
		'default' => '',
		'form_id' => false
	),
	'hostname' => array(
		'method' => 'textbox',
		'friendly_name' => 'Description',
		'description' => 'Name of this device (will be used for config saving and SVN if no hostname is present in config).',
		'value' => '|arg1:hostname|',
		'max_length' => '128',
	),
	'ipaddress' => array(
		'method' => 'textbox',
		'friendly_name' => 'IP Address',
		'description' => 'This is the IP Address used to communicate with the device.',
		'value' => '|arg1:ipaddress|',
		'max_length' => '128',
	),
	'directory' => array(
		'method' => 'textbox',
		'friendly_name' => 'Directory',
		'description' => 'This is the relative directory structure used to store the configs.',
		'value' => '|arg1:directory|',
		'max_length' => '255',
	),
	'schedule' => array(
		'method' => 'drop_array',
		'friendly_name' => 'Schedule',
		'description' => 'How often to Backup this device.',
		'value' => '|arg1:schedule|',
		'default' => 1,
		'array' => array(1 => 'Daily', 7 => 'Weekly', 10 => 'Monthly'),
	),
	'devicetype' => array(
		'method' => 'drop_array',
		'friendly_name' => 'Device Type',
		'description' => 'Choose the type of device that the router is.',
		'value' => '|arg1:devicetype|',
		'default' => 0,
		'array' => $dtypes,
	),
	'account' => array(
		'method' => 'drop_array',
		'friendly_name' => 'Authenication Account',
		'description' => 'Choose an account to use to Login to the router',
		'value' => '|arg1:account|',
		'default' => 0,
		'array' => $acc,
	),
	'id' => array(
		'method' => 'hidden_zero',
		'value' => '|arg1:id|'
	),
	'action' => array(
		'method' => 'hidden_zero',
		'value' => 'edit'
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
		$device = db_fetch_row('SELECT * FROM plugin_routerconfigs_devices WHERE id=' . get_request_var('id'), FALSE);
	}

	if (isset($device['id'])) {
		top_header();

		display_tabs ();

		html_start_box('', '100%', '', '4', 'center', '');

		form_alternate_row();
		print '<td><h2>Debug for ' . $device['hostname'] . ' (' . $device['ipaddress'] . ')<br><br>';
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
		$device = db_fetch_row('SELECT prb.*, prd.hostname, prd.ipaddress
			FROM plugin_routerconfigs_devices AS prd
			INNER JOIN plugin_routerconfigs_backups AS prb
			ON prb.device = prd.id 
			WHERE prb.device=' . get_request_var('id') . '
			ORDER BY btime DESC', FALSE);
	}

	if (isset($device['id'])) {
		top_header();

		display_tabs ();

		html_start_box('', '100%', '', '4', 'center', '');

		form_alternate_row();
		print '<td><h2>Router Config for ' . $device['hostname'] . ' (' . $device['ipaddress'] . ')<br><br>';
		print 'Backup from ' . date('M j Y H:i:s', $device['btime']) . '<br>';
		print 'File: ' . $device['directory'] . '/' . $device['filename'];
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

function actions_devices () {
	global $ds_actions, $config;
	if (isset_request_var('selected_items')) {
		$selected_items = sanitize_unserialize_selected_items(get_nfilter_request_var('selected_items'));

		if ($selected_items != false) {
			if (get_nfilter_request_var('drp_action') == '2') {
				for ($i=0; $i<count($selected_items); $i++) {
					db_execute('DELETE FROM plugin_routerconfigs_devices WHERE id = ' . $selected_items[$i]);
				}
			}
		}

		header('Location: router-devices.php?header=false');
		exit;
	}

	/* setup some variables */
	$account_list  = '';
	$account_array = array();

	/* loop through each of the devices selected on the previous page and get more info about them */
	while (list($var,$val) = each($_POST)) {
		if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
			/* ================= input validation ================= */
			input_validate_input_number($matches[1]);
			/* ==================================================== */

			$account_list .= '<li>' . db_fetch_cell('select hostname from plugin_routerconfigs_devices where id=' . $matches[1]) . '</li>';
			$account_array[] = $matches[1];
		}
	}

	if (sizeof($account_array)) {
		if (get_nfilter_request_var('drp_action') == '1') { /* Backup */
			ini_set('max_execution_time', 0);
			ini_set('memory_limit', '256M');
			foreach ($account_array as $id) {
				$device = db_fetch_assoc('SELECT * FROM plugin_routerconfigs_devices WHERE id = ' . $id);
				 plugin_routerconfigs_download_config($device[0]);
			}
			header('Location: router-devices.php?header=false');
			exit;
		}
	}

	top_header();

	form_start('router-devices.php');

	html_start_box($ds_actions{get_nfilter_request_var('drp_action')}, '60%', '', '3', 'center', '');

	if (sizeof($account_array)) {
		if (get_nfilter_request_var('drp_action') == '2') { /* Delete */
			print "<tr>
				<td colspan='2' class='textArea'>
					<p>When you click 'Continue', the following device(s) will be deleted.</p>
					<p><ul>$account_list</ul></p>
				</td>
			</tr>";
		}
		$save_html = "<input type='button' value='Cancel' onClick='cactiReturnTo()'>&nbsp;<input type='submit' value='Continue' title='Delete Device(s)'>";
	}else{
		print "<tr><td class='even'><span class='textError'>You must select at least one query.</span></td></tr>\n";

		$save_html = "<input type='button' value='Return' onClick='cactiReturnTo()'>";
	}

	print "<tr>
		<td class='saveRow'>
			<input type='hidden' name='action' value='actions'>
			<input type='hidden' name='selected_items' value='" . (isset($account_array) ? serialize($account_array) : '') . "'>
			<input type='hidden' name='drp_action' value='" . get_request_var('drp_action') . "'>
			$save_html
		</td>
	</tr>";

	html_end_box();

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
	global $account_edit;

	/* ================= input validation ================= */
	get_filter_request_var('id');
	/* ==================================================== */

	$account = array();
	if (!isempty_request_var('id')) {
		$account = db_fetch_row('SELECT * FROM plugin_routerconfigs_devices WHERE id=' . get_request_var('id'), FALSE);
		$account['password'] = '';
		$header_label = '[edit: ' . $account['hostname'] . ']';
	}else{
		$header_label = '[new]';
	}

	html_start_box('Query: ' . $header_label, '100%', '', '3', 'center', '');
	draw_edit_form(array(
		'config' => array('form_name' => 'chk'),
		'fields' => inject_form_variables($account_edit, $account)
		)
	);

	html_end_box();
	form_save_button('router-devices.php');
}

function show_devices() {
	global $host, $username, $password, $command;
	global $config, $ds_actions, $acc;

	get_filter_request_var('account');
	get_filter_request_var('page');

	$account = '';
	if (isset_request_var('account')) {
		$account = get_request_var('account');
	}

	load_current_session_value('page', 'sess_routerconfigs_devices_current_page', '1');
	$num_rows = 30;

	$sql = 'SELECT * FROM plugin_routerconfigs_devices ';
	if ($account != '') {
		$sql .= ' WHERE account = ' . $account;
	}
	$sql   .= 'ORDER BY hostname LIMIT ' . ($num_rows*(get_request_var('page')-1)) . ', ' . $num_rows;
	$result = db_fetch_assoc($sql);

	$total_rows = db_fetch_cell('SELECT COUNT(*) FROM plugin_routerconfigs_devices' . ($account != '' ? ' WHERE account = ' . $account:''));

	$url_page_select = get_page_list(get_request_var('page'), MAX_DISPLAY_PAGES, $num_rows, $total_rows, 'router-devices.php?' . ($account != '' ? 'account=' . $account:''));

	html_start_box('', '100%', '', '4', 'center', '');

	$nav = html_nav_bar('router-devices.php', MAX_DISPLAY_PAGES, get_request_var('page'), '20', $total_rows, 10, 'Devices', 'page', 'main');

	print $nav;

	html_header_checkbox(array('Actions', 'Hostname', 'Configs', 'IP Address', 'Directory', 'Last Backup', 'Last Change', 'Changed By', 'Enabled'));

	if (sizeof($result)) {
		foreach ($result as $row) {
			form_alternate_row('line' . $row['id'], false);

			$total = db_fetch_cell('SELECT count(device) FROM plugin_routerconfigs_backups WHERE device=' . $row['id']);

			$cell = '<a class="hyperLink" href="telnet://' . $row['ipaddress'] .'"><img border=0 src="images/telnet.jpeg" height=10 alt="Telnet" title="Telnet"></a>';
			if (file_exists($config['base_path'] . '/plugins/traceroute/tracenow.php')) {
				$cell .= '<a class="hyperLink" href="' . htmlspecialchars($config['url_path'] . 'plugins/traceroute/tracenow.php?ip=' . $row['ipaddress']) .'"><img border=0 src="images/reddot.png" height=10 alt="Trace Route" title="Trace Route"></a>';
			}
			$cell .= '<a class="hyperLink" href="router-devices.php?action=viewdebug&id=' . $row['id'] . '"><img border=0 src="images/feedback.jpg" height=10 alt="Router Debug Info" title="Router Debug Info"></a>';

			form_selectable_cell($cell, $row['id'], '', 'width:1%;');
			form_selectable_cell('<a class="linkEditMain" href="router-devices.php?&action=edit&id=' . $row['id'] . '">' . $row['hostname'] . '</a>', $row['id']);
			form_selectable_cell("<a class='hyperLink' href='router-devices.php?action=viewconfig&id=" . $row['id'] . "'>Current</a> - <a class='hyperLink' href='router-backups.php?device=" . $row['id'] . "'>Backups ($total)</a>", $row['id']);
			form_selectable_cell($row['ipaddress'], $row['id']);
			form_selectable_cell($row['directory'], $row['id']);
			form_selectable_cell(($row['lastbackup'] < 1 ? '' : date('M j Y H:i:s', $row['lastbackup'])), $row['id']);
			form_selectable_cell(($row['lastchange'] < 1 ? '' : date('M j Y H:i:s', $row['lastchange'])), $row['id']);
			form_selectable_cell($row['username'], $row['id']);
			form_selectable_cell(($row['enabled'] == 'on' ? 'Yes' : '<span class="deviceDown"><b>No</b></span>'), $row['id']);
			form_checkbox_cell($row['hostname'], $row['id']);
			form_end_row();
		}
	}else{
		form_alternate_row();
		print '<td colspan="5">No Router Devices Found</td>';
		form_end_row();
	}

	html_end_box(false);

	draw_actions_dropdown($ds_actions);

	print "&nbsp;&nbsp;&nbsp;<input type='button' value='Add' onClick='cactiReturnTo(\"router-devices.php?action=edit\")'>";
}

