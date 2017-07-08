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

include_once($config['base_path'] . '/plugins/routerconfigs/Text/Diff.php');
include_once($config['base_path'] . '/plugins/routerconfigs/Text/Diff/Renderer.php');
include_once($config['base_path'] . '/plugins/routerconfigs/Text/Diff/Renderer/table.php');

top_header();

$device1 = get_filter_request_var('device1');
$device2 = get_filter_request_var('device2');

$file1   = get_filter_request_var('file1');
$file2   = get_filter_request_var('file2');

$files1  = array();
$files2  = array();

if (empty($device1) || empty($device2)) {
	$devices = db_fetch_assoc('SELECT id, directory, hostname
		FROM plugin_routerconfigs_devices
		ORDER BY hostname');

	if (sizeof($devices)) {
		$default = $devices[0]['id'];

		if (!empty($device1)) {
			if ($device1 == $default) {
				$device2 = $default;
			}
		}else{
			if ($device2 == $default) {
				$device1 = $default;
			}
		}
	}
}

if (!empty($device1)) {
	$files1 = db_fetch_assoc_prepared('SELECT id, directory, filename
		FROM plugin_routerconfigs_backups
		WHERE device = ?
		ORDER BY directory, filename DESC', array($device1));
}else{
	$files1 = array();
}

if (!empty($device2)) {
	$files2 = db_fetch_assoc_prepared('SELECT id, directory, filename
		FROM plugin_routerconfigs_backups
		WHERE device = ? ORDER BY directory, filename DESC', array($device2));
}else{
	$files2 = array();
}

display_tabs();

/* show a filter form */
form_start('router-compare.php', 'form1');

html_start_box('', '100%', '', '1', 'center', '');
html_header(array('File', 'File'));

form_alternate_row();

print "<td width='50%'><select id='device1' name='device1' onChange='changeDeviceA()'>";
foreach ($devices as $f) {
	print '<option value=' . $f['id'] . ($device1 == $f['id'] ? ' selected' : '') . '>' . $f['directory'] . '/' . $f['hostname'] . '</option>';
}
print '</select><br>';

print "<select id='file1' name='file1' onChange='changeFileForm()'><option value='0'></option>";
foreach ($files1 as $f) {
	print '<option value=' . $f['id'] . ($file1 == $f['id'] ? ' selected' : '') . '>' . $f['filename'] . '</option>';
}
print '</select></td>';

print "<td width='50%'><select id='device2' name='device2' onChange='changeDeviceB()'>";
foreach ($devices as $f) {
	print '<option value="' . $f['id'] . ($device2 == $f['id'] ? '" selected' : '"') . '>' . $f['directory'] . '/' . $f['hostname'] . '</option>';
}
print '</select><br>';

print "<select id='file2' name='file2' onChange='changeFileForm()'><option value=0></option>";
foreach ($files2 as $f) {
	print '<option value="' . $f['id'] . ($file2 == $f['id'] ? '" selected' : '"') . '>' . $f['filename'] . '</option>';
}
print '</select></td></tr>';

html_end_box(false);
form_end();

html_start_box(__('Compare Output', 'routerconfigs'), '100%', '', '1', 'center', '');

if (!empty($file1) && !empty($file2)) {
	$device1 = db_fetch_row_prepared('SELECT * FROM plugin_routerconfigs_backups WHERE id = ?', array($file1));
	$device2 = db_fetch_row_prepared('SELECT * FROM plugin_routerconfigs_backups WHERE id = ?', array($file2));

	if (isset($device1['id']) && isset($device2['id'])) {
		/* Load the lines of each file. */
		$lines1 = explode("\n", $device1['config']);
		$lines2 = explode("\n", $device2['config']);

		/* Create the Diff object. */
		$diff = new Text_Diff('auto', array($lines1, $lines2));

		/* Output the diff in unified format. */
		$renderer = new Text_Diff_Renderer_table();

		$text = $renderer->render($diff);

		html_start_box('', '100%', '', '1', 'center', '');
		html_header(array($device1['directory'] . '/' . $device1['filename'], '', $device2['directory'] . '/' . $device2['filename']));

		print "<tr bgcolor='#6d88ad' height='1'><td width='50%'></td><td width='1'></td><td width='50%'></td></tr>";

		if (trim($text) == '') {
			print '<tr><td colspan=3><center>' . __('There are no Changes', 'routerconfigs') . '</center></td></tr>';
		} else {
			$text = str_replace("\n", '<br>', $text);
			$text = str_replace('</td></tr>', '</td></tr>' . "\n", $text);

			echo $text;
		}

		html_end_box(false);
	}else{
		print '<tr><td><h3>' . __('Error, you must have backups for each device.', 'routerconfigs') . '</h3></td></tr>';
	}
}else{
	print '<tr><td><h3>' . __('Error, you must have backups for each device.', 'routerconfigs') . '</h3></td></tr>';
}

html_end_box();

?>
<script type='text/javascript'>
	function changeDeviceA () {
		strURL  = 'router-compare.php?header=false&device1='+$('#device1').val();
		strURL += '&device2='+$('#device2').val();
		strURL += '&file1=';
		strURL += '&file2='+$('#file2').val();
		loadPageNoHeader(strURL);
	}

	function changeDeviceB () {
		strURL  = 'router-compare.php?header=false&device1='+$('#device1').val();
		strURL += '&device2='+$('#device2').val();
		strURL += '&file1='+$('#file1').val();
		strURL += '&file2=';
		loadPageNoHeader(strURL);
	}

	function changeFileForm () {
		strURL  = 'router-compare.php?header=false&device1=' + $('#device1').val();
		strURL += '&device2='+$('#device2').val();
		strURL += '&file1='+$('#file1').val();
		strURL += '&file2='+$('#file2').val();
		loadPageNoHeader(strURL);
	}
</script>
<?php

bottom_footer();

