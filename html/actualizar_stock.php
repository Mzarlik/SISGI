<?php
// actualizar_stock.php
require_once 'config.php';
session_start();

// Validar sesión y método
if (!isset($_SESSION['usuario']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Acceso no autorizado']);
    exit();
}

$conn = get_db_connection();
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Error de conexión']);
    exit();
}

// Recibir datos
$id = (int)$_POST['id'];
$tipo = $conn->real_escape_string($_POST['tipo']);
$marca = $conn->real_escape_string($_POST['marca']);
$modelo = $conn->real_escape_string($_POST['modelo']);
$descripcion = $conn->real_escape_string($_POST['descripcion']);
$unidades = (int)$_POST['unidades'];
$fecha_alta = $conn->real_escape_string($_POST['fecha_alta']);

// Consulta de actualización
$sql = "UPDATE stock_material SET 
        tipo='$tipo', 
        marca='$marca', 
        modelo='$modelo', 
        descripcion='$descripcion', 
        unidades=$unidades, 
        fecha_alta='$fecha_alta' 
        WHERE id=$id";

if ($conn->query($sql)) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Error SQL: ' . $conn->error]);
}

$conn->close();
?>