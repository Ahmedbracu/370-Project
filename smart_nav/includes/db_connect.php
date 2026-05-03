<?php
$is_local = in_array($_SERVER['HTTP_HOST'], ['localhost', '127.0.0.1']);

if ($is_local) {
    // Local XAMPP Credentials
    $host     = "localhost";
    $user     = "root";
    $password = "";
    $database = "smart_navigation_db";
} else {
    // Live InfinityFree Credentials
    $host     = "sql112.infinityfree.com"; 
    $user     = "if0_41756518"; 
    $password = "GluVkyBnM2"; // password
    $database = "if0_41756518_smartnav";
}

$conn = mysqli_connect($host, $user, $password, $database);

if (!$conn) {
    die(json_encode(['error' => 'Connection failed: ' . mysqli_connect_error()]));
}

mysqli_set_charset($conn, "utf8");
?>
