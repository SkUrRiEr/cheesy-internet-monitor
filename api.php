<?php

require("config.php");

$sql = "SELECT UNIX_TIMESTAMP(dns_down) AS dns_down,
        UNIX_TIMESTAMP(conn_down) AS conn_down,
        UNIX_TIMESTAMP(dns_up) AS dns_up,
        UNIX_TIMESTAMP(conn_up) AS conn_up,
        UNIX_TIMESTAMP(reboot_start) AS reboot_start,
        UNIX_TIMESTAMP(COALESCE(LEAST(dns_down, conn_down), dns_down, conn_down)) AS token
    FROM conlog";

if (isset($_REQUEST["token"]) && is_numeric($_REQUEST["token"])) {
    $sql .= " WHERE COALESCE(LEAST(dns_down, conn_down), dns_down, conn_down)
       >= FROM_UNIXTIME(".$_REQUEST["token"].")";
} else {
    $sql .= " ORDER BY token DESC LIMIT 1";
}

$result = $db->query($sql);

$data = array();
$last_token = 0;

while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
    $data[] = $row;
    if ($row["token"] > $last_token) {
        $last_token = $row["token"];
    }
}

header("Content-type: application/json");

echo json_encode(array(
    "data" => $data,
    "count" => count($data),
    "last_token" => $last_token
));
