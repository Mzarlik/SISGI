<?php
require_once 'config.php';
$conn = get_db_connection();

echo "--- inventario_soporte personal_asignado (First 20) --- \n";
$res = $conn->query("SELECT DISTINCT personal_asignado FROM inventario_soporte WHERE personal_asignado IS NOT NULL AND personal_asignado != '' AND personal_asignado != 'STOCK' LIMIT 20");
while($row = $res->fetch_row()) {
    echo $row[0] . "\n";
}

echo "--- registros_ad name concatenation (First 20) --- \n";
$res = $conn->query("SELECT DISTINCT TRIM(REPLACE(CONCAT(nombres, ' ', COALESCE(apellido_paterno,''), ' ', COALESCE(apellido_materno,'')), '  ', ' ')) FROM registros_ad LIMIT 20");
while($row = $res->fetch_row()) {
    echo $row[0] . "\n";
}
?>
