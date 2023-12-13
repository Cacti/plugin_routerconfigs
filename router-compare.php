<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2007-2023 The Cacti Group                                 |
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

if (cacti_version_compare(CACTI_VERSION, '1.2.23', '<')) {
	print get_md5_include_css('plugins/routerconfigs/diff.css');
}

if (read_config_option('routerconfigs_presentation') == 'console') {
	top_header();
} else {
	general_header();
}

compare_validate_vars();

$device1  = get_request_var('device1');
$device2  = get_request_var('device2');
$file1    = get_request_var('file1');
$file2    = get_request_var('file2');
$diffmode = get_request_var('diffmode');

$files1   = array();
$files2   = array();

$devices = db_fetch_assoc('SELECT id, directory, hostname
	FROM plugin_routerconfigs_devices
	ORDER BY hostname');

if (cacti_sizeof($devices)) {
	foreach ($devices as $d) {
		$default = $d['id'];
		break;
	}

	if (!is_numeric($device1)) {
		$device1 == $default;
	}

	if (!is_numeric($device2)) {
		$device1 = $default;
	}
}

if (is_numeric($device1)) {
	$files1 = db_fetch_assoc_prepared('SELECT id, directory, filename
		FROM plugin_routerconfigs_backups
		WHERE device = ?
		ORDER BY filename DESC', array($device1));
} else {
	$files1 = array();
}

if (is_numeric($device2)) {
	$files2 = db_fetch_assoc_prepared('SELECT id, directory, filename
		FROM plugin_routerconfigs_backups
		WHERE device = ? ORDER BY filename DESC', array($device2));
} else {
	$files2 = array();
}

display_tabs();

html_start_box(__('Router Backup Comparison', 'routerconfigs'), '100%', '', '4', 'center', '');

?>
<tr class='even noprint'>
	<td>
	<form id='form_devices' action='router-devices.php'>
		<table class='filterTable'>
			<tr>
				<td>
					<?php print __('Diff Mode', 'routerconfigs');?>
				</td>
				<td>
					<select id='diffmode'>
						<option value='inline'<?php if (get_request_var('diffmode') == 'inline') {?> selected<?php }?>><?php print __('Inline','routerconfigs');?></option>
						<option value='sdiff'<?php if (get_request_var('diffmode') == 'sdiff') {?> selected<?php }?>><?php print __('Side by Side','routerconfigs');?></option>
					</select>
				</td>
			</tr>
		</table>
	</form>
	<script type='text/javascript'>
	$(function() {
		$('#diffmode').change(function() {
			var strURL = urlPath + 'plugins/routerconfigs/router-compare.php';
			strURL += '?header=false';
			strURL += '&diffmode=' + $('#diffmode').val();
			strURL += '&file1=' + $('#file1').val();
			strURL += '&file2=' + $('#file2').val();

			loadPageNoHeader(strURL);
		});
	});
	</script>
</tr>
<?php

html_end_box();

/* show a filter form */
form_start('router-compare.php', 'chk');

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

	if (isset($device1['id'])) {
		$filepath1 = plugin_routerconfigs_dir($device1['directory']) . $device1['filename'];
		if (file_exists($filepath1)) {
			$lines1 = @file($filepath1, FILE_IGNORE_NEW_LINES);
			if ($lines1 === false) {
				$lines1 = array('File \'' . $filepath1 .'\' (' . $file1 .' ) failed to load');
			}
		} else {
			$lines1 = array('File \'' . $filepath1 .'\' (' . $file1 .' ) missing');
		}
	} else {
		$lines1 = array('Unable to find backup id ' . $file1);
	}

	if (isset($device2['id'])) {
		$filepath2 = plugin_routerconfigs_dir($device2['directory']) . $device2['filename'];
		if (file_exists($filepath2)) {
			$lines2 = @file($filepath2, FILE_IGNORE_NEW_LINES);
			if ($lines2 === false) {
				$lines2 = array('File \'' . $filepath2 .'\' (' . $file2 .' ) failed to load');
			}
		} else {
			$lines2 = array('File \'' . $filepath2 .'\' (' . $file2 .' ) missing');
		}
	} else {
		$lines2 = array('Unable to find backup id ' . $file1);
	}

	if (cacti_version_compare(CACTI_VERSION, '1.2.23', '<')) {
		/* Create the Diff object. */
		include_once(__DIR__ . '/HordeTextInclude.php');

		$diff = new Horde_Text_Diff('Native', array($lines1, $lines2));

		/* Output the diff in unified format. */
		if (get_request_var('diffmode') == 'sdiff') {
			$renderer = new Horde_Text_Diff_Renderer_table(array('auto'));
		} else {
			$renderer = new Horde_Text_Diff_Renderer_unified();
		}

		$text = $renderer->render($diff);
	} else {
		include_once($config['base_path'] . '/include/vendor/phpdiff/Diff.php');
		include_once($config['base_path'] . '/include/vendor/phpdiff/Renderer/Html/Inline.php');
		include_once($config['base_path'] . '/include/vendor/phpdiff/Renderer/Html/SideBySide.php');

		$options = array(
			'ignoreWhitespace' => true,
			'ignoreCase' => false
		);

		$diff = new Diff($lines1, $lines2, $options);

		if (get_request_var('diffmode') == 'sdiff') {
			$renderer = new Diff_Renderer_Html_SideBySide;
		} else {
			$renderer = new Diff_Renderer_Html_Inline;
		}

		$text = $diff->render($renderer);
	}

	html_start_box('', '100%', '', '1', 'center', '');

	if (get_request_var('diffmode') == 'sdiff') {
		html_header(array($device1['directory'] . '/' . $device1['filename'], $device2['directory'] . '/' . $device2['filename']));

		print "<tr height='1'><td width='50%'></td><td width='50%'></td></tr>";

		if (trim($text) == '') {
			print '<tr><td colspan="2"><center>' . __('There are no Changes', 'routerconfigs') . '</center></td></tr>';
		} else {
			$text = str_replace("\n", '<br>', $text);
			$text = str_replace('</td></tr>', '</td></tr>' . "\n", $text);

			print $text;
		}
	} elseif (trim($text) == '') {
		print '<tr><td colspan="2"><center>' . __('There are no Changes', 'routerconfigs') . '</center></td></tr>';
	} else {
		$text = str_replace("\n", '<br>', $text);
		$text = str_replace('</td></tr>', '</td></tr>' . "\n", $text);

		print $text;
	}

	html_end_box(false);
} else {
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

function compare_validate_vars() {
	/* ================= input validation and session storage ================= */
	$filters = array(
		'diffmode' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'inline',
			'options' => array('options' => 'sanitize_search_string')
		),
		'device1' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => '',
		),
		'device2' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => '',
		),
		'file1' => array(
			'filter' => FILTER_CALLBACK,
			'default' => '',
			'options' => array('options' => 'sanitize_search_string')
		),
		'file2' => array(
			'filter' => FILTER_CALLBACK,
			'default' => '',
			'options' => array('options' => 'sanitize_search_string')
		),
	);

	validate_store_request_vars($filters, 'sess_rc_compare');
	/* ================= input validation ================= */
}

