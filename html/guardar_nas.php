<?php
// Suprimir advertencias para no romper el JSON
ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
header('Content-Type: application/json');

require_once 'config.php'; 
session_start();

if (!isset($_SESSION['usuario'])) { 
    echo json_encode(['success'=>false, 'message'=>'Acceso denegado']); 
    exit; 
}

$conn = get_db_connection();
if (!$conn) {
    echo json_encode(['success'=>false, 'message'=>'Error de conexión BD']);
    exit;
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

// Recolección segura de datos (usando operador ternario para evitar errores)
$u = isset($_POST['ubicacion']) ? trim($_POST['ubicacion']) : '';
$n = isset($_POST['nombre_carpeta']) ? trim($_POST['nombre_carpeta']) : '';
$i = isset($_POST['ip_servidor']) ? trim($_POST['ip_servidor']) : '';
$us = isset($_POST['usuario']) ? trim($_POST['usuario']) : '';
$p = isset($_POST['password']) ? trim($_POST['password']) : '';
$obs = isset($_POST['observaciones']) ? trim($_POST['observaciones']) : '';

if ($id > 0) {
    // UPDATE
    $stmt = $conn->prepare("UPDATE carpetas_nas SET ubicacion=?, nombre_carpeta=?, ip_servidor=?, usuario=?, password=?, observaciones=? WHERE id=?");
    $stmt->bind_param("ssssssi", $u, $n, $i, $us, $p, $obs, $id);
} else {
    // INSERT
    $stmt = $conn->prepare("INSERT INTO carpetas_nas (ubicacion, nombre_carpeta, ip_servidor, usuario, password, observaciones) VALUES (?,?,?,?,?,?)");
    $stmt->bind_param("ssssss", $u, $n, $i, $us, $p, $obs);
}

if ($stmt->execute()) {
    echo json_encode(['success'=>true]);
} else {
    echo json_encode(['success'=>false, 'message'=>'Error SQL: ' . $stmt->error]);
}
$stmt->close();
$conn->close();
?>