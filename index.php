<!DOCTYPE html>
<html>
    <head>
        <title>Iedex Internet Monitor</title>
        <meta http-equiv="refresh" content="30" />
        <style>
.emoticon {
    font-size: 20em;
    margin: 0px;
}

.desc {
    margin: 0px;
    font-size: 20em;
    white-space: pre;
}

.time {
    font-size: 20em;
    margin: 0px;
    white-space: pre;
}

p, body {
    margin: 0px;
}

body {
    text-align: center;
    overflow: hidden;
}
        </style>
        <script src="jQuery/jquery.js" type="text/javascript">
        </script>
<?php

require("config.php");

$sql = "SELECT * FROM conlog ORDER BY eventID DESC LIMIT 1";

$result = $db->query($sql);

$row = $result->fetch(PDO::FETCH_ASSOC);

$status = "UP";
$rebooting = false;
$time = null;

if ($row) {
    if ($row["conn_up"] == null && $row["dns_up"] == null && $row["conn_down"] != null && $row["dns_down"] != null) {
        $status = "DOWN";

        $time = min(strtotime($row["conn_down"]), strtotime($row["dns_down"]));
    } elseif ($row["conn_up"] == null && $row["conn_down"] != null) {
        $status = "CONN";

        $time = strtotime($row["conn_down"]);
    } elseif ($row["dns_up"] == null && $row["dns_down"] != null) {
        $status = "DNS";

        $time = strtotime($row["conn_up"]);
    } else {
        $status = "UP";

        if ($row["conn_up"] == null) {
            $time = strtotime($row["dns_up"]);
        } elseif ($row["dns_up"] == null) {
            $time = strtotime($row["conn_up"]);
        } else {
            $time = max(strtotime($row["conn_up"]), strtotime($row["dns_up"]));
        }
    }

    if ($row["reboot_start"] != null) {
        $rebooting = true;

        $time = strtotime($row["reboot_start"]);
    }
}

switch ($status) {
    case "UP":
        $emoticon = "&#x1f601;";
        $colour = "#00FF00";
        $desc = "UP";
        break;
    case "DOWN":
        $emoticon = "&#x1f620;";
        $colour = "#FF0000";
        $desc = "DOWN!!!";
        break;
    case "DNS":
    case "CONN":
        $emoticon = "&#x1f631;";
        $colour = "#FF7700";
        $desc = "DOWN!";
        break;
}

if ($status != "UP" && $rebooting) {
    $emoticon = "&#x1f5d8;";
    $desc = "REBOOTING...";
}

$uptime = time() - $time;

?>
        <script type="text/javascript">
var uptime = <?php echo $uptime ?>;
var curtime = Date.now() / 1000;
var lasttime = -1;

function format2digit(val) {
    var prefix = "00" + val;

    return prefix.slice(-2);
}

function timetick() {
    var now = Date.now() / 1000;

    var seconds = Math.round(uptime + now - curtime);

    if (seconds != lasttime) {
        lasttime = seconds;

        var minutes;
        var hours;
        var days;
        var outtime = seconds + " seconds";

        if (seconds > 60) {
            minutes = Math.floor(seconds / 60);
            seconds = format2digit(seconds - minutes * 60);
            outtime = minutes + ":" + seconds;
        }

        if (minutes > 60) {
            hours = Math.floor(minutes / 60);
            minutes = format2digit(minutes - hours * 60);
            outtime = hours + ":" + minutes + ":" + seconds;
        }

        if (hours > 24) {
            days = Math.floor(hours / 24);
            hours -= days * 24;
            outtime = days + " days, " + hours + ":" + minutes + ":" + seconds;
        }

        $(".time").html("For " + outtime);
    }

    resizeFn();

    window.requestAnimationFrame(timetick);
}

$(timetick);

/* emoticon: 70%
 * desc: 20%
 * time: 10%
 */

function resizeFn() {
    var width = $(window).innerWidth();
    var height = $(window).innerHeight() - 10;

    var emot = $(".emoticon");
    var desc = $(".desc");
    var time = $(".time");

    var max_efs = Math.floor(parseInt(emot.css("font-size")) * width / emot.width());
    var max_dfs = Math.floor(parseInt(desc.css("font-size")) * width / desc.width());
    var max_tfs = Math.floor(parseInt(time.css("font-size")) * width / time.width());

    var efs = Math.floor(height * 0.7);
    var dfs = Math.floor(height * 0.2);
    var tfs = Math.floor(height * 0.1);

    if (efs > max_efs) {
        efs = max_efs;

        dfs = Math.floor(efs * 2 / 7);

        if (dfs > max_dfs) {
            dfs = max_dfs;
        }

        tfs = Math.floor(dfs / 2);

        if (tfs > max_tfs) {
            tfs = max_tfs;
        }
    } else if (dfs > max_dfs) {
        dfs = max_dfs;

        tfs = Math.floor(dfs / 2);

        if (tfs > max_tfs) {
            tfs = max_tfs;
        }

        efs = Math.min(height - dfs - tfs, max_efs);
    } else if (tfs > max_tfs) {
        tfs = max_tfs;

        efs = Math.min(height - dfs - tfs, max_efs);
    }

    emot.css({
        fontSize: efs + "px",
        lineHeight: efs + "px"
    });

    desc.css({
        fontSize: dfs + "px",
        lineHeight: dfs + "px"
    });

    time.css({
        fontSize: tfs + "px",
        lineHeight: tfs + "px"
    });
}

$(resizeFn);

$(window).resize(resizeFn);
        </script>
    </head>
    <body style="background-color: <?php echo $colour ?>">
        <div><span class="emoticon"><?php echo $emoticon ?></span></div>
        <div><span class="desc"><?php echo $desc ?></span></div>
        <div><span class="time">For ...</span></div>
    </body>
</html>
