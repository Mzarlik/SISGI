<?php
// actualizar_usuario.php
require_once 'session_check.php';
require_once 'config.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$conn = get_db_connection();
$conn->set_charset("utf8mb4");

// Recibir Datos
$id = $_POST['id'] ?? 0;
$id_direccion = $_POST['id_direccion'] ?? 0;
$num_oficio = $_POST['num_oficio'] ?? '';
$nombres = $_POST['nombres'] ?? '';
$ap_pat = $_POST['apellido_paterno'] ?? '';
$ap_mat = $_POST['apellido_materno'] ?? '';
$usuario = $_POST['usuario'] ?? '';
$contrasena = $_POST['contrasena'] ?? '';
$cargo = $_POST['cargo'] ?? '';
$jefe_inmediato = $_POST['jefe_inmediato'] ?? '';
$correo = $_POST['correo_electronico'] ?? '';
$telefono = $_POST['telefono'] ?? '';
$num_empleado = $_POST['num_empleado'] ?? '';

if ($id <= 0 || empty($id_direccion) || empty($nombres)) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos.']);
    exit;
}

// UPDATE
// Nota: Usamos 'contrasena' (si en tu BD es 'password' o 'contraseña', cámbialo aquí)
$sql = "UPDATE registros_ad SET 
            id_direccion = ?, 
            num_oficio = ?, 
            nombres = ?, 
            apellido_paterno = ?, 
            apellido_materno = ?, 
            usuario = ?, 
            contrasena = ?,
            cargo = ?,
            jefe_inmediato = ?,
            correo_electronico = ?,
            telefono = ?,
            num_empleado = ?
        WHERE id = ?";

$stmt = $conn->prepare($sql);

if ($stmt) {
    // i (int), s (string) x 11, i (int)
    $stmt->bind_param("isssssssssssi", 
        $id_direccion, 
        $num_oficio, 
        $nombres, 
        $ap_pat, 
        $ap_mat, 
        $usuario, 
        $contrasena,
        $cargo,
        $jefe_inmediato,
        $correo,
        $telefono,
        $num_empleado,
        $id
    );

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Actualizado']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error SQL: ' . $stmt->error]);
    }
    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Error preparando consulta: ' . $conn->error]);
}

$conn->close();
?>