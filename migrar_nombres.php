<?php
// migrar_nombres.php
// Script para dividir nombre_completo en nombres, paterno y materno
require_once 'session_check.php';
require_once 'config.php'; // Asegúrate de que esto conecte a tu DB
$conn = get_db_connection();

if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}

// 1. OBTENER TODOS LOS USUARIOS QUE AÚN NO TIENEN LOS DATOS DIVIDIDOS
// Asumimos que la tabla se llama 'directorio_ext' o 'usuarios'. CAMBIA ESTO por tu tabla real.
$tabla = "registros_ad"; 
$sql = "SELECT id, nombres_completo FROM $tabla WHERE nombres IS NULL OR nombres = ''";
$result = $conn->query($sql);

echo "<h1>Procesando Nombres...</h1>";
echo "<p>Total de registros a procesar: " . $result->num_rows . "</p><hr>";

$contador = 0;

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $id = $row['id'];
        $nombreCompleto = trim($row['nombres_completo']); // Limpiamos espacios extra
        
        // --- LÓGICA DE DIVISIÓN ---
        $partes = explode(" ", $nombreCompleto);
        $total_palabras = count($partes);

        $nombres = "";
        $paterno = "";
        $materno = "";

        if ($total_palabras == 1) {
            // Caso: "Juan" (Sin apellidos)
            $nombres = $partes[0];
        
        } elseif ($total_palabras == 2) {
            // Caso: "Juan Perez" (Asumimos Nombre + Paterno)
            $nombres = $partes[0];
            $paterno = $partes[1];
        
        } elseif ($total_palabras == 3) {
            // Caso: "Juan Perez Lopez"
            $nombres = $partes[0];
            $paterno = $partes[1];
            $materno = $partes[2];
        
        } elseif ($total_palabras == 4) {
            // Caso: "Juan Carlos Perez Lopez"
            // Por estándar en LATAM, las últimas 2 suelen ser apellidos
            $nombres = $partes[0] . " " . $partes[1];
            $paterno = $partes[2];
            $materno = $partes[3];
        
        } else {
            // Caso complejo: "Maria del Carmen de la Cruz Lopez" (Más de 4 palabras)
            // Lógica: Todo es nombre excepto las últimas 2 palabras
            // OJO: Esto puede fallar con apellidos compuestos como "De la Cruz", requiere revisión manual.
            $materno = array_pop($partes); // Último elemento
            $paterno = array_pop($partes); // Penúltimo elemento
            $nombres = implode(" ", $partes); // Lo que sobra es el nombre
        }

        // --- ACTUALIZACIÓN EN BASE DE DATOS ---
        $updateSql = "UPDATE $tabla SET 
                      nombres = ?, 
                      apellido_paterno = ?, 
                      apellido_materno = ? 
                      WHERE id = ?";
        
        $stmt = $conn->prepare($updateSql);
        $stmt->bind_param("sssi", $nombres, $paterno, $materno, $id);
        
        if ($stmt->execute()) {
            echo "<div style='color:green; font-family:monospace;'>";
            echo "✅ ID $id: '$nombreCompleto' -> N:[$nombres] P:[$paterno] M:[$materno]";
            echo "</div>";
            $contador++;
        } else {
            echo "<div style='color:red;'>❌ Error ID $id: " . $conn->error . "</div>";
        }
        $stmt->close();
    }
} else {
    echo "No hay registros pendientes de migrar.";
}

echo "<hr><h3>Proceso terminado. $contador registros actualizados.</h3>";
echo "<p>⚠️ Por favor revisa manualmente los casos especiales (apellidos como 'De la Cruz').</p>";

$conn->close();
?>