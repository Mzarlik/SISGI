<?php
require_once 'config.php';
$conn = get_db_connection();

$sql = "SELECT COUNT(*) 
        FROM inventario_soporte inv
        JOIN cat_direcciones cd ON UPPER(inv.nombre_ubicacion) LIKE CONCAT('%', UPPER(cd.nombre_direccion), '%')";
$res = $conn->query($sql);
echo "Matches via LIKE: " . $res->fetch_row()[0] . "\n";
?>
