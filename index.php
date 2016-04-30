<!DOCTYPE html>
<html>
    <head>
        <meta http-equiv="refresh" content="10" />
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

$sql = "SELECT * FROM conlog ORDER BY COALESCE(LEAST(dns_down, conn_down), dns_down, conn_down) DESC LIMIT 1";

$result = $db->query($sql);

$row = $result->fetch(PDO::FETCH_ASSOC);

$status = "UP";
$rebooting = false;
$eventtime = null;

if ($row) {
    if ($row["conn_up"] == null && $row["dns_up"] == null && $row["conn_down"] != null && $row["dns_down"] != null) {
        $status = "DOWN";

        $eventtime = min(strtotime($row["conn_down"]), strtotime($row["dns_down"]));
    } elseif ($row["conn_up"] == null && $row["conn_down"] != null) {
        $status = "CONN";

        $eventtime = strtotime($row["conn_down"]);
    } elseif ($row["dns_up"] == null && $row["dns_down"] != null) {
        $status = "DNS";

        $eventtime = strtotime($row["dns_down"]);
    } else {
        $status = "UP";

        if ($row["conn_up"] == null) {
            $eventtime = strtotime($row["dns_up"]);
        } elseif ($row["dns_up"] == null) {
            $eventtime = strtotime($row["conn_up"]);
        } else {
            $eventtime = max(strtotime($row["conn_up"]), strtotime($row["dns_up"]));
        }
    }

    if ($row["reboot_start"] != null) {
        $rebooting = true;

        $eventtime = strtotime($row["reboot_start"]);
    }
}

switch ($status) {
    case "UP":
        $emoticon = "&#x1f638;";
        $colour = "#00FF00";
        $desc = "IS HAS INTERNETS!!!";
        break;
    case "DOWN":
        $emoticon = "&#x1f63e;";
        $colour = "#FF0000";
        $desc = "IS NOT HAS INTERNETS!!";
        break;
    case "DNS":
    case "CONN":
        $emoticon = "&#x1f63f;";
        $colour = "#FF7700";
        $desc = "MAYBE IS NOT HAS INTERNETS!";
        break;
}

if ($status != "UP" && $rebooting) {
    $emoticon = "&#x1f5d8;";
    $desc = "IS REBOOTS!";
}

if ($eventtime == null) {
    $eventtime = time();
}

?>
        <title><?php echo $desc ?></title>
        <script type="text/javascript">
var eventtime = <?php echo $eventtime ?>;
var lasttime = -1;

function format2digit(val) {
    var prefix = "00" + val;

    return prefix.slice(-2);
}

function timetick() {
    var now = Date.now() / 1000;

    var seconds = Math.round(now - eventtime);

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

var lastimgwidth = 0;

function resizeFn() {
    var img = $("#graph");

    var width = $(window).innerWidth();
    var height = $(window).innerHeight() - 10;

    height -= img.height();

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

    if (img.width() != lastimgwidth) {
        lastimgwidth = img.width();
        img.attr("src", "graph.php?width=" + img.width());
    }
}

$(resizeFn);

$(window).resize(resizeFn);
        </script>
    </head>
    <body style="background-color: <?php echo $colour ?>">
        <div><span class="emoticon"><?php echo $emoticon ?></span></div>
        <div><span class="desc"><?php echo $desc ?></span></div>
        <div><span class="time">For ...</span></div>
        <p><img id="graph" style="width: 100%" src="" height="100" /></p>
    </body>
</html>
