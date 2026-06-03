<?php
require_once 'config.php';
session_start();

// Configurar la cabecera para devolver una respuesta JSON
header('Content-Type: application/json');

// 1. SEGURIDAD Y VALIDACIÓN DE ROL
// Verifica si el usuario está logueado y si tiene un rol con permiso de edición
if (!isset($_SESSION['usuario']) || !in_array(($_SESSION['rol'] ?? 'tecnico'), ['admin', 'masterweb'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Permiso denegado. Se requiere rol de administrador para esta acción.']);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(['success' => false, 'message' => 'Método de solicitud no permitido.']);
    exit();
}

// ===============================================
// 2. OBTENER Y VALIDAR DATOS
// ===============================================

$id_registro = $_POST['id'] ?? null;

// Campos a actualizar (deben coincidir con el data-campo del JS)
$datos_actualizar = [
    'num_inventario'    => $_POST['num_inventario'] ?? null,
    'tipo'              => $_POST['tipo'] ?? null,
    'marca'             => $_POST['marca'] ?? null,
    'modelo'            => $_POST['modelo'] ?? null,
    'num_serie'         => $_POST['num_serie'] ?? null,
    'descripcion'       => $_POST['descripcion'] ?? null,
    'personal_asignado' => $_POST['personal_asignado'] ?? null,
    'ubicacion'         => $_POST['ubicacion'] ?? null
];

// Comprobación mínima
if (empty($id_registro)) {
    echo json_encode(['success' => false, 'message' => 'ID de registro faltante.']);
    exit();
}

$conn = get_db_connection();

if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Error de conexión a la base de datos.']);
    exit();
}

try {
    // 3. CONSTRUIR LA SENTENCIA UPDATE
    $set_clauses = [];
    $params = [];
    $types = '';

    foreach ($datos_actualizar as $columna => $valor) {
        // Ignorar los campos que no fueron enviados o son nulos, a menos que sean requeridos
        if ($valor !== null) {
            $set_clauses[] = "`$columna` = ?";
            $params[] = $valor;
            $types .= 's'; // Todos los campos son strings/ENUMs
        }
    }

    if (empty($set_clauses)) {
        echo json_encode(['success' => false, 'message' => 'No se proporcionaron datos para actualizar.']);
        exit();
    }

    // Agregar el ID al final de los parámetros para el WHERE
    $params[] = $id_registro;
    $types .= 'i'; // El ID es un entero

    $sql = "UPDATE inventario_soporte SET " . implode(', ', $set_clauses) . " WHERE id = ?";
    
    $stmt = $conn->prepare($sql);

    if ($stmt === false) {
        throw new Exception("Error al preparar la consulta: " . $conn->error);
    }

    // Ligar los parámetros dinámicamente
    // Usamos call_user_func_array para llamar a bind_param con un array de argumentos
    $bind_names = array_merge([$types], $params);
    call_user_func_array([$stmt, 'bind_param'], ref_values($bind_names));

    // 4. EJECUTAR
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true, 
            'message' => 'Registro actualizado correctamente.',
            'rows_affected' => $stmt->affected_rows
        ]);
    } else {
         // Manejo de errores específicos, como clave duplicada
        if ($conn->errno == 1062) {
             $error_message = 'Error: El Número de Inventario o Serie ya existe en otro registro.';
        } else {
             $error_message = 'Error al actualizar: ' . $stmt->error;
        }
        echo json_encode(['success' => false, 'message' => $error_message]);
    }

    $stmt->close();

} catch (Exception $e) {
    error_log("Error en actualizar_inventario.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor: ' . $e->getMessage()]);
}

$conn->close();

/**
 * Función auxiliar para pasar referencias a bind_param
 */
function ref_values($arr){
    if (strnatcmp(phpversion(),'5.3') >= 0) { // Versiones >= PHP 5.3
        $refs = array();
        foreach($arr as $key => $value)
            $refs[$key] = &$arr[$key];
        return $refs;
    }
    return $arr;
}
?>