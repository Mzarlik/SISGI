<?php
// guardar_registro.php (VERSIÓN LIMPIA - SOLO IDs)
require_once 'session_check.php';
require_once 'config.php';
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Acceso no autorizado']);
    exit;
}

$conn = get_db_connection();
$conn->set_charset("utf8mb4");

// Recibir Datos
$id_direccion = $_POST['id_direccion'] ?? 0; // Solo nos importa esto

$num_oficio = $_POST['num_oficio'] ?? '';
$fecha_alta = $_POST['fecha_alta'] ?? '';
$num_empleado = $_POST['num_empleado'] ?? '';
$nombres = $_POST['nombres'] ?? '';
$ap_pat = $_POST['apellido_paterno'] ?? '';
$ap_mat = $_POST['apellido_materno'] ?? '';
$usuario = $_POST['usuario'] ?? '';
$contrasena = $_POST['contrasena'] ?? '';
$cargo = $_POST['cargo'] ?? '';
$correo = $_POST['correo_electronico'] ?? '';
$telefono = $_POST['telefono'] ?? '';

// Validar campos obligatorios
if (empty($id_direccion) || empty($nombres) || empty($usuario)) {
    echo json_encode(['success' => false, 'message' => 'Faltan datos obligatorios.']);
    exit;
}

// INSERTAR
$sql = "INSERT INTO registros_ad 
        (id_direccion, num_oficio, fecha_alta, num_empleado, nombres, apellido_paterno, apellido_materno, usuario, contrasena, cargo, correo_electronico, telefono) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = $conn->prepare($sql);

if ($stmt) {
    // Tipos: i (int) y 11 strings (s)
    $stmt->bind_param("isssssssssss", 
        $id_direccion, 
        $num_oficio, 
        $fecha_alta, 
        $num_empleado, 
        $nombres, 
        $ap_pat, 
        $ap_mat, 
        $usuario, 
        $contrasena,
        $cargo,
        $correo,
        $telefono
    );

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Usuario registrado correctamente']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error SQL: ' . $stmt->error]);
    }
    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Error preparando consulta: ' . $conn->error]);
}

$conn->close();
?>