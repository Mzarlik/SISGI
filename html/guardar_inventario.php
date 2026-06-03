<?php
require_once 'config.php';
session_start();

if (!isset($_SESSION['usuario'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Acceso no autorizado. Inicie sesión.']);
    exit();
}

header('Content-Type: application/json');

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(['success' => false, 'message' => 'Método de solicitud no permitido.']);
    exit();
}

// 1. OBTENER Y SANITIZAR DATOS
$num_inventario      = trim($_POST['num_inventario'] ?? '');
$tipo                = trim($_POST['tipo'] ?? '');
$marca               = trim($_POST['marca'] ?? '');
$modelo              = trim($_POST['modelo'] ?? '');
$num_serie           = trim($_POST['num_serie'] ?? '');
$descripcion         = trim($_POST['descripcion'] ?? '');
$personal_asignado   = trim($_POST['personal_asignado'] ?? '');
$ubicacion           = trim($_POST['ubicacion'] ?? '');

// 2. VALIDACIÓN DE CAMPOS OBLIGATORIOS
if (empty($num_inventario) || empty($tipo) || empty($marca) || empty($modelo) || empty($num_serie) || empty($ubicacion)) {
    echo json_encode(['success' => false, 'message' => 'Por favor, complete todos los campos obligatorios.']);
    exit();
}

$conn = get_db_connection();
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Error de conexión a la base de datos.']);
    exit();
}

try {

    // Verificamos si ya existe el número de inventario O el número de serie
    $sql_check = "SELECT num_inventario, num_serie FROM inventario_soporte WHERE num_inventario = ? OR num_serie = ? LIMIT 1";
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->bind_param("ss", $num_inventario, $num_serie);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();

    if ($result_check->num_rows > 0) {
        $existente = $result_check->fetch_assoc();
        echo json_encode([
            'success' => false, 
            'message' => "El equipo ya se encuentra dado de alta con el Número de Inventario: " . $existente['num_inventario'] . " y Número de Serie: " . $existente['num_serie'] . "."
        ]);
        $stmt_check->close();
        $conn->close();
        exit();
    }
    $stmt_check->close();

    // ===============================================
    // 4. INSERCIÓN (SI NO EXISTE EL EQUIPO)
    // ===============================================
    $sql = "INSERT INTO inventario_soporte (num_inventario, tipo, marca, modelo, num_serie, descripcion, personal_asignado, ubicacion) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        throw new Exception("Error al preparar la consulta: " . $conn->error);
    }

    $stmt->bind_param("ssssssss", $num_inventario, $tipo, $marca, $modelo, $num_serie, $descripcion, $personal_asignado, $ubicacion);

    if ($stmt->execute()) {
        echo json_encode([
            'success' => true, 
            'message' => 'Equipo ' . htmlspecialchars($num_inventario) . ' registrado exitosamente.'
        ]);
    } else {
        throw new Exception($stmt->error);
    }

    $stmt->close();

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error interno: ' . $e->getMessage()]);
}

$conn->close();
?>