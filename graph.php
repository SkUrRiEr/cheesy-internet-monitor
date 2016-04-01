<?php

require("config.php");

$width = $_REQUEST["width"];
$height = 100;
$graphheight = 80;
$tickheight = 4;
$fontsize = $height - $graphheight - $tickheight - 3;
$fontoffset = $graphheight + $tickheight + 1;
$interval = 24; // Hours

if (isset($_REQUEST["interval"]) && is_numeric($_REQUEST["interval"])) {
    $interval = $_REQUEST["interval"];
}

$resolution = $interval * 60 * 60 / $width; // seconds per pixel

$sql = "SELECT * FROM conlog
    WHERE dns_down > DATE_SUB(NOW(), INTERVAL ".$interval." HOUR)
        OR dns_up > DATE_SUB(NOW(), INTERVAL ".$interval." HOUR)
        OR conn_down > DATE_SUB(NOW(), INTERVAL ".$interval." HOUR)
        OR conn_up > DATE_SUB(NOW(), INTERVAL ".$interval." HOUR)
        OR reboot_start > DATE_SUB(NOW(), INTERVAL ".$interval." HOUR)
    ORDER BY COALESCE(LEAST(dns_down, conn_down), dns_down, conn_down) ASC";

$result = $db->query($sql);

$timeline = array();

$starttime = time() - $interval * 60 * 60;

function string2offset($str)
{
    return time2offset(strtotime($str));
}

function time2offset($time)
{
    global $resolution, $starttime;

    return round(($time - $starttime) / $resolution);
}

$endtime = 0;

while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
    if ($row["dns_down"] == null || $row["conn_down"] == null) {
        $event = array(
            "state" => $row["conn_down"] != null ? "CONN" : "DNS"
        );

        $start = $row["conn_down"] != null ? $row["conn_down"] : $row["dns_down"];
        $end = $row["conn_up"] != null ? $row["conn_up"] : $row["dns_up"];

        if ($start - 1 >= $endtime) {
            $timeline[] = array(
                "state" => "UP",
                "start" => $endtime,
                "end" => $start - 1
            );
        }

        $event["start"] = string2offset($start);
        $event["end"] = $end == null ? $width : string2offset($end);

        $endtime = $event["end"] + 1;

        if ($row["reboot_start"] != null) {
            $reboot_time = string2offset($row["reboot_start"]);

            $event["reboot"] = $reboot_time;
        }

        $timeline[] = $event;
    } else {
        $dns_down = string2offset($row["dns_down"]);
        $conn_down = string2offset($row["conn_down"]);

        $part_start = min($dns_down, $conn_down);
        $full_start = max($dns_down, $conn_down);

        $dns_up = string2offset($row["dns_up"]);
        $conn_up = string2offset($row["conn_up"]);

        $full_end = min($dns_up, $conn_up);
        $part_end = max($dns_up, $conn_up);

        if ($part_start - 1 >= $endtime) {
            $timeline[] = array(
                "state" => "UP",
                "start" => $endtime,
                "end" => $part_start - 1
            );
        }

        if ($part_end < $endtime) {
            continue;
        }

        $endtime = $part_end + 1;

        $reboot_time = string2offset($row["conn_up"]);

        if ($part_start < $full_start) {
            $event = array(
                "state" => $part_start == $dns_down ? "DNS" : "CONN",
                "start" => $part_start,
                "end" => $full_start - 1
            );

            if ($reboot_time < $full_start) {
                $event["reboot"] = $reboot_time;
            }

            $timeline[] = $event;
        }

        if ($full_start <= $full_end) {
            $event = array(
                "state" => "DOWN",
                "start" => $full_start,
                "end" => $full_end
            );

            if ($reboot_time >= $full_start && $reboot_time <= $full_end) {
                $event["reboot"] = $reboot_time;
            }

            $timeline[] = $event;
        }

        if ($full_end < $part_end) {
            $event = array(
                "state" => $part_end == $dns_up ? "DNS" : "CONN",
                "start" => $full_end + 1,
                "end" => $part_end
            );

            if ($reboot_time > $full_end) {
                $event["reboot"] = $reboot_time;
            }

            $timeline[] = $event;
        }
    }
}

if (count($timeline) == 0) {
    $timeline[] = array(
        "state" => "UP",
        "start" => 0,
        "end" => $width
    );
} elseif ($endtime <= $width) {
    $timeline[] = array(
        "state" => "UP",
        "start" => $endtime,
        "end" => $width
    );
}

$im = imagecreatetruecolor($width, $height);

$white = imagecolorallocate($im, 255, 255, 255);

imagefill($im, 0, 0, $white);

$green = imagecolorallocate($im, 0, 255, 0);
$orange = imagecolorallocate($im, 255, 119, 0);
$red = imagecolorallocate($im, 255, 0, 0);
$black = imagecolorallocate($im, 0, 0, 0);

foreach ($timeline as $event) {
    switch ($event["state"]) {
        case "UP":
            $colour = $green;
            break;
        case "DOWN":
            $colour = $red;
            break;
        case "DNS":
        case "CONN":
            $colour = $orange;
            break;
        default:
            continue;
    }

    imagefilledrectangle($im, $event["start"], 0, $event["end"], $graphheight, $colour);

    if (isset($event["reboot"])) {
        imageline($im, $event["reboot"], $graphheight * 0.25, $event["reboot"], $graphheight * 0.75, $black);
    }
}

imagerectangle($im, 0, 0, $width - 1, $height - 1, $black);

$last = $width - 1;

for ($i = 0; $i < $interval; $i++) {
    $hour = date("H") - $i;

    while ($hour < 0) {
        $hour += 24;
    }

    $offset = time2offset($time = mktime(date("H") - $i, 0, 0, date("m"), date("d"), date("Y")));

    imageline($im, $offset, $graphheight, $offset, $graphheight + $tickheight, $black);

    if ($hour == 0) {
        $hour = "12 AM";
    } elseif ($hour == 12) {
        $hour = "12 PM";
    } elseif ($hour > 12) {
        $hour = ($hour - 12)." PM";
    } else {
        $hour .= " AM";
    }

    $bbox = imageftbbox($fontsize, 0, $font, $hour);
    $toffset = ($bbox[2] - $bbox[0]) / 2;

    if ($offset + $toffset + 2 < $last && $offset - $toffset > 2) {
        imagefttext($im, $fontsize, 0, $offset - $toffset, $fontoffset + $fontsize, $black, $font, $hour);

        $last = $offset - $toffset;
    }
}

header("Content-type: image/png");
imagepng($im);
imagedestroy($im);
