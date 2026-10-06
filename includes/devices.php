<?php
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
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

/*
 * Cacti "Console -> Management -> Devices" action hooks that let an operator
 * add (import) selected Cacti devices into RouterConfigs for backup, modelled
 * on MacTrack's device import. Registered in setup.php and invoked by
 * host.php; the per-device add/reject logic lives in
 * plugin_routerconfigs_import_cacti_device() (includes/functions.php) so it
 * can be unit tested (issue #133, closes #115).
 */

/**
 * Add the RouterConfigs import entry to the Devices page action dropdown.
 *
 * The Devices page (host.php) authorizes on Device Management, not the
 * RouterConfigs realm, so the entry is only offered to users who also hold
 * the RouterConfigs (router-devices.php) realm.
 *
 * @param array $action The existing Device Management action map.
 *
 * @return array The action map with the RouterConfigs import action added.
 */
function routerconfigs_device_action_array($action) {
	if (api_plugin_user_realm_auth('router-devices.php')) {
		$action['plugin_routerconfigs_device'] = __('Add to RouterConfigs Backup', 'routerconfigs');
	}

	return $action;
}

/**
 * Render the confirmation body for the RouterConfigs import action, listing
 * the selected Cacti devices that will be added.
 *
 * @param array $save The bulk-action form submission (includes 'drp_action',
 *                   and 'host_array'/'host_list' when devices were selected).
 *
 * @return array The unmodified $save array, for hook chaining.
 */
function routerconfigs_device_action_prepare($save) {
	if (isset($save['drp_action']) && $save['drp_action'] == 'plugin_routerconfigs_device') {
		if (isset($save['host_array'])) {
			print '<tr>';
			print "<td colspan='2' class='textArea'><p>" .
				__('Click \'Continue\' to add the following Device(s) to RouterConfigs for backup. Any device already present in RouterConfigs is skipped.', 'routerconfigs') .
				'</p><ul>' . $save['host_list'] . '</ul></td>';
			print '</tr>';
		}
	}

	return $save;
}

/**
 * Execute the RouterConfigs import action, adding each selected Cacti device
 * and raising a per-device added/rejected message.
 *
 * @param string $action The action id chosen on the Devices page.
 *
 * @return string The unmodified $action, for hook chaining.
 *
 * @global array $config Cacti global configuration array; used to locate the
 *                      plugin's functions library.
 */
function routerconfigs_device_action_execute($action) {
	global $config;

	if ($action == 'plugin_routerconfigs_device') {
		// host.php authorizes on Device Management, not the RouterConfigs realm;
		// enforce it here so a user without RouterConfigs access cannot import.
		if (!api_plugin_user_realm_auth('router-devices.php')) {
			return $action;
		}

		if (isset_request_var('selected_items')) {
			$selected_items = sanitize_unserialize_selected_items(get_nfilter_request_var('selected_items'));

			if ($selected_items != false) {
				require_once($config['base_path'] . '/plugins/routerconfigs/includes/functions.php');

				foreach ($selected_items as $host_id) {
					plugin_routerconfigs_import_cacti_device((int) $host_id);
				}
			}
		}
	}

	return $action;
}
