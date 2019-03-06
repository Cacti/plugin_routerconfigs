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

define('RCONFIG_ACCOUNT_DELETE', 1);

define('RCONFIG_DEVICE_BACKUP',  1);
define('RCONFIG_DEVICE_DELETE',  2);
define('RCONFIG_DEVICE_ENABLE',  3);
define('RCONFIG_DEVICE_DISABLE', 4);

define('RCONFIG_DEVTYPE_DELETE', 1);

define('RCONFIG_CONNECT_DEFAULT', '');
define('RCONFIG_CONNECT_BOTH',   'both');
define('RCONFIG_CONNECT_SSH',    'ssh');
define('RCONFIG_CONNECT_TELNET', 'telnet');
define('RCONFIG_CONNECT_SCP',    'scp');
define('RCONFIG_CONNECT_SFTP',   'sftp');

define('RCONFIG_BACKUP_DAILY',   1);
define('RCONFIG_BACKUP_WEEKLY',  7);
define('RCONFIG_BACKUP_MONTHLY', 10);

define('RCONFIG_RETENTION_MONTH_ONE', 30);
define('RCONFIG_RETENTION_MONTH_TWO', 60);
define('RCONFIG_RETENTION_MONTH_THREE', 90);
define('RCONFIG_RETENTION_MONTH_FOUR', 120);
define('RCONFIG_RETENTION_MONTH_SIX', 180);
define('RCONFIG_RETENTION_YEAR_ONE', 365);
define('RCONFIG_RETENTION_YEAR_TWO', 730);
define('RCONFIG_RETENTION_YEAR_THREE', 1095);
define('RCONFIG_RETENTION_YEAR_FOUR', 1460);
define('RCONFIG_RETENTION_YEAR_FIVE', 1825);
