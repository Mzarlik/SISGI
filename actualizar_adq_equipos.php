<?php
require_once 'session_check.php';
require_once 'config.php';
header('Content-Type: application/json');

// 1. VALIDACIÓN DE SEGURIDAD Y MÉTODO
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    exit();
}

session_start();
if (!isset($_SESSION['usuario']) || ($_SESSION['rol'] ?? '') !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Sesión inválida o no autorizada.']);
    exit();
}

$conn = get_db_connection();
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Error de conexión a la base de datos.']);
    exit();
}

// 2. RECUPERACIÓN Y SANITIZACIÓN DE DATOS
$id_adquisicion = isset($_POST['id_adquisicion']) ? (int)$_POST['id_adquisicion'] : 0;
$tipo = $_POST['tipo'] ?? '';
$marca = $_POST['marca'] ?? '';
$modelo = $_POST['modelo'] ?? '';
$capacidad_almacenamiento = !empty($_POST['capacidad_almacenamiento']) ? $_POST['capacidad_almacenamiento'] : NULL;
$memoria_ram = !empty($_POST['memoria_ram']) ? $_POST['memoria_ram'] : NULL;
$capitulos = $_POST['capitulos'] ?? '';
$precio = isset($_POST['precio']) ? (float)$_POST['precio'] : 0.0;
$detalles = $_POST['detalles'] ?? '';

if ($id_adquisicion === 0 || empty($tipo) || empty($marca) || empty($modelo)) {
    echo json_encode(['success' => false, 'message' => 'Faltan campos obligatorios para actualizar.']);
    exit();
}

// 3. OBTENER LA RUTA DE LA IMAGEN ACTUAL (Por si no se sube una nueva o para borrarla si se reemplaza)
$ruta_imagen_db = NULL;
$sql_img = "SELECT ruta_imagen FROM Adq_equipos WHERE id_adquisicion = ?";
$stmt_img = $conn->prepare($sql_img);
if ($stmt_img) {
    $stmt_img->bind_param("i", $id_adquisicion);
    $stmt_img->execute();
    $res_img = $stmt_img->get_result();
    if ($row_img = $res_img->fetch_assoc()) {
        $ruta_imagen_db = $row_img['ruta_imagen'];
    }
    $stmt_img->close();
}

// 4. PROCESAMIENTO DE LA NUEVA IMAGEN (SI ES QUE SE SUBIÓ UNA)
if (isset($_FILES['imagen_equipo']) && $_FILES['imagen_equipo']['error'] === UPLOAD_ERR_OK) {
    $directorio_base = "uploads/equipos/";

    // Verificar y crear el directorio con permisos correctos en el NAS
    if (!file_exists($directorio_base)) {
        mkdir($directorio_base, 0777, true);
    }

    // Limpieza del nombre de archivo original
    $nombre_original = basename($_FILES["imagen_equipo"]["name"]);
    $nombre_limpio = preg_replace("/[^a-zA-Z0-9.]/", "_", $nombre_original);
    
    // Generar nombre único con timestamp
    $nombre_archivo = time() . "_" . $nombre_limpio;
    $ruta_destino = $directorio_base . $nombre_archivo;

    // Intentar mover el archivo temporal a la carpeta destino
    if (move_uploaded_file($_FILES["imagen_equipo"]["tmp_name"], $ruta_destino)) {
        // Si ya existía una imagen previa físicamente en el servidor, la eliminamos para no dejar basura
        if (!empty($ruta_imagen_db) && file_exists($ruta_imagen_db)) {
            unlink($ruta_imagen_db);
        }
        // Actualizamos la variable con la ruta del nuevo archivo cargado
        $ruta_imagen_db = $ruta_destino;
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al guardar la nueva imagen en el NAS.']);
        exit();
    }
}

// 5. ACTUALIZACIÓN EN LA BASE DE DATOS
$sql_update = "UPDATE Adq_equipos 
               SET tipo = ?, marca = ?, modelo = ?, capacidad_almacenamiento = ?, memoria_ram = ?, capitulos = ?, precio = ?, detalles = ?, ruta_imagen = ? 
               WHERE id_adquisicion = ?";

$stmt_up = $conn->prepare($sql_update);

if ($stmt_up) {
    $stmt_up->bind_param("ssssssdssi", 
        $tipo, $marca, $modelo, $capacidad_almacenamiento, $memoria_ram, 
        $capitulos, $precio, $detalles, $ruta_imagen_db, $id_adquisicion
    );

    if ($stmt_up->execute()) {
        echo json_encode(['success' => true, 'message' => 'Registro actualizado correctamente.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al actualizar en la base de datos: ' . $stmt_up->error]);
    }
    $stmt_up->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Error al preparar la consulta de actualización.']);
}

$conn->close();
?>