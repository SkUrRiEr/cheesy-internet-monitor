<?php

require("config.php");

$dns_down = $conn_down = $dns_up = $conn_up = null;

function todb($time)
{
    if ($time == null) {
        return "NULL";
    }

    return "FROM_UNIXTIME(".$time.")";
}

while ($line = fgets(STDIN)) {
    if (preg_match("/(.*) (CONNECTIVITY|DNS) (Up|Failure)$/", $line, $regs)) {
        $ti = date_parse_from_format("l j F  H:i:s T Y", $regs[1]);
        $time = mktime($ti["hour"], $ti["minute"], $ti["second"], $ti["month"], $ti["day"], $ti["year"]);

        if ($time) {
            switch ($regs[2]) {
                case "CONNECTIVITY":
                    switch ($regs[3]) {
                        case "Up":
                            if ($conn_down != null && $conn_up == null) {
                                $conn_up = $time;
                            }
                            break;
                        case "Failure":
                            if ($conn_down == null) {
                                $conn_down = $time;
                            }
                            break;
                    }
                    break;
                case "DNS":
                    switch ($regs[3]) {
                        case "Up":
                            if ($dns_down != null && $dns_up == null) {
                                $dns_up = $time;
                            }
                            break;
                        case "Failure":
                            if ($dns_down == null) {
                                $dns_down = $time;
                            }
                            break;
                    }
                    break;
            }

            if (($dns_down != null && $dns_up != null && $conn_down != null && $conn_up != null) ||
                ($dns_down != null && $dns_up != null && $conn_down == null) ||
                ($conn_down != null && $conn_up != null && $dns_down == null)) {
                $sql = "INSERT INTO conlog (dns_down, dns_up, conn_down, conn_up)
                    VALUES (".todb($dns_down).", ".todb($dns_up).", ".todb($conn_down).", ".todb($conn_up).")";

                $db->exec($sql);

                $dns_down = $conn_down = $dns_up = $conn_up = null;
            }
        }
    }
}
