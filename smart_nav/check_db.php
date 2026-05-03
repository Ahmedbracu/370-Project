<?php
require_once 'includes/db_connect.php';
$res = $conn->query("SHOW CREATE TABLE incident_report");
echo $res->fetch_row()[1] . "\n\n";
$res = $conn->query("SHOW CREATE TABLE traffic_data");
echo $res->fetch_row()[1] . "\n\n";
