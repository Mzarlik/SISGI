<?php
// Archivo: get_direcciones.php
require_once 'session_check.php';
require_once 'config.php';

// --- CORRECCIÓN: Usar la misma conexión que registro_equipos.php ---
if (!isset($conn)) {
    // Primero intentamos usar la función de tu config.php (que sabemos que funciona)
    if (function_exists('get_db_connection')) {
        $conn = get_db_connection();
    } else {
        // Solo si no existe la función, intentamos conectar manualmente
        $conn = new mysqli("localhost", "root", 'Mallr0093$', "mi_basedatos");
    }
}

// --- DIAGNÓSTICO DE ERROR ---
// Si falla, ahora te dirá la razón exacta (ej: Access denied, Unknown database)
if ($conn->connect_error) {
    die('<option value="">Error SQL: ' . $conn->connect_error . '</option>');
}

// Verificar codificación (opcional, ayuda con acentos)
$conn->set_charset("utf8");

if (isset($_GET['id_secretaria'])) {
    $id_secretaria = intval($_GET['id_secretaria']); 

    $sql = "SELECT id_direccion, nombre_direccion 
            FROM cat_direcciones 
            WHERE id_secretaria = $id_secretaria 
            ORDER BY nombre_direccion ASC";

    $result = $conn->query($sql);

    if ($result && $result->num_rows > 0) {
        echo '<option value="">-- Selecciona una Dirección --</option>';
        while ($row = $result->fetch_assoc()) {
            echo '<option value="' . $row['id_direccion'] . '">' . htmlspecialchars($row['nombre_direccion']) . '</option>';
        }
    } else {
        // Si no hay resultados o hubo error en la consulta
        if (!$result) {
             echo '<option value="">Error en consulta: ' . $conn->error . '</option>';
        } else {
             echo '<option value="">No hay direcciones registradas</option>';
        }
    }
} else {
    echo '<option value="">Error: No se recibió ID</option>';
}
?>