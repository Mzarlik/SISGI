<?php
// actualizar_licencia.php (VERSIÓN FINAL PARA IDs)
require_once 'session_check.php';
require_once 'config.php';
session_start();

// Encabezado JSON y UTF-8
header('Content-Type: application/json; charset=utf-8');

// 1. Seguridad
if (!isset($_SESSION['usuario'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 2. Obtener datos
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    // Aquí recibimos el ID de la dirección (el número)
    $id_direccion_nueva = isset($_POST['direccion']) ? (int)$_POST['direccion'] : 0;
    
    $area = $_POST['area'] ?? '';
    $correo = $_POST['correo'] ?? '';
    $password = $_POST['password'] ?? '';
    // Conectados lo tratamos como string por si usas formato "0/10" o solo número "0"
    $conectados = $_POST['conectados'] ?? '0';

    if ($id <= 0 || empty($correo)) {
        echo json_encode(['success' => false, 'message' => 'Datos incompletos.']);
        exit;
    }

    $conn = get_db_connection();
    if (!$conn) {
        echo json_encode(['success' => false, 'message' => 'Error de conexión a BD']);
        exit;
    }

    // 🔴 VITAL: Forzar UTF-8 para que los acentos del Área se guarden bien
    $conn->set_charset("utf8mb4");

    // 3. ACTUALIZACIÓN INTELIGENTE
    // Guardamos el ID en 'id_direccion'.
    // Vaciamos la columna vieja 'Dirección' (texto) para mantener la limpieza.
    // Usamos backticks ` ` en Dirección por si acaso.
    
    $sql = "UPDATE cuentas_office SET 
                id_direccion = ?, 
                `Dirección` = '',  /* Limpiamos el texto viejo */
                Area = ?, 
                Correo = ?, 
                Password = ?, 
                Conectados = ? 
            WHERE id = ?";
    
    $stmt = $conn->prepare($sql);
    
    if ($stmt) {
        // Tipos: i (int), s (string), s, s, s, i (int)
        $stmt->bind_param("issssi", 
            $id_direccion_nueva, 
            $area, 
            $correo, 
            $password, 
            $conectados, 
            $id
        );
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Actualizado correctamente']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error SQL: ' . $stmt->error]);
        }
        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'Error preparando consulta: ' . $conn->error]);
    }
    
    $conn->close();

} else {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
}
?>