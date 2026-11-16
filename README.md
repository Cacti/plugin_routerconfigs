# routerconfigs

The routerconfigs plugin is designed to act in conjunction with the Cacti servers
tftp server to receive backups from your router devices.  It also provides the
ability to view and diff those router configurations as they change over time.
It is designed primarily for Cisco device types, but may work with other device
types.

NOTE: Whilst this plugin is actively maintained by the Cacti Group, it is a
community plugin that primarily receives contributions from the Cacti community.
The Cacti Group has recently updated the plugin functionality to support the
vast majority of devices on Cacti 1.x and beyond.  Help from the community on
functionality changes, compatibility and the like are always welcome.

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

This plugin requires a TFTP server on the Cacti server (see below for an example
under CentOS 6)

There are a few options in Cacti you will need to change to then get the plugin
up and running.  They are located under Settings > Router Configs

Setting | Description
--- | ---
TFTP Server IP | The IP Address of the Cacti server given to the routers
TFTP Backup Directory Path | The directory that your TFTP server stores its files
Archive Path | The directory to copy the configuration to

With those global settings in place, you will then need to - Create a device
type - Create an authentication account - Create a device

On other operating systems, or for CentOS 7, you will have to find equivalent
instructions.

## MikroTik RouterOS

MikroTik RouterOS cannot copy its configuration to a TFTP server, so the
built-in `MikroTik RouterOS` device type uses a **Capture Command** instead: the
plugin logs in over SSH, runs `/export` on its own exec channel, and saves the
output as the backup. No TFTP server is involved for these devices.

To back up a MikroTik device:

1. Create an authentication account for a RouterOS user that is allowed to log
   in over SSH and run `/export` (the `ssh` and `read` policies; `show-sensitive`
   also needs `sensitive`). The default `admin` user works, but a dedicated user
   in a read-only group is preferable, as the credentials are stored by the
   plugin. Only password authentication is supported.
2. Create a device and choose the `MikroTik RouterOS` device type. Its
   Connection Type is SSH; Telnet is not supported for capture.
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

## TFTP Server setup example (CentOS 6)

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

I have provided a copy of this file for you.  Then we just need to turn on the
tftp server so do this.

```console
chkconfig xinetd on
service xinetd start
```

-----------------------------------------------
Copyright (c) 2004-2026 - The Cacti Group, Inc.
