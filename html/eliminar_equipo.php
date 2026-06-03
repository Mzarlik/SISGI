<?php
// Define la respuesta como JSON
header('Content-Type: application/json');

// Incluir el archivo de configuración y conexión
require_once 'config.php'; 
session_start();

// --- 1. Verificación de Sesión ---
if (!isset($_SESSION['usuario'])) {
    echo json_encode(['success' => false, 'message' => 'Acceso denegado. Se requiere autenticación.']);
    exit();
}

// --- 2. Recolección y Validación de Datos ---
if (!isset($_POST['id'])) {
    echo json_encode(['success' => false, 'message' => 'Error: Falta el ID del equipo a eliminar.']);
    exit();
}

$id = (int)$_POST['id'];
$conn = get_db_connection();

if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Error: No se pudo establecer la conexión a la base de datos.']);
    exit();
}

// --- 3. Preparar y Ejecutar la Sentencia DELETE ---
$sql = "DELETE FROM equiposbd WHERE id = ?";

$stmt = $conn->prepare($sql);

if ($stmt === false) {
    echo json_encode(['success' => false, 'message' => 'Error al preparar la sentencia: ' . $conn->error]);
    $conn->close();
    exit();
}

// Vincular: i (integer para el ID)
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        // Éxito
        echo json_encode(['success' => true, 'message' => 'Equipo ' . $id . ' eliminado correctamente.']);
    } else {
        // ID no encontrado
        echo json_encode(['success' => false, 'message' => 'Error: No se encontró el equipo con ID ' . $id . '.']);
    }
} else {
    // Error de ejecución
    echo json_encode(['success' => false, 'message' => 'Error al ejecutar la eliminación: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
?>