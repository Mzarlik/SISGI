<?php
require_once 'config.php';
$conn = get_db_connection();

echo "--- COLUMNS OF equiposbd --- \n";
$res = $conn->query("SHOW COLUMNS FROM equiposbd");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        echo $row['Field'] . " (" . $row['Type'] . ")\n";
    }
}

echo "--- ROW COUNT OF equiposbd --- \n";
$res = $conn->query("SELECT COUNT(*) FROM equiposbd");
if ($res) {
    echo "Count: " . $res->fetch_row()[0] . "\n";
}
?>
