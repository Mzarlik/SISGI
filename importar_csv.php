<?php
// importar_csv.php
$host = "localhost";
$user = "admin_db";
$pass = "*\$oporte24-28!*";
$db = "SISGI_db";

// Conexión independiente para la carga masiva
$conn = new mysqli($host, $user, $pass, $db);
$conn->set_charset("utf8mb4");

if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}

// Ruta a tu archivo extraído
$archivo = "SOLIDARIDAD.csv";

if (($gestor = fopen($archivo, "r")) !== FALSE) {
    // Saltamos la fila 1 (los encabezados del CSV)
    fgetcsv($gestor, 10000, ",");

    $stmt = $conn->prepare("INSERT INTO inventario_soporte 
        (estatus, municipio, num_inventario, no_bien_mueble, no_inv_anterior, 
         descripcion_corta, descripcion, marca, modelo, num_serie, color, 
         personal_asignado, area_asignacion, nombre_ubicacion, ubicacion, oficio_entrega_sefiplan) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    while (($datos = fgetcsv($gestor, 10000, ",")) !== FALSE) {
        // Limpiamos la comilla simple que pone Excel al inicio de algunos strings
        $num_inv = str_replace("'", "", $datos[2] ?? '');
        $num_serie = str_replace("'", "", $datos[10] ?? '');
        
        $estatus = $datos[0] ?? '';
        $municipio = $datos[1] ?? '';
        $no_bien = $datos[3] ?? '';
        $inv_ant = $datos[4] ?? '';
        
        // Omitimos $datos[5] porque es la fecha de adquisición (no está en tu tabla actual)
        
        $desc_corta = $datos[6] ?? '';
        $desc = $datos[7] ?? ''; // Esta es la descripción extendida
        $marca = $datos[8] ?? '';
        $modelo = $datos[9] ?? '';
        $color = $datos[11] ?? '';
        $resguardante = $datos[12] ?? '';
        $area = $datos[13] ?? '';
        $nombre_ubi = $datos[14] ?? '';
        $ubicacion = $datos[15] ?? '';
        $oficio = $datos[16] ?? '';

        $stmt->bind_param("ssssssssssssssss", 
            $estatus, $municipio, $num_inv, $no_bien, $inv_ant, 
            $desc_corta, $desc, $marca, $modelo, $num_serie, $color, 
            $resguardante, $area, $nombre_ubi, $ubicacion, $oficio
        );
        $stmt->execute();
    }
    fclose($gestor);
    $stmt->close();
    echo "¡Catálogo importado correctamente a inventario_soporte!";
} else {
    echo "Error: Asegúrate de que el archivo SOLIDARIDAD.csv esté en la misma carpeta que este script.";
}
$conn->close();
?>