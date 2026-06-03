<?php
require_once 'config.php';
session_start();

// 1. Verificar Sesión
if (!isset($_SESSION['usuario'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión no válida']);
    exit();
}

$conn = get_db_connection();
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Error de conexión a la base de datos']);
    exit();
}

// 2. Captura de datos del formulario
$tipo = $_POST['tipo'] ?? '';
$marca = $_POST['marca'] ?? '';
$modelo = $_POST['modelo'] ?? '';
$almacenamiento = $_POST['capacidad_almacenamiento'] ?? '';
$ram = $_POST['memoria_ram'] ?? '';
$capitulos = $_POST['capitulos'] ?? '';
$precio = $_POST['precio'] ?? 0;
$detalles = $_POST['detalles'] ?? '';

// 3. Validación básica
if (empty($tipo) || empty($marca) || empty($modelo) || empty($capitulos)) {
    echo json_encode(['success' => false, 'message' => 'Por favor, complete los campos obligatorios (Tipo, Marca, Modelo y Capítulo)']);
    exit();
}

// ==========================================================
// NUEVA VALIDACIÓN: Verificar duplicados exactos
// ==========================================================
$sql_check = "SELECT id_adquisicion FROM Adq_equipos WHERE tipo = ? AND marca = ? AND modelo = ? AND precio = ? AND detalles = ?";
$stmt_check = $conn->prepare($sql_check);
$stmt_check->bind_param("sssds", $tipo, $marca, $modelo, $precio, $detalles);
$stmt_check->execute();
$stmt_check->store_result();

if ($stmt_check->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'Ya se encuentra registrado este bien informatico']);
    $stmt_check->close();
    $conn->close();
    exit();
}
$stmt_check->close();
// ==========================================================

// 4. Preparar la sentencia SQL de inserción
$sql = "INSERT INTO Adq_equipos (tipo, marca, modelo, capacidad_almacenamiento, memoria_ram, capitulos, precio, detalles) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = $conn->prepare($sql);

if ($stmt) {
    $stmt->bind_param("ssssssds", 
        $tipo, 
        $marca, 
        $modelo, 
        $almacenamiento, 
        $ram, 
        $capitulos, 
        $precio, 
        $detalles
    );

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Equipo registrado correctamente en el inventario']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al ejecutar el registro: ' . $stmt->error]);
    }
    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Error al preparar la consulta: ' . $conn->error]);
}

$conn->close();
?>