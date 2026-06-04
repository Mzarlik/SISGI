<?php
// importar_usuarios.php
require_once 'config.php';
$conn = get_db_connection();
$conn->set_charset("utf8mb4");

$archivo = "USUARIOS.csv"; // Asegúrate de que tu archivo se llame así

if (!file_exists($archivo)) {
    die("Error: Sube el archivo '$archivo' a la misma carpeta de este script (C:\\xampp\\htdocs\\SISGI\\).");
}

if (($gestor = fopen($archivo, "r")) !== FALSE) {
    // Saltar la primera fila (encabezados)
    fgetcsv($gestor, 10000, ","); 

    // Preparar INSERT
    $sql = "INSERT INTO registros_ad 
            (num_empleado, nombres, apellido_paterno, apellido_materno, cargo, id_direccion, jefe_inmediato, correo_electronico, telefono, fecha_alta) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);

    $fecha_actual = date('Y-m-d');
    $contador = 0;

    while (($datos = fgetcsv($gestor, 10000, ",")) !== FALSE) {
        $num_empleado    = trim($datos[0] ?? '');
        $nombre_completo = trim($datos[1] ?? '');
        $cargo           = trim($datos[2] ?? '');
        $area_depto      = trim($datos[3] ?? '');
        $jefe            = trim($datos[4] ?? '');
        $correo          = trim($datos[5] ?? '');
        $telefono        = trim($datos[6] ?? '');

        // 1. Dividir el nombre en Nombre(s), Apellido Paterno y Materno
        $partes = array_values(array_filter(explode(" ", $nombre_completo)));
        $total_palabras = count($partes);
        $nombres = ""; $paterno = ""; $materno = "";
        
        if ($total_palabras == 1) { $nombres = $partes[0]; }
        elseif ($total_palabras == 2) { $nombres = $partes[0]; $paterno = $partes[1]; }
        elseif ($total_palabras == 3) { $nombres = $partes[0]; $paterno = $partes[1]; $materno = $partes[2]; }
        elseif ($total_palabras >= 4) {
            $materno = array_pop($partes);
            $paterno = array_pop($partes);
            $nombres = implode(" ", $partes);
        }

        // 2. Buscar o crear el Área en cat_direcciones
        $id_direccion = 0;
        if (!empty($area_depto)) {
            $stmt_dir = $conn->prepare("SELECT id_direccion FROM cat_direcciones WHERE nombre_direccion = ?");
            $stmt_dir->bind_param("s", $area_depto);
            $stmt_dir->execute();
            $res_dir = $stmt_dir->get_result();
            if ($row = $res_dir->fetch_assoc()) {
                $id_direccion = $row['id_direccion'];
            } else {
                $id_sec_default = 1; // ID de la Secretaría SATQ por defecto
                $stmt_ins = $conn->prepare("INSERT INTO cat_direcciones (id_secretaria, nombre_direccion) VALUES (?, ?)");
                $stmt_ins->bind_param("is", $id_sec_default, $area_depto);
                $stmt_ins->execute();
                $id_direccion = $stmt_ins->insert_id;
                $stmt_ins->close();
            }
            $stmt_dir->close();
        }

        // 3. Insertar el registro final del usuario
        $stmt->bind_param("sssssissss", $num_empleado, $nombres, $paterno, $materno, $cargo, $id_direccion, $jefe, $correo, $telefono, $fecha_actual);
        $stmt->execute();
        $contador++;
    }
    fclose($gestor);
    $stmt->close();
    echo "<h1 style='color:green;'>¡Importación Exitosa!</h1><p>Se importaron <b>$contador</b> usuarios correctamente y sus áreas se vincularon de manera inteligente.</p>";
}
$conn->close();
?>