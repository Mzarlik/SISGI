<?php
// diagnostico.php
require_once 'session_check.php';
require_once 'config.php';
$conn = get_db_connection();

echo "<h1>🩺 Diagnóstico de Base de Datos</h1>";
echo "<p>PHP Version: " . phpversion() . "</p>";
echo "<p>Charset conexion: " . $conn->character_set_name() . "</p>";

// 1. VER ESTRUCTURA REAL DE LA TABLA
echo "<h2>1. Columnas de 'cuentas_office'</h2>";
$sql = "SHOW COLUMNS FROM cuentas_office";
$res = $conn->query($sql);

if ($res) {
    echo "<table border='1' cellpadding='5'><tr><th>Campo</th><th>Tipo</th></tr>";
    $columnas = [];
    while($row = $res->fetch_assoc()) {
        echo "<tr><td>" . $row['Field'] . "</td><td>" . $row['Type'] . "</td></tr>";
        $columnas[] = $row['Field'];
    }
    echo "</table>";
    
    // Verificamos problemas comunes
    $pk = $columnas[0]; // Asumimos que la primera es la PK
    echo "<p><strong>Probable Llave Primaria (ID):</strong> $pk</p>";
    
    if (in_array('id_cuenta', $columnas)) echo "<p style='color:green'>✅ Existe 'id_cuenta'</p>";
    else echo "<p style='color:red'>❌ NO existe 'id_cuenta' (Por eso fallaba antes)</p>";

    if (in_array('id', $columnas)) echo "<p style='color:green'>✅ Existe 'id'</p>";
    
    // Revisamos la columna Dirección con acento y sin acento
    $tieneAcento = false;
    $tieneSinAcento = false;
    foreach($columnas as $col) {
        // Truco para detectar codificación
        if (utf8_encode(utf8_decode($col)) == "Dirección") $tieneAcento = true;
        if ($col == "Direccion") $tieneSinAcento = true;
    }
    
    if ($tieneAcento) echo "<p>⚠️ Tienes una columna llamada 'Dirección' (Con acento). Esto suele causar problemas de codificación.</p>";
    if ($tieneSinAcento) echo "<p>ℹ️ Tienes una columna llamada 'Direccion' (Sin acento).</p>";

} else {
    echo "<p style='color:red'>Error SQL: " . $conn->error . "</p>";
}

// 2. PRUEBA DE DATOS RAW
echo "<h2>2. Prueba de Datos (Primer registro)</h2>";
$sql = "SELECT * FROM cuentas_office LIMIT 1";
$res = $conn->query($sql);
if ($res && $row = $res->fetch_assoc()) {
    echo "<pre>";
    var_dump($row); // Esto nos muestra el dato crudo
    echo "</pre>";
} else {
    echo "La tabla está vacía o falló la consulta.";
}
?>