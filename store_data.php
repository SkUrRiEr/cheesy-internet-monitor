<?php

if ($argc != 3) {
    echo "INCORECT ARGS PASSED. FAIL";

    exit(1);
}

require("config.php");

switch ($argv[1]) {
    case "dns":
        switch ($argv[2]) {
            case "up":
                $sql = "UPDATE conlog SET dns_up = NOW() WHERE dns_up IS NULL AND dns_down IS NOT NULL
                    ORDER BY COALESCE(LEAST(dns_down, conn_down), dns_down, conn_down) DESC LIMIT 1";

                $db->exec($sql);

                break;
            case "down":
                $sql = "UPDATE conlog SET dns_down = NOW() WHERE dns_down IS NULL AND conn_up IS NULL";

                if ($db->exec($sql) == 0) {
                    $sql = "SELECT COUNT(*) FROM conlog WHERE dns_down IS NOT NULL AND dns_up IS NULL";

                    $result = $db->query($sql);

                    if ($result->fetchColumn() == 0) {
                        $sql = "INSERT INTO conlog (dns_down) VALUES (NOW())";

                        $db->exec($sql);
                    }
                }

                break;
        }

        break;
    case "conn":
        switch ($argv[2]) {
            case "up":
                $sql = "UPDATE conlog SET conn_up = NOW() WHERE conn_up IS NULL AND conn_down IS NOT NULL
                    ORDER BY COALESCE(LEAST(dns_down, conn_down), dns_down, conn_down) DESC LIMIT 1";

                $db->exec($sql);

                break;
            case "down":
                $sql = "UPDATE conlog SET conn_down = NOW() WHERE conn_down IS NULL AND dns_up IS NULL";

                if ($db->exec($sql) == 0) {
                    $sql = "SELECT COUNT(*) FROM conlog WHERE conn_down IS NOT NULL AND conn_up IS NULL";

                    $result = $db->query($sql);

                    if ($result->fetchColumn() == 0) {
                        $sql = "INSERT INTO conlog (conn_down) VALUES (NOW())";

                        $db->exec($sql);
                    }
                }

                break;
        }

        break;
    case "reboot":
        switch ($argv[2]) {
            case "start":
                $sql = "UPDATE conlog SET reboot_start = NOW() WHERE reboot_start IS NULL AND (
                    (dns_down IS NOT NULL AND dns_up IS NULL) OR 
                    (conn_down IS NOT NULL AND conn_up IS NULL)
                )";

                $db->exec($sql);
        }
}

if ($mqtt != null) {
    $client = new LibMQTT\Client($mqtt["server"], $mqtt["port"], $mqtt["clientID"]);

    if (isset($mqtt["tls"])) {
        $client->setCryptoProtocol("tls");

        if (isset($mqtt["cafile"]) && file_exists($mqtt["cafile"])) {
            $client->setCAFile($mqtt["cafile"]);
        }
    }

    if ($client->connect()) {
        $sql = "SELECT * FROM conlog ORDER BY COALESCE(LEAST(dns_down, conn_down), dns_down, conn_down) DESC LIMIT 1";

        $result = $db->query($sql);

        $row = $result->fetch(PDO::FETCH_ASSOC);

        $status = "UP";
        $rebooting = false;
        $eventtime = null;

        if ($row) {
            if ($row["conn_up"] == null && $row["dns_up"] == null
                && $row["conn_down"] != null && $row["dns_down"] != null) {
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
                $reboottime = strtotime($row["reboot_start"]);

                if ($status != "UP" && $reboottime > $eventtime) {
                    $status = "REBOOTING";
                    $eventtime = $reboottime;
                }
            }

            $msg = $status." ".date("r", $eventtime);

            $client->publish("internetmonitor/status", $msg, 0);
        }
    }
}
