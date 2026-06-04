<?php
require_once 'config.php';
$conn = get_db_connection();

$usuario = 'admin';
$password_plain = 'admin123'; // Puedes cambiar esta contraseña temporal
$rol = 'admin';

$password_hash = password_hash($password_plain, PASSWORD_DEFAULT);

$stmt = $conn->prepare("SELECT id FROM usuarios WHERE usuario = ?");
$stmt->bind_param("s", $usuario);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    // Si ya existe, actualizamos su contraseña y aseguramos su rol
    $stmt->close();
    $stmt_upd = $conn->prepare("UPDATE usuarios SET password = ?, rol = ? WHERE usuario = ?");
    $stmt_upd->bind_param("sss", $password_hash, $rol, $usuario);
    echo $stmt_upd->execute() ? "Usuario '$usuario' restaurado con éxito. Contraseña temporal: $password_plain" : "Error: " . $stmt_upd->error;
} else {
    // Si no existe, lo creamos desde cero
    $stmt->close();
    $stmt_ins = $conn->prepare("INSERT INTO usuarios (usuario, password, rol) VALUES (?, ?, ?)");
    $stmt_ins->bind_param("sss", $usuario, $password_hash, $rol);
    echo $stmt_ins->execute() ? "Usuario '$usuario' creado con éxito. Contraseña: $password_plain" : "Error: " . $stmt_ins->error;
}
?>
