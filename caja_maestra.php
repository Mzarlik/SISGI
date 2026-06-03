<?php
// Configuración de DB rápida
require_once 'session_check.php';
$DB_HOST = "localhost";
$DB_USER = "root";
$DB_PASS = 'Mallr0093$'; // <--- OJO: Comillas simples por el signo de pesos
$DB_NAME = "mi_basedatos";

date_default_timezone_set('America/Cancun'); // ¡Excelente detalle la zona horaria!

function get_db_connection() {
    global $DB_HOST, $DB_USER, $DB_PASS, $DB_NAME;
    
    // El @ oculta errores feos de PHP para manejarlos nosotros
    $conn = @new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
    
    if ($conn->connect_errno) {
        // Guardamos el error real en el log del servidor (no se muestra al usuario)
        error_log("Error fatal de conexión DB: " . $conn->connect_error);
        return null; 
    }
    
    $conn->set_charset("utf8mb4");
    return $conn;
}

// --- PROCESAR FORMULARIOS ---

// 1. Registrar Aportación
if (isset($_POST['btn_aportar'])) {
    $socio = $_POST['socio_id'];
    $monto = $_POST['monto'];
    $fecha = $_POST['fecha'];
    $conn->query("INSERT INTO movimientos (socio_id, tipo, monto, fecha) VALUES ($socio, 'aportacion', $monto, '$fecha')");
    header("Location: caja_maestra.php"); exit;
}

// 2. Registrar Ganancia (Dinero extra para la caja)
if (isset($_POST['btn_ganancia'])) {
    $monto = $_POST['monto'];
    $concepto = $_POST['concepto'];
    $fecha = $_POST['fecha'];
    $conn->query("INSERT INTO ganancias_caja (monto, concepto, fecha) VALUES ($monto, '$concepto', '$fecha')");
    header("Location: caja_maestra.php"); exit;
}

// 3. Registrar Nuevo Socio
if (isset($_POST['btn_socio'])) {
    $nombre = $_POST['nombre'];
    $conn->query("INSERT INTO socios (nombre) VALUES ('$nombre')");
    header("Location: caja_maestra.php"); exit;
}

// --- CÁLCULOS MATEMÁTICOS ---

// A. Total Capital Ahorrado (Suma de aportaciones de todos)
$sql = "SELECT SUM(monto) as total FROM movimientos WHERE tipo='aportacion'";
$total_capital = $conn->query($sql)->fetch_assoc()['total'] ?? 0;

// B. Total Ganancias Generadas (Intereses cobrados + Multas)
$sql = "SELECT SUM(monto) as total FROM ganancias_caja";
$total_rendimientos = $conn->query($sql)->fetch_assoc()['total'] ?? 0;

// C. Dinero Total en Caja Física
$dinero_en_caja = $total_capital + $total_rendimientos;

// --- OBTENER SOCIOS Y SUS TOTALES INDIVIDUALES ---
$socios_data = [];
$res = $conn->query("SELECT * FROM socios");
while($row = $res->fetch_assoc()) {
    // Cuánto ha ahorrado este socio
    $id = $row['id'];
    $q = $conn->query("SELECT SUM(monto) as total FROM movimientos WHERE socio_id=$id AND tipo='aportacion'");
    $mi_ahorro = $q->fetch_assoc()['total'] ?? 0;
    
    // CÁLCULO DE LA DISTRIBUCIÓN
    // 1. ¿Qué porcentaje del pastel representa mi ahorro?
    $mi_porcentaje = ($total_capital > 0) ? ($mi_ahorro / $total_capital) : 0;
    
    // 2. ¿Cuánto me toca de las ganancias según mi porcentaje?
    $mi_ganancia = $total_rendimientos * $mi_porcentaje;
    
    // 3. ¿Cuánto me llevo si retiro todo hoy?
    $mi_total_retiro = $mi_ahorro + $mi_ganancia;

    $row['ahorro'] = $mi_ahorro;
    $row['share'] = $mi_porcentaje * 100; // Para mostrar %
    $row['ganancia_proyectada'] = $mi_ganancia;
    $row['total_final'] = $mi_total_retiro;
    
    $socios_data[] = $row;
}
include 'header.php';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Caja de Ahorro Familiar</title>
    <script src="js/tailwindcss.js"></script>
    <link rel="stylesheet" href="css/all.min.css">
</head>
<body class="bg-gray-100 p-6 font-sans">

    <div class="max-w-6xl mx-auto">
        
        <!-- ENCABEZADO RESUMEN -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-white p-6 rounded-xl shadow border-l-4 border-blue-500">
                <p class="text-gray-500 text-sm font-bold uppercase">Capital Ahorrado</p>
                <h2 class="text-3xl font-bold text-blue-600">$<?= number_format($total_capital, 2) ?></h2>
            </div>
            <div class="bg-white p-6 rounded-xl shadow border-l-4 border-green-500">
                <p class="text-gray-500 text-sm font-bold uppercase">Rendimientos (Intereses)</p>
                <h2 class="text-3xl font-bold text-green-600">$<?= number_format($total_rendimientos, 2) ?></h2>
            </div>
            <div class="bg-white p-6 rounded-xl shadow border-l-4 border-purple-600">
                <p class="text-gray-500 text-sm font-bold uppercase">Dinero Total en Caja</p>
                <h2 class="text-3xl font-bold text-purple-700">$<?= number_format($dinero_en_caja, 2) ?></h2>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            
            <!-- COLUMNA IZQ: ACCIONES -->
            <div class="space-y-6">
                
                <!-- Formulario Aportación -->
                <div class="bg-white p-6 rounded-xl shadow">
                    <h3 class="font-bold text-lg mb-4 text-gray-700"><i class="fas fa-piggy-bank"></i> Nueva Aportación</h3>
                    <form method="POST">
                        <select name="socio_id" class="w-full p-2 border rounded mb-3 bg-gray-50" required>
                            <option value="">Selecciona Socio</option>
                            <?php foreach($socios_data as $s): ?>
                                <option value="<?= $s['id'] ?>"><?= $s['nombre'] ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="number" step="0.01" name="monto" placeholder="Monto ($)" class="w-full p-2 border rounded mb-3" required>
                        <input type="date" name="fecha" value="<?= date('Y-m-d') ?>" class="w-full p-2 border rounded mb-3" required>
                        <button type="submit" name="btn_aportar" class="w-full bg-blue-600 text-white p-2 rounded hover:bg-blue-700 font-bold">Guardar Aportación</button>
                    </form>
                </div>

                <!-- Formulario Ganancias (Intereses) -->
                <div class="bg-white p-6 rounded-xl shadow">
                    <h3 class="font-bold text-lg mb-4 text-gray-700"><i class="fas fa-chart-line"></i> Registrar Rendimiento</h3>
                    <p class="text-xs text-gray-500 mb-3">Ingresa aquí intereses cobrados por préstamos o multas.</p>
                    <form method="POST">
                        <input type="text" name="concepto" placeholder="Ej: Interés Préstamo Juan" class="w-full p-2 border rounded mb-3" required>
                        <input type="number" step="0.01" name="monto" placeholder="Monto Ganado ($)" class="w-full p-2 border rounded mb-3" required>
                        <input type="date" name="fecha" value="<?= date('Y-m-d') ?>" class="w-full p-2 border rounded mb-3" required>
                        <button type="submit" name="btn_ganancia" class="w-full bg-green-600 text-white p-2 rounded hover:bg-green-700 font-bold">Registrar Ganancia</button>
                    </form>
                </div>

                <!-- Formulario Nuevo Socio -->
                <div class="bg-white p-6 rounded-xl shadow">
                    <h3 class="font-bold text-lg mb-4 text-gray-700"><i class="fas fa-user-plus"></i> Nuevo Socio</h3>
                    <form method="POST" class="flex gap-2">
                        <input type="text" name="nombre" placeholder="Nombre" class="w-full p-2 border rounded" required>
                        <button type="submit" name="btn_socio" class="bg-gray-800 text-white p-2 rounded hover:bg-gray-900">Crear</button>
                    </form>
                </div>

            </div>

            <!-- COLUMNA DER: TABLA DE DISTRIBUCIÓN -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-xl shadow overflow-hidden">
                    <div class="bg-gray-800 text-white p-4 flex justify-between items-center">
                        <h3 class="font-bold"><i class="fas fa-users"></i> Estado de Cuenta Global</h3>
                        <span class="text-xs bg-gray-600 px-2 py-1 rounded"><?= count($socios_data) ?> Socios</span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm text-left">
                            <thead class="bg-gray-100 text-gray-600 uppercase font-bold">
                                <tr>
                                    <th class="p-3">Socio</th>
                                    <th class="p-3 text-right">Ahorro Total</th>
                                    <th class="p-3 text-center">% Part.</th>
                                    <th class="p-3 text-right text-green-600">Rédito (Ganancia)</th>
                                    <th class="p-3 text-right font-black">Total a Retirar</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php foreach($socios_data as $s): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="p-3 font-medium"><?= htmlspecialchars($s['nombre']) ?></td>
                                    <td class="p-3 text-right font-mono">$<?= number_format($s['ahorro'], 2) ?></td>
                                    <td class="p-3 text-center">
                                        <span class="bg-blue-100 text-blue-800 text-xs font-bold px-2 py-1 rounded">
                                            <?= number_format($s['share'], 1) ?>%
                                        </span>
                                    </td>
                                    <td class="p-3 text-right font-mono text-green-600 font-bold">
                                        + $<?= number_format($s['ganancia_proyectada'], 2) ?>
                                    </td>
                                    <td class="p-3 text-right font-mono font-black text-gray-800 bg-gray-50">
                                        $<?= number_format($s['total_final'], 2) ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="p-4 bg-yellow-50 text-yellow-800 text-xs border-t border-yellow-100">
                        <i class="fas fa-info-circle"></i> <strong>Nota:</strong> El "Rédito" se calcula proporcionalmente. Si alguien aporta más dinero hoy, su porcentaje sube y automáticamente le toca más parte de las ganancias acumuladas.
                    </div>
                </div>
            </div>

        </div>

    </div>

</body>
</html>