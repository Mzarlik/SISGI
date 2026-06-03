<?php
/**
 * SISTEMA DE INVENTARIO - GUARDAR EQUIPO
 * Procesa la recepción de datos vía AJAX y realiza validaciones previas.
 */

require_once 'config.php';
session_start();

// 1. Establecer respuesta como JSON
header('Content-Type: application/json');

// 2. Seguridad: Verificar si el usuario está logueado
if (!isset($_SESSION['usuario'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión expirada. Por favor, inicie sesión nuevamente.']);
    exit();
}

// 3. Conexión a la base de datos
$conn = get_db_connection();
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Error crítico: No se pudo conectar con la base de datos.']);
    exit();
}

try {
    // 4. Recopilación y Limpieza de datos (Sanitización básica)
    $secretaria        = trim($_POST['secretaria'] ?? '');
    $direccion         = trim($_POST['direccion'] ?? '');
    $usuarioDominio    = trim($_POST['usuarioDominio'] ?? '');
    $usuariosEquipo    = trim($_POST['usuariosEquipo'] ?? '');
    $tipoequipo        = trim($_POST['tipoequipo'] ?? '');
    $marca_modelo      = trim($_POST['marca_modelo'] ?? '');
    $procesador        = trim($_POST['procesador'] ?? '');
    $ram               = trim($_POST['ram'] ?? '');
    $tipodisco_capa    = trim($_POST['tipodisco_capa'] ?? '');
    $sistemaOperativo  = trim($_POST['sistemaOperativo'] ?? '');
    $nivelAccesoEquipo = trim($_POST['nivelAccesoEquipo'] ?? '');
    $numInventario     = trim($_POST['numInventario'] ?? '');
    $direccionIP       = trim($_POST['direccionIP'] ?? '');

    // 5. Validación de campos obligatorios
    if (empty($numInventario) || empty($secretaria) || empty($direccion) || empty($usuarioDominio)) {
        throw new Exception("Los campos marcados como obligatorios no pueden estar vacíos.");
    }

    // --- 6. VALIDACIÓN DE DUPLICADOS (Proactiva) ---

    // A. Verificar si el Número de Inventario ya existe
    $stmtCheckInv = $conn->prepare("SELECT id FROM equiposbd WHERE numInventario = ? LIMIT 1");
    $stmtCheckInv->bind_param("s", $numInventario);
    $stmtCheckInv->execute();
    $resInv = $stmtCheckInv->get_result();
    
    if ($resInv->num_rows > 0) {
        throw new Exception("El Número de Inventario **$numInventario** ya se encuentra registrado.");
    }
    $stmtCheckInv->close();

    // B. Verificar si la IP ya existe (solo si no es 0.0.0.0 o vacía)
    if (!empty($direccionIP) && $direccionIP !== '0.0.0.0') {
        $stmtCheckIP = $conn->prepare("SELECT id FROM equiposbd WHERE direccionIP = ? LIMIT 1");
        $stmtCheckIP->bind_param("s", $direccionIP);
        $stmtCheckIP->execute();
        $resIP = $stmtCheckIP->get_result();
        
        if ($resIP->num_rows > 0) {
            throw new Exception("La Dirección IP **$direccionIP** ya está asignada a otro equipo.");
        }
        $stmtCheckIP->close();
    }

    // 7. Preparar la Inserción (Prepared Statement)
    $sqlInsert = "INSERT INTO equiposbd (
                    secretaria, direccion, usuarioDominio, usuariosEquipo, 
                    tipoequipo, marca_modelo, procesador, ram, 
                    tipodisco_capa, sistemaOperativo, nivelAccesoEquipo, 
                    numInventario, direccionIP
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sqlInsert);
    
    // "sssssssssssss" indica que los 13 parámetros son strings
    $stmt->bind_param(
        "sssssssssssss", 
        $secretaria, $direccion, $usuarioDominio, $usuariosEquipo, 
        $tipoequipo, $marca_modelo, $procesador, $ram, 
        $tipodisco_capa, $sistemaOperativo, $nivelAccesoEquipo, 
        $numInventario, $direccionIP
    );

    // 8. Ejecutar y confirmar
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true, 
            'message' => "Equipo registrado exitosamente con el folio: " . $numInventario
        ]);
    } else {
        throw new Exception("Error al ejecutar el registro en la base de datos.");
    }

    $stmt->close();

} catch (Exception $e) {
    // Captura cualquier error y lo devuelve al frontend
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
} finally {
    $conn->close();
}