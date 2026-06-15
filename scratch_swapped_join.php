<?php
require_once 'config.php';
$conn = get_db_connection();

$sql = "SELECT COUNT(*) 
        FROM inventario_soporte inv
        JOIN registros_ad r ON TRIM(REPLACE(CONCAT(r.apellido_materno, ' ', r.nombres, ' ', COALESCE(r.apellido_paterno,'')), '  ', ' ')) = inv.personal_asignado
        JOIN cat_direcciones d ON r.id_direccion = d.id_direccion
        JOIN Secretarias s ON d.id_secretaria = s.id_secretaria";
$res = $conn->query($sql);
echo "Matches via swapped columns: " . $res->fetch_row()[0] . "\n";
?>
