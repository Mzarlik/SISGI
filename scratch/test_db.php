<?php
require_once 'config.php';
$conn = get_db_connection();
if (!$conn) {
    die("DB connection failed\n");
}
echo "Connected successfully to " . $DB_NAME . "\n";

$sql = "CREATE TABLE IF NOT EXISTS eventos_calendario (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(255) NOT NULL,
    fecha_inicio DATETIME NOT NULL,
    fecha_fin DATETIME NOT NULL,
    tipo_evento VARCHAR(100) NOT NULL,
    direccion_destino VARCHAR(255) DEFAULT NULL,
    asignado_a VARCHAR(255) DEFAULT NULL,
    descripcion TEXT DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

if ($conn->query($sql) === TRUE) {
    echo "Table 'eventos_calendario' created successfully.\n";
} else {
    echo "Error creating table: " . $conn->error . "\n";
}

$conn->close();
?>
