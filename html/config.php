<?php
// config.php

$DB_HOST = "localhost";
$DB_USER = "admin_db";
$DB_PASS = '*$oporte24-28!*'; // <--- OJO: Comillas simples por el signo de pesos
$DB_NAME = "SISGI_db";

date_default_timezone_set('America/Cancun'); // ¡Excelente detalle la zona horaria!

function get_db_connection() {
    global $DB_HOST, $DB_USER, $DB_PASS, $DB_NAME;
    
    // El @ oculta errores feos de PHP para manejarlos nosotros
    $conn = @new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
    
    if ($conn->connect_errno) {
        // Guardamos el error real en el log del servidor (no se muestra al usuario)
        error_log("Error fatal de conexión DB: " . $conn->connect_error);
        return null; 
    }
    
    $conn->set_charset("utf8mb4");
    return $conn;
}
?>
