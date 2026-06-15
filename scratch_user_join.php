<?php
require_once 'config.php';
$conn = get_db_connection();

$sql = "SELECT COUNT(*) 
        FROM inventario_soporte inv
        JOIN registros_ad r ON TRIM(REPLACE(CONCAT(r.nombres, ' ', COALESCE(r.apellido_paterno,''), ' ', COALESCE(r.apellido_materno,'')), '  ', ' ')) = inv.personal_asignado
        JOIN cat_direcciones d ON r.id_direccion = d.id_direccion
        JOIN Secretarias s ON d.id_secretaria = s.id_secretaria";
$res = $conn->query($sql);
echo "Exact matches via registros_ad name: " . $res->fetch_row()[0] . "\n";

$sql2 = "SELECT COUNT(*) FROM inventario_soporte WHERE personal_asignado IS NOT NULL AND personal_asignado <> '' AND personal_asignado <> 'STOCK' AND personal_asignado <> 'Sin Asignar'";
echo "Total assigned items in inventario_soporte: " . $res2 = $conn->query($sql2)->fetch_row()[0] . "\n";
?>
