# Cheesy Internet Monitor

A cheesy internet monitor that can interract with [my Netgear modem rebooter](https://github.com/SkUrRiEr/netgear_modem_rebooter).

This consists of:
 - `check_connectivity.sh` which does the actual internet monitoring,
 - `store_data.php` which stores different state into the database,
 - `config.php` which contains the database configuration and
 - `index.php` which is a simple monitoring website with a graph.

## Dependencies

 - PHP
   - PDO
   - MySQL PDO module
   - GD (optional)
 - A MySQL database initialised with the database schema in `db_schema.sql`
 - A web server to run the monitor page on (optional)
 - jQuery for the javascript parts (optional)

Optional parts are required for the monitoring page.

## Installation

1. Put the files somewhere your webserver can see (optional)
2. Create a directory called `jQuery` and extract a jQuery release into it (optional)
3. Initialise a database with `db_schema.sql`
4. Modify `config.php` with the database configuration and a path to a font (font optional)
5. Arrange for `check_connectivity.sh` to be called periodically

Optional parts are required for the monitoring page.

## Example config files

`config.php`
```
<?php

$db = new PDO("mysql:host=127.0.0.1;dbname=cheesy_internet_monitor", "monitor", "gouda");
$font = "/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf";
```

`crontab` - for running this periodically with cron
```
*/1 * * * * /home/stilton/internetmonitor/check_connectivity.sh
```

## Hacking to suit your particular setup

`check_connectivity.sh` has all the actual monitoring code in it. By default, this expects to:

1. Be able to resolve www.google.com on your router at 192.168.100.254
2. Be able to ping 8.8.8.8 (One of Google's public DNS servers)
3. Find a file at `/tmp/wait_for_modem.stamp` when your router starts rebooting

However you can easily re-write it to do whatever you want or ignore it entirely and write your own method of logging the data.

`store_data.php` has a simple API which does the following:
 - `store_data.php dns down` - logs when DNS goes down
 - `store_data.php dns up` - logs when DNS goes up
 - `store_data.php conn down` - logs when connectivity goes down
 - `store_data.php conn up` - logs when connectivity goes up
 - `store_data.php reboot start` - logs when the router is rebooted

`store_data.php` is designed to track outage lengths by logging their start and end times in the database.

The database contains records in the `conlog` table with the following columns
 - `eventID` incrementing event ID
 - `dns_down` timestamp of DNS going down
 - `dns_up` timestamp of DNS going up
 - `conn_down` timestamp of connectivity going down
 - `conn_up` timestamp of connectivity going up
 - `reboot_start` timestamp when the router is rebooted

An event is considered "open" if either DNS or connectivity has gone down and not gone up yet.

Open events are added to by "down" or "reboot" calls and are closed once there is a corresponding "up" timestamp for every "down" timestamp. "Up" and "reboot" events are ignored if there is no open event.

For each instance of connectivity or DNS going down, only the first down, reboot and up times are recorded.

I.e. the following sequence of events:

```
12:13 store_data.php dns down
12:14 store_data.php dns down
12:15 store_data.php dns up
12:16 store_data.php conn down
12:17 store_data.php conn down
12:18 store_data.php dns down
12:19 store_data.php reboot start
12:20 store_data.php conn up
12:21 store_data.php dns up
12:22 store_data.php conn up
12:23 store_data.php dns up
```

Would result in two records:

| `eventID` | `dns_down` | `dns_up` | `conn_down` | `conn_up` | `reboot_start` |
| --------- | ---------- | -------- | ----------- | --------- | -------------- |
| 1         | 12:13      | 12:15    |             |           |                |
| 2         | 12:18      | 12:21    | 12:16       | 12:20     | 12:19          |

`index.php` takes the newest event and formats it's data for display.
