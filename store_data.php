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
