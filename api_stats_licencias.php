<?php
// api_stats_licencias.php
require_once 'session_check.php';
require_once 'config.php';
header('Content-Type: application/json; charset=utf-8');
session_start();

if (!isset($_SESSION['usuario'])) {
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

$conn = get_db_connection();
$conn->set_charset("utf8mb4");

// 1. Traemos TODAS las licencias con su Secretaría
// Usamos LEFT JOIN para que aunque no tenga secretaría asignada, cuente en el global
$sql = "SELECT 
            t1.Conectados,
            t1.Correo,
            COALESCE(s.nombres, 'Sin Asignar') as secretaria
        FROM cuentas_office t1
        LEFT JOIN Direcciones d ON t1.id_direccion = d.id_direcciones
        LEFT JOIN Secretarias s ON d.id_secretaria = s.id_secretaria";

$result = $conn->query($sql);

// 2. Variables para acumular
$global_usadas = 0;
$global_total_slots = 0; // Capacidad total (sumando los /5 o /10 de todos)
$cuentas_sin_uso = 0;    // Cuentas con 0 dispositivos
$stats_secretaria = [];

while($row = $result->fetch_assoc()) {
    // LÓGICA DE PARSEO: Convertir "3/5" a números
    $texto = $row['Conectados'] ?? '0/5';
    
    // Si el campo está vacío o no tiene '/', asumimos 0/5
    if(strpos($texto, '/') === false) {
        $usadas = intval($texto); // Si solo dice "3"
        $capacidad = 5; // Default estándar
    } else {
        $partes = explode('/', $texto);
        $usadas = intval($partes[0]);
        $capacidad = intval($partes[1]);
    }

    // Acumuladores Globales
    $global_usadas += $usadas;
    $global_total_slots += $capacidad;
    
    // Detector de Desperdicio
    if($usadas === 0) {
        $cuentas_sin_uso++;
    }

    // Acumuladores por Secretaría
    $sec = $row['secretaria'];
    if(!isset($stats_secretaria[$sec])) {
        $stats_secretaria[$sec] = ['usadas' => 0, 'capacidad' => 0, 'cuentas' => 0];
    }
    $stats_secretaria[$sec]['usadas'] += $usadas;
    $stats_secretaria[$sec]['capacidad'] += $capacidad;
    $stats_secretaria[$sec]['cuentas']++;
}

// 3. Respuesta JSON
echo json_encode([
    'global' => [
        'usadas' => $global_usadas,
        'libres' => $global_total_slots - $global_usadas,
        'total_capacidad' => $global_total_slots,
        'cuentas_fantasma' => $cuentas_sin_uso
    ],
    'secretarias' => $stats_secretaria
]);

$conn->close();
?>