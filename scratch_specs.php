<?php
require_once 'config.php';
$conn = get_db_connection();

$res = $conn->query("SELECT id, num_inventario, descripcion FROM inventario_soporte WHERE id_tipo_bien IN (1, 2, 3, 4) LIMIT 50");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        echo "ID: " . $row['id'] . " | Desc: " . $row['descripcion'] . "\n";
        echo "--------------------------------------------------------\n";
    }
}
?>
