<?php
// guardar_edicion_licencia.php
require_once 'session_check.php';
// 1. ACTIVAR LIMPIEZA DE BÚFER (La Aspiradora)
ob_start(); 

require_once 'config.php';

// Configuración de errores para que no ensucien la respuesta
ini_set('display_errors', 0);
ini_set('log_errors', 1);

session_start();
header('Content-Type: application/json');

// Función para responder y cerrar limpiamente
function responder($success, $message) {
    // Borramos cualquier texto/error previo que se haya colado
    ob_end_clean(); 
    echo json_encode(['success' => $success, 'message' => $message]);
    exit;
}

// 2. VERIFICACIONES
if (!isset($_SESSION['usuario'])) {
    responder(false, 'Sesión caducada. Recarga la página.');
}

// Conexión segura
$conn = function_exists('get_db_connection') ? get_db_connection() : new mysqli("localhost", "root", "", "mi_basedatos");

if ($conn->connect_error) {
    responder(false, 'Error de conexión BD: ' . $conn->connect_error);
}

// 3. RECIBIR DATOS
// Usamos $_POST directo para simplificar depuración
$id = $_POST['id'] ?? '';
$direccion = $_POST['direccion'] ?? ''; 
$area = $_POST['area'] ?? '';           
$correo = $_POST['correo'] ?? '';
$password = $_POST['password'] ?? '';

// 4. VALIDAR
if (empty($id)) {
    responder(false, 'Error: No llegó el ID de la licencia.');
}
if (empty($correo) || empty($password)) {
    responder(false, 'Correo y Contraseña son obligatorios.');
}

// 5. ACTUALIZAR
// Nota: Usamos `Dirección` y `Area` (tal cual tu base de datos)
$sql = "UPDATE cuentas_office SET `Dirección`=?, `Area`=?, Correo=?, Password=? WHERE id=?";
$stmt = $conn->prepare($sql);

if (!$stmt) {
    responder(false, 'Error preparando consulta: ' . $conn->error);
}

$stmt->bind_param("ssssi", $direccion, $area, $correo, $password, $id);

if ($stmt->execute()) {
    responder(true, 'Guardado correctamente');
} else {
    responder(false, 'Error SQL: ' . $stmt->error);
}

// Cierre final (por si acaso no entró a responder antes)
$stmt->close();
$conn->close();
?>