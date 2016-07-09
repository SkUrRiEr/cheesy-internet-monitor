<?php

require __DIR__ . '/vendor/autoload.php';

$db = new PDO("mysql:host=127.0.0.1;dbname=cheesy_internet_monitor", "monitor", "gouda");
$font = "/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf";
$mqtt = array(
    "server" => "big-fromage.local",
    "port" => 1883,
    "clientID" => "cheesyinternetmonitor"
);
