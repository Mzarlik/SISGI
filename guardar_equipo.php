<?php
/**
 * GUARDAR EQUIPO (CORREGIDO: CONTEO DE VARIABLES BIND_PARAM)
 */
require_once 'session_check.php';
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

try {
    require_once 'config.php';
    session_start();

    // 1. CAPTURAR USUARIO
    $usuario_registro = $_SESSION['usuario'] ?? 'Desconocido';

    // Seguridad
    if (!isset($_SESSION['usuario'])) {
        throw new Exception('Sesión expirada. Recarga la página e inicia sesión.');
    }

    $conn = get_db_connection();
    if (!$conn) {
        throw new Exception('Error de conexión a la Base de Datos.');
    }

    // --- RECOPILAR DATOS ---
    $id = isset($_POST['id']) && is_numeric($_POST['id']) ? (int)$_POST['id'] : null;
    $id_cuenta_office = !empty($_POST['id_cuenta_office']) ? (int)$_POST['id_cuenta_office'] : null;

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
    
    // Campos nuevos
    $antivirus_eset    = trim($_POST['antivirus_eset'] ?? 'No');
    $estatus_equipo    = trim($_POST['estatus_equipo'] ?? 'Operativo');
    $observaciones     = trim($_POST['observaciones'] ?? '');

    // Validaciones
    if (empty($numInventario) || empty($secretaria) || empty($direccion)) {
        throw new Exception("Inventario, Secretaría y Dirección son obligatorios.");
    }

    // ==========================================
    // ESCENARIO 1: ACTUALIZACIÓN (UPDATE)
    // ==========================================
    if ($id) {
        // Validar duplicado inventario
        $stmtCheck = $conn->prepare("SELECT id FROM equiposbd WHERE numInventario = ? AND id != ?");
        $stmtCheck->bind_param("si", $numInventario, $id);
        $stmtCheck->execute();
        if ($stmtCheck->get_result()->num_rows > 0) throw new Exception("El Inventario '$numInventario' ya pertenece a otro equipo.");
        $stmtCheck->close();

        // Validar duplicado IP
        if (!empty($direccionIP) && $direccionIP !== '0.0.0.0') {
            $stmtIP = $conn->prepare("SELECT id FROM equiposbd WHERE direccionIP = ? AND id != ?");
            $stmtIP->bind_param("si", $direccionIP, $id);
            $stmtIP->execute();
            if ($stmtIP->get_result()->num_rows > 0) throw new Exception("La IP '$direccionIP' ya está en uso.");
            $stmtIP->close();
        }

        $sqlUpdate = "UPDATE equiposbd SET 
            secretaria=?, direccion=?, usuarioDominio=?, usuariosEquipo=?, 
            tipoequipo=?, marca_modelo=?, procesador=?, ram=?, 
            tipodisco_capa=?, sistemaOperativo=?, nivelAccesoEquipo=?, 
            numInventario=?, direccionIP=?, id_cuenta_office=?,
            antivirus_eset=?, estatus_equipo=?, observaciones=?, registrado_por=?
            WHERE id=?";

        $stmt = $conn->prepare($sqlUpdate);
        
        // CORRECCIÓN: 19 variables = 13 's' + 1 'i' + 4 's' + 1 'i'
        // Cadena: "sssssssssssssissssi"
        $stmt->bind_param(
            "sssssssssssssissssi", 
            $secretaria, $direccion, $usuarioDominio, $usuariosEquipo, 
            $tipoequipo, $marca_modelo, $procesador, $ram, 
            $tipodisco_capa, $sistemaOperativo, $nivelAccesoEquipo, 
            $numInventario, 
            $direccionIP,      
            $id_cuenta_office, 
            $antivirus_eset, $estatus_equipo, $observaciones,
            $usuario_registro, 
            $id
        );

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => "Equipo actualizado correctamente."]);
        } else {
            throw new Exception("Error al actualizar: " . $stmt->error);
        }
        $stmt->close();

    } 
    // ==========================================
    // ESCENARIO 2: NUEVO REGISTRO (INSERT)
    // ==========================================
    else {
        // Validar duplicado inventario
        $stmtCheck = $conn->prepare("SELECT id FROM equiposbd WHERE numInventario = ?");
        $stmtCheck->bind_param("s", $numInventario);
        $stmtCheck->execute();
        if ($stmtCheck->get_result()->num_rows > 0) throw new Exception("El Inventario '$numInventario' ya existe.");
        $stmtCheck->close();

        $sqlInsert = "INSERT INTO equiposbd (
            secretaria, direccion, usuarioDominio, usuariosEquipo, 
            tipoequipo, marca_modelo, procesador, ram, 
            tipodisco_capa, sistemaOperativo, nivelAccesoEquipo, 
            numInventario, direccionIP, id_cuenta_office,
            antivirus_eset, estatus_equipo, observaciones, registrado_por
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($sqlInsert);
        
        // CORRECCIÓN: 18 variables = 13 's' + 1 'i' + 4 's'
        // Cadena: "sssssssssssssissss"
        $stmt->bind_param(
            "sssssssssssssissss", 
            $secretaria, $direccion, $usuarioDominio, $usuariosEquipo, 
            $tipoequipo, $marca_modelo, $procesador, $ram, 
            $tipodisco_capa, $sistemaOperativo, $nivelAccesoEquipo, 
            $numInventario, 
            $direccionIP,       
            $id_cuenta_office,  
            $antivirus_eset, $estatus_equipo, $observaciones,
            $usuario_registro
        );

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => "Equipo registrado exitosamente."]);
        } else {
            throw new Exception("Error al registrar: " . $stmt->error);
        }
        $stmt->close();
    }

    // Actualizar contadores
    if($id_cuenta_office) {
        $conn->query("UPDATE cuentas_office co SET co.Conectados = CONCAT((SELECT COUNT(*) FROM equiposbd WHERE id_cuenta_office = co.id), '/10')");
    }

    $conn->close();

} catch (Exception $e) {
    http_response_code(200); 
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
}
?>