<?php
require_once 'config.php';
session_start();

// 1. SEGURIDAD
if (!isset($_SESSION['usuario'])) { header("Location: index.php"); exit(); }

if ($_SESSION['rol'] === 'redes') {
    // Si es redes, lo regresamos al dashboard con un mensaje de error opcional
    header("Location: dashboard.php?error=acceso_denegado");
    exit();
}

$conn = get_db_connection();
$busqueda = isset($_GET['q']) ? trim($_GET['q']) : '';

// Arrays para resultados (Solo Equipos y AD)
$data_equipos = [];
$data_ad = [];

// Variable para depuración (solo admin)
$errores_sql = [];

if (!empty($busqueda) && $conn) {
    $term = "%" . $conn->real_escape_string($busqueda) . "%";

    // --- FUNCIÓN HELPER PARA CONSULTAS ---
    function consulta_segura($conn, $sql, $types, $params) {
        $stmt = $conn->prepare($sql);
        if (!$stmt) return ['error' => $conn->error];
        $stmt->bind_param($types, ...$params);
        if (!$stmt->execute()) return ['error' => $stmt->error];
        $res = $stmt->get_result();
        $data = $res->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return ['data' => $data];
    }

    // ---------------------------------------------------------
    // 1. EQUIPOS (Tabla 'equipobd' o 'equiposbd')
    // Columnas: secretaria, usuariosEquipo, numInventario, direccionIP
    // ---------------------------------------------------------
    $sql2 = "SELECT secretaria, usuarioDominio, usuariosEquipo, numInventario, direccionIP FROM equiposbd 
             WHERE usuariosEquipo LIKE ? OR usuarioDominio LIKE ?";
    $res2 = consulta_segura($conn, $sql2, "ss", [$term, $term]);
    
    if(isset($res2['error'])) $errores_sql['Equipos'] = $res2['error'];
    else $data_equipos = $res2['data'];

    // ---------------------------------------------------------
    // 2. REGISTROS AD (Active Directory)
    // Columnas: direccion, num_oficio, nombres, apellido_paterno, apellido_materno, fecha_alta, usuario
    // ---------------------------------------------------------
    $sql3 = "SELECT direccion, num_oficio, nombres, apellido_paterno, apellido_materno, fecha_alta, usuario 
             FROM registros_ad 
             WHERE CONCAT_WS(' ', nombres, apellido_paterno, apellido_materno) LIKE ? 
             OR usuario LIKE ?";
    $res3 = consulta_segura($conn, $sql3, "ss", [$term, $term]);
    
    if(isset($res3['error'])) $errores_sql['AD'] = $res3['error'];
    else $data_ad = $res3['data'];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Búsqueda Relacional | Sistema</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="js/tailwindcss.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'] },
                    colors: {
                        'brand-dark': '#721538',
                        'brand-bg': '#d6d1ca',
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-brand-bg min-h-screen p-4 sm:p-8">

    <div class="max-w-7xl mx-auto">
        
        <!-- HEADER Y BUSCADOR -->
        <div class="flex flex-col md:flex-row justify-between items-center gap-4 mb-8">
            <h1 class="text-3xl font-extrabold text-brand-dark flex items-center gap-3">
                <i class="fas fa-network-wired"></i> Búsqueda Relacional
            </h1>
            
            <form class="w-full md:w-1/2 flex gap-2 relative">
                <input type="text" name="q" value="<?= htmlspecialchars($busqueda) ?>" 
                       class="w-full pl-5 pr-14 py-3 rounded-full border-2 border-brand-dark/20 focus:border-brand-dark focus:outline-none shadow-lg placeholder-gray-500 text-gray-700"
                       placeholder="Buscar por Nombre, Usuario o Inventario..." autofocus>
                <button type="submit" class="absolute right-2 top-2 bg-brand-dark text-white p-2 rounded-full w-10 h-10 flex items-center justify-center hover:bg-opacity-90 transition shadow-md">
                    <i class="fas fa-search"></i>
                </button>
            </form>

            <a href="dashboard.php" class="text-gray-600 hover:text-brand-dark font-bold underline text-sm flex items-center gap-2 bg-white px-4 py-2 rounded-full shadow-sm">
                <i class="fas fa-home"></i> Dashboard
            </a>
        </div>

        <!-- MENSAJES DE ERROR SQL (Solo visible si hay fallo técnico) -->
        <?php if (!empty($errores_sql)): ?>
            <div class="bg-red-100 text-red-700 p-4 rounded-xl mb-6 border border-red-300 shadow-sm">
                <strong class="font-bold flex items-center gap-2"><i class="fas fa-exclamation-triangle"></i> Atención técnica:</strong>
                <ul class="list-disc ml-8 text-xs mt-2 font-mono">
                    <?php foreach($errores_sql as $k => $v) echo "<li><b>$k:</b> $v</li>"; ?>
                </ul>
            </div>
        <?php endif; ?>

        <!-- RESULTADOS -->
        <?php if(empty($busqueda)): ?>
            
            <div class="flex flex-col items-center justify-center py-24 opacity-40">
                <div class="bg-brand-dark/10 p-8 rounded-full mb-4">
                    <i class="fas fa-search-location text-6xl text-brand-dark"></i>
                </div>
                <p class="text-2xl font-bold text-brand-dark">Expediente 360°</p>
                <p class="text-gray-600 mt-2">Ingresa un dato para rastrear su relación (Solo AD y Equipos).</p>
            </div>

        <?php else: ?>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                
                <!-- 1. REGISTROS AD -->
                <div class="bg-white rounded-2xl shadow-lg overflow-hidden border border-gray-100">
                    <div class="bg-violet-50 px-6 py-4 border-b border-violet-100 flex justify-between items-center">
                        <h3 class="font-bold text-violet-800 flex items-center gap-2 text-lg">
                            <span class="bg-violet-200 p-1.5 rounded-lg"><i class="fab fa-windows"></i></span> 
                            Registros AD
                        </h3>
                        <span class="bg-violet-600 text-white text-xs px-2.5 py-1 rounded-full font-bold"><?= count($data_ad) ?></span>
                    </div>
                    <div class="divide-y divide-gray-100">
                        <?php if(!empty($data_ad)): foreach($data_ad as $row): ?>
                            <div class="p-5 hover:bg-violet-50/30 transition duration-150">
                                <div class="flex justify-between items-start mb-2">
                                    <p class="font-bold text-lg text-gray-800">
                                        <?= htmlspecialchars($row['nombres'] . ' ' . $row['apellido_paterno'] . ' ' . $row['apellido_materno']) ?>
                                    </p>
                                    <span class="text-xs font-mono font-bold bg-violet-100 text-violet-700 px-2 py-1 rounded border border-violet-200">
                                        <?= htmlspecialchars($row['usuario']) ?>
                                    </span>
                                </div>
                                <div class="grid grid-cols-2 gap-x-4 gap-y-2 text-sm text-gray-600 mt-3 bg-gray-50 p-3 rounded-lg">
                                    <p><span class="font-semibold text-gray-400 text-xs uppercase">Dirección</span><br> <?= htmlspecialchars($row['direccion']) ?></p>
                                    <p><span class="font-semibold text-gray-400 text-xs uppercase">Oficio</span><br> <?= htmlspecialchars($row['num_oficio']) ?></p>
                                    <p class="col-span-2"><span class="font-semibold text-gray-400 text-xs uppercase">Fecha Alta</span><br> <?= htmlspecialchars($row['fecha_alta']) ?></p>
                                </div>
                            </div>
                        <?php endforeach; else: ?>
                            <div class="p-8 text-center text-gray-400 italic bg-gray-50/50">
                                <i class="fas fa-ghost mb-2 text-xl block opacity-30"></i> No se encontró en Active Directory.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- 2. EQUIPOS DE CÓMPUTO -->
                <div class="bg-white rounded-2xl shadow-lg overflow-hidden border border-gray-100">
                    <div class="bg-cyan-50 px-6 py-4 border-b border-cyan-100 flex justify-between items-center">
                        <h3 class="font-bold text-cyan-800 flex items-center gap-2 text-lg">
                            <span class="bg-cyan-200 p-1.5 rounded-lg"><i class="fas fa-desktop"></i></span> 
                            Equipos
                        </h3>
                        <span class="bg-cyan-600 text-white text-xs px-2.5 py-1 rounded-full font-bold"><?= count($data_equipos) ?></span>
                    </div>
                    <div class="divide-y divide-gray-100">
                        <?php if(!empty($data_equipos)): foreach($data_equipos as $row): ?>
                            <div class="p-5 hover:bg-cyan-50/30 transition duration-150">
                                <div class="flex items-center gap-4 mb-4">
                                    <div class="bg-cyan-100 p-3 rounded-full text-cyan-600 text-xl"><i class="fas fa-laptop"></i></div>
                                    <div>
                                        <p class="font-bold text-gray-800">Inv: <?= htmlspecialchars($row['numInventario']) ?></p>
                                        <p class="text-xs font-mono text-gray-500 bg-gray-100 px-2 py-0.5 rounded inline-block mt-1">IP: <?= htmlspecialchars($row['direccionIP']) ?></p>
                                    </div>
                                </div>
                                <div class="text-sm bg-gray-50 p-3 rounded-lg border border-gray-100 grid grid-cols-1 gap-2">
                                    <p><span class="text-gray-400 font-semibold text-xs uppercase">Usuario dominio:</span> <br><?= htmlspecialchars($row['usuarioDominio']) ?></p>
                                    <p><span class="text-gray-400 font-semibold text-xs uppercase">Usuario:</span> <br><?= htmlspecialchars($row['usuariosEquipo']) ?></p>
                                    <p><span class="text-gray-400 font-semibold text-xs uppercase">Secretaría:</span> <br><?= htmlspecialchars($row['secretaria']) ?></p>
                                </div>
                            </div>
                        <?php endforeach; else: ?>
                            <div class="p-8 text-center text-gray-400 italic bg-gray-50/50">
                                <i class="fas fa-mouse mb-2 text-xl block opacity-30"></i> Sin equipo de cómputo registrado.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>

        <?php endif; ?>

    </div>

</body>
</html>
<?php if($conn) $conn->close(); ?>