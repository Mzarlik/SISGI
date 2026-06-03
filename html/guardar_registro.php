<?php
// CRÍTICO: Suprimir errores y ADVERTENCIAS en pantalla para no ensuciar el JSON
ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

// 1. Inicio de Sesión
session_start();
header('Content-Type: application/json'); // Respuesta siempre en JSON

// Incluir configuración
require_once 'config.php';

// 2. Verificación de Sesión
if (!isset($_SESSION['usuario'])) {
    echo json_encode(['success' => false, 'message' => 'Acceso denegado. Se requiere autenticación.']);
    exit();
}

// 3. Recolección y **VALIDACIÓN ROBUSTA DE DATOS**
$required_fields = ['secretaria', 'direccion', 'num_oficio', 'nombres', 'apellido_paterno', 'apellido_materno', 'fecha_alta', 'usuario', 'contrasena', 'num_empleado'];

foreach ($required_fields as $field) {
    if (!isset($_POST[$field]) || trim($_POST[$field]) === '') {
        $friendly_name = ucfirst(str_replace('_', ' ', $field));
        echo json_encode(['success' => false, 'message' => "Error: El campo '{$friendly_name}' es obligatorio y no puede estar vacío."]);
        exit();
    }
}


// 4. Limpieza y asignación de variables (Aquí se asumen los campos ya validados)
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0; 
$sec = trim($_POST['secretaria']);
$dir = trim($_POST['direccion']);
$num = trim($_POST['num_oficio']);
$nombre = trim($_POST['nombres']);
$appa = trim($_POST['apellido_paterno']);
$appma = trim($_POST['apellido_materno']);
$alta = trim($_POST['fecha_alta']);
$cuenta = trim($_POST['usuario']);
$pass = trim($_POST['contrasena']); // Aquí podrías hashear: password_hash(trim($_POST['contrasena']), PASSWORD_DEFAULT);
$emple = trim($_POST['num_empleado']);

// 5. Conexión a BD
$conn = get_db_connection();

if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Error: No se pudo establecer la conexión a la base de datos.']);
    exit();
}

// 5.5. CRÍTICO: VERIFICAR DUPLICADOS ANTES DE GUARDAR
// Se busca un registro que coincida con Nombres, Apellido Paterno Y Apellido Materno.
// Se excluye al propio ID si estamos en modo ACTUALIZACIÓN (para que no se detecte a sí mismo como duplicado).

$sql_check = "SELECT id FROM registros_ad WHERE nombres = ? AND apellido_paterno = ? AND apellido_materno = ?";

// Si estamos en modo ACTUALIZACIÓN, agregamos la condición para excluir el ID actual
if ($id > 0) {
    $sql_check .= " AND id != ?";
}

$stmt_check = $conn->prepare($sql_check);

// El tipo de binding depende si estamos actualizando o creando
if ($id > 0) {
    $stmt_check->bind_param("sssi", $nombre, $appa, $appma, $id);
} else {
    $stmt_check->bind_param("sss", $nombre, $appa, $appma);
}

$stmt_check->execute();
$stmt_check->store_result(); // Necesario para obtener el número de filas

if ($stmt_check->num_rows > 0) {
    // DUPLICADO ENCONTRADO
    $stmt_check->close();
    $conn->close();
    echo json_encode([
        'success' => false, 
        'message' => 'Error de Registro: Ya existe un usuario registrado con esos Nombres y Apellidos.'
    ]);
    exit();
}
$stmt_check->close();
// Si llegamos aquí, NO HAY DUPLICADOS, podemos proceder con la inserción/actualización.


// 6. Determinar si es UPDATE o INSERT
if ($id > 0) {
    // --- MODO ACTUALIZACIÓN (UPDATE) ---
    $sql = "UPDATE registros_ad SET 
                secretaria = ?, 
                direccion = ?, 
                num_oficio = ?, 
                nombres = ?, 
                apellido_paterno = ?,
                apellido_materno = ?,
                fecha_alta = ?, 
                usuario = ?, 
                contrasena = ?,
                num_empleado = ? 
            WHERE id = ?";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Error al preparar UPDATE: ' . $conn->error]);
        exit();
    }
    $stmt->bind_param("ssssssssssi", $sec, $dir, $num, $nombre, $appa, $appma, $alta, $cuenta, $pass, $emple, $id);
    $accion = "actualizado";

} else {
    // --- MODO NUEVO REGISTRO (INSERT) ---
    $sql = "INSERT INTO registros_ad 
                (secretaria, direccion, num_oficio, nombres, apellido_paterno, apellido_materno, fecha_alta, usuario, contrasena, num_empleado) 
            VALUES (?,?,?,?,?,?,?,?,?,?)";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Error al preparar INSERT: ' . $conn->error]);
        exit();
    }
    $stmt->bind_param("ssssssssss", $sec, $dir, $num, $nombre, $appa, $appma, $alta, $cuenta, $pass, $emple);
    $accion = "creado";
}

// 7. Ejecutar
if ($stmt->execute()) {
    if ($id > 0 && $stmt->affected_rows === 0) {
        echo json_encode(['success' => true, 'message' => 'Registro ' . $id . ' guardado, pero no se detectaron cambios.']);
    } else {
        echo json_encode(['success' => true, 'message' => 'Registro ' . ($id > 0 ? $id : $conn->insert_id) . ' ' . $accion . ' correctamente.']);
    }
} else {
    // Error de ejecución
    http_response_code(500); // Enviar código de error HTTP
    echo json_encode(['success' => false, 'message' => "Error al ejecutar la sentencia: " . $stmt->error]);
}

$stmt->close();
$conn->close();
?>