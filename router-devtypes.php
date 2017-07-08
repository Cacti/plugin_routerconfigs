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
include_once('./include/auth.php');
include_once($config['base_path'] . '/plugins/routerconfigs/functions.php');

$ds_actions = array(
	1 => __('Delete', 'routerconfigs'),
);

set_default_action();

set_request_var('tab', 'devtypes');

$acc = array('None');
$accounts = db_fetch_assoc('SELECT id, name
	FROM plugin_routerconfigs_accounts
	ORDER BY name');

if (!empty($accounts)) {
	foreach ($accounts as $a) {
		$acc[$a['id']] = $a['name'];
	}
}

$dtypes = array(__('Auto-Detect', 'routerconfigs'));
$dtypesarr = db_fetch_assoc('SELECT id, name
	FROM plugin_routerconfigs_devicetypes
	ORDER BY name');

if (!empty($dtypes)) {
	if (sizeof($dtypesarr)) {
		foreach ($dtypesarr as $a) {
			$dtypes[$a['id']] = $a['name'];
		}
	}
}

$devicetype_edit = array(
    'name' => array(
        'method' => 'textbox',
        'friendly_name' => __('Name', 'routerconfigs'),
        'description' => __('Name of this device type.', 'routerconfigs'),
        'value' => '|arg1:name|',
		'size' => '30',
        'max_length' => '64',
    ),
    'username' => array(
        'method' => 'textbox',
        'friendly_name' => __('Username Prompt', 'routerconfigs'),
        'description' => __('This is the username prompt to match on login.', 'routerconfigs'),
        'value' => '|arg1:username|',
		'size' => '20',
        'max_length' => '64',
    ),
    'password' => array(
        'method' => 'textbox',
        'friendly_name' => __('Password Prompt', 'routerconfigs'),
        'description' => __('This is the password prompt to match on login.', 'routerconfigs'),
        'value' => '|arg1:password|',
		'size' => '20',
        'max_length' => '255',
    ),
    'copytftp' => array(
        'method' => 'textbox',
        'friendly_name' => __('Copy TFTP', 'routerconfigs'),
        'description' => __('This is the CLI text used to send the backup to tftp server.', 'routerconfigs'),
        'value' => '|arg1:copytftp|',
		'size' => '20',
        'max_length' => '64',
    ),
    'version' => array(
        'method' => 'textbox',
        'friendly_name' => __('Show Version', 'routerconfigs'),
        'description' => __('This is the CLI text used to display the current version.', 'routerconfigs'),
        'value' => '|arg1:version|',
		'size' => '20',
        'max_length' => '64',
    ),
    'confirm' => array(
        'method' => 'textbox',
        'friendly_name' => __('Confirm', 'routerconfigs'),
        'description' => __('Is there a confirmation prompt for copying the config?', 'routerconfigs'),
        'value' => '|arg1:confirm|',
		'size' => '10',
        'max_length' => '64',
    ),
    'forceconfirm' => array(
        'method' => 'checkbox',
        'friendly_name' => __('Force Confirm', 'routerconfigs'),
        'description' => __('Is there a force confirmation prompt for copying the config?', 'routerconfigs'),
        'value' => '|arg1:forceconfirm|',
        'default' => 'on',
    ),
    'checkendinconfig' => array(
        'method' => 'checkbox',
        'friendly_name' => __('Check End in Config', 'routerconfigs'),
        'description' => __('Check end in config?', 'routerconfigs'),
        'value' => '|arg1:checkendinconfig|',
		'default' => 'on'
    ),
    'id' => array(
        'method' => 'hidden_zero',
        'value' => '|arg1:id|'
    )
);

switch (get_request_var('action')) {
	case 'actions':
		actions_devicetypes();

		break;
	case 'save':
		save_devicetypes();

		break;
	case 'edit':
        general_header();
		display_tabs ();
		edit_devicetypes();
		bottom_footer();

		break;
	default:
        general_header();
		display_tabs ();
		show_devicetypes ();
		bottom_footer();

		break;
}

function actions_devicetypes () {
    global $ds_actions, $config;

    if (isset_request_var('selected_items')) {
		$selected_items = sanitize_unserialize_selected_items(get_nfilter_request_var('selected_items'));

		if ($selected_items != false) {
			if (get_nfilter_request_var('drp_action') == '1') {
				for ($i = 0; $i < count($selected_items); $i++) {
					db_execute_prepared('DELETE FROM plugin_routerconfigs_devicetypes
						WHERE id = ?',
						array($selected_items[$i]));
				}
			}
		}

		header('Location: router-devtypes.php?header=false');
		exit;
	}

	/* setup some variables */
	$devtype_list = '';
	$devtype_array = array();

	/* loop through each of the devices selected on the previous page and get more info about them */
	while (list($var, $val) = each($_POST)) {
		if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
			/* ================= input validation ================= */
			input_validate_input_number($matches[1]);
			/* ==================================================== */

			$devtype_list .= '<li>' . db_fetch_cell_prepared('SLECT name FROM plugin_routerconfigs_devicetypes WHERE id = ?', array($matches[1])) . '</li>';
			$devtype_array[] = $matches[1];
		}
	}

	top_header();

	form_start('router-devtypes.php');

	if (get_nfilter_request_var('drp_action') > 0) {
		html_start_box($ds_actions{get_nfilter_request_var('drp_action')}, '60%', '', '3', 'center', '');
	}else{
		html_start_box('', '60%', '', '3', 'center', '');
	}

	if (sizeof($devtype_array)) {
		if (get_nfilter_request_var('drp_action') == '1') { /* Delete */
			print "<tr>
				<td colspan='2' class='textArea'>
					<p>" . __('When you click \'Continue\', the following device(s) will be deleted.', 'routerconfigs') . "</p>
					<ul>$devtype_list</ul>
				</td>
			</tr>";
		}

		$save_html = "<input type='button' value='" . __esc('Cancel', 'routerconfigs') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' value='" . __esc('Continue', 'routerconfigs') . "' title='" . __esc('Delete Device(s)', 'routerconfigs') . "'>";
	} else {
		print "<tr><td class='even'><span class='textError'>You must select at least one device for this function.</span></td></tr>\n";

		$save_html = "<input type='button' value='Return' onClick='cactiReturnTo()'>";
	}

	print "<tr>
		<td class='saveRow'>
			<input type='hidden' name='action' value='actions'>
			<input type='hidden' name='selected_items' value='" . (isset($devtype_array) ? serialize($devtype_array) : '') . "'>
			<input type='hidden' name='drp_action' value='" . get_request_var('drp_action') . "'>
			$save_html
		</td>
	</tr>";

	html_end_box();

	form_end();

	bottom_footer();
}

function save_devicetypes () {
	/* ================= input validation ================= */
	get_filter_request_var('id');
	/* ==================================================== */

	if (isset_request_var('id')) {
		$save['id'] = get_request_var('id');
	} else {
		$save['id'] = '';
	}

	$save['name']         = get_nfilter_request_var('name');
	$save['username']     = get_nfilter_request_var('username');
	$save['password']     = get_nfilter_request_var('password');
	$save['copytftp']     = get_nfilter_request_var('copytftp');
	$save['version']      = get_nfilter_request_var('version');
	$save['confirm']      = get_nfilter_request_var('confirm');
	$save['forceconfirm'] = get_nfilter_request_var('forceconfirm');
    $save['checkendinconfig'] = get_nfilter_request_var('checkendinconfig');

	$id = sql_save($save, 'plugin_routerconfigs_devicetypes', 'id');

	if (is_error_message()) {
		header('Location: router-devtypes.php?action=edit&id=' . (empty($id) ? get_request_var('id') : $id));
		exit;
    }

    header('Location: router-devtypes.php?header=true');
    exit;
}

function edit_devicetypes () {
	global $devicetype_edit; global $config; global $form_id;

	/* ================= input validation ================= */
	get_filter_request_var('id');
	/* ==================================================== */

	$devicetype = array();
	if (!isempty_request_var('id')) {
		$devicetype = db_fetch_row_prepared('SELECT *
			FROM plugin_routerconfigs_devicetypes
			WHERE id = ?',
			array(get_request_var('id')));

		$header_label = __('Query [edit: %s]', $devicetype['name'], 'routerconfigs');
	}else{
		$header_label = __('Query [new]', 'routerconfigs');
	}

	form_start('router-devtypes.php', 'chk');

	html_start_box($header_label, '100%', '', '3', 'center', '');

   	draw_edit_form(
		array(
			'config' => array('no_form_tag' => true),
			'fields' => inject_form_variables($devicetype_edit, $devicetype)
		)
	);

	html_end_box();

	form_save_button('router-devtypes.php');
}

function show_devicetypes() {
	global $host, $username, $password, $command;
	global $config, $ds_actions, $acc, $form_id;

	/* ================= input validation ================= */
	get_filter_request_var('account');
	get_filter_request_var('page');
	/* ==================================================== */

	$account = '';

	if (isset_request_var('account')) {
		$account = get_request_var('account');
	}

	load_current_session_value('page', 'sess_routerconfigs_devtypes_current_page', '1');
	$num_rows = 30;

	$result = db_fetch_assoc('SELECT *
		FROM plugin_routerconfigs_devicetypes
		ORDER BY id
		LIMIT ' . ($num_rows*(get_request_var('page')-1)) . ', ' . $num_rows);

	$total_rows = db_fetch_cell('SELECT COUNT(*)
		FROM plugin_routerconfigs_devicetypes' . ($account != '' ? ' WHERE account = ' . $account:''));

	$nav = html_nav_bar('router-devtypes.php', MAX_DISPLAY_PAGES, get_request_var('page'), $num_rows, $total_rows, 11, __('Device Types', 'routerconfigs'), 'page', 'main');

	form_start('router-devtypes.php', 'chk');

    html_start_box(__('View Router Device Types', 'routerconfigs'), '100%', '', '4', 'center', 'router-devtypes.php?action=edit');

	print $nav;

	html_header_checkbox(
		array(
			__('Actions', 'routerconfigs'),
			__('ID', 'routerconfigs'),
			__('Name', 'routerconfigs'),
			__('Username', 'routerconfigs'),
			__('Password', 'routerconfigs'),
			__('Copy TFTP', 'routerconfigs'),
			__('Version', 'routerconfigs'),
			__('Confirm', 'routerconfigs'),
			__('Force Confirm', 'routerconfigs'),
			__('Check End In Config', 'routerconfigs')
		)
	);

	if (sizeof($result)) {
		foreach ($result as $row) {
			form_alternate_row('line' . $row['id'], false);

			$cell = '<a class="hyperLink" href="' . htmlspecialchars('router-devtypes.php?action=edit&id=' . $row['id']) . '"><img src="' . $config['url_path'] . 'plugins/routerconfigs/images/feedback.jpg" height=10 title="' . __esc('Edit Device Type', 'routerconfigs') . '"></a>';
            form_selectable_cell($cell, $row['id'], '1%', 'left');
            form_selectable_cell($row['id'], $row['id']);
			form_selectable_cell('<a class="linkEditMain" href="' . htmlspecialchars('router-devtypes.php?&action=edit&id=' . $row['id']) . '">' . $row['name'] . '</a>', $row['id']);
			form_selectable_cell($row['username'], $row['id']);
			form_selectable_cell($row['password'], $row['id']);
			form_selectable_cell($row['copytftp'], $row['id']);
            form_selectable_cell($row['version'], $row['id']);
            form_selectable_cell(($row['confirm'] == 'y' ? '<span class="deviceUp">' . __('Yes', 'routerconfigs') . '</span>' : '<span class="deviceDown">' . __('No', 'routerconfigs') . '</span>'), $row['id']);
            form_selectable_cell(($row['forceconfirm'] == 'on' ? '<span class="deviceUp">' . __('Yes', 'routerconfigs') . '</span>' : '<span class="deviceDown">' . __('No', 'routerconfigs') . '</span>'), $row['id']);
			form_selectable_cell(($row['checkendinconfig'] == 'on' ? '<span class="deviceUp">' . __('Yes', 'routerconfigs') . '</span>' : '<span class="deviceDown">' . __('No', 'routerconfigs') . '</span>'), $row['id']);
			form_checkbox_cell($row['name'], $row['id']);
			form_end_row();
		}
	}else{
		print "<tr class='even'><td colspan='11'>" . __('No Router Device Types Found', 'routerconfigs') . "</td></tr>\n";
	}

	html_end_box(false);

	draw_actions_dropdown($ds_actions);
}

