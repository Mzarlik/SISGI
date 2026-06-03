<?php
// procesar_salida.php

require_once 'session_check.php';
require_once 'config.php';
session_start();

header('Content-Type: application/json');

// ==========================================
// 1. VALIDACIÓN Y SEGURIDAD
// ==========================================

// 1.1 Verificar sesión y método POST
if (!isset($_SESSION['usuario']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Acceso no autorizado o sesión expirada.']);
    exit();
}

// 1.2 Verificar datos POST obligatorios
if (empty($_POST['id']) || empty($_POST['cantidad'])) {
    echo json_encode(['success' => false, 'message' => 'Faltan datos obligatorios (ID de material o Cantidad).']);
    exit();
}

// 1.3 Obtener conexión
$conn = get_db_connection();
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Error al conectar con la base de datos.']);
    exit();
}

// 1.4 Recolección y Sanitización de Datos
$id = (int)$_POST['id'];
$cantidad_retirar = (int)$_POST['cantidad'];
$descripcion_salida = trim($_POST['descripcion_salida'] ?? '');
$usuario_registro = $_SESSION['usuario']; // Obtenemos el usuario de la sesión

if ($cantidad_retirar <= 0) {
    echo json_encode(['success' => false, 'message' => 'La cantidad a retirar debe ser positiva.']);
    $conn->close();
    exit();
}


// ==========================================
// 2. VERIFICACIÓN DE STOCK (PRE-TRANSACCIÓN)
// ==========================================

$stmtCheck = $conn->prepare("SELECT unidades FROM stock_material WHERE id = ?");
$stmtCheck->bind_param("i", $id);
$stmtCheck->execute();
$resCheck = $stmtCheck->get_result();

if ($resCheck->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'El material seleccionado no existe.']);
    $stmtCheck->close();
    $conn->close();
    exit();
}

$fila = $resCheck->fetch_assoc();
$stock_actual = (int)$fila['unidades'];

// Verificar si hay suficiente stock
if ($cantidad_retirar > $stock_actual) {
    echo json_encode(['success' => false, 'message' => "Stock insuficiente. Solo hay {$stock_actual} unidades disponibles."]);
    $stmtCheck->close();
    $conn->close();
    exit();
}

$stmtCheck->close();

// Calcular el nuevo stock para la respuesta
$nuevo_stock = $stock_actual - $cantidad_retirar;


// ==========================================
// 3. TRANSACCIÓN DE BANDA (MySQL)
// ==========================================

// Iniciar la transacción para asegurar que ambas operaciones (INSERT y UPDATE) se completen
$conn->begin_transaction();
$transaccion_exitosa = false;

try {
    // 3.1: REGISTRAR EL MOVIMIENTO EN movimientos_stock
    $sql_insert = "INSERT INTO movimientos_stock 
                   (material_id, tipo_movimiento, cantidad, descripcion_salida, usuario) 
                   VALUES (?, 'SALIDA', ?, ?, ?)";
    
    $stmtInsert = $conn->prepare($sql_insert);
    // Parámetros: material_id (i), cantidad (i), descripcion_salida (s), usuario (s)
    $stmtInsert->bind_param("iiss", $id, $cantidad_retirar, $descripcion_salida, $usuario_registro);
    
    if (!$stmtInsert->execute()) {
        throw new Exception("Fallo al registrar el movimiento.");
    }
    $stmtInsert->close();

    // 3.2: ACTUALIZAR EL STOCK EN stock_material (RESTANDO)
    $sql_update = "UPDATE stock_material SET unidades = unidades - ? WHERE id = ?";
    
    $stmtUpdate = $conn->prepare($sql_update);
    // Parámetros: cantidad (i), id (i)
    $stmtUpdate->bind_param("ii", $cantidad_retirar, $id);
    
    if (!$stmtUpdate->execute()) {
        throw new Exception("Fallo al actualizar el stock.");
    }

    if ($conn->affected_rows === 0) {
        // Esto podría ocurrir si el ID no existe, aunque ya lo chequeamos antes
        throw new Exception("No se encontró el material para actualizar."); 
    }
    $stmtUpdate->close();

    // Si todo fue bien, confirmar los cambios
    $conn->commit();
    $transaccion_exitosa = true;

} catch (Exception $e) {
    // Si algo falló, revertir los cambios
    $conn->rollback();
    // Registrar el error en el log para debugging
    error_log("Error de Transacción en Salida: " . $e->getMessage());
    $transaccion_exitosa = false;
    $mensaje_error = "Error interno: " . $e->getMessage();
}


// ==========================================
// 4. RESPUESTA AL CLIENTE
// ==========================================

if ($transaccion_exitosa) {
    echo json_encode([
        'success' => true, 
        'message' => 'Salida registrada y stock actualizado correctamente.',
        'nuevo_stock' => $nuevo_stock
    ]);
} else {
    // Usar el mensaje de error capturado o uno genérico
    echo json_encode(['success' => false, 'message' => $mensaje_error ?? 'Error desconocido al procesar la salida.']);
}

$conn->close();
?>