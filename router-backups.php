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

switch (get_nfilter_request_var('action')) {
	case 'viewconfig':
		view_device_config();
		break;
	default:
		top_header();

		display_tabs();
		show_devices();

		bottom_footer();
		break;
}

function view_device_config() {
	/* ================= input validation ================= */
	get_filter_request_var('id');
	get_filter_request_var('device');
	/* ==================================================== */

	plugin_routerconfigs_view_device_config(get_filter_request_var('id'), get_filter_request_var('device'), 'router-backups.php');
}

function backups_validate_vars() {
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
			'default' => 'description',
			'options' => array('options' => 'sanitize_search_string')
			),
		'sort_direction' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'ASC',
			'options' => array('options' => 'sanitize_search_string')
			),
		'device' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			),
	);

	validate_store_request_vars($filters, 'sess_routerconfigs_backups');
	/* ================= input validation ================= */
}

function show_devices () {
	global $action, $device, $config, $item_rows;

	backups_validate_vars();

	/* if the number of rows is -1, set it to the default */
	if (get_request_var('rows') == -1) {
		$num_rows = read_config_option('num_rows_table');
	} else {
		$num_rows = get_request_var('rows');
	}

	if (get_filter_request_var('page') > 0) {
		$page = get_request_var('page');
	} else {
		$page = 1;
	}

	load_current_session_value('page', 'sess_routerconfigs_backups_current_page', '1');

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

	$sqlwhere = '';
	if ($device > '0') {
		$sqlwhere = 'WHERE prb.device = ' . $device;
	}
	$sql = 'SELECT prd.hostname, prd.ipaddress, prb.id, prb.lastuser, prb.lastchange,
		prb.btime, prb.device, prb.directory, prb.filename, prd.lastbackup
		FROM plugin_routerconfigs_devices AS prd
		INNER JOIN plugin_routerconfigs_backups AS prb
		ON prd.id = prb.device
		'.$sqlwhere.'
		ORDER BY prb.btime DESC
		LIMIT ' . ($num_rows*(get_request_var('page')-1)) . ', ' . $num_rows;

	$result = db_fetch_assoc($sql);

	$total_rows = sizeof($result);

	?>
	<script type='text/javascript'>

	function applyFilter() {
		strURL  = 'router-backups.php?device=' + $('#device').val();
		strURL += '&rows=' + $('#rows').val();
		strURL += '&filter=' + $('#filter').val();
		strURL += '&header=false';
		loadPageNoHeader(strURL);
	}

	function clearFilter() {
		strURL = 'router-backups.php?clear=1&header=false';
		loadPageNoHeader(strURL);
	}

	$(function() {
		$('#rows, #device, #filter').change(function() {
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

	html_start_box(__('Router Backups', 'routerconfigs'), '100%', '', '4', 'center', '');

	?>
	<tr class='even noprint'>
		<td>
		<form id='form_devices' action='router-backups.php'>
			<table class='filterTable'>
				<tr>
					<td>
						<select id='device'>
							<option value='-1'<?php if (get_request_var('device') == '-1') {?> selected<?php }?>><?php print __('Any','routerconfigs');?></option>
							<?php
							$devices = db_fetch_assoc('SELECT id, hostname FROM plugin_routerconfigs_devices ORDER BY hostname');

							if (sizeof($devices)) {
								foreach ($devices as $device) {
									print "<option value='" . $device['id'] . "'"; if (get_request_var('device') == $device['id']) { print ' selected'; } print '>' . htmlspecialchars($device['hostname']) . "</option>\n";
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
		'functions' => array(
			'display' => __('Functions', 'routerconfigs'),
			'align' => 'center',
			'sort' => 'ASC',
			'tip' => __('Perform functions against this backup','routerconfigs')
		),
		'directory' => array(
			'display' => __('Directory', 'routerconfigs'),
			'align' => 'left',
			'sort' => 'ASC',
			'tip' => __('The directory of the stored device backups', 'routerconfigs')
		),
		'backup' => array(
			'display' => __('Backup Time'),
			'align' => 'left',
			'sort' => 'ASC',
			'tip' => __('The last Backup time of the device')
		),
		'change' => array(
			'display' => __('Last Change'),
			'align' => 'left',
			'sort' => 'ASC',
			'tip' => __('The last Change time of the device')
		),
		'change_by' => array(
			'display' => __('Changed By'),
			'align' => 'left',
			'sort' => 'ASC',
			'tip' => __('The last person to change the configuration of the device')
		),
		'filename' => array(
			'display' => __('Filename'),
			'align' => 'left',
			'sort' => 'ASC',
			'tip' => __('The filename of the stored device backups')
		),
	);

	form_start('router-backups.php', 'chk');

	$nav = html_nav_bar('router-backups.php', MAX_DISPLAY_PAGES, get_request_var('page'), $num_rows, $total_rows, 7, 'Backups', 'page', 'main');
	print $nav;

	html_start_box('', '100%', '', '3', 'center', '');

	html_header_sort($display_text, get_request_var('sort_column'), get_request_var('sort_direction'), false);

	if (sizeof($result)) {
		$r = db_fetch_assoc('SELECT device, id FROM plugin_routerconfigs_backups ORDER BY btime ASC');
		$latest = array();
		if (count($r)) {
			foreach ($r as $s) {
				$latest[$s['device']] = $s['id'];
			}
		}

		$c = 0;
		foreach ($result as $row) {
			$lastchange = plugin_routerconfigs_date_from_time_with_na($row['lastchange']);;
			$lastbackup = plugin_routerconfigs_date_from_time_with_na($row['btime']);;

			form_alternate_row('line' . $row['id'], true);

			form_selectable_cell(filter_value($row['hostname'], get_request_var('filter'), 'router-devices.php?action=edit&id=' . $row['device']), $row['device']);
			form_selectable_cell($row['id'], $row['id'], null, 'text-align: right');

			form_selectable_cell(
				"<a class='hyperLink' href='router-backups.php?action=viewconfig&id=" . $row['id'] . "'>" . __('View Config', 'routerconfigs') . 
				"</a> - " .
 				"<a class='hyperLink' href='router-compare.php?device1=" . $row['device'] . '&device2=' . $row['device'] . '&file1=' . $row['id'] . '&file2=' . $latest[$row['device']] . "'>" . __('Compare', 'routerconfigs') . "</a></td>", $row['device']);

			form_selectable_cell(filter_value($row['directory'], get_request_var('filter')),$row['device']);
			form_selectable_cell(filter_value($lastbackup, get_request_var('filter')),$row['device']);
			form_selectable_cell(filter_value($lastchange, get_request_var('filter')),$row['device']);
			form_selectable_cell(filter_value($row['lastuser'], get_request_var('filter')),$row['device']);
			form_selectable_cell(filter_value($row['filename'], get_request_var('filter')),$row['device']);

			form_end_row();
		}
	} else {
		print "<tr class='tableRow'><td colspan='11'><em>" . __('No Router Backups Found', 'routerconfigs') . "</em></td></tr>";
	}

	html_end_box(false);

	form_end();

	bottom_footer();
}
