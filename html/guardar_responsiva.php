<?php
// guardar_responsiva.php (Para el botón de la tabla)
session_start();

// Verifica permisos (ajústalo según tus roles)
$rol_actual = isset($_SESSION['rol']) ? $_SESSION['rol'] : '';
if ($rol_actual !== 'admin' && $rol_actual !== 'masterweb') {
    die("⛔ Sin permisos.");
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    if (!isset($_FILES['responsiva']) || !isset($_POST['nombre_correo'])) {
        die("❌ Faltan datos.");
    }

    $correo = trim($_POST['nombre_correo']);
    $archivo = $_FILES['responsiva'];

    $ext = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
    if ($ext != 'pdf') { die("⚠️ Solo PDF."); }

    // RUTA FÍSICA DEL NAS (LA MISMA QUE EN EL REGISTRO)
    $carpetaDestino = "/volume1/web/responsivas/";
    
    if (!file_exists($carpetaDestino)) {
        if (!mkdir($carpetaDestino, 0777, true)) {
            die("❌ Error: No se pudo crear la carpeta en el NAS.");
        }
    }

    $nombreFinal = $correo . ".pdf";
    $rutaFinal = $carpetaDestino . $nombreFinal;

    if (move_uploaded_file($archivo['tmp_name'], $rutaFinal)) {
        echo "✅ Archivo guardado correctamente.";
    } else {
        echo "❌ Error de permisos en el NAS.";
    }

} else {
    echo "❌ Petición inválida.";
}
?>