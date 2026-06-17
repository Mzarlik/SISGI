<?php
require_once 'c:/xampp/htdocs/SISGI/config.php';
$conn = get_db_connection();
if (!$conn) {
    die("Database connection failed\n");
}
$conn->set_charset("utf8mb4");

echo "=== VERIFICACION DE JEFES EN BD ===\n";
$res = $conn->query("SELECT jefe_inmediato, COUNT(*) as count FROM registros_ad GROUP BY jefe_inmediato ORDER BY count DESC");
while ($row = $res->fetch_assoc()) {
    echo "Jefe: '" . $row['jefe_inmediato'] . "' | Count: " . $row['count'] . "\n";
}
$conn->close();
?>
