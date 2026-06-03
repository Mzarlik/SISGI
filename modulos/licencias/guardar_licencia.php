<?php
// guardar_licencia.php
require_once 'config.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['usuario']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Acceso no autorizado.']);
    exit();
}

$conn = get_db_connection();

// Recibir datos del formulario
$direccion = $_POST['direccion'] ?? '';
$area = $_POST['area'] ?? '';
$correo = $_POST['correo'] ?? '';
$password = $_POST['password'] ?? '';

// Validaciones básicas
if (empty($direccion) || empty($area) || empty($correo) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Todos los campos son obligatorios.']);
    $conn->close();
    exit();
}

// Verificar si el correo ya existe
$check_sql = "SELECT id FROM cuentas_office WHERE Correo = ?";
$stmt_check = $conn->prepare($check_sql);
$stmt_check->bind_param("s", $correo);
$stmt_check->execute();
$result_check = $stmt_check->get_result();

if ($result_check->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'Este correo ya está registrado.']);
    $stmt_check->close();
    $conn->close();
    exit();
}
$stmt_check->close();

// Insertar la nueva cuenta
// NOTA: Asegúrate de que los nombres de las columnas coincidan EXACTAMENTE con tu BD.
// Según tu imagen: Dirección, Area, Correo, Password
$sql = "INSERT INTO cuentas_office (Dirección, Area, Correo, Password) VALUES (?, ?, ?, ?)";
$stmt = $conn->prepare($sql);

if ($stmt) {
    $stmt->bind_param("ssss", $direccion, $area, $correo, $password);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Cuenta registrada con éxito.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al guardar en la base de datos: ' . $stmt->error]);
    }
    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Error al preparar la consulta: ' . $conn->error]);
}

$conn->close();
?>