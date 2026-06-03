<?php
// actualizar_licencia.php

require_once 'config.php';
session_start();

header('Content-Type: application/json');

// 1. Verificación de seguridad básica
// Solo verificamos que el usuario esté logueado y que la petición sea POST
if (!isset($_SESSION['usuario']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Acceso no autorizado.']);
    exit();
}

/**
 * SE ELIMINÓ LA VERIFICACIÓN DE ROL DE ADMINISTRADOR
 * Ahora cualquier usuario autenticado (técnico o admin) puede realizar cambios.
 */

// 2. Conexión y Captura de Datos
$conn = get_db_connection();

$id         = $_POST['id'] ?? 0;
$direccion  = $_POST['Dirección'] ?? '';
$area       = $_POST['Area'] ?? '';
$correo     = $_POST['Correo'] ?? '';
$password   = $_POST['Password'] ?? '';
$conectados = $_POST['Conectados'] ?? '';

$id = (int)$id;

// 3. Validación de campos obligatorios
if ($id <= 0 || empty($direccion) || empty($area) || empty($correo) || empty($password) || empty($conectados)) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos o ID inválido para la actualización.']);
    $conn->close();
    exit();
}

// 4. Ejecutar la Actualización (UPDATE)
$sql = "UPDATE cuentas_office SET Dirección = ?, Area = ?, Correo = ?, Password = ?, Conectados = ? WHERE id = ?";
$stmt = $conn->prepare($sql);

if ($stmt) {
    // Tipos de parámetros: sssssi
    $stmt->bind_param("sssssi", $direccion, $area, $correo, $password, $conectados, $id);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode(['success' => true, 'message' => 'Registro actualizado con éxito.']);
        } else {
            // Esto ocurre si los datos enviados son idénticos a los actuales
            echo json_encode(['success' => true, 'message' => 'No se realizaron cambios (datos idénticos).']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al ejecutar la consulta: ' . $stmt->error]);
    }
    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Error al preparar la consulta: ' . $conn->error]);
}

$conn->close();
?>