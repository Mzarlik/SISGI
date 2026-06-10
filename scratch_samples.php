<?php
require_once 'config.php';
$conn = get_db_connection();

echo "--- equiposbd numInventario --- \n";
$res = $conn->query("SELECT numInventario FROM equiposbd WHERE numInventario IS NOT NULL AND numInventario != '' LIMIT 10");
while($row = $res->fetch_row()) {
    echo $row[0] . "\n";
}

echo "--- inventario_soporte num_inventario --- \n";
$res = $conn->query("SELECT num_inventario FROM inventario_soporte WHERE num_inventario IS NOT NULL AND num_inventario != '' LIMIT 10");
while($row = $res->fetch_row()) {
    echo $row[0] . "\n";
}
?>
