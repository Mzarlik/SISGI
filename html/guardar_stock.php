<?php
// Script para guardar un nuevo registro de material en stock_material
require_once 'config.php'; // Incluye el archivo que define get_db_connection()
session_start();

// 1. Verificar sesión de usuario
if (!isset($_SESSION['usuario'])) {
    http_response_code(401); // No autorizado
    echo json_encode(['success' => false, 'message' => 'Sesión expirada. Por favor, inicia sesión.']);
    exit();
}

// Configurar encabezados para respuesta JSON
header('Content-Type: application/json');

// 2. Verificar que la solicitud sea POST y que existan los campos principales
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['tipo']) || empty($_POST['unidades']) || empty($_POST['fecha_alta'])) {
    echo json_encode(['success' => false, 'message' => 'Error en la solicitud o datos incompletos.']);
    exit();
}

// --- 3. Obtener la Conexión de la DB ---
// Llama a la función definida en config.php
$conn = get_db_connection();

if ($conn === null) {
    echo json_encode(['success' => false, 'message' => 'Error al conectar con la base de datos. Verifique config.php']);
    exit();
}

// --- 4. Recolección y Sanitización de Datos ---
$tipo = trim($_POST['tipo']);
// Asegurar que unidades sea un entero
$unidades = (int)$_POST['unidades']; 
$fecha_alta = trim($_POST['fecha_alta']);

// Los campos marca, modelo y descripcion son opcionales
$marca = isset($_POST['marca']) ? trim($_POST['marca']) : '';
$modelo = isset($_POST['modelo']) ? trim($_POST['modelo']) : '';
$descripcion = isset($_POST['descripcion']) ? trim($_POST['descripcion']) : null; // null si no se envió

// 5. Validación Específica para Tipos que Requieren Marca/Modelo
$tipos_con_detalle = ["disco duro", "usb", "memoria ram"];
$requiere_detalle = in_array($tipo, $tipos_con_detalle);

if ($requiere_detalle && (empty($marca) || empty($modelo))) {
    // Es buena práctica asegurarse de cerrar la conexión en caso de error
    $conn->close();
    echo json_encode(['success' => false, 'message' => 'Para este tipo de material, la Marca y el Modelo son obligatorios.']);
    exit();
}

// 6. Preparación e Inserción en la Base de Datos
$sql = "INSERT INTO stock_material (tipo, marca, modelo, descripcion, unidades, fecha_alta) 
        VALUES (?, ?, ?, ?, ?, ?)";

try {
    $stmt = $conn->prepare($sql);
    
    // Enlazar parámetros: s=string, i=integer
    // Parámetros: tipo (s), marca (s), modelo (s), descripcion (s), unidades (i), fecha_alta (s)
    $stmt->bind_param("ssssis", $tipo, $marca, $modelo, $descripcion, $unidades, $fecha_alta);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Material "' . htmlspecialchars($tipo) . '" registrado con éxito.']);
    } else {
        // Manejo de error de ejecución de la consulta
        error_log("Error de ejecución SQL: " . $stmt->error);
        echo json_encode(['success' => false, 'message' => 'Error al registrar el material: ' . $stmt->error]);
    }
    
    $stmt->close();

} catch (Exception $e) {
    // Manejo de excepciones (ejemplo: error en la preparación de la consulta)
    error_log("Excepción al guardar stock: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor al guardar los datos.']);
}

// 7. Cerrar la conexión
$conn->close();
?>