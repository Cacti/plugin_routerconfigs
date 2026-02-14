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
