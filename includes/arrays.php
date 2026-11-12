<?php

declare(strict_types = 1);
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
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
require_once(__DIR__ . '/scheduler.php');

global $config, $rc_settings, $rc_connection_types,
$rc_schedules_backup, $rc_schedules_retry, $rc_schedules_retention, $rc_schedules_download,
$rc_schedules_recur,
$rc_diff_context, $rc_notification_list_setting,
$rc_account_actions, $rc_device_actions, $rc_devtype_actions,
$rc_account_edit_fields, $rc_device_edit_fields, $rc_devtype_edit_fields;

if (function_exists('gethostname')) {
	$rc_hostname = gethostname();
} else {
	$rc_hostname = false;
}

if ($rc_hostname === false) {
	$rc_hostname = php_uname('n');
}

$rc_account_actions = [
	RCONFIG_ACCOUNT_DELETE => __('Delete', 'routerconfigs'),
];

$rc_device_actions = [
	RCONFIG_DEVICE_BACKUP            => __('Backup', 'routerconfigs'),
	RCONFIG_DEVICE_EDIT              => __('Edit (bulk change settings)', 'routerconfigs'),
	RCONFIG_DEVICE_DELETE            => __('Delete', 'routerconfigs'),
	RCONFIG_DEVICE_ENABLE            => __('Enable', 'routerconfigs'),
	RCONFIG_DEVICE_DISABLE           => __('Disable', 'routerconfigs'),
	RCONFIG_DEVICE_CLEAR_SSH_HOSTKEY => __('Clear SSH Host Key', 'routerconfigs')
];

$rc_devtype_actions = [
	RCONFIG_DEVTYPE_DELETE => __('Delete', 'routerconfigs'),
];

$rc_connection_types_settings = [
	RCONFIG_CONNECT_BOTH    => __('SSH/Telnet', 'routerconfigs'),
	RCONFIG_CONNECT_SSH     => __('SSH', 'routerconfigs'),
	RCONFIG_CONNECT_TELNET  => __('Telnet', 'routerconfigs'),
	RCONFIG_CONNECT_SCP     => __('SCP', 'routerconfigs'),
	RCONFIG_CONNECT_SFTP    => __('SFTP', 'routerconfigs'),
];

$rc_connection_types = [
	RCONFIG_CONNECT_DEFAULT => __('Inherit', 'routerconfigs'),
] + $rc_connection_types_settings;

$rc_schedules_backup = [
	RCONFIG_BACKUP_DAILY   => __('Daily', 'routerconfigs'),
	RCONFIG_BACKUP_WEEKLY  => __('Weekly', 'routerconfigs'),
	RCONFIG_BACKUP_MONTHLY => __('Monthly', 'routerconfigs'),
];

$rc_schedules_recur = plugin_routerconfigs_sched_recur_options();

$rc_schedules_retention = [
	RCONFIG_RETENTION_MONTH_ONE   => __('%d Month', 1, 'routerconfigs'), // * 30
	RCONFIG_RETENTION_MONTH_TWO   => __('%d Months', 2, 'routerconfigs'),
	RCONFIG_RETENTION_MONTH_THREE => __('%d Months', 3, 'routerconfigs'),
	RCONFIG_RETENTION_MONTH_FOUR  => __('%d Months', 4, 'routerconfigs'),
	RCONFIG_RETENTION_MONTH_SIX   => __('%d Months', 6, 'routerconfigs'),
	RCONFIG_RETENTION_YEAR_ONE    => __('%d Year', 1, 'routerconfigs'),
	RCONFIG_RETENTION_YEAR_TWO    => __('%d Years', 2, 'routerconfigs'),
	RCONFIG_RETENTION_YEAR_THREE  => __('%d Years', 3, 'routerconfigs'),
	RCONFIG_RETENTION_YEAR_FOUR   => __('%d Years', 4, 'routerconfigs'),
	RCONFIG_RETENTION_YEAR_FIVE   => __('%d Years', 5, 'routerconfigs'),
];

$rc_schedules_retry = [
	'0'   => __('Never', 'routerconfigs'),
	'1'   => __('%d hour', 1, 'routerconfigs'),
	'2'   => __('%d hours', 2, 'routerconfigs'),
	'3'   => __('%d hours', 3, 'routerconfigs'),
	'4'   => __('%d hours', 4, 'routerconfigs'),
	'6'   => __('%d hours', 6, 'routerconfigs'),
	'8'   => __('%d hours', 8, 'routerconfigs'),
	'12'  => __('%d hours', 12, 'routerconfigs'),
];

$rc_schedules_download = [
	'0'   => __('00:00 (12am)', 'routerconfigs'),
	'1'   => __('01:00 (1am)', 'routerconfigs'),
	'2'   => __('02:00 (2am)', 'routerconfigs'),
	'3'   => __('03:00 (3am)', 'routerconfigs'),
	'4'   => __('04:00 (4am)', 'routerconfigs'),
	'5'   => __('05:00 (5am)', 'routerconfigs'),
	'6'   => __('06:00 (6am)', 'routerconfigs'),
	'7'   => __('07:00 (7am)', 'routerconfigs'),
	'8'   => __('08:00 (8am)', 'routerconfigs'),
	'9'   => __('09:00 (9am)', 'routerconfigs'),
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
];

$rc_diff_context = [
	'1'   => __('%d line', 1, 'routerconfigs'),
	'2'   => __('%d lines', 2, 'routerconfigs'),
	'3'   => __('%d lines', 3, 'routerconfigs'),
	'5'   => __('%d lines', 5, 'routerconfigs'),
	'10'  => __('%d lines', 10, 'routerconfigs'),
	'15'  => __('%d lines', 15, 'routerconfigs'),
	'25'  => __('%d lines', 25, 'routerconfigs'),
	'50'  => __('%d lines', 50, 'routerconfigs'),
];

$rc_account_edit_fields = [
	'name' => [
		'method'        => 'textbox',
		'friendly_name' => __('Name', 'routerconfigs'),
		'description'   => __('Give this account a meaningful name that will be displayed.', 'routerconfigs'),
		'value'         => '|arg1:name|',
		'max_length'    => '64',
		],
	'username' => [
		'method'        => 'textbox',
		'friendly_name' => __('Username', 'routerconfigs'),
		'description'   => __('The username that will be used for authentication.', 'routerconfigs'),
		'value'         => '|arg1:username|',
		'max_length'    => '64',
		],
	'password' => [
		'method'        => 'textbox_password',
		'friendly_name' => __('Password', 'routerconfigs'),
		'description'   => __('The password used for authentication.', 'routerconfigs'),
		'value'         => '|arg1:password|',
		'default'       => '',
		'max_length'    => '64',
		'size'          => '30'
		],
	'enablepw' => [
		'method'        => 'textbox_password',
		'friendly_name' => __('Enable Password', 'routerconfigs'),
		'description'   => __('Your Enable Password, if required.', 'routerconfigs'),
		'value'         => '|arg1:enablepw|',
		'default'       => '',
		'max_length'    => '64',
		'size'          => '30'
		],
	'id' => [
		'method' => 'hidden_zero',
		'value'  => '|arg1:id|'
		]
];

$rc_device_edit_fields = [
	'enabled' => [
		'method'        => 'checkbox',
		'friendly_name' => __('Enable Device', 'routerconfigs'),
		'description'   => __('Uncheck this box to disabled this device from being backed up.', 'routerconfigs'),
		'value'         => '|arg1:enabled|',
		'default'       => '',
		'form_id'       => false
	],
	'hostname' => [
		'method'        => 'textbox',
		'friendly_name' => __('Description', 'routerconfigs'),
		'description'   => __('Name of this device (will be used for config saving and SVN if no hostname is present in config).', 'routerconfigs'),
		'value'         => '|arg1:hostname|',
		'max_length'    => '128',
	],
	'ipaddress' => [
		'method'        => 'textbox',
		'friendly_name' => __('IP Address', 'routerconfigs'),
		'description'   => __('This is the IP Address used to communicate with the device.', 'routerconfigs'),
		'value'         => '|arg1:ipaddress|',
		'max_length'    => '128',
	],
	'tftpserver' => [
		'method'        => 'textbox',
		'friendly_name' => __('TFTP Server', 'routerconfigs'),
		'description'   => __('Optional TFTP server this device copies its configuration to. Leave blank to use the global TFTP Server setting.', 'routerconfigs'),
		'value'         => '|arg1:tftpserver|',
		'max_length'    => '128',
	],
	'ssh_hostkey' => [
		'method'        => 'custom',
		'friendly_name' => __('Stored SSH Host Key', 'routerconfigs'),
		'description'   => __('Compare this host-key algorithm and SHA-1 hex fingerprint with the device through a trusted channel. On the server, replace the path below with the matching public host-key file and run: awk \'{print $2}\' /etc/ssh/ssh_host_ed25519_key.pub | base64 -d | openssl dgst -sha1 | awk \'{print toupper($NF)}\'. Use the device action to clear it after a legitimate key change.', 'routerconfigs'),
		'value'         => '|arg1:ssh_hostkey_display|',
	],
	'directory' => [
		'method'        => 'dirpath',
		'friendly_name' => __('Directory', 'routerconfigs'),
		'description'   => __('This is the relative directory structure used to store the configs.', 'routerconfigs'),
		'value'         => '|arg1:directory|',
		'max_length'    => '255',
	],
	'sched_type' => [
		'method'        => 'drop_array',
		'friendly_name' => __('Schedule Type', 'routerconfigs'),
		'description'   => __('How often to Backup this device. Daily with a Rerun Every of 10 performs a backup every 10 days; Monthly performs a true calendar-month backup.', 'routerconfigs'),
		'value'         => '|arg1:sched_type|',
		'default'       => SCHEDULE_DAILY,
		'array'         => plugin_routerconfigs_sched_types(),
	],
	'recur_every' => [
		'method'        => 'drop_array',
		'friendly_name' => __('Rerun Every', 'routerconfigs'),
		'description'   => __('For Daily schedules, rerun the backup every X days. For Weekly schedules without specific Days of Week, rerun it every X weeks; once Days of Week are selected the backup runs on those days every week and this field does not apply.', 'routerconfigs'),
		'value'         => '|arg1:recur_every|',
		'default'       => 1,
		'array'         => $rc_schedules_recur,
	],
	'day_of_week' => [
		'method'        => 'drop_multi',
		'friendly_name' => __('Days of Week', 'routerconfigs'),
		'description'   => __('For a Weekly schedule, the day(s) of the week to run the backup on, every week. Leave empty to run every Rerun Every weeks instead.', 'routerconfigs'),
		'value'         => '|arg1:day_of_week|',
		'array'         => plugin_routerconfigs_sched_days_of_week(),
	],
	'month' => [
		'method'        => 'drop_multi',
		'friendly_name' => __('Months of Year', 'routerconfigs'),
		'description'   => __('For a Monthly schedule, the month(s) of the year to run the backup in.', 'routerconfigs'),
		'value'         => '|arg1:month|',
		'array'         => plugin_routerconfigs_sched_months(),
	],
	'day_of_month' => [
		'method'        => 'drop_multi',
		'friendly_name' => __('Days of Month', 'routerconfigs'),
		'description'   => __('For a Monthly schedule, the day(s) of the month to run the backup on (Last = last day of the month).', 'routerconfigs'),
		'value'         => '|arg1:day_of_month|',
		'array'         => plugin_routerconfigs_sched_days_of_month(),
	],
	'monthly_week' => [
		'method'        => 'drop_multi',
		'friendly_name' => __('Weeks of Month', 'routerconfigs'),
		'description'   => __('For a Monthly on Day schedule, the week(s) of the month to run the backup in.', 'routerconfigs'),
		'value'         => '|arg1:monthly_week|',
		'array'         => plugin_routerconfigs_sched_weeks_of_month(),
	],
	'monthly_day' => [
		'method'        => 'drop_multi',
		'friendly_name' => __('Days of Week (Monthly)', 'routerconfigs'),
		'description'   => __('For a Monthly on Day schedule, the weekday(s) to pair with the Weeks of Month selection.', 'routerconfigs'),
		'value'         => '|arg1:monthly_day|',
		'array'         => plugin_routerconfigs_sched_days_of_week(),
	],
	'devicetype' => [
		'method'        => 'drop_sql',
		'friendly_name' => __('Device Type', 'routerconfigs'),
		'description'   => __('Choose the type of device that the router is.', 'routerconfigs'),
		'value'         => '|arg1:devicetype|',
		'sql'           => 'SELECT id, name FROM plugin_routerconfigs_devicetypes ORDER BY name',
		'default'       => 0,
		'none_value'    => __('Auto-Detect', 'routerconfigs'),
	],
	'connecttype' => [
		'method'        => 'drop_array',
		'friendly_name' => __('Connection Type', 'routerconfigs'),
		'description'   => __('This is the type of connection used to communicate with the device.', 'routerconfigs'),
		'value'         => '|arg1:connecttype|',
		'default'       => RCONFIG_CONNECT_DEFAULT,
		'array'         => $rc_connection_types,
	],
	'account' => [
		'method'        => 'drop_sql',
		'friendly_name' => __('Authentication Account', 'routerconfigs'),
		'description'   => __('Choose an account to use to Login to the router', 'routerconfigs'),
		'value'         => '|arg1:account|',
		'sql'           => 'SELECT id, name FROM plugin_routerconfigs_accounts ORDER BY name',
		'default'       => 0,
		'none_value'    => __('None', 'routerconfigs'),
	],
	'timeout' => [
		'friendly_name' => __('Default timeout', 'routerconfigs'),
		'description'   => __('Default time to wait in seconds for a response', 'routerconfigs'),
		'method'        => 'textbox',
		'value'         => '|arg1:timeout|',
		'max_length'    => '3',
		'size'          => '3',
		'default'       => '1'
	],
	'sleep' => [
		'friendly_name' => __('Default sleep time', 'routerconfigs'),
		'description'   => __('Default time to sleep in microseconds (1/1,000,000th of a second)', 'routerconfigs'),
		'method'        => 'textbox',
		'max_length'    => '10',
		'size'          => '8',
		'value'         => '|arg1:sleep|',
		'default'       => '125000'
	],
	'elevated' => [
		'method'        => 'checkbox',
		'friendly_name' => __('Assume elevated', 'routerconfigs'),
		'description'   => __('Check this box to assume this device is always elevated', 'routerconfigs'),
		'value'         => '|arg1:elevated|',
		'default'       => '',
		'form_id'       => false
	],
	'id' => [
		'method' => 'hidden_zero',
		'value'  => '|arg1:id|'
	]
];

$rc_devtype_edit_fields = [
	'name' => [
		'method'        => 'textbox',
		'friendly_name' => __('Name', 'routerconfigs'),
		'description'   => __('Name of this device type.', 'routerconfigs'),
		'value'         => '|arg1:name|',
		'size'          => '30',
		'max_length'    => '64',
	],
	'connecttype' => [
		'method'        => 'drop_array',
		'friendly_name' => __('Connection Type', 'routerconfigs'),
		'description'   => __('This is the type of connection used to communicate with the device.', 'routerconfigs'),
		'value'         => '|arg1:connecttype|',
		'default'       => RCONFIG_CONNECT_DEFAULT,
		'array'         => $rc_connection_types,
	],
	'promptuser' => [
		'method'        => 'textbox',
		'friendly_name' => __('Username Prompt', 'routerconfigs'),
		'description'   => __('This is the username prompt to match on login.', 'routerconfigs'),
		'value'         => '|arg1:promptuser|',
		'size'          => '20',
		'max_length'    => '64',
	],
	'promptpass' => [
		'method'        => 'textbox',
		'friendly_name' => __('Password Prompt', 'routerconfigs'),
		'description'   => __('This is the password prompt to match on login.', 'routerconfigs'),
		'value'         => '|arg1:promptpass|',
		'size'          => '20',
		'max_length'    => '255',
	],
	'configfile' => [
		'method'        => 'textbox',
		'friendly_name' => __('Configuration file', 'routerconfigs'),
		'description'   => __('This is the standard location of the configuration file on the remote device. It is used for SCP and SFTP method', 'routerconfigs'),
		'value'         => '|arg1:configfile|',
		'size'          => '20',
		'max_length'    => '64',
	],
	'copytftp' => [
		'method'        => 'textbox',
		'friendly_name' => __('Copy TFTP', 'routerconfigs'),
		'description'   => __('This is the CLI text used to send the backup to tftp server.', 'routerconfigs'),
		'value'         => '|arg1:copytftp|',
		'size'          => '20',
		'max_length'    => '64',
	],
	'version' => [
		'method'        => 'textbox',
		'friendly_name' => __('Show Version', 'routerconfigs'),
		'description'   => __('This is the CLI text used to display the current version.', 'routerconfigs'),
		'value'         => '|arg1:version|',
		'size'          => '20',
		'max_length'    => '64',
	],
	'promptconfirm' => [
		'method'        => 'textbox',
		'friendly_name' => __('Confirmation Prompt', 'routerconfigs'),
		'description'   => __('This is the confirmation prompt to match before transmission.', 'routerconfigs'),
		'value'         => '|arg1:promptconfirm|',
		'size'          => '20',
		'max_length'    => '64',
	],
	'confirm' => [
		'method'        => 'textbox',
		'friendly_name' => __('Confirm', 'routerconfigs'),
		'description'   => __('Is there a confirmation prompt for copying the config?', 'routerconfigs'),
		'value'         => '|arg1:confirm|',
		'size'          => '10',
		'max_length'    => '64',
	],
	'forceconfirm' => [
		'method'        => 'checkbox',
		'friendly_name' => __('Force Confirm', 'routerconfigs'),
		'description'   => __('Is there a force confirmation prompt for copying the config?', 'routerconfigs'),
		'value'         => '|arg1:forceconfirm|',
	],
	'checkendinconfig' => [
		'method'        => 'checkbox',
		'friendly_name' => __('Check End in Config', 'routerconfigs'),
		'description'   => __('Check end in config?', 'routerconfigs'),
		'value'         => '|arg1:checkendinconfig|',
	],
	'elevated' => [
		'method'        => 'checkbox',
		'friendly_name' => __('Assume elevated', 'routerconfigs'),
		'description'   => __('Check this box to assume this device is always elevated', 'routerconfigs'),
		'value'         => '|arg1:elevated|',
		'default'       => '',
		'form_id'       => false
	],
	'timeout' => [
		'friendly_name' => __('Default timeout', 'routerconfigs'),
		'description'   => __('Default time to wait in seconds for a response', 'routerconfigs'),
		'method'        => 'textbox',
		'value'         => '|arg1:timeout|',
		'default'       => '1',
		'size'          => '3',
		'max_length'    => '3'
	],
	'sleep' => [
		'friendly_name' => __('Default sleep time', 'routerconfigs'),
		'description'   => __('Default time to sleep in microseconds (1/1,000,000th of a second)', 'routerconfigs'),
		'method'        => 'textbox',
		'value'         => '|arg1:sleep|',
		'default'       => '125000',
		'size'          => '8',
		'max_length'    => '7'
	],
	'anykey' => [
		'friendly_name' => __('Any Key prompt', 'routerconfigs'),
		'description'   => __('Text to match a \'Press Any Key To Continue\' styled prompt', 'routerconfigs'),
		'method'        => 'textbox',
		'value'         => '|arg1:anykey|',
		'default'       => '',
		'max_length'    => '50'
	],
	'loginprompts' => [
		'friendly_name' => __('Post-Login Prompts', 'routerconfigs'),
		'description'   => __('Optional prompt/response pairs sent right after login, before the copy-to-TFTP command, one per line as prompt|response. The prompt is matched as a case-insensitive regular expression against the device output (so it cannot itself contain |), and the response is sent as typed, or use <CR> to send just a carriage return. A response is only sent once its prompt has been seen; otherwise a warning is logged and nothing is sent. These are answered first; the Any Key prompt above is answered later, while the device is being enabled, so a prompt answered here never reaches Any Key. Example: Press any key|<CR>', 'routerconfigs'),
		'method'        => 'textarea',
		'value'         => '|arg1:loginprompts|',
		'textarea_rows' => '4',
		'textarea_cols' => '60',
	],
	'id' => [
		'method' => 'hidden_zero',
		'value'  => '|arg1:id|'
	]
];

$rc_settings = [
	'routerconfigs_header' => [
		'friendly_name' => __('General Settings', 'routerconfigs'),
		'method'        => 'spacer',
	],
	'routerconfigs_presentation' => [
		'friendly_name' => __('Presentation Mode', 'routerconfigs'),
		'description'   => __('Should Router Configs be presented on the Console or a Top Tab.', 'routerconfigs'),
		'method'        => 'drop_array',
		'default'       => 'console',
		'array'         => ['console' => __('Console', 'routerconfigs'), 'toptab' => __('Top Tab', 'routerconfigs')]
	],
	'routerconfigs_diff_context' => [
		'friendly_name' => __('Config Diff Context Lines', 'routerconfigs'),
		'description'   => __('The default number of unchanged lines to display before and after each change when comparing two backups. A larger value makes it easier to see the surrounding configuration context. This may be overridden per comparison on the Compare page.', 'routerconfigs'),
		'method'        => 'drop_array',
		'default'       => '3',
		'array'         => $rc_diff_context,
	],
	'routerconfigs_timeout' => [
		'friendly_name' => __('Default timeout', 'routerconfigs'),
		'description'   => __('Default time to wait in seconds for a response', 'routerconfigs'),
		'method'        => 'textbox',
		'max_length'    => '3',
		'size'          => '3',
		'default'       => '1'
	],
	'routerconfigs_connect_timeout' => [
		'friendly_name' => __('Connection timeout', 'routerconfigs'),
		'description'   => __('Maximum time to wait in seconds when establishing the initial network connection to a device. A hung or unreachable device is abandoned after this timeout so a scheduled backup run continues with the remaining devices.', 'routerconfigs'),
		'method'        => 'textbox',
		'max_length'    => '3',
		'size'          => '3',
		'default'       => '10'
	],
	'routerconfigs_sleep' => [
		'friendly_name' => __('Default sleep time', 'routerconfigs'),
		'description'   => __('Default time to sleep in microseconds (1/1,000,000th of a second)', 'routerconfigs'),
		'method'        => 'textbox',
		'max_length'    => '10',
		'size'          => '8',
		'default'       => '125000'
	],
	'routerconfigs_exit' => [
		'friendly_name' => __('Close without exit', 'routerconfigs'),
		'description'   => __('If ticked, when closing down the device connection, no \'exit\' command is issued', 'routerconfigs'),
		'method'        => 'checkbox'
	],
	'routerconfigs_debug_buffer' => [
		'friendly_name' => __('Debug Connection Buffer', 'routerconfigs'),
		'description'   => __('Whether to log direct output of device connection', 'routerconfigs'),
		'method'        => 'checkbox'
	],
	'routerconfigs_elevated' => [
		'friendly_name' => __('Assume all devices elevated', 'routerconfigs'),
		'description'   => __('Whether to assume all devices are elevated', 'routerconfigs'),
		'method'        => 'checkbox'
	],
	'routerconfigs_hour' => [
		'friendly_name' => __('Download Hour', 'routerconfigs'),
		'description'   => __('The hour of the day to perform the full downloads.', 'routerconfigs'),
		'method'        => 'drop_array',
		'default'       => '0',
		'array'         => $rc_schedules_download,
	],
	'routerconfigs_retry' => [
		'friendly_name' => __('Retry Schedule', 'routerconfigs'),
		'description'   => __('The time to wait before attempting to perform an additional download when scheduled download fails', 'routerconfigs'),
		'method'        => 'drop_array',
		'default'       => '4',
		'array'         => $rc_schedules_retry,
	],
	'routerconfigs_retention' => [
		'friendly_name' => __('Retention Period', 'routerconfigs'),
		'description'   => __('The number of days to retain old backups.', 'routerconfigs'),
		'method'        => 'drop_array',
		'default'       => '30',
		'array'         => $rc_schedules_retention,
	],
	'routerconfigs_retention_keep_inactive' => [
		'friendly_name' => __('Keep Inactive Device Backups', 'routerconfigs'),
		'description'   => __('When checked, the retention cleanup will not delete aged-out backups that belong to disabled devices or to devices that have been removed, so configurations for decommissioned or unreachable equipment are retained.', 'routerconfigs'),
		'method'        => 'checkbox',
		'default'       => ''
	],
	'routerconfigs_header_transfer' => [
		'friendly_name' => __('Transfer Options', 'routerconfigs'),
		'method'        => 'spacer',
	],
	'routerconfigs_connecttype' => [
		'friendly_name' => __('Default connection type', 'routerconfigs'),
		'description'   => __('Default type of connection used to communicate with the device.', 'routerconfigs'),
		'method'        => 'drop_array',
		'default'       => RCONFIG_CONNECT_BOTH,
				'array'       => $rc_connection_types_settings,
	],
	'routerconfigs_archive_separate' => [
		'friendly_name' => __('Separate By Device', 'routerconfigs'),
		'description'   => __('Separate archived Configs into a folder per device', 'routerconfigs'),
		'method'        => 'checkbox',
		'default'       => 'on'
	],
	'routerconfigs_archive_datedir' => [
		'friendly_name' => __('Dated Subdirectory', 'routerconfigs'),
		'description'   => __('Insert a date-based subdirectory into the archive path (before the per-device folder), so each run is stored under its backup date, e.g. .../2020-11-04/hostname/.... Off preserves the existing layout.', 'routerconfigs'),
		'method'        => 'drop_array',
		'default'       => '',
		'array'         => plugin_routerconfigs_sched_date_dir_formats(),
	],
	'routerconfigs_scp_path' => [
		'friendly_name' => __('SCP Path', 'routerconfigs'),
		'description'   => __('When using SCP, leaving this blank will use PHP\'s SCP module which does not always work. External SCP is disabled while SSH host key verification is enabled because it cannot reuse the verified connection.', 'routerconfigs'),
		'method'        => 'textbox',
		'max_length'    => 255,
		'size'          => '50',
	],
	'routerconfigs_verify_hostkey' => [
		'friendly_name' => __('Verify SSH Host Keys', 'routerconfigs'),
		'description'   => __('Record each device\'s SSH host key on first connect and refuse to send credentials if it later changes. SSH/Telnet mode will not fall back to Telnet while verification is enabled. After a legitimate key change, use the device action to clear the stored host key.', 'routerconfigs'),
		'method'        => 'checkbox',
		'default'       => ''
	],
	'routerconfigs_tftpserver' => [
		'friendly_name' => __('TFTP Server IP', 'routerconfigs'),
		'description'   => __('Must be an IP pointing to your Cacti server.', 'routerconfigs'),
		'method'        => 'textbox',
		'max_length'    => 255,
		'size'          => '30',
		'default'       => gethostbyname($rc_hostname)
	],
	'routerconfigs_backup_path' => [
		'friendly_name' => __('TFTP Backup Directory Path', 'routerconfigs'),
		'description'   => __('The path to where your Configs will be backed up, it must be the path that the local TFTP Server writes to.', 'routerconfigs'),
		'method'        => 'dirpath',
		'max_length'    => 255,
		'size'          => '50',
		'default'       => $config['base_path'] . '/backups/'
	],
	'routerconfigs_archive_path' => [
		'friendly_name' => __('Archive Directory Path', 'routerconfigs'),
		'description'   => __('The path to where your Configs will be archived (moved from TFTP directory)', 'routerconfigs'),
		'method'        => 'dirpath',
		'max_length'    => 255,
		'size'          => '50',
		'default'       => $config['base_path'] . '/plugins/routerconfigs/backups/'
	],
	'routerconfigs_header_email' => [
		'friendly_name' => __('Email Options', 'routerconfigs'),
		'method'        => 'spacer',
	],
	'routerconfigs_from' => [
		'friendly_name' => __('From Address', 'routerconfigs'),
		'description'   => __('Email address the nightly backup will be sent from.', 'routerconfigs'),
		'method'        => 'textbox',
		'size'          => 40,
		'max_length'    => 255,
		'default'       => ''
	],
	'routerconfigs_name' => [
		'friendly_name' => __('From Name', 'routerconfigs'),
		'description'   => __('Name the nightly backup will be sent from.', 'routerconfigs'),
		'method'        => 'textbox',
		'size'          => 40,
		'max_length'    => 255,
		'default'       => ''
	],
	'routerconfigs_email' => [
		'friendly_name' => __('Email Address', 'routerconfigs'),
		'description'   => __('A comma delimited list of Email addresses to send the nightly backup Email to.', 'routerconfigs'),
		'method'        => 'textarea',
		'class'         => 'textAreaNotes',
		'textarea_rows' => '5',
		'textarea_cols' => '40',
		'size'          => 40,
		'max_length'    => 255,
		'default'       => ''
	],
	'routerconfigs_email_diff' => [
		'friendly_name' => __('Include Config Diffs', 'routerconfigs'),
		'description'   => __('Append a unified diff of each device whose configuration changed during the run to the backup Email, so a reviewer can see exactly what changed. Binary (non-text) configurations are skipped. Volatile lines that change on every backup (Cisco IOS \'! Last configuration change\', \'! NVRAM config last updated\', \'ntp clock-period\' and \'Current configuration :\', and NX-OS \'!Time:\' and \'!Running configuration last done at:\') are always ignored. Warning: the diff is sent in clear text to every recipient of the Email, so it can reveal configuration that should stay private; leave Include Secrets in Diffs unchecked.', 'routerconfigs'),
		'method'        => 'checkbox',
		'default'       => ''
	],
	'routerconfigs_email_diff_secrets' => [
		'friendly_name' => __('Include Secrets in Diffs', 'routerconfigs'),
		'description'   => __('Send secret values in the emailed diff as they are. When unchecked (the default), the changed lines are found on the raw configurations and printed redacted, failing closed: lines containing secret-like words (passw, passphrase, pwd, secret, psk, key, community, md5, authentication, credential, token, private) and SNMP host and user lines are reduced to their first word followed by &lt;secret hidden&gt;, and every line of a PEM block, a certificate or key-string block, or a multi-line quoted value is hidden; a block that never ends hides the rest of the configuration. This also hides harmless lines (e.g. a description mentioning a key), but every changed line still shows up, marked (changed) when only its hidden part differs, and a block that never ends is noted in the Email. Stored backups are never changed.', 'routerconfigs'),
		'method'        => 'checkbox',
		'default'       => ''
	],
	'routerconfigs_email_diff_exclude' => [
		'friendly_name' => __('Diff Exclude Patterns', 'routerconfigs'),
		'description'   => __('Optional regular expressions (one per line) matching configuration lines to ignore when comparing, so volatile lines such as timestamps or serial numbers do not show up as changes. Each pattern is matched case-insensitively anywhere in a line unless anchored with ^ or $; a pattern that is not a valid regular expression is ignored.', 'routerconfigs'),
		'method'        => 'textarea',
		'class'         => 'textAreaNotes',
		'textarea_rows' => '4',
		'textarea_cols' => '40',
		'default'       => ''
	],
];

// Added to the settings by routerconfigs_config_settings() only when Thold's
// plugin_notification_lists table exists, since its drop_sql needs it.
$rc_notification_list_setting = [
	'friendly_name' => __('Thold Notification List', 'routerconfigs'),
	'description'   => __('When Thold is installed, also send the backup Email to the recipients of the selected Thold Notification List.', 'routerconfigs'),
	'method'        => 'drop_sql',
	'default'       => '0',
	'none_value'    => __('None', 'routerconfigs'),
	'sql'           => 'SELECT id, name FROM plugin_notification_lists ORDER BY name',
];
