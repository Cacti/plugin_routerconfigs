<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2007-2019 The Cacti Group                                 |
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
include_once(__DIR__ . '/include/functions.php');

set_default_action();

if (isset_request_var('password')) {
	$password = get_nfilter_request_var('password');
}else{
	$password = '';
}

if (isset_request_var('username')) {
	$username = get_nfilter_request_var('username');
}else{
	$username = '';
}

switch (get_request_var('action')) {
	case 'actions':
		actions_accounts();
		break;
	case 'save':
		save_accounts ();
		break;
	case 'edit':
		top_header();

		display_tabs ();
		edit_accounts();

		bottom_footer();
		break;
	default:
		top_header();

		display_tabs ();
		show_accounts ();

		bottom_footer();
		break;
}

function actions_accounts () {
	global $rc_account_actions, $config;

	if (isset_request_var('selected_items')) {
		$selected_items = sanitize_unserialize_selected_items(get_nfilter_request_var('selected_items'));

		if ($selected_items != false) {
			if (get_nfilter_request_var('drp_action') == RCONFIG_ACCOUNT_DELETE) {
				for ($i=0; $i<count($selected_items); $i++) {
					db_execute('DELETE FROM plugin_routerconfigs_accounts WHERE id = ' . $selected_items[$i]);
				}
			}
		}

		header('Location: router-accounts.php?header=false');
		exit;
	}

	/* setup some variables */
	$account_list  = '';
	$account_array = array();

	/* loop through each of the accounts selected on the previous page and get more info about them */
	foreach ($_POST as $var => $val) {
		if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
			/* ================= input validation ================= */
			input_validate_input_number($matches[1]);
			/* ==================================================== */

			$account_list .= '<li>' . db_fetch_cell('SELECT name FROM plugin_routerconfigs_accounts WHERE id=' . $matches[1]) . '</li>';
			$account_array[] = $matches[1];
		}
	}

	top_header();

	form_start('router-accounts.php');

	if (get_nfilter_request_var('drp_action') > 0) {
		html_start_box($rc_account_actions{get_nfilter_request_var('drp_action')}, '60%', '', '3', 'center', '');
	}else{
		html_start_box('', '60%', '', '3', 'center', '');
	}

	if (sizeof($account_array)) {
		if (get_nfilter_request_var('drp_action') == RCONFIG_ACCOUNT_DELETE) { /* Delete */
			print "<tr>
				<td colspan='2' class='textArea'>
					<p>" . __('Click \'Continue\' to delete the following account(s).', 'routerconfigs') . "</p>
					<p><ul>$account_list</ul></p>
				</td>
			</tr>";
		}

		$save_html = "<input type='button' value='" . __esc('Cancel', 'routerconfigs') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' value='" . __esc('Continue', 'routerconfigs') . "' title='" . __esc('Delete Device(s)', 'routerconfigs') . "'>";
	}else{
		print "<tr><td class='even'><span class='textError'>" . __('You must select at least one device.', 'routerconfigs') . "</span></td></tr>\n";
		$save_html = "<input type='button' value='" . __esc('Return', 'routerconfigs') . "' onClick='cactiReturnTo()'>";
	}

	print "<tr>
		<td class='saveRow'>
			<input type='hidden' name='action' value='actions'>
			<input type='hidden' name='selected_items' value='" . (isset($account_array) ? serialize($account_array) : '') . "'>
			<input type='hidden' name='drp_action' value='" . get_nfilter_request_var('drp_action') . "'>
			$save_html
		</td>
	</tr>";

	html_end_box();

	form_end();

	bottom_footer();
}

function save_accounts () {
	/* ================= input validation ================= */
	get_filter_request_var('id');
	/* ==================================================== */

	$save['id']       = get_request_var('id');
	$save['name']     = get_nfilter_request_var('name');
	$save['username'] = get_nfilter_request_var('username');
	$save['elevated'] = get_nfilter_request_var('elevated');

	if (get_nfilter_request_var('password') == get_nfilter_request_var('password_confirm')) {
		if (!isempty_request_var('password')) {
			$save['password'] = plugin_routerconfigs_encode(get_nfilter_request_var('password'));
		} else if ($save['id'] < 1) {
			raise_message(4);
		}
	} else {
		raise_message(4);
	}

	if (get_nfilter_request_var('enablepw') == get_nfilter_request_var('enablepw_confirm')) {
		if (!isempty_request_var('enablepw')) {
			$save['enablepw'] = plugin_routerconfigs_encode(get_nfilter_request_var('enablepw'));
		} else {
			$save['enablepw'] = '';
		}
	} else {
		raise_message(4);
	}

	$id = sql_save($save, 'plugin_routerconfigs_accounts', 'id');

	if (is_error_message()) {
		header('Location: router-accounts.php?header=false&action=edit&id=' . (empty($id) ? get_request_var('id') : $id));
		exit;
	}

	header('Location: router-accounts.php?header=fasle');
	exit;
}

function edit_accounts () {
	global $rc_account_edit_fields;

	/* ================= input validation ================= */
	get_filter_request_var('id');
	/* ==================================================== */

	$account = array();
	if (!isempty_request_var('id')) {
		$account = db_fetch_row('SELECT * FROM plugin_routerconfigs_accounts WHERE id=' . get_request_var('id'), FALSE);
		$account['password'] = '';
		$header_label = __('Account: [edit: %s]', $account['name'], 'routerconfigs');
	}else{
		$header_label = __('Account: [new]', 'routerconfigs');
	}

	form_start('router-accounts.php', 'chk');

	html_start_box($header_label, '100%', '', '3', 'center', '');

	draw_edit_form(
		array(
			'config' => array('no_form_tag' => true),
			'fields' => inject_form_variables($rc_account_edit_fields, $account)
		)
	);

	html_end_box();

	form_save_button('router-accounts.php');
}

function show_accounts () {
	global $host, $username, $password, $command;
	global $config, $rc_account_actions;

	get_filter_request_var('page');
	load_current_session_value('page', 'sess_wmi_accounts_current_page', '1');
	$num_rows = 30;

	$result = db_fetch_assoc('SELECT *
		FROM plugin_routerconfigs_accounts
		LIMIT ' . ($num_rows*(get_request_var('page')-1)) . ", $num_rows");

	$total_rows = db_fetch_cell('SELECT COUNT(*)
		FROM plugin_routerconfigs_accounts');

	$nav = html_nav_bar('router-accounts.php', MAX_DISPLAY_PAGES, get_request_var('page'), $num_rows, $total_rows, 5, __('Accounts', 'routerconfigs'), 'page', 'main');

	form_start('router-accounts.php', 'chk');

	html_start_box(__('Account Management', 'routerconfigs'), '100%', '', '4', 'center', 'router-accounts.php?action=edit');

	print $nav;

	html_header_checkbox(array(__('Description', 'routerconfigs'), __('Username', 'routerconfigs'), __('Devices', 'routerconfigs')));

	$c=0;
	if (sizeof($result)) {
		foreach ($result as $row) {
			$count = db_fetch_cell_prepared('SELECT count(account) FROM plugin_routerconfigs_devices WHERE account = ?', array($row['id']));

			form_alternate_row('line' . $row['id'], false);
			form_selectable_cell('<a class="linkEditMain" href="router-accounts.php?&action=edit&id=' . $row['id'] . '">' . $row['name'] . '</a>', $row['id']);
			form_selectable_cell($row['username'], $row['id']);
			form_selectable_cell('<a class="hyperLink" href="router-devices.php?account=' . $row['id'] . '">' . $count . '</a>', $row['id']);
			form_checkbox_cell($row['name'], $row['id']);
			form_end_row();
		}
	}else{
		form_alternate_row();
		print '<td colspan="10"><em>' . __('No Router Accounts Found', 'routerconfigs') . '</em></td>';
		form_end_row();
	}

	html_end_box(false);

	draw_actions_dropdown($rc_account_actions);

	form_end();
}
