<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2019 The Cacti Group                                 |
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
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

require_once(__DIR__ . '/constants.php');

global	$config, $rc_settings, $rc_connection_types,
	$rc_schedules_backup, $rc_schedules_retry, $rc_schedules_retention, $rc_schedules_download,
	$rc_account_actions, $rc_device_actions, $rc_devtype_actions,
	$rc_account_edit_fields, $rc_device_edit_fields, $rc_devtype_edit_fields;

if (function_exists('gethostname')) {
	$rc_hostname = gethostname();
}else{
	$rc_hostname = php_uname('n');
}

$rc_account_actions = array(
	RCONFIG_ACCOUNT_DELETE => __('Delete', 'routerconfigs'),
);

$rc_device_actions = array(
	RCONFIG_DEVICE_BACKUP  => __('Backup', 'routerconfigs'),
	RCONFIG_DEVICE_DELETE  => __('Delete', 'routerconfigs'),
	RCONFIG_DEVICE_ENABLE  => __('Enable', 'routerconfigs'),
	RCONFIG_DEVICE_DISABLE => __('Disable', 'routerconfigs')
);

$rc_devtype_actions = array(
	RCONFIG_DEVTYPE_DELETE => __('Delete', 'routerconfigs'),
);

$rc_connection_types_settings = array(
	RCONFIG_CONNECT_BOTH    => __('SSH/Telnet', 'routerconfigs'),
	RCONFIG_CONNECT_SSH     => __('SSH', 'routerconfigs'),
	RCONFIG_CONNECT_TELNET  => __('Telnet', 'routerconfigs'),
	RCONFIG_CONNECT_SCP     => __('SCP', 'routerconfigs'),
	RCONFIG_CONNECT_SFTP    => __('SFTP', 'routerconfigs'),
);

$rc_connection_types = array(
	RCONFIG_CONNECT_DEFAULT => __('Inherit', 'routerconfigs'),
) + $rc_connection_types_settings;

$rc_schedules_backup = array(
	RCONFIG_BACKUP_DAILY   => __('Daily', 'routerconfigs'),
	RCONFIG_BACKUP_WEEKLY  => __('Weekly', 'routerconfigs'),
	RCONFIG_BACKUP_MONTHLY => __('Monthly', 'routerconfigs'),
);

$rc_schedules_retention = array(
	RCONFIG_RETENTION_MONTH_ONE   => __('%d Month', 1, 'routerconfigs'), // * 30
	RCONFIG_RETENTION_MONTH_TWO   => __('%d Months', 2, 'routerconfigs'),
	RCONFIG_RETENTION_MONTH_THREE => __('%d Months', 3, 'routerconfigs'),
	RCONFIG_RETENTION_MONTH_FOUR  => __('%d Months', 4, 'routerconfigs'),
	RCONFIG_RETENTION_MONTH_SIX   => __('%d Months', 6, 'routerconfigs'),
	RCONFIG_RETENTION_YEAR_ONE    =>__('%d Year', 1, 'routerconfigs'),
	RCONFIG_RETENTION_YEAR_TWO    =>__('%d Years', 2, 'routerconfigs'),
	RCONFIG_RETENTION_YEAR_THREE  =>__('%d Years', 3, 'routerconfigs'),
	RCONFIG_RETENTION_YEAR_FOUR   =>__('%d Years', 4, 'routerconfigs'),
	RCONFIG_RETENTION_YEAR_FIVE   =>__('%d Years', 5, 'routerconfigs'),
);

$rc_schedules_retry = array(
	'0'  => __('Never', 'routerconfigs'),
	'1'  => __('%d hour', 1, 'routerconfigs'),
	'2'  => __('%d hours', 2, 'routerconfigs'),
	'3'  => __('%d hours', 3, 'routerconfigs'),
	'4'  => __('%d hours', 4, 'routerconfigs'),
	'6'  => __('%d hours', 6, 'routerconfigs'),
	'8'  => __('%d hours', 8, 'routerconfigs'),
	'12'  => __('%d hours', 12, 'routerconfigs'),
);

$rc_schedules_download = array(
	'0'  => __('00:00 (12am)', 'routerconfigs'),
	'1'  => __('01:00 (1am)', 'routerconfigs'),
	'2'  => __('02:00 (2am)', 'routerconfigs'),
	'3'  => __('03:00 (3am)', 'routerconfigs'),
	'4'  => __('04:00 (4am)', 'routerconfigs'),
	'5'  => __('05:00 (5am)', 'routerconfigs'),
	'6'  => __('06:00 (6am)', 'routerconfigs'),
	'7'  => __('07:00 (7am)', 'routerconfigs'),
	'8'  => __('08:00 (8am)', 'routerconfigs'),
	'9'  => __('09:00 (9am)', 'routerconfigs'),
	'10'  => __('10:00 (12am)', 'routerconfigs'),
	'11'  => __('11:00 (11am)', 'routerconfigs'),
	'12'  => __('12:00 (12pm)', 'routerconfigs'),
	'13'  => __('13:00 (1pm)', 'routerconfigs'),
	'14'  => __('14:00 (2pm)', 'routerconfigs'),
	'15'  => __('15:00 (3pm)', 'routerconfigs'),
	'16'  => __('16:00 (4pm)', 'routerconfigs'),
	'17'  => __('17:00 (5pm)', 'routerconfigs'),
	'18'  => __('18:00 (6pm)', 'routerconfigs'),
	'19'  => __('19:00 (7pm)', 'routerconfigs'),
	'20'  => __('20:00 (8pm)', 'routerconfigs'),
	'21'  => __('21:00 (9pm)', 'routerconfigs'),
	'22'  => __('22:00 (10pm)', 'routerconfigs'),
	'23'  => __('23:00 (11pm)', 'routerconfigs'),
);

$rc_account_edit_fields = array(
	'name' => array(
		'method' => 'textbox',
		'friendly_name' => __('Name', 'routerconfigs'),
		'description' => __('Give this account a meaningful name that will be displayed.', 'routerconfigs'),
		'value' => '|arg1:name|',
		'max_length' => '64',
		),
	'username' => array(
		'method' => 'textbox',
		'friendly_name' => __('Username', 'routerconfigs'),
		'description' => __('The username that will be used for authentication.', 'routerconfigs'),
		'value' => '|arg1:username|',
		'max_length' => '64',
		),
	'password' => array(
		'method' => 'textbox_password',
		'friendly_name' => __('Password', 'routerconfigs'),
		'description' => __('The password used for authentication.', 'routerconfigs'),
		'value' => '|arg1:password|',
		'default' => '',
		'max_length' => '64',
		'size' => '30'
		),
	'enablepw' => array(
		'method' => 'textbox_password',
		'friendly_name' => __('Enable Password', 'routerconfigs'),
		'description' => __('Your Enable Password, if required.', 'routerconfigs'),
		'value' => '|arg1:enablepw|',
		'default' => '',
		'max_length' => '64',
		'size' => '30'
		),
	'id' => array(
		'method' => 'hidden_zero',
		'value' => '|arg1:id|'
		)
);

$rc_device_edit_fields = array(
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
		'array' => $rc_schedules_backup,
	),
	'devicetype' => array(
		'method' => 'drop_sql',
		'friendly_name' => __('Device Type', 'routerconfigs'),
		'description' => __('Choose the type of device that the router is.', 'routerconfigs'),
		'value' => '|arg1:devicetype|',
		'sql' => 'SELECT id, name FROM plugin_routerconfigs_devicetypes ORDER BY name',
		'default' => 0,
		'none_value' => __('Auto-Detect', 'routerconfigs'),
	),
	'connecttype' => array(
		'method' => 'drop_array',
		'friendly_name' => __('Connection Type', 'routerconfigs'),
		'description' => __('This is the type of connection used to communicate with the device.', 'routerconfigs'),
		'value' => '|arg1:connecttype|',
		'default' => RCONFIG_CONNECT_DEFAULT,
		'array' => $rc_connection_types,
	),
	'account' => array(
		'method' => 'drop_sql',
		'friendly_name' => __('Authentication Account', 'routerconfigs'),
		'description' => __('Choose an account to use to Login to the router', 'routerconfigs'),
		'value' => '|arg1:account|',
		'sql' => 'SELECT id, name FROM plugin_routerconfigs_accounts ORDER BY name',
		'default' => 0,
		'none_value' => __('None', 'routerconfigs'),
	),
	'timeout' => array(
		'friendly_name' => __('Default timeout', 'routerconfigs'),
		'description' => __('Default time to wait in seconds for a resposne', 'routerconfigs'),
		'method' => 'textbox',
		'value' => '|arg1:timeout|',
		'max_length' => '3',
		'default' => '1'
	),
	'sleep' => array(
		'friendly_name' => __('Default sleep time', 'routerconfigs'),
		'description' => __('Default time to sleep in microseconds (1/1,000,000th of a second)', 'routerconfigs'),
		'method' => 'textbox',
		'max_length' => '10',
		'value' => '|arg1:sleep|',
		'default' => '125000'
	),
	'elevated' => array(
		'method' => 'checkbox',
		'friendly_name' => __('Assume elevated', 'routerconfigs'),
		'description' => __('Check this box to assume this device is always elevated', 'routerconfigs'),
		'value' => '|arg1:elevated|',
		'default' => '',
		'form_id' => false
	),
	'id' => array(
		'method' => 'hidden_zero',
		'value' => '|arg1:id|'
	)
);

$rc_devtype_edit_fields = array(
	'name' => array(
		'method' => 'textbox',
		'friendly_name' => __('Name', 'routerconfigs'),
		'description' => __('Name of this device type.', 'routerconfigs'),
		'value' => '|arg1:name|',
		'size' => '30',
		'max_length' => '64',
	),
	'connecttype' => array(
		'method' => 'drop_array',
		'friendly_name' => __('Connection Type', 'routerconfigs'),
		'description' => __('This is the type of connection used to communicate with the device.', 'routerconfigs'),
		'value' => '|arg1:connecttype|',
		'default' => RCONFIG_CONNECT_DEFAULT,
		'array' => $rc_connection_types,
	),
	'promptuser' => array(
		'method' => 'textbox',
		'friendly_name' => __('Username Prompt', 'routerconfigs'),
		'description' => __('This is the username prompt to match on login.', 'routerconfigs'),
		'value' => '|arg1:promptuser|',
		'size' => '20',
		'max_length' => '64',
	),
	'promptpass' => array(
		'method' => 'textbox',
		'friendly_name' => __('Password Prompt', 'routerconfigs'),
		'description' => __('This is the password prompt to match on login.', 'routerconfigs'),
		'value' => '|arg1:promptpass|',
		'size' => '20',
		'max_length' => '255',
	),
	'configfile' => array(
		'method' => 'textbox',
		'friendly_name' => __('Configuration file', 'routerconfigs'),
		'description' => __('This is the standard location of the configuration file on the remote device.', 'routerconfigs'),
		'value' => '|arg1:configfile|',
		'size' => '20',
		'max_length' => '64',
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
	'promptconfirm' => array(
		'method' => 'textbox',
		'friendly_name' => __('Confirmation Prompt', 'routerconfigs'),
		'description' => __('This is the confirmation prompt to match before transmission.', 'routerconfigs'),
		'value' => '|arg1:promptconfirm|',
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
	),
	'checkendinconfig' => array(
		'method' => 'checkbox',
		'friendly_name' => __('Check End in Config', 'routerconfigs'),
		'description' => __('Check end in config?', 'routerconfigs'),
		'value' => '|arg1:checkendinconfig|',
	),
	'elevated' => array(
		'method' => 'checkbox',
		'friendly_name' => __('Assume elevated', 'routerconfigs'),
		'description' => __('Check this box to assume this device is always elevated', 'routerconfigs'),
		'value' => '|arg1:elevated|',
		'default' => '',
		'form_id' => false
	),
	'timeout' => array(
		'friendly_name' => __('Default timeout', 'routerconfigs'),
		'description' => __('Default time to wait in seconds for a resposne', 'routerconfigs'),
		'method' => 'textbox',
		'value' => '|arg1:timeout|',
		'default' => '1',
		'max_length' => '3'
	),
	'sleep' => array(
		'friendly_name' => __('Default sleep time', 'routerconfigs'),
		'description' => __('Default time to sleep in microseconds (1/1,000,000th of a second)', 'routerconfigs'),
		'method' => 'textbox',
		'value' => '|arg1:sleep|',
		'default' => '125000',
		'max_length' => '7'
	),
	'anykey' => array(
		'friendly_name' => __('Any Key prompt', 'routerconfigs'),
		'description' => __('Text to match a \'Press Any Key To Continue\' styled prompt', 'routerconfigs'),
		'method' => 'textbox',
		'value' => '|arg1:anykey|',
		'default' => '',
		'max_length' => '50'
	),
	'id' => array(
		'method' => 'hidden_zero',
		'value' => '|arg1:id|'
	)
);

$rc_settings = array(
	'routerconfigs_header' => array(
		'friendly_name' => __('Router Configs', 'routerconfigs'),
		'method' => 'spacer',
	),
	'routerconfigs_timeout' => array(
		'friendly_name' => __('Default timeout', 'routerconfigs'),
		'description' => __('Default time to wait in seconds for a resposne', 'routerconfigs'),
		'method' => 'textbox',
		'max_length' => '3',
		'default' => '1'
	),
	'routerconfigs_sleep' => array(
		'friendly_name' => __('Default sleep time', 'routerconfigs'),
		'description' => __('Default time to sleep in microseconds (1/1,000,000th of a second)', 'routerconfigs'),
		'method' => 'textbox',
		'max_length' => '10',
		'default' => '125000'
	),
	'routerconfigs_exit' => array(
		'friendly_name' => __('Close without exit', 'routerconfigs'),
		'description' => __('If ticked, when closing down the device connection, no \'exit\' command is issued', 'routerconfigs'),
		'method' => 'checkbox'
	),
	'routerconfigs_debug_buffer' => array(
		'friendly_name' => __('Debug Connection Buffer', 'routerconfigs'),
		'description' => __('Whether to log direct output of device connection', 'routerconfigs'),
		'method' => 'checkbox'
	),
	'routerconfigs_elevated' => array(
		'friendly_name' => __('Assume all devices elevated', 'routerconfigs'),
		'description' => __('Whether to assume all devices are elevated', 'routerconfigs'),
		'method' => 'checkbox'
	),
	'routerconfigs_hour' => array(
		'friendly_name' => __('Download Hour', 'routerconfigs'),
		'description' => __('The hour of the day to perform the full downloads.', 'routerconfigs'),
		'method' => 'drop_array',
		'default' => '0',
		'array' => $rc_schedules_download,
	),
	'routerconfigs_retry' => array(
		'friendly_name' => __('Retry Schedule', 'routerconfigs'),
		'description' => __('The time to wait before attempting to perform an additional download when scheduled download fails', 'routerconfigs'),
		'method' => 'drop_array',
		'default' => '4',
		'array' => $rc_schedules_retry,
	),
	'routerconfigs_retention' => array(
		'friendly_name' => __('Retention Period', 'routerconfigs'),
		'description' => __('The number of days to retain old backups.', 'routerconfigs'),
		'method' => 'drop_array',
		'default' => '30',
		'array' => $rc_schedules_retention,
	),
	'routerconfigs_header_transfer' => array(
		'friendly_name' => __('Transfer Options', 'routerconfigs'),
		'method' => 'spacer',
	),
	'routerconfigs_connecttype' => array(
		'friendly_name' => __('Default connection type', 'routerconfigs'),
		'description' => __('Default type of connection used to communicate with the device.', 'routerconfigs'),
		'method' => 'drop_array',
		'default' => RCONFIG_CONNECT_BOTH,
                'array' => $rc_connection_types_settings,
	),
	'routerconfigs_archive_separate' => array(
		'friendly_name' => __('Separate By Device', 'routerconfigs'),
		'description' => __('Separate archived Configs into a folder per device', 'routerconfigs'),
		'method' => 'checkbox',
		'default' => 'on'
	),
	'routerconfigs_scp_path' => array(
		'friendly_name' => __('SCP Path', 'routerconfigs'),
		'description' => __('When using SCP, leaving this blank will use PHP\'s SCP module which does not always work', 'routerconfigs'),
		'method' => 'textbox',
		'max_length' => 255,
		'size' => '50',
	),
	'routerconfigs_tftpserver' => array(
		'friendly_name' => __('TFTP Server IP', 'routerconfigs'),
		'description' => __('Must be an IP pointing to your Cacti server.', 'routerconfigs'),
		'method' => 'textbox',
		'max_length' => 255,
		'default' => gethostbyname($rc_hostname)
	),
	'routerconfigs_backup_path' => array(
		'friendly_name' => __('TFTP Backup Directory Path', 'routerconfigs'),
		'description' => __('The path to where your Configs will be backed up, it must be the path that the local TFTP Server writes to.', 'routerconfigs'),
		'method' => 'dirpath',
		'max_length' => 255,
		'size' => '50',
		'default' => $config['base_path'] . '/backups/'
	),
	'routerconfigs_archive_path' => array(
		'friendly_name' => __('Archive Directory Path', 'routerconfigs'),
		'description' => __('The path to where your Configs will be archived (moved from TFTP directory)', 'routerconfigs'),
		'method' => 'dirpath',
		'max_length' => 255,
		'size' => '50',
		'default' => $config['base_path'] . '/plugins/routerconfigs/backups/'
	),
	'routerconfigs_header_email' => array(
		'friendly_name' => __('Email Options', 'routerconfigs'),
		'method' => 'spacer',
	),
	'routerconfigs_from' => array(
		'friendly_name' => __('From Address', 'routerconfigs'),
		'description' => __('Email address the nightly backup will be sent from.', 'routerconfigs'),
		'method' => 'textbox',
		'size' => 40,
		'max_length' => 255,
		'default' => ''
	),
	'routerconfigs_name' => array(
		'friendly_name' => __('From Name', 'routerconfigs'),
		'description' => __('Name the nightly backup will be sent from.', 'routerconfigs'),
		'method' => 'textbox',
		'size' => 40,
		'max_length' => 255,
		'default' => ''
	),
	'routerconfigs_email' => array(
		'friendly_name' => __('Email Address', 'routerconfigs'),
		'description' => __('A comma delimited list of Email addresses to send the nightly backup Email to.', 'routerconfigs'),
		'method' => 'textarea',
		'class' => 'textAreaNotes',
		'textarea_rows' => '5',
		'textarea_cols' => '40',
		'size' => 40,
		'max_length' => 255,
		'default' => ''
	),
);
