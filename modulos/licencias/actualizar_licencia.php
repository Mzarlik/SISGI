<?php
require_once 'config.php';
session_start();

header('Content-Type: application/json');

// 1. Seguridad: Solo usuarios logueados
if (!isset($_SESSION['usuario'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

// 2. Validar que lleguen datos por POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Obtener y limpiar datos
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $direccion = $_POST['direccion'] ?? '';
    $area = $_POST['area'] ?? '';
    $correo = $_POST['correo'] ?? '';
    $password = $_POST['password'] ?? '';
    $conectados = isset($_POST['conectados']) ? (int)$_POST['conectados'] : 0;

    if ($id <= 0 || empty($correo)) {
        echo json_encode(['success' => false, 'message' => 'Datos incompletos (ID o Correo faltante)']);
        exit;
    }

    $conn = get_db_connection();
    if (!$conn) {
        echo json_encode(['success' => false, 'message' => 'Error de conexión a BD']);
        exit;
    }

    // 3. Actualizar en Base de Datos
    // NOTA: Verifica que los nombres de columnas coincidan exactamente con tu BD
    // (Dirección, Area, Correo, Password, Conectados)
    $sql = "UPDATE cuentas_office SET Dirección = ?, Area = ?, Correo = ?, Password = ?, Conectados = ? WHERE id = ?";
    
    $stmt = $conn->prepare($sql);
    
    if ($stmt) {
        // s = string, i = integer. Orden: Dir(s), Area(s), Correo(s), Pass(s), Conect(i), Id(i)
        $stmt->bind_param("ssssii", $direccion, $area, $correo, $password, $conectados, $id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Actualizado correctamente']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error SQL: ' . $stmt->error]);
        }
        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'Error en la preparación de la consulta']);
    }
    
    $conn->close();

} else {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
}
?>