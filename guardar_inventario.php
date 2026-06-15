<?php
require_once 'session_check.php';
require_once 'config.php';
session_start();

// Responder siempre en formato JSON para el fetch de JavaScript
header('Content-Type: application/json');

if (!isset($_SESSION['usuario']) || $_SESSION['rol'] === 'redes') {
    echo json_encode(['success' => false, 'message' => 'Acceso denegado.']);
    exit();
}

// Validar que la conexión a la BD exista
$conn = get_db_connection();
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Error de conexión a la base de datos.']);
    exit();
}

// 1. Recibir y limpiar los datos del POST
$num_inventario = trim($_POST['num_inventario'] ?? '');
$id_tipo_bien = (int)($_POST['id_tipo_bien'] ?? 0);
$marca = trim($_POST['marca'] ?? '');
$modelo = trim($_POST['modelo'] ?? '');
$num_serie = trim($_POST['num_serie'] ?? '');
$descripcion = trim($_POST['descripcion'] ?? '');
$personal_asignado = trim($_POST['personal_asignado'] ?? 'STOCK');
if(empty($personal_asignado)) $personal_asignado = 'STOCK';
$ubicacion = trim($_POST['ubicacion'] ?? '');
// Nuevos campos
$estatus = trim($_POST['estatus'] ?? 'En Stock');
$municipio = trim($_POST['municipio'] ?? '');
$color = trim($_POST['color'] ?? '');
$no_bien_mueble = trim($_POST['no_bien_mueble'] ?? '');
$no_inv_anterior = trim($_POST['no_inv_anterior'] ?? '');


// Validación básica
if (empty($num_inventario) || empty($id_tipo_bien) || empty($marca) || empty($modelo) || empty($num_serie) || empty($ubicacion)) {
    echo json_encode(['success' => false, 'message' => 'Por favor, completa todos los campos obligatorios.']);
    $conn->close();
    exit();
}

// --- NUEVO: Lógica de Periféricos en combo ---
$incluye_teclado = isset($_POST['incluye_teclado']);
$incluye_mouse = isset($_POST['incluye_mouse']);

$texto_perifericos = "";
if ($incluye_teclado && $incluye_mouse) {
    $texto_perifericos = "\n[NOTA: Incluye Teclado y Mouse con el mismo número de inventario]";
} elseif ($incluye_teclado) {
    $texto_perifericos = "\n[NOTA: Incluye Teclado con el mismo número de inventario]";
} elseif ($incluye_mouse) {
    $texto_perifericos = "\n[NOTA: Incluye Mouse con el mismo número de inventario]";
}

// Concatenamos la nota a la descripción original
if (!empty($texto_perifericos)) {
    $descripcion = $descripcion . $texto_perifericos;
}
// ---------------------------------------------


// 2. Procesamiento de la Imagen y Creación de Carpetas
$ruta_guardado_db = null;
$ruta_archivo_final = null;

// Cuando los sistemas se ejecutan en entornos de red locales o NAS (como Web Station), 
// es más seguro y eficiente usar rutas relativas desde donde se ejecuta este script PHP
// para llegar a la carpeta pública, en lugar de rutas de red UNC de Windows.
// Si este script está en /web/soporte/ y quieres guardar en /web/inventario/:
$ruta_base = '/var/services/web/inventario'; 

// Limpiamos el número de inventario para que el nombre de la carpeta sea seguro en el sistema de archivos
$carpeta_segura = preg_replace('/[^a-zA-Z0-9_-]/', '_', $num_inventario);
$ruta_destino = $ruta_base . '/' . $carpeta_segura;

// Crear la carpeta si no existe
if (!file_exists($ruta_destino)) {
    // 0777 asegura permisos amplios, true permite crear estructura de árbol si es necesario
    if (!mkdir($ruta_destino, 0777, true)) {
        echo json_encode(['success' => false, 'message' => 'No se pudo crear la carpeta para la evidencia. Verifica los permisos del servidor.']);
        $conn->close();
        exit();
    }
}

// Validar y mover el archivo subido
if (isset($_FILES['foto_evidencia']) && $_FILES['foto_evidencia']['error'] === UPLOAD_ERR_OK) {
    $archivo_tmp = $_FILES['foto_evidencia']['tmp_name'];
    $nombre_original = basename($_FILES['foto_evidencia']['name']);
    
    // Extraer extensión y generar nombre único
    $extension = strtolower(pathinfo($nombre_original, PATHINFO_EXTENSION));
    
    // Validar que sea una imagen
    $extensiones_validas = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    if (!in_array($extension, $extensiones_validas)) {
        echo json_encode(['success' => false, 'message' => 'El archivo no es una imagen válida.']);
        $conn->close();
        exit();
    }

    $nuevo_nombre = 'evidencia_' . time() . '.' . $extension;
    $ruta_archivo_final = $ruta_destino . '/' . $nuevo_nombre;

    if (move_uploaded_file($archivo_tmp, $ruta_archivo_final)) {
        // Guardamos la ruta relativa en la base de datos para facilitar la visualización posterior
        $ruta_guardado_db = $carpeta_segura . '/' . $nuevo_nombre;
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al mover la imagen a la carpeta destino.']);
        $conn->close();
        exit();
    }
} else {
    echo json_encode(['success' => false, 'message' => 'La foto de evidencia es obligatoria o superó el tamaño permitido.']);
    $conn->close();
    exit();
}

// 3. Inserción en la Base de Datos
// Ajusta "ruta_foto" al nombre real de la columna que uses en tu tabla inventario_soporte
$sql_insert = "INSERT INTO inventario_soporte
    (num_inventario, id_tipo_bien, marca, modelo, num_serie, descripcion, personal_asignado, ubicacion, ruta_foto, estatus, municipio, color, no_bien_mueble, no_inv_anterior)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = $conn->prepare($sql_insert);
if ($stmt === false) {
    echo json_encode(['success' => false, 'message' => 'Error al preparar la consulta: ' . $conn->error]);
    $conn->close();
    exit();
}

// s: string, i: integer
$stmt->bind_param("sissssssssssss", 
    $num_inventario, $id_tipo_bien, $marca, $modelo, $num_serie, 
    $descripcion, $personal_asignado, $ubicacion, $ruta_guardado_db,
    $estatus, $municipio, $color, $no_bien_mueble, $no_inv_anterior
);

if ($stmt->execute()) {
    echo json_encode([
        'success' => true, 
        'message' => 'El equipo se ha registrado correctamente con su evidencia fotográfica.'
    ]);
} else {
    if (file_exists($ruta_archivo_final)) {
        unlink($ruta_archivo_final);
    }
    echo json_encode([
        'success' => false, 
        'message' => 'Error al registrar en la base de datos: ' . $stmt->error
    ]);
}

$stmt->close();
$conn->close();
?>