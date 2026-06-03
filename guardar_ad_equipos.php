<?php
require_once 'session_check.php';
require_once 'config.php';
header('Content-Type: application/json');

// Validar que se reciba un POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    exit();
}

$conn = get_db_connection();

// Recibir los datos del POST (asegúrate de sanitizarlos en tu entorno real)
$tipo = $_POST['tipo'] ?? '';
$marca = $_POST['marca'] ?? '';
$modelo = $_POST['modelo'] ?? '';
$capacidad_almacenamiento = !empty($_POST['capacidad_almacenamiento']) ? $_POST['capacidad_almacenamiento'] : NULL;
$memoria_ram = !empty($_POST['memoria_ram']) ? $_POST['memoria_ram'] : NULL;
$capitulos = $_POST['capitulos'] ?? '';
$precio = $_POST['precio'] ?? 0;
$detalles = $_POST['detalles'] ?? '';

// -----------------------------------------------------
// LÓGICA DE CARGA DE IMAGEN (CARPETA EN EL NAS)
// -----------------------------------------------------
$ruta_imagen_db = NULL; // Por defecto es null por si no suben imagen

if (isset($_FILES['imagen_equipo']) && $_FILES['imagen_equipo']['error'] === UPLOAD_ERR_OK) {
    // Definimos el nombre de la carpeta (relativa al archivo php actual)
    $directorio_base = "uploads/equipos/";

    // Si la carpeta no existe, la creamos (0777 da permisos de escritura/lectura)
    if (!file_exists($directorio_base)) {
        mkdir($directorio_base, 0777, true);
    }

    // Limpiamos el nombre del archivo para evitar espacios y caracteres extraños
    $nombre_original = basename($_FILES["imagen_equipo"]["name"]);
    $nombre_limpio = preg_replace("/[^a-zA-Z0-9.]/", "_", $nombre_original);
    
    // Agregamos un timestamp para que no se sobreescriban archivos con el mismo nombre
    $nombre_archivo = time() . "_" . $nombre_limpio;
    $ruta_destino = $directorio_base . $nombre_archivo;

    // Movemos el archivo temporal a nuestra carpeta en el NAS
    if (move_uploaded_file($_FILES["imagen_equipo"]["tmp_name"], $ruta_destino)) {
        $ruta_imagen_db = $ruta_destino; // Esta es la ruta que se guardará en MySQL
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al guardar la imagen en el servidor.']);
        exit();
    }
}

// -----------------------------------------------------
// INSERCIÓN EN LA BASE DE DATOS
// -----------------------------------------------------
// IMPORTANTE: Asegúrate de que la columna "ruta_imagen" exista en tu tabla.
$sql = "INSERT INTO Adq_equipos (tipo, marca, modelo, capacidad_almacenamiento, memoria_ram, capitulos, precio, detalles, ruta_imagen) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = $conn->prepare($sql);

if ($stmt) {
    // 'sssssssss' indica que son 9 variables de tipo string (o que se tratarán como tal)
    $stmt->bind_param("ssssssdss", 
        $tipo, $marca, $modelo, $capacidad_almacenamiento, $memoria_ram, 
        $capitulos, $precio, $detalles, $ruta_imagen_db
    );

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Adquisición registrada correctamente.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error en la base de datos: ' . $stmt->error]);
    }
    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Error en la preparación de la consulta.']);
}

$conn->close();
?>