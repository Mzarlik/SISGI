<?php
// config.php

$DB_HOST = "localhost";
$DB_USER = "admin_db";
$DB_PASS = '*$oporte24-28!*';// <--- OJO: Comillas simples por el signo de pesos
$DB_NAME = "SISGI_db";

date_default_timezone_set('America/Cancun'); 

// --- CONFIGURACIÓN DE ACTIVE DIRECTORY ---
define('AD_SERVER', '172.16.10.61');
define('AD_DOMINIO', 'solidaridad.mx');
define('AD_BASE_DN', 'OU=Usuarios,OU=DNTIC,OU=Usuarios,OU=Solidaridad,DC=solidaridad,DC=mx');

function get_db_connection() {
    global $DB_HOST, $DB_USER, $DB_PASS, $DB_NAME;
    
    $conn = @new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
    
    if ($conn->connect_errno) {
        error_log("Error fatal de conexión DB: " . $conn->connect_error);
        return null; 
    }
    
    $conn->set_charset("utf8mb4");
    return $conn;
}
?>