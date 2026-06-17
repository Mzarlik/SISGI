<?php
require_once 'c:/xampp/htdocs/SISGI/config.php';
$conn = get_db_connection();

echo "Input: 'MARTIN ALVAREZ JOSE ABRAHAM' -> Output: '" . obtener_nombre_jefe_formateado($conn, 'MARTIN ALVAREZ JOSE ABRAHAM') . "'\n";
echo "Input: 'AGUILAR AMBROSIO CARMEN' -> Output: '" . obtener_nombre_jefe_formateado($conn, 'AGUILAR AMBROSIO CARMEN') . "'\n";
echo "Input: 'LIC. SIRIUS SHANTAL TENORIO CARDONA' -> Output: '" . obtener_nombre_jefe_formateado($conn, 'LIC. SIRIUS SHANTAL TENORIO CARDONA') . "'\n";
echo "Input: '---' -> Output: '" . obtener_nombre_jefe_formateado($conn, '---') . "'\n";
echo "Input: '' -> Output: '" . obtener_nombre_jefe_formateado($conn, '') . "'\n";
echo "Input: 'MARTIN ALVAREZ' (No match, <3 parts) -> Output: '" . obtener_nombre_jefe_formateado($conn, 'MARTIN ALVAREZ') . "'\n";
echo "Input: 'NUEVO JEFE DESCONOCIDO' (No match, >=3 parts, fallback) -> Output: '" . obtener_nombre_jefe_formateado($conn, 'NUEVO JEFE DESCONOCIDO') . "'\n";
echo "Input: 'LIC. NUEVO JEFE DESCONOCIDO' (No match, has title, no fallback) -> Output: '" . obtener_nombre_jefe_formateado($conn, 'LIC. NUEVO JEFE DESCONOCIDO') . "'\n";

$conn->close();
?>
