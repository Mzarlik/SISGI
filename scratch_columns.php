<?php
require_once 'config.php';
$conn = get_db_connection();

$res = $conn->query("SELECT nombres, apellido_paterno, apellido_materno FROM registros_ad LIMIT 5");
while($row = $res->fetch_assoc()) {
    print_r($row);
}
?>
