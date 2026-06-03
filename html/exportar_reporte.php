<?php
// exportar_reporte.php
require_once 'config.php';
session_start();

// Validar sesión
if (!isset($_SESSION['usuario'])) {
    exit("Acceso denegado");
}

// Verificar qué reporte quiere el usuario
$tipo = $_GET['tipo'] ?? 'directorio'; // por defecto directorio

$conn = get_db_connection();
$filename = "reporte_" . $tipo . "_" . date('Y-m-d') . ".csv";

// 1. Configurar cabeceras para forzar descarga
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// 2. Abrir salida de archivo (php://output escribe directo al navegador)
$output = fopen('php://output', 'w');

// 3. ¡TRUCO IMPORTANTE! Agregar BOM para que Excel lea bien los acentos y Ñ
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// 4. Lógica según el tipo de reporte
if ($tipo == 'directorio') {
    // Encabezados de columnas
    fputcsv($output, ['ID', 'Direccion', 'Area', 'Nombre', 'Extension', 'Directo']);
    
    
    // Consulta
    $sql = "SELECT id, direccion, area_departamento, nombre_personal, extension, numero_directo FROM directorio_ext";
    $result = $conn->query($sql);

    while ($row = $result->fetch_assoc()) {
        fputcsv($output, $row);
    }

} elseif ($tipo == 'licencias') {
    fputcsv($output, ['ID', 'Direccion', 'Area', 'Correo']);
    
    $sql = "SELECT id, Dirección, Area, Correo, FROM cuentas_office";
    $result = $conn->query($sql);
    
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, $row);
    }

} elseif ($tipo == 'nas') {
    fputcsv($output, ['ID', 'Ubicacion', 'Carpeta', 'IP', 'Usuario', 'Observaciones']);
    $sql = "SELECT * FROM carpetas_nas";
    $result = $conn->query($sql);
    
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, $row);
    }


} elseif ($tipo == 'usuariosAD') {
    fputcsv($output, ['secretaria', 'direccion', 'nombres', 'apellido_paterno', 'apellido_materno', 'usuario', 'num_oficio']);
    $sql = "SELECT secretaria, direccion, nombres, apellido_paterno, apellido_materno, usuario, num_oficio FROM registros_ad"; 
    $result = $conn->query($sql);
    
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, $row);
    }
}

// 5. Cerrar y terminar
fclose($output);
$conn->close();
exit();
?>