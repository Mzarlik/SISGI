<?php
// obtener_cuentas_office.php
require_once 'session_check.php';
require_once 'config.php';
header('Content-Type: application/json');

try {
    $conn = get_db_connection();

    // Seleccionamos ID, Correo y el conteo actual (o el campo 'Conectados')
    // Ordenamos por correo para facilitar la búsqueda
    $sql = "SELECT id, Correo, Conectados FROM cuentas_office ORDER BY Correo ASC";
    $result = $conn->query($sql);

    $cuentas = [];
    while ($row = $result->fetch_assoc()) {
        $cuentas[] = $row;
    }

    echo json_encode(['success' => true, 'data' => $cuentas]);
    $conn->close();

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>