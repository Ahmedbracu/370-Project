<?php
require_once 'includes/db_connect.php';

$queries = [
    "ALTER TABLE traffic_data ADD COLUMN user_id INT DEFAULT NULL",
    "ALTER TABLE traffic_data ADD COLUMN description TEXT DEFAULT NULL"
];

foreach ($queries as $q) {
    if ($conn->query($q)) {
        echo "Success: $q\n";
    } else {
        echo "Error: " . $conn->error . "\n";
    }
}
echo "Done.\n";
