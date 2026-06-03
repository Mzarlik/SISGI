<?php
header('Content-Type: application/json');
require_once 'config.php'; session_start();
if (!isset($_SESSION['usuario'])) { echo json_encode(['success'=>false, 'message'=>'Acceso denegado']); exit; }

$conn = get_db_connection();
$stmt = $conn->prepare("INSERT INTO directorio_ext (direccion, area_departamento, nombre_personal, extension, numero_directo) VALUES (?,?,?,?,?)");
$stmt->bind_param("sssss", $_POST['direccion'], $_POST['area'], $_POST['nombre'], $_POST['extension'], $_POST['directo']);

if ($stmt->execute()) echo json_encode(['success'=>true]);
else echo json_encode(['success'=>false, 'message'=>$stmt->error]);
$conn->close();
?>