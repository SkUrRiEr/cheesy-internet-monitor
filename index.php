<html>
    <head>
        <title>Iedex Internet Monitor</title>
        <meta http-equiv="refresh" content="5" />
        <style>
h1.emoticon {
    font-size: 20em;
    text-align: center;
    margin: 0px;
}

h2.desc {
    margin: 0px;
    text-align: center;
    font-size: 5em;
}

h3.time {
    font-size: 2em;
    text-align: center;
    margin: 0px;
}
        </style>
    </head>
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

$uptime = $seconds = time() - $time;

$outtime = $seconds;

if ($seconds > 60) {
    $minutes = floor($seconds / 60);
    $seconds -= $minutes * 60;
    $outtime = sprintf("%d:%02d", $minutes, $seconds);
}

if ($minutes > 60) {
    $hours = floor($minutes / 60);
    $minutes -= $hours * 60;
    $outtime = sprintf("%d:%02d:%02d", $hours, $minutes, $seconds);
}

if ($hours > 24) {
    $days = floor($hours / 24);
    $hours -= $days * 24;
    $outtime = $days." days, ".sprintf("%d:%02d:%02d", $hours, $minutes, $seconds);
}

?>
    <body style="background-color: <?php echo $colour ?>">
        <h1 class="emoticon"><?php echo $emoticon ?></h1>
        <h2 class="desc"><?php echo $desc ?></h2>
        <h3 class="time">For <?php echo $outtime ?></h3>
    </body>
</html>
