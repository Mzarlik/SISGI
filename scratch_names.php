<?php
require_once 'config.php';
$conn = get_db_connection();

echo "--- inventario_soporte nombre_ubicacion (First 10) --- \n";
$res = $conn->query("SELECT DISTINCT nombre_ubicacion FROM inventario_soporte WHERE nombre_ubicacion IS NOT NULL AND nombre_ubicacion != '' LIMIT 10");
while($row = $res->fetch_row()) {
    echo $row[0] . "\n";
}

echo "--- cat_direcciones nombre_direccion (First 10) --- \n";
$res = $conn->query("SELECT nombre_direccion FROM cat_direcciones LIMIT 10");
while($row = $res->fetch_row()) {
    echo $row[0] . "\n";
}
?>
