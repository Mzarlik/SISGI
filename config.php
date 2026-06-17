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

function obtener_nombre_jefe_formateado($conn, $jefe) {
    if (!$conn) return $jefe;
    $jefe = trim($jefe);
    if (empty($jefe) || $jefe === '---') {
        return $jefe;
    }
    
    // 1. Intentar buscar en la base de datos registros_ad haciendo match de palabras significativas
    $parts = array_values(array_filter(explode(" ", $jefe)));
    $sql = "SELECT nombres, apellido_paterno, apellido_materno FROM registros_ad WHERE 1=1";
    $hasSearchWords = false;
    foreach ($parts as $p) {
        $pClean = trim($p, ".,");
        if (strlen($pClean) <= 2) continue; // omitir palabras cortas como 'DE', 'LA', etc.
        $safe_p = $conn->real_escape_string($pClean);
        $sql .= " AND (nombres LIKE '%$safe_p%' OR apellido_paterno LIKE '%$safe_p%' OR apellido_materno LIKE '%$safe_p%')";
        $hasSearchWords = true;
    }
    
    if ($hasSearchWords) {
        $sql .= " LIMIT 1";
        $res = $conn->query($sql);
        if ($res && $res->num_rows > 0) {
            $row = $res->fetch_assoc();
            return trim($row['nombres'] . " " . $row['apellido_paterno'] . " " . $row['apellido_materno']);
        }
    }
    
    // 2. Si no hay coincidencia exacta en la BD, reordenar heurísticamente si el nombre está invertido (Paterno Materno Nombres)
    // No formateamos si ya tiene un prefijo de título profesional (ej: LIC, ING, DR, DRA, C.)
    if (count($parts) >= 3 && !preg_match('/^(LIC|ING|DR|DRA|C)\.?\s/i', $jefe)) {
        $paterno = $parts[0];
        $materno = $parts[1];
        $nombres = implode(" ", array_slice($parts, 2));
        return trim($nombres . " " . $paterno . " " . $materno);
    }
    
    return $jefe;
}
?>