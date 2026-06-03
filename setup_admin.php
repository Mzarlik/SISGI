<?php
require_once 'session_check.php';
require_once 'config.php';
$conn = get_db_connection();

$usuario = 'franco';
$password_plain = 'Siwey01*';

$stmt = $conn->prepare("SELECT id FROM usuarios WHERE usuario = ?");
$stmt->bind_param("s", $usuario);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    echo "El usuario admin ya existe.";
    exit;
}

$stmt->close();

$password_hash = password_hash($password_plain, PASSWORD_DEFAULT);

$stmt = $conn->prepare("INSERT INTO usuarios (usuario, password) VALUES (?, ?)");
$stmt->bind_param("ss", $usuario, $password_hash);

echo $stmt->execute() ? "Usuario admin creado correctamente." : "Error: " . $stmt->error;
?>
