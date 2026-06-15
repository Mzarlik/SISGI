<?php
require_once 'config.php';
$conn = get_db_connection();

$res = $conn->query("SELECT COUNT(*) FROM equiposbd eq JOIN inventario_soporte inv ON eq.numInventario = inv.num_inventario");
echo "Matches by numInventario: " . $res->fetch_row()[0] . "\n";

$res2 = $conn->query("SELECT COUNT(*) FROM equiposbd WHERE numInventario IS NOT NULL AND numInventario != ''");
echo "equiposbd with non-empty numInventario: " . $res2->fetch_row()[0] . "\n";

$res3 = $conn->query("SELECT COUNT(*) FROM inventario_soporte WHERE num_inventario IS NOT NULL AND num_inventario != ''");
echo "inventario_soporte with non-empty num_inventario: " . $res3->fetch_row()[0] . "\n";
?>
