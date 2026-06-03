<?php
require_once 'config.php';
session_start();
header('Content-Type: application/json');

// 1. Verificar Sesión y Permisos
if (!isset($_SESSION['usuario'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

$rol = $_SESSION['rol'] ?? 'tecnico';
if ($rol !== 'admin' && $rol !== 'masterweb') {
    echo json_encode(['success' => false, 'message' => 'Permisos insuficientes']);
    exit();
}

// 2. Verificar Datos
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $conn = get_db_connection();
    
    $id = $_POST['id'];
    $direccion = $_POST['direccion'] ?? '';
    $area = $_POST['area_departamento'] ?? '';
    $nombre = $_POST['nombre_personal'] ?? '';
    $ext = $_POST['extension'] ?? '';
    $directo = $_POST['numero_directo'] ?? '';

    // 3. Actualizar
    $sql = "UPDATE directorio_ext SET 
            direccion = ?, 
            area_departamento = ?, 
            nombre_personal = ?, 
            extension = ?, 
            numero_directo = ? 
            WHERE id = ?";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssssi", $direccion, $area, $nombre, $ext, $directo, $id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error SQL: ' . $conn->error]);
    }
    
    $stmt->close();
    $conn->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
}
?>