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
include_once($config['library_path'] . '/poller.php');
include_once(__DIR__ . '/include/functions.php');

set_default_action();

set_request_var('tab', 'devices');

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
	plugin_routerconfigs_view_device_config(null, get_request_var('id'), 'router-devices.php');
}

function actions_devices() {
	global $rc_device_actions, $config;
	if (isset_request_var('selected_items')) {
		$selected_items = sanitize_unserialize_selected_items(get_nfilter_request_var('selected_items'));

		if ($selected_items != false) {
			switch(get_nfilter_request_var('drp_action')) {
			case RCONFIG_DEVICE_DELETE:
				for ($i=0; $i<count($selected_items); $i++) {
					db_execute_prepared('DELETE FROM plugin_routerconfigs_devices
						WHERE id = ?',
						array($selected_items[$i]));
				}

				break;
			case RCONFIG_DEVICE_ENABLE:
				for ($i=0; $i<count($selected_items); $i++) {
					db_execute_prepared('UPDATE plugin_routerconfigs_devices
						SET enabled="on"
						WHERE id = ?',
						array($selected_items[$i]));
				}

				break;
			case RCONFIG_DEVICE_DISABLE:
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
	foreach ($_POST as $var => $val) {
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
		if (get_nfilter_request_var('drp_action') == RCONFIG_DEVICE_BACKUP) { /* Backup */
			$command_string = trim(read_config_option('path_php_binary'));

			if (trim($command_string) == '') {
			        $command_string = 'php';
			}

			$extra_args = ' -q ' . $config['base_path'] . '/plugins/routerconfigs/router-download.php' .
				' --devices=' . implode(',',$device_array);

			plugin_routerconfigs_log(__("DEBUG: Executing manual backup using '%s' with arguments '%s'",$command_string,$extra_args,'routerconfigs'));
			exec_background($command_string, $extra_args);
			header('Location: router-devices.php?header=false');
			exit;
		}
	}

	top_header();

	form_start('router-devices.php');

	if (get_nfilter_request_var('drp_action') > 0) {
		html_start_box($rc_device_actions{get_nfilter_request_var('drp_action')}, '60%', '', '3', 'center', '');
	}else{
		html_start_box('', '60%', '', '3', 'center', '');
	}

	if (sizeof($device_array)) {
		switch (get_nfilter_request_var('drp_action')) {
		case RCONFIG_DEVICE_DELETE:
			print "<tr>
				<td colspan='2' class='textArea'>
					<p>" . __('Click \'Continue\' to Delete the following device(s).', 'routerconfigs') . "</p>
					<p><ul>$device_list</ul></p>
				</td>
			</tr>";
			$save_html = "<input type='button' value='" . __esc('Cancel', 'routerconfigs') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' value='" . __esc('Continue', 'routerconfigs') . "' title='" . __esc('Delete Device(s)', 'routerconfigs') . "'>";
			break;
		case RCONFIG_DEVICE_ENABLE:
			print "<tr>
				<td colspan='2' class='textArea'>
					<p>" . __('Click \'Continue\' to Enable the following device(s).', 'routerconfigs') . "</p>
					<p><ul>$device_list</ul></p>
				</td>
			</tr>";
			$save_html = "<input type='button' value='" . __esc('Cancel', 'routerconfigs') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' value='" . __esc('Continue', 'routerconfigs') . "' title='" . __esc('Enable Device(s)', 'routerconfigs') . "'>";
			break;
		case RCONFIG_DEVICE_DISABLE:
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

	$save['hostname']    = get_nfilter_request_var('hostname');
	$save['ipaddress']   = get_nfilter_request_var('ipaddress');
	$save['directory']   = get_nfilter_request_var('directory');
	$save['account']     = get_nfilter_request_var('account');
	$save['devicetype']  = get_nfilter_request_var('devicetype');
	$save['schedule']    = get_nfilter_request_var('schedule');
	$save['connecttype'] = get_nfilter_request_var('connecttype');
	$save['timeout']     = get_nfilter_request_var('timeout');
	$save['sleep']       = get_nfilter_request_var('sleep');
	$save['elevated']    = get_nfilter_request_var('elevated');

	$id = sql_save($save, 'plugin_routerconfigs_devices', 'id');

	if (is_error_message()) {
		header('Location: router-devices.php?header=false&action=edit&id=' . (empty($id) ? get_request_var('id') : $id));
		exit;
	}

	header('Location: router-devices.php?header=false');
	exit;

}

function edit_devices () {
	global $rc_device_edit_fields;

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
			'fields' => inject_form_variables($rc_device_edit_fields, $account)
		)
	);

	html_end_box();

	form_save_button('router-devices.php');
}

function devices_validate_vars() {
	/* ================= input validation and session storage ================= */
	$filters = array(
		'rows' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			),
		'page' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => '1'
			),
		'filter' => array(
			'filter' => FILTER_CALLBACK,
			'pageset' => true,
			'default' => '',
			'options' => array('options' => 'sanitize_search_string')
			),
		'sort_column' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'hostname',
			'options' => array('options' => 'sanitize_search_string')
			),
		'sort_direction' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'ASC',
			'options' => array('options' => 'sanitize_search_string')
			),
		'devicetype' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			),
		'elevated' => array(
			'filter' => FILTER_CALLBACK,
			'default' => '',
			'options' => array('options' => 'sanitize_search_string')
			),
		'account' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			),
	);

	validate_store_request_vars($filters, 'sess_routerconfigs_devices');
	/* ================= input validation ================= */
}

function addDateToArray(&$date_array, $date, $text) {
	if (!array_key_exists($date, $date_array)) {
		$date_array[$date] = $text;
	}
}

function show_devices() {
	global $host, $username, $password, $command;
	global $config, $rc_device_actions, $item_rows;

	devices_validate_vars();

	get_filter_request_var('account');
	get_filter_request_var('page');

	if (get_request_var('rows') == -1) {
		$num_rows = read_config_option('num_rows_table');
	} else {
		$num_rows = get_request_var('rows');
	}

	if (isset_request_var('page')) {
		$page = get_request_var('page');
	}else{
		$page = 1;
	}

	load_current_session_value('page', 'sess_routerconfigs_devices_current_page', '1');

	$devicetype = '';
	if (isset_request_var('devicetype')) {
		$devicetype = get_filter_request_var('devicetype');

		if (isset($_SESSION['routerconfigs_devices_devicetype']) && $_SESSION['routerconfigs_devices_devicetype'] != $devicetype) {
			$page = 1;
			set_request_var('page','1');
		}

		$_SESSION['routerconfigs_devices_devicetype'] = $devicetype;
	} else if (isset($_SESSION['routerconfigs_devices_devicetype']) && $_SESSION['routerconfigs_devices_devicetype'] != '') {
		$devicetype = $_SESSION['routerconfigs_devices_devicetype'];
	}

	$account = '';
	if (isset_request_var('account')) {
		$account = get_filter_request_var('account');

		if (isset($_SESSION['routerconfigs_devices_account']) && $_SESSION['routerconfigs_devices_account'] != $account) {
			$page = 1;
			set_request_var('page','1');
		}

		$_SESSION['routerconfigs_devices_account'] = $account;
	} else if (isset($_SESSION['routerconfigs_devices_account']) && $_SESSION['routerconfigs_devices_account'] != '') {
		$account = $_SESSION['routerconfigs_devices_account'];
	}

	$sqlwhere = '';
	if ($account > 0) {
		$sqlwhere .= ($sqlwhere == ''?'WHERE':'AND') . ' account = ' . $account;
	}

	if ($devicetype > 0) {
		$sqlwhere .= ($sqlwhere == ''?'WHERE':'AND') . ' devicetype = ' . $devicetype;
	}

	$total_rows = db_fetch_cell('SELECT COUNT(*) FROM plugin_routerconfigs_devices ' . $sqlwhere);

	$sort_column = get_request_var('sort_column');
	$sort_direction = get_request_var('sort_direction');
	$sort_limit = $num_rows*($page-1);

	$sql = "SELECT * FROM (SELECT
			rc_d.id, rc_d.enabled, rc_d.ipaddress, rc_d.hostname, rc_d.directory,
			rc_d.account, rc_d.lastchange, rc_d.device,
			rc_d.lastuser, rc_d.schedule, rc_d.lasterror,
			rc_d.lastbackup, rc_d.nextbackup, rc_d.lastattempt,
			rc_d.nextattempt, IFNULL(rc_dt.name,'') as devicetype,
			rc_dt.id as devicetypeid,
			IF(IFNULL(rc_d.connecttype,'')='',
				IF(IFNULL(rc_dt.connecttype,'')='',
					rc_sc.value,
					rc_dt.connecttype
				),
				rc_d.connecttype
			) AS connecttype,
			IF(IFNULL(rc_d.sleep,'')='',
				IF(IFNULL(rc_dt.sleep,'')='',
					rc_ss.value,
					rc_dt.sleep
				),
				rc_d.sleep
			) AS sleep,
			IF(IFNULL(rc_d.timeout,'')='',
				IF(IFNULL(rc_dt.timeout,'')='',
					rc_sc.value,
					rc_dt.timeout
				),
				rc_d.timeout
			) AS timeout,
			debug
		FROM plugin_routerconfigs_devices rc_d
		LEFT JOIN plugin_routerconfigs_devicetypes rc_dt
		ON rc_dt.id = rc_d.devicetype
		LEFT JOIN plugin_routerconfigs_accounts rc_da
		ON rc_da.id = rc_d.account
		LEFT JOIN settings rc_ss
		ON rc_ss.name = 'routerconfigs_sleep'
		LEFT JOIN settings rc_st
		ON rc_st.name = 'routerconfigs_timeout'
		LEFT JOIN settings rc_sc
		ON rc_sc.name = 'routerconfigs_connecttype'
		$sqlwhere
		) AS t ORDER BY $sort_column $sort_direction
		LIMIT $sort_limit, $num_rows";

	$result = db_fetch_assoc($sql);

	?>
	<script type='text/javascript'>

	function applyFilter() {
		strURL  = 'router-devices.php?header=false'
		strURL += '&devicetype=' + $('#devicetype').val();
		strURL += '&account=' + $('#account').val();
		strURL += '&rows=' + $('#rows').val();
		strURL += '&filter=' + $('#filter').val();
		loadPageNoHeader(strURL);
	}

	function clearFilter() {
		strURL = 'router-backups.php?clear=1&header=false';
		loadPageNoHeader(strURL);
	}

	$(function() {
		$('#rows, #devicetype, #account, #filter').change(function() {
			applyFilter();
		});

		$('#refresh').click(function() {
			applyFilter();
		});

		$('#clear').click(function() {
			clearFilter();
		});

		$('#form_devices').submit(function(event) {
			event.preventDefault();
			applyFilter();
		});
	});

	</script>
	<?php

	html_start_box(__('Router Device Management', 'routerconfigs'), '100%', '', '4', 'center', 'router-devices.php?action=edit');


	?>
	<tr class='even noprint'>
		<td>
		<form id='form_devices' action='router-devicess.php'>
			<table class='filterTable'>
				<tr>
					<td>
						<?php print __('Device Type','routerconfigs');?>
					</td>
					<td>
						<select id='devicetype'>
							<option value='-1'<?php if (get_request_var('devicetype') == '-1') {?> selected<?php }?>><?php print __('Any','routerconfigs');?></option>
							<?php
							$devicetypes = db_fetch_assoc('SELECT id, name FROM plugin_routerconfigs_devicetypes ORDER BY name');

							if (sizeof($devicetypes)) {
								foreach ($devicetypes as $devicetype) {
									print "<option value='" . $devicetype['id'] . "'"; if (get_request_var('devicetype') == $devicetype['id']) { print ' selected'; } print '>' . htmlspecialchars($devicetype['name']) . "</option>\n";
								}
							}
							?>
						</select>
					</td>
					<td>
						<?php print __('Account','routerconfigs');?>
					</td>
					<td>
						<select id='account'>
							<option value='-1'<?php if (get_request_var('account') == '-1') {?> selected<?php }?>><?php print __('Any','routerconfigs');?></option>
							<?php
							$accounts = db_fetch_assoc('SELECT id, name FROM plugin_routerconfigs_accounts ORDER BY name');

							if (sizeof($accounts)) {
								foreach ($accounts as $account) {
									print "<option value='" . $account['id'] . "'"; if (get_request_var('account') == $account['id']) { print ' selected'; } print '>' . htmlspecialchars($account['name']) . "</option>\n";
								}
							}
							?>
						</select>
					</td>
					<td>
						<?php print __('Search','routerconfigs');?>
					</td>
					<td>
						<input id='filter' type='text' size='25' value='<?php print html_escape_request_var('filter');?>'>
					</td>
					<td>
						<select id='rows'>
							<option value='-1'<?php print (get_request_var('rows') == '-1' ? ' selected>':'>') . __('Default');?></option>
							<?php
							if (sizeof($item_rows)) {
								foreach ($item_rows as $key => $value) {
									print "<option value='" . $key . "'"; if (get_request_var('rows') == $key) { print ' selected'; } print '>' . htmlspecialchars($value) . "</option>\n";
								}
							}
							?>
						</select>
					</td>
					<td>
						<span>
							<input type='button' id='refresh' value='<?php print __('Go','routerconfigs');?>' title='<?php print __esc('Set/Refresh Filters','routerconfigs');?>'>
							<input type='button' id='clear' value='<?php print __('Clear','routerconfigs');?>' title='<?php print __esc('Clear Filters','routerconfigs');?>'>
						</span>
					</td>
				</tr>
			</table>
		</form>
		</td>
	</tr>
	<?php

	html_end_box();


	$display_text = array(
		'nosort_actions' => array(
			'display' => __('Actions', 'routerconfigs'),
		),
		'hostname' => array(
			'display' => __('Hostname', 'routerconfigs'),
			'align' => 'left',
			'sort' => 'ASC',
			'tip' => __('Either an IP address, or hostname.  If a hostname, it must be resolvable by either DNS, or from your hosts file.', 'routerconfigs')
		),
		'id' => array(
			'display' => __('ID','routerconfigs'),
			'align' => 'right',
			'sort' => 'ASC',
			'tip' => __('The internal database ID for this Device.  Useful when performing automation or debugging.', 'routerconfigs')
		),
		'enabled' => array(
			'display' => __('Enabled', 'routerconfigs'),
			'align' => 'left',
			'sort' => 'ASC',
		),
		'nosort_lastbackup' => array(
			'display' => __('Last Backup', 'routerconfigs'),
			'align' => 'center',
			'sort' => 'ASC',
			'tip' => __('The last Backup time of the device', 'routerconfigs')
		),
		'devicetype' => array(
			'display' => __('Device Type', 'routerconfigs'),
			'align' => 'left',
			'sort' => 'ASC',
			'tip' => __('The device type of the device', 'routerconfigs')
		),
		'connecttype' => array(
			'display' => __('Connect Type', 'routerconfigs'),
			'align' => 'left',
			'sort' => 'ASC',
			'tip' => __('The connection type for this device', 'routerconfigs')
		),
		'nosort_configs' => array(
			'display' => __('Configs', 'routerconfigs'),
			'align' => 'left',
			'tip' => __('The number of configurations for this device', 'routerconfigs')
		),
		'ipaddress' => array(
			'display' => __('IP Address', 'routerconfigs'),
			'align' => 'left',
			'sort' => 'ASC',
			'tip' => __('The IP address of this device', 'routerconfigs')
		),
		'nextbackup' => array(
			'display' => __('Next Backup', 'routerconfigs'),
			'align' => 'left',
			'sort' => 'DESC',
		),
		'lastbackup' => array(
			'display' => __('Date Backup', 'routerconfigs'),
			'align' => 'left',
			'sort' => 'DESC',
		),
		'nextattempt' => array(
			'display' => __('Next Attempt', 'routerconfigs'),
			'align' => 'left',
			'sort' => 'DESC',
		),
		'lastattempt' => array(
			'display' => __('Last Attempt', 'routerconfigs'),
			'align' => 'left',
			'sort' => 'DESC',
		),
		'lastchange' => array(
			'display' => __('Date Change', 'routerconfigs'),
			'align' => 'left',
			'sort' => 'ASC',
		),
		'changedby' => array(
			'display' => __('Changed By', 'routerconfigs'),
			'align' => 'left',
			'sort' => 'ASC',
		),
		'directory' => array(
			'display' => __('Directory', 'routerconfigs'),
			'align' => 'left',
			'sort' => 'ASC',
			'tip' => __('The directory of the stored device backups', 'routerconfigs')
		),
	);

	form_start('router-devices.php', 'chk');

	$nav = html_nav_bar('router-devices.php', MAX_DISPLAY_PAGES, get_request_var('page'), $num_rows, $total_rows, 10, 'Devices', 'page', 'main');
	print $nav;

	html_start_box('', '100%', '', '3', 'center', '');
	html_header_sort_checkbox($display_text, get_request_var('sort_column'), get_request_var('sort_direction'), false);

	if (sizeof($result)) {
		$do_today = new DateTime();
		$date_today = $do_today->format('Y-m-d \0\0:\0\0:\0\0');
		$do_yesterday = clone $do_today;
		$do_yesterday->modify('-1 day');
		$date_yesterday = $do_yesterday->format('Y-m-d \0\0:\0\0:\0\0');

		$day_of_week = $do_today->format("w");

		$do_week = new DateTime();
		$do_week->modify("-$day_of_week days");
		$date_week = $do_week->format('Y-m-d \0\0:\0\0:\0\0');

		$date_month = $do_today->format('Y-m-01 \0\0:\0\0:\0\0');
		$date_year = $do_today->modify('-1 year')->format('Y-m-d \0\0:\0\0:\0\0');

		$date_array = array();
		addDateToArray($date_array, $date_today, __('Today', 'routerconfigs'));
		addDateToArray($date_array, $date_yesterday, __('Yesterday', 'routerconfigs'));
		addDateToArray($date_array, $date_week, '<i>'.__('This Week', 'routerconfigs').'</i>');
		addDateToArray($date_array, $date_month, '<u>'.__('This Month', 'routerconfigs').'</i>');
		addDateToArray($date_array, $date_year, '<u><i>'.__('Within a Year', 'routerconfigs').'</i></u>');
		addDateToArray($date_array, '2000-01-01 00:00:00', '<b><i>'.__('Long, Long Ago', 'routerconfigs').'</b></i>');
		addDateToArray($date_array, '', '<b><u>Never</u></b>');

		foreach ($result as $row) {
			form_alternate_row('line' . $row['id'], false);

			$total = db_fetch_cell_prepared('SELECT count(device)
				FROM plugin_routerconfigs_backups
				WHERE device = ?',
				array($row['id']));

			$state = $date_array[''];
			if ($row['lastbackup'] > 0) {
				$do_backup = new DateTime();
				$do_backup->setTimestamp($row['lastbackup']);
				$date_backup = $do_backup->format('Y-m-d H:i:s');

				foreach ($date_array as $date_value=>$date_state) {
					if ($date_backup >= $date_value) {
						$state = $date_state;
						break;
					}
				}
			}

			if (empty($row['devicetype'])) {
				$row['devicetype'] = __('Auto-Detect', 'routerconfigs');
			}

			$enabled = ($row['enabled'] == 'on' ? '<span class="deviceUp">' . __('Yes', 'routerconfigs') . '</span>' : '<span class="deviceDown">' . __('No', 'routerconfigs') . '</span>');

			$cell = '';

			// Loop through all the classes to find the interfaces used by all of them
			// using the '+=' to merge/de-dupe
			$interfaces = array();
			$classes = PHPConnection::GetTypes($row['connecttype']);
			foreach ($classes as $className) {
				$interfaces += class_implements("$className");
			}

			// Loop through the interfaces left
			foreach ($interfaces as $interfaceName) {
				if ($interfaceName == 'ShellSsh') {
					$cell .= '<a class="hyperLink" href="ssh://' . $row['ipaddress'] .'"><img src="' . $config['url_path'] . 'plugins/routerconfigs/images/ssh.jpg" style="height:14px;" alt="" title="' . __esc('Connect via SSH', 'routerconfigs') . '"></a>';
				}

				if ($interfaceName == 'ShellTelnet') {
					$cell .= '<a class="hyperLink" href="telnet://' . $row['ipaddress'] .'"><img src="' . $config['url_path'] . 'plugins/routerconfigs/images/telnet.jpg" style="height:14px;" alt="" title="' . __esc('Connect via Telnet', 'routerconfigs') . '"></a>';
				}
			}


			if (file_exists($config['base_path'] . '/plugins/traceroute/tracenow.php')) {
				$cell .= '<a class="hyperLink" href="' . htmlspecialchars($config['url_path'] . 'plugins/traceroute/tracenow.php?ip=' . $row['ipaddress']) .'"><img src="' . $config['url_path'] . 'plugins/routerconfigs/images/reddot.png" height=14 alt="" title="' . __esc('Trace Route', 'routerconfigs') . '"></a>';
			}
			$cell .= '<a class="linkEditMain" href="router-devices.php?action=viewdebug&id=' . $row['id'] . '"><img src="' . $config['url_path'] . 'plugins/routerconfigs/images/feedback.jpg" height=14 alt="" title="' . __esc('Router Debug Info', 'routerconfigs') . '"></a>';
			form_selectable_cell($cell, $row['id'], '', 'width:1%;');

			form_selectable_cell(filter_value($row['hostname'], get_request_var('filter'), 'router-devices.php?&action=edit&id=' . $row['id']), $row['id'],'10%');
			form_selectable_cell(filter_value($row['id'], get_request_var('filter'), 'router-devices.php?&action=edit&id=' . $row['id']), $row['id'], '1%', 'text-align:right');
			form_selectable_cell($enabled, $row['id'], '5%', 'text-align:center');
			form_selectable_cell($state, $row['id'], '10%', 'text-align:center');
			form_selectable_cell(filter_value($row['devicetype'], get_request_var('filter'), 'router-devtypes.php?&action=edit&id=' . $row['devicetypeid']), $row['id'], '10%');
			form_selectable_cell(filter_value($row['connecttype'], get_request_var('filter')), $row['id'], '10%');

			form_selectable_cell(filter_value(__('Current', 'routerconfig'), get_request_var('filter'), 'router-devices.php?action=viewconfig&id=' . $row['id']).' - '.filter_value(__('Backups (%s)', $total, 'routerconfigs'), get_request_var('filter'), 'router-backups.php?device=' . $row['id']), $row['id'], '14%');

			form_selectable_cell(filter_value($row['ipaddress'], get_request_var('filter')), $row['id'], '5%');
			form_selectable_cell(filter_value(plugin_routerconfigs_date_from_time_with_na($row['nextbackup']), get_request_var('filter')), $row['id'], '10%');
			form_selectable_cell(filter_value(plugin_routerconfigs_date_from_time_with_na($row['lastbackup']), get_request_var('filter')), $row['id'], '10%');
			form_selectable_cell(filter_value(plugin_routerconfigs_date_from_time_with_na($row['nextattempt']), get_request_var('filter')), $row['id'], '10%');
			form_selectable_cell(filter_value(plugin_routerconfigs_date_from_time_with_na($row['lastattempt']), get_request_var('filter')), $row['id'], '10%');
			form_selectable_cell(filter_value(plugin_routerconfigs_date_from_time_with_na($row['lastchange']), get_request_var('filter')), $row['id'], '10%');
			form_selectable_cell(filter_value($row['lastuser'], get_request_var('filter')), $row['id'], '10%');
			form_selectable_cell(filter_value($row['directory'], get_request_var('filter')), $row['id']);
			form_checkbox_cell($row['hostname'], $row['id']);
			form_end_row();
		}
	}else{
		print "<tr class='even'><td colspan='13'>" . __('No Router Devices Found', 'routerconfigs') . "</td></tr>\n";
	}

	html_end_box(false);

	draw_actions_dropdown($rc_device_actions);

	form_end();
}

