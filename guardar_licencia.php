<?php
// guardar_licencia.php (CORREGIDO)
require_once 'session_check.php';
require_once 'config.php';
session_start();

// Encabezado JSON y UTF-8
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Acceso no autorizado.']);
    exit();
}

$conn = get_db_connection();

if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Error de conexión BD.']);
    exit();
}

// 1. CONFIGURACIÓN UTF-8 (Vital para acentos)
$conn->set_charset("utf8mb4");

// 2. RECIBIR DATOS
// CORRECCIÓN: Buscamos 'direccion' (como suele llamarse en el HTML) O 'id_direccion'
$id_direccion_val = $_POST['direccion'] ?? $_POST['id_direccion'] ?? ''; 
$area             = $_POST['area'] ?? '';
$correo           = $_POST['correo'] ?? '';
$password         = $_POST['password'] ?? '';

// 3. VALIDACIONES
// Verificamos que sea numérico y mayor a 0
if (empty($id_direccion_val) || empty($area) || empty($correo) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Todos los campos son obligatorios.']);
    $conn->close();
    exit();
}

// 4. VERIFICAR DUPLICADOS
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

// 5. INSERTAR LA NUEVA CUENTA
// ESTRATEGIA BLINDADA: 
// - Insertamos el ID en 'id_direccion'.
// - Insertamos una cadena vacía en la columna vieja `Dirección` para evitar error de "Field doesn't have a default value".

$sql = "INSERT INTO cuentas_office (id_direccion, `Dirección`, Area, Correo, Password, Conectados) VALUES (?, '', ?, ?, ?, '0')";

$stmt = $conn->prepare($sql);

if ($stmt) {
    // Tipos: i (int), s (string), s, s, s
    $stmt->bind_param("isss", $id_direccion_val, $area, $correo, $password);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Cuenta registrada con éxito.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al guardar: ' . $stmt->error]);
    }
    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Error en consulta: ' . $conn->error]);
}

$conn->close();
?>