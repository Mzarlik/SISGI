<?php
require_once 'session_check.php';
header('Content-Type: application/json');
require_once 'config.php'; 
session_start();

if (!isset($_SESSION['usuario'])) {
    echo json_encode(['success' => false, 'message' => 'Acceso denegado.']);
    exit();
}

// 1. AGREGAMOS 'secretaria' AL INICIO DEL ARRAY
$campos = ['secretaria', 'direccion', 'usuarioDominio', 'usuariosEquipo', 'tipoequipo', 
           'marca_modelo', 'procesador', 'ram', 'tipodisco_capa', 'sistemaOperativo', 
           'nivelAccesoEquipo', 'numInventario', 'direccionIP'];

$datos = [];
foreach ($campos as $c) {
    // Si el campo no viene, guardamos cadena vacía o NULL según prefieras
    $datos[] = isset($_POST[$c]) ? trim($_POST[$c]) : '';
}

$conn = get_db_connection();
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Error de conexión a BD']);
    exit();
}

// 2. ACTUALIZAMOS LA SENTENCIA SQL (13 signos de interrogación ahora)
$sql = "INSERT INTO equiposbd (secretaria, direccion, usuarioDominio, usuariosEquipo, tipoequipo, marca_modelo, procesador, ram, tipodisco_capa, sistemaOperativo, nivelAccesoEquipo, numInventario, direccionIP) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)";

$stmt = $conn->prepare($sql);

// 3. ACTUALIZAMOS LOS TIPOS (13 letras 's')
$stmt->bind_param("sssssssssssss", ...$datos);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Equipo registrado exitosamente.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Error SQL: ' . $stmt->error]);
}
$stmt->close();
$conn->close();
?>