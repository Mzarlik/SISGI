<?php
// eliminar_licencia.php

// 1. LIMPIEZA DE BÚFER (Crucial para que no falle la respuesta)
ob_start();

require_once 'config.php';

// Ocultamos errores en pantalla y activamos log
ini_set('display_errors', 0);
ini_set('log_errors', 1);

session_start();
header('Content-Type: application/json');

function responder($success, $message) {
    ob_end_clean(); // Limpia cualquier basura antes de responder
    echo json_encode(['success' => $success, 'message' => $message]);
    exit;
}

// 2. SEGURIDAD
if (!isset($_SESSION['usuario'])) {
    responder(false, 'Sesión caducada.');
}

// 3. RECIBIR ID
$id = $_POST['id'] ?? '';

if (empty($id)) {
    responder(false, 'Error: No se recibió el ID a eliminar.');
}

// 4. CONEXIÓN
$conn = function_exists('get_db_connection') ? get_db_connection() : new mysqli("localhost", "root", "", "mi_basedatos");
if ($conn->connect_error) {
    responder(false, 'Error BD: ' . $conn->connect_error);
}

// 5. ELIMINAR REGISTRO
$stmt = $conn->prepare("DELETE FROM cuentas_office WHERE id = ?");
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    responder(true, 'Licencia eliminada correctamente.');
} else {
    responder(false, 'Error al eliminar: ' . $stmt->error);
}

$stmt->close();
$conn->close();
?>