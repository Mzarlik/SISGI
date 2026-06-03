<?php
// obtener_areas.php (CORREGIDO PARA 'nombres_direcciones')
require_once 'session_check.php';
require_once 'config.php';
header('Content-Type: application/json');

// Reporte de errores para depuración
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = get_db_connection(); 

    // 1. Obtener Secretarías
    // Asegúrate que en la tabla Secretarias la columna sea 'nombres' (si es diferente, cámbialo aquí)
    $querySec = "SELECT id_secretaria, nombres FROM Secretarias ORDER BY nombres ASC";
    $resultSec = $conn->query($querySec);
    
    $data = [];

    while ($sec = $resultSec->fetch_assoc()) {
        $idSec = $sec['id_secretaria'];
        
        // --- AQUÍ ESTÁ EL AJUSTE CLAVE ---
        // Definimos el nombre exacto de la columna en TU base de datos
        $columna_real_bd = "nombres_direcciones"; 
        
        $queryDir = "SELECT id_direcciones, $columna_real_bd 
                     FROM Direcciones 
                     WHERE id_secretaria = ? 
                     ORDER BY $columna_real_bd ASC";
        
        $stmtDir = $conn->prepare($queryDir);
        $stmtDir->bind_param("i", $idSec);
        $stmtDir->execute();
        $resultDir = $stmtDir->get_result();
        
        $direcciones = [];
        while ($dir = $resultDir->fetch_assoc()) {
            // TRADUCCIÓN:
            // Leemos de la BD: $dir['nombres_direcciones'] (PLURAL)
            // Lo guardamos para JS como: 'nombre_direcciones' (SINGULAR)
            // Esto hace que el JavaScript funcione sin cambios.
            $direcciones[] = [
                'id_direcciones' => $dir['id_direcciones'],
                'nombre_direcciones' => $dir[$columna_real_bd] 
            ];
        }
        $stmtDir->close();

        $data[] = [
            'id_secretaria' => $sec['id_secretaria'],
            'nombre_secretaria' => $sec['nombres'],
            'direcciones' => $direcciones
        ];
    }

    $conn->close();
    echo json_encode(['success' => true, 'data' => $data]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>