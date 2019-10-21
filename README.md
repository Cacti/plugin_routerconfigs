# routerconfigs

The routerconfigs plugin is designed to act in conjuction with the Cacti servers
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
commands manually, the chances are the device is experiencing this issue.
Please feel free to post an issue on the GitHub site for verification should you
require assistance.

## Installation

Just like any other Cacti plugin, untar the package to the Cacti plugins
directory, rename the directory to 'routerconfigs', and then from Cacti's Plugin
Management interface, Install and Enable the pluign.

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

## ChangeLog

--- 1.5.2 ---

* feature#110: Allow confirmation prompt to be dynamically defined

--- 1.5.1 ---

* issue#105: New backups created actually backup but do not show up in user
  interface

* issue#106: Devices with no devicetype set cause runtime errors

* feature#111: Enable backup to assume elevation for devices/types that do
  not offer elevation

--- 1.5.0 ---

* issue#95: Upgrade does not function as expected

* issue#96: i18n is not being properly utilised

* issue#101: Hours do not display properly for retry schedule

* issue#100: When attempting to compare device backups, warning is issued about
  unsaved changes

* issue#104: Device types do not populate when installing from scratch

* feature#74: Feature Request: Enable SCP/SFTP method of backup

* feature#97: Allow connection type to be inherited

--- 1.4.2 ---

* issue#90: Undefined column 'AnyKey'

* issue#91: Empty settings against device type can cause runtime errors

--- 1.4.1 ---

* issue#87: Login stalls due to "Press Any Key" styled prompt

* issue#88: Configuration files are not always completely downloaded

* feature#65: Make routerconfigs able to backup HP devices

--- 1.4.0 ---

* issue#57: Fixed issue with schedule time not being 100% right

* issue#60: PHP 5.4 generates runtime errors

* issue#61: Login Password being used when elevating via Enable

* issue#62: Undefined variable 'result' when attempting to elevate during backup

* issue#63: Ensure correct action icons are displayed

* issue#64: Comparison between devices is not working as expected

* issue#69: View Configuration is broken with recent file-based changes

* issue#70: Allow close without issuing an exit command

* issue#72: Backup time showing only latest backup time

* issue#82: Field definitions for Device Type are missing max_length

* issue#84: Manual backup was not working from web interface

* feature#27: Improve Backup Directory Structure

--- 1.3.4 ---

* feature: Added field to set timeout and sleep

* feature: Added suffix to allow filtering in email systems on failure

* feature: Added Scheduled, Manual, Reattempt, Forced tags to subject line when
  applicable

* feature: Apply some styling to the backup email

* feature: Backup file now displayed in email

* feature: Added number of disabled devices to email

* issue: Fixed issue where nextbackup being null cause device to be ignored

* issue: Fixed issue where subject was being hard coded

* issue: Fixed issue where confirmation command always sent as 'y'

* issue: Fixed issue where number of rows when listing devices was blank

* issue: Fixed issue where telnet username prompt was only looked for once

* issue: Fixed issue where subject always thought it was manual

* issue: Fixed issue where stats cache may believe a file existed when it did
  not

* issue: Fixed issue where connection errors may be returned as blank

* issue: Fixed issue where %file% and %server% where not always replaced
  properly

--- 1.3.3 ---

* feature: Added field to clearly identify next automated backup time

* feature: Added field to clearly identify next automated retry time

* feature: Added setting for retry schedule

* feature: Rejigged device selection queries to use new fields

* feature: Move failed backups to beginning of email as you are more worried
  about those!

* issue: Fixed issue where hostname was incorrectly picked up and prevents wipe
  of hostname

* issue: Fixed issue where number of rows for filter pages didn't
  match displayed rows

* issue: Fixed issue where --retry option would not actually activate

* issue: Fixed issue where device was not marked for retry until after a
  connection was made

* issue: Fixed issue where if date matched week, month or year, backup date
  would not show 'Today' even if it was today

--- 1.3.2 ---

* feature: Add two new options, email name and download hour

* feature: Expanded editing area of email to, so email addresses can be seen

* issue: Another attempt to correct automatic vs manual backups

* issue: Prevent 0/0 emails from being generated

* issue: Prevent endless loop when connecting via telnet and not enabled

* issue: Correct string requirement error for command line options

--- 1.3.1 ---

* feature: Parameters can now be specified with values separated by space ( ) or
  equals sign (=)

* feature: Debug Buffer can be turned on via command line

* issue: Number of devices display in manual mode was always 0

* issue: Echo'd tftp command would sometimes cause premature termination

* issue: Only send destination filename and server once

* issue: Device inclusion could be duplicated causing more than one download

* issue: Correct spelling of 'socked'

* issue: Include successful devices in email if using --force option.

* issue: Correct wording during object creation, it's not connecting and needs
  classType

* issue: Suppress PHP warning when SSH connection fails

--- 1.3 ---

* feature: Completely rejigged PHPSsh/PHPTelnet to use base PHPConnection class

* feature: Moved RouterConfig settings to their own tab as more have been added

* feature: Added debug flag to suppress log output of debug buffer (does not
  affect adding to debug buffer to db)

* issue: A return was being sent to early in Telnet mode

* issue: SSH and Telnet would not operate the same after initial connection

* issue: TFTP bytes copied would not be picked up as a transfer completion

* issue: Midnight full download would not trigger without --retry cause no full
  downloads

* issue: Default sort column was not a valid field

--- 1.2 ---

* issue: Correct most config comparision code

* issue: Added in missing Horde library functions and created common include
  file

* issue: Fixed the poller spawn issue that was preventing automatic backups

* issue: Fixed comparison device/file selection code

* issue: Don't send email if nothing changed and checking for failures

* feature: Filtering - Added to both Devices and Backups tab

* feature: Backups - Now all use same code

* feature: Backups - Manual now work the same way as automatic ones

* feature: Backups - Manual can be done regardless of last attempt state

* feature: Backups - Success state should work with more devices

* feature: Backups - Devices can be specified on command line to run individual
  backups

* feature: Debug - Notifications are better formatted and consistent

* feature: Debug - Passwords are now hidden from debug for the most part.

* note: router-redownload.php no longer exists, any code relying on this should
  use router-download.php

--- 1.1 ---

* issue#9: Constructurs missing on the Horde class library

* issue#16: HTML style issues during enable/disable operations

* issue: Updating text domain for i18n

--- 0.3 ---

* compat: Remove PIA 1.x support

* compat: Register Realms

* compat: 0.8.7g

--- 0.2 ---

* feature: Allow the use of enable passwords

* feature: Add support for SSH Connections (thanks to Abdul Pallarés)

--- 0.1 ---

* Initial Release

* Nightly Backups

* View Config of any backup

* Compare Configs

* Automatically update Hostname from config file

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

