<?php
// 1. Configuración y Seguridad
require_once 'session_check.php';
ini_set('display_errors', 0); // Ocultar errores en producción
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

require_once 'config.php'; 
session_start();

// 2. Verificación de Sesión
// Si el usuario no está logueado, redirige a la página de inicio
if (!isset($_SESSION['usuario'])) { 
    header("Location: index.php"); 
    exit(); 
}

$conn = get_db_connection();

// 3. Recolección y Validación del ID
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    // Redirigir de vuelta a la lista con un mensaje de error
    header("Location: consultar_directorio.php?status=error&message=ID de registro inválido.");
    $conn->close();
    exit();
}

// 4. Ejecución del DELETE (Sentencia Preparada para seguridad)
$sql = "DELETE FROM directorio_ext WHERE id = ?";
$stmt = $conn->prepare($sql);

if (!$stmt) {
    // Error en la preparación de la consulta
    header("Location: consultar_directorio.php?status=error&message=Error SQL al preparar la consulta.");
    $conn->close();
    exit();
}

// Vincula el parámetro 'i' (integer)
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    // Éxito: Redirigir de vuelta a la lista con mensaje de éxito
    // El script 'consultar_directorio.php' ya está preparado para mostrar este mensaje
    header("Location: consultar_directorio.php?status=deleted");
} else {
    // Error al ejecutar la eliminación
    header("Location: consultar_directorio.php?status=error&message=Error al eliminar el registro: " . $stmt->error);
}

$stmt->close();
$conn->close();
exit();
?>