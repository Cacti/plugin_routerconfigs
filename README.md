# routerconfigs

Router Configs backs up router and switch configurations, keeps an archive of
previous versions, and shows the differences between backups. Devices can use
TFTP transfers or SSH command capture, depending on their device type.

The 2.0 release adds calendar schedules, optional configuration diffs in email,
dated archive directories, and controls for inactive-device backups. This source
branch also includes the SSH capture device types listed below.

## Versions and compatibility

This README describes the `develop` source branch.

| Code | Plugin version | Minimum Cacti declared in `INFO` |
| --- | --- | --- |
| [Source branch](INFO) | 2.1 | 1.2.29 |
| [Published v2.0](https://github.com/Cacti/plugin_routerconfigs/releases/tag/v2.0) | 2.0 | 1.2.29 |

Use the published release’s assets and tagged documentation when installing that
package. Branch code can include later changes, even when its version number
matches the release. Cacti’s own PHP and database requirements also apply.

Install this plugin in **`<cacti-root>/plugins/routerconfigs`**, using the directory
name `routerconfigs` rather than `plugin_routerconfigs`.

See [CHANGELOG.md](CHANGELOG.md) for the version history.

## Problematic Devices

Some HP devices are returning ANSI codes as part of their output, rather than
normal ASCII output given by most other devices.  These devices currently will
not work with Router Configs.

To verify this, use the debug buffer option to capture all input/output used by
routerconfigs. If the output does not look as you would see when running these
commands manually, the chances are the device is experiencing this issue. Please
feel free to post an issue on the GitHub site for verification should you
require assistance.

## Cacti compatibility

If you are running a version of Cacti below 1.2.31, please add the function
below to the `applySkin()` function in `include/layout.js` to enable the Cancel
buttons on forms to work:

```js
$(document).off('click.cactiReturnTo', '.cactiReturnTo')
    .on('click.cactiReturnTo', '.cactiReturnTo', function(event) {
        event.preventDefault();
        cactiReturnTo($(this).attr('data-url'));
    });
```

## Installation

Just like any other Cacti plugin, untar the package to the Cacti plugins
directory, rename the directory to 'routerconfigs', and then from Cacti's Plugin
Management interface, Install and Enable the plugin.

TFTP device types require a reachable TFTP server and writable backup directory.
SSH capture device types need PHP’s `ssh2` extension and SSH access to the device;
they do not require TFTP. Configure the service using your supported operating
system’s package and service documentation. The CentOS 6 example below is historical.

There are a few options in Cacti you will need to change to then get the plugin
up and running.  They are located under Settings > Router Configs

Setting | Description
--- | ---
TFTP Server IP | The IP Address of the Cacti server given to the routers
TFTP Backup Directory Path | The directory that your TFTP server stores its files
Archive Directory Path | The directory to copy the configuration to
Separate By Device | Archive configurations into a folder per device
Dated Subdirectory | Insert a date-based subdirectory into the archive path, before the per-device folder, so each run is stored under its backup date
SCP Path | Path of an external SCP binary; leave blank to use PHP's SCP module. External SCP is disabled while Verify SSH Host Keys is on
Verify SSH Host Keys | Record each device's SSH host key on first connect and refuse to send credentials if it later changes; SSH/Telnet mode will not fall back to Telnet while this is on
Include Config Diffs | Append a unified diff of each changed device to the backup Email; secret values are redacted unless Include Secrets in Diffs is checked
Include Secrets in Diffs | Send secret values in the emailed diff as they are
Diff Exclude Patterns | Regular expressions, one per line, for configuration lines to ignore when comparing

Access to the plugin pages is controlled by the 'Router Configs' realm.

With those global settings in place, you will then need to - Create a device
type - Create an authentication account - Create a device

Verify the archive and transfer-directory permissions using the accounts that
run the Cacti poller, web server and transfer service.

## Built-in device types

These device types are created on install and upgrade. An existing device type
with the same name is never overwritten, so edit them freely. Prompts, commands
and privilege levels differ between models and firmware releases, so check a
definition against your own devices before relying on it.

Device type | Method | Command
--- | --- | ---
Cisco IOS, Cisco CatOS, Cisco Nexus | TFTP | `copy ... tftp`
HP Comware, Dell Switch | TFTP | `copy ... tftp`
MikroTik RouterOS | SSH capture | `/export`
Juniper Junos | SSH capture | `show configuration`
Arista EOS | SSH capture | `show running-config`
HPE Aruba CX | SSH capture | `show running-config`
Extreme EXOS | SSH capture | `show configuration`
Huawei VRP | SSH capture | `display current-configuration`
Fortinet FortiOS | SSH capture | `show full-configuration`
Ruckus ICX | TFTP | `copy running-config tftp %SERVER% %FILE%`
Ubiquiti EdgeSwitch | TFTP | `copy nvram:startup-config tftp://%SERVER%/%FILE%`
Netgear Managed | TFTP | `copy nvram:startup-config tftp://%SERVER%/%FILE%`
TP-Link JetStream | TFTP | `copy startup-config tftp ip-address %SERVER% filename %FILE%`

SSH capture device types need the SSH service on the device and a user that can
run the command without an enable step (for example privilege 15 or a
read-only role); they never fall back to Telnet and need no TFTP server. Use
the MikroTik section below as the model for the account and the notes on
sensitive values and diff exclusion. Other HPE Aruba (ArubaOS-Switch) stacks can
use a TFTP device type with Post-Login Prompts.

## MikroTik RouterOS

MikroTik RouterOS cannot copy its configuration to a TFTP server, so the
built-in `MikroTik RouterOS` device type uses a **Capture Command** instead: the
plugin logs in over SSH, runs `/export` on its own exec channel, and saves the
output as the backup. No TFTP server is involved for these devices, so one does
not need to be set for them (other device types still need it).

To back up a MikroTik device:

1. Create an authentication account for a RouterOS user that is allowed to log
   in over SSH and run `/export` (the `ssh` and `read` policies; `show-sensitive`
   also needs `sensitive`). The default `admin` user works, but a dedicated user
   in a read-only group is preferable, as the credentials are stored by the
   plugin. Only password authentication is supported.
2. Create a device and choose the `MikroTik RouterOS` device type. A capture
   command always runs over SSH, whatever the Connection Type.
3. Make sure the SSH service is enabled on the router (`/ip service`).

Notes:

* `/export` omits sensitive values (passwords, keys, secrets) on RouterOS 7, so
  a backup is a readable record of the configuration, not a full restore image.
  To include most of them, set the Capture Command to `/export show-sensitive`,
  and protect the backup directory accordingly; user account passwords are never
  exported.
* The first line of an export is a header comment with the export date and
  time, so it shows up as a difference between two backups. Add it to the `Diff
  Exclude Patterns` setting if you do not want it in emailed diffs.
* Any other device type can use the same field with a command that prints its
  configuration to the terminal.
* Restoring a saved configuration back to a device is not supported.

## Bugs and Feature Enhancements

Bug and feature enhancements for the routerconfigs plugin are handled in GitHub.
If you find a first search the Cacti forums for a solution before creating an
issue in GitHub.

## TFTP Server setup example (CentOS 6, historical)

This example targets an obsolete operating system and its `xinetd` service. Use
your current distribution’s TFTP documentation for a new installation.

For CentOS 6, just run these commands:

```console
yum install tftp-server
```

The edit the tftp startup script (/etc/xinetd.d/tftp) to change the server
arguments, I used this line:

```console
server_args= -c -s /home/configs/backups
```

You will need to create this folder (or whatever folder you specify) and give
the apache server and the tftp server permissions to access it

Then we just need to turn on the tftp server so do this.

```console
chkconfig xinetd on
service xinetd start
```

-----------------------------------------------
Copyright (c) 2004-2026 - The Cacti Group, Inc.
