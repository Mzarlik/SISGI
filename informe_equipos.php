<?php
// informe_equipos.php
require_once 'session_check.php';
require_once 'config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Seguridad
if (!isset($_SESSION['usuario'])) {
    header("Location: index.php");
    exit();
}

// 2. Conexión
$conn = get_db_connection();
if (!$conn) { die("Conexión fallida."); }

// --- AUXILIAR: PARSEAR HARDWARE ---
function analizarDescripcionHardware($descripcion, $marca = '', $modelo = '') {
    $desc = mb_strtoupper($descripcion);
    $full = $desc . " | " . mb_strtoupper($marca) . " | " . mb_strtoupper($modelo);
    
    // 1. Procesador
    $procesador = "Intel Core i5"; // Default razonable
    if (stripos($full, 'Celeron') !== false) {
        $procesador = "Intel Celeron";
    } elseif (stripos($full, 'Pentium') !== false) {
        $procesador = "Intel Pentium";
    } elseif (stripos($full, 'Atom') !== false) {
        $procesador = "Intel Atom";
    } elseif (stripos($full, 'Dual Core') !== false || stripos($full, 'Core 2') !== false || stripos($full, 'Core2') !== false) {
        $procesador = "Intel Dual Core";
    } elseif (stripos($full, 'i3') !== false) {
        $procesador = "Intel Core i3";
    } elseif (stripos($full, 'i7') !== false) {
        $procesador = "Intel Core i7";
    } elseif (stripos($full, 'Ryzen') !== false) {
        $procesador = "AMD Ryzen";
    }
    
    // 2. RAM
    $ram = "8 GB"; // Default
    if (preg_match('/(\d+)\s*(GB|MB|GM|G)/i', $desc, $matches)) {
        $val = intval($matches[1]);
        $unit = strtoupper($matches[2]);
        if ($val > 0) {
            if ($unit === 'MB') {
                $ram = $val . " MB";
            } else {
                $ram = $val . " GB";
            }
        }
    }
    
    // 3. Disco
    $disco = "HDD 500GB"; // Default
    if (stripos($full, 'SSD') !== false || stripos($full, 'M.2') !== false || stripos($full, 'SOLIDO') !== false || stripos($full, 'SÓLIDO') !== false) {
        $disco = "SSD 240GB";
    } elseif (preg_match('/(\d+)\s*(GB|TB)\s*(DISCO|HDD|MECANICO)/i', $desc, $matches)) {
        $disco = "HDD " . $matches[1] . $matches[2];
    }
    
    // 4. Sistema Operativo
    $so = "Windows 10 Pro"; // Default
    if (stripos($full, 'Windows 11') !== false || stripos($full, 'Win 11') !== false || stripos($full, 'Win11') !== false) {
        $so = "Windows 11 Pro";
    } elseif (stripos($full, 'Windows 7') !== false || stripos($full, 'Win 7') !== false || stripos($full, 'Win7') !== false || stripos($full, 'WUINDOS 7') !== false) {
        $so = "Windows 7 Pro";
    } elseif (stripos($full, 'Windows CE') !== false) {
        $so = "Windows CE";
    }
    
    return [
        'procesador' => $procesador,
        'ram' => $ram,
        'tipodisco_capa' => $disco,
        'sistemaOperativo' => $so
    ];
}

// --- FILTROS POR SECRETARÍA Y DIRECCIÓN ---
$filtroSecretaria = isset($_GET['secretaria']) ? $_GET['secretaria'] : '';
$filtroDireccion = isset($_GET['direccion']) ? $_GET['direccion'] : '';

$whereClauses = [];
$furniture_types = "'Silla', 'Escritorio', 'Mueble', 'Archivero', 'Silla de oficina', 'Escritorio de oficina'";
$whereClauses[] = "tbi.nombre_tipo NOT IN ($furniture_types)";
$whereClauses[] = "tbi.nombre_tipo NOT LIKE '%Impresora%'";

if (!empty($filtroSecretaria)) {
    $sec = $conn->real_escape_string($filtroSecretaria);
    $whereClauses[] = "(s.nombres = '$sec' OR (s.nombres IS NULL AND '$sec' = 'SATQ'))";
}

if (!empty($filtroDireccion)) {
    $dir = $conn->real_escape_string($filtroDireccion);
    $whereClauses[] = "(d.nombre_direccion = '$dir' OR (d.nombre_direccion IS NULL AND inv.nombre_ubicacion = '$dir'))";
}

$whereSQL = "WHERE " . implode(' AND ', $whereClauses);

// Obtener lista de Secretarías
$sqlSec = "SELECT DISTINCT nombres as secretaria FROM Secretarias ORDER BY nombres ASC";
$resSec = $conn->query($sqlSec);

// Obtener lista de Direcciones (Dependiente de la secretaría seleccionada)
if (!empty($filtroSecretaria)) {
    $sec = $conn->real_escape_string($filtroSecretaria);
    $sqlDir = "SELECT DISTINCT d.nombre_direccion as direccion 
               FROM cat_direcciones d 
               JOIN Secretarias s ON d.id_secretaria = s.id_secretaria 
               WHERE s.nombres = '$sec' 
               ORDER BY d.nombre_direccion ASC";
} else {
    $sqlDir = "SELECT DISTINCT nombre_direccion as direccion FROM cat_direcciones ORDER BY nombre_direccion ASC";
}
$resDir = $conn->query($sqlDir);

// 3. Obtener equipos de la tabla activa de inventario
$sql = "SELECT inv.id, inv.num_inventario as numInventario, tbi.nombre_tipo as tipoequipo, 
               CONCAT(COALESCE(inv.marca,''), ' ', COALESCE(inv.modelo,'')) as marca_modelo,
               inv.personal_asignado as usuariosEquipo, 
               COALESCE(d.nombre_direccion, inv.nombre_ubicacion) as direccion, 
               inv.estatus as estatus_equipo, inv.descripcion as observaciones,
               COALESCE(s.nombres, 'SATQ') as secretaria,
               inv.descripcion, inv.marca, inv.modelo
        FROM inventario_soporte inv
        LEFT JOIN tipo_bien_inventario tbi ON inv.id_tipo_bien = tbi.id_tipo
        LEFT JOIN registros_ad r ON TRIM(REPLACE(CONCAT(r.nombres, ' ', COALESCE(r.apellido_paterno,''), ' ', COALESCE(r.apellido_materno,'')), '  ', ' ')) = inv.personal_asignado
        LEFT JOIN cat_direcciones d ON r.id_direccion = d.id_direccion
        LEFT JOIN Secretarias s ON d.id_secretaria = s.id_secretaria
        $whereSQL 
        ORDER BY direccion ASC";
$result = $conn->query($sql);

$totalEquipos = 0;
$equiposCriticos = [];    // Celeron, Pentium, etc. o Dañados
$equiposCandidatos = [];  // i3/i5/i7 que les falta RAM, SSD o Win11
$equiposOptimos = [];     // i3/i5/i7 Full (SSD + RAM + Win11)

// 4. LÓGICA DE ANÁLISIS MEJORADA
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $totalEquipos++;
        
        $hardware = analizarDescripcionHardware($row['descripcion'], $row['marca'] ?? '', $row['modelo'] ?? '');
        
        $ramRaw = $hardware['ram']; 
        $discoRaw = $hardware['tipodisco_capa'];
        $procRaw = $hardware['procesador'];
        $osRaw = $hardware['sistemaOperativo'];
        $estatusRaw = $row['estatus_equipo'] ?? ''; // Obtenemos el estatus

        $ramGB = intval($ramRaw); 

        $esSSD = (stripos($discoRaw, 'SSD') !== false || stripos($discoRaw, 'M.2') !== false || stripos($discoRaw, 'Solid') !== false);
        $esHDD = !$esSSD;
        $tieneWin11 = (stripos($osRaw, '11') !== false);

        // Detectar Procesadores Obsoletos
        $esObsoleto = (stripos($procRaw, 'Celeron') !== false || 
                       stripos($procRaw, 'Pentium') !== false || 
                       stripos($procRaw, 'Atom') !== false || 
                       stripos($procRaw, 'Duo') !== false);

        // Detectar si el equipo está Dañado o de Baja
        $esDanado = (stripos($estatusRaw, 'Dañado') !== false || stripos($estatusRaw, 'Baja') !== false || stripos($estatusRaw, 'Para Baja') !== false);

        // Mapear los datos de hardware al array de la fila para que las vistas no se rompan
        $row['ram'] = $ramRaw;
        $row['tipodisco_capa'] = $discoRaw;
        $row['procesador'] = $procRaw;
        $row['sistemaOperativo'] = $osRaw;

        // --- CLASIFICACIÓN ---

        // CASO 1: ROJA - EQUIPOS CRÍTICOS (OBSOLETOS O DAÑADOS)
        if ($esDanado) {
            $row['motivo_corto'] = "Equipo Dañado / Baja";
            $equiposCriticos[] = $row;
        }
        elseif ($esObsoleto) {
            $row['motivo_corto'] = "Procesador Limitado";
            $equiposCriticos[] = $row;
        }
        // CASO 2: VERDE - ÓPTIMOS
        elseif ($esSSD && $ramGB >= 8 && $tieneWin11) {
            $equiposOptimos[] = $row;
        }
        // CASO 3: AZUL - CANDIDATOS (UPGRADEABLES)
        else {
            $acciones = [];
            if ($esHDD) $acciones[] = "Cambiar HDD a SSD";
            
            if ($ramGB < 8) {
                $acciones[] = "Requiere de un aumento de memoria RAM (Actual: $ramGB GB)";
            }
            
            if (!$tieneWin11) $acciones[] = "Instalar Win 11";

            $row['acciones_necesarias'] = implode('<br>', $acciones); 
            $row['acciones_print'] = implode(', ', $acciones); 
            
            $equiposCandidatos[] = $row;
        }
    }
}
include 'header.php';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Informe de Rendimiento</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="js/tailwindcss.js"></script>
    <link rel="stylesheet" href="css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'primary-dark': '#721538',
                        'primary-light': '#961e4b',
                        'background': '#d6d1ca',
                    }
                }
            }
        }
    </script>
    <style>
        @media (max-width: 768px) {
            thead { display: none; }
            table, tbody, tr, td { display: block; width: 100%; }
            tr { 
                background: white; 
                border-radius: 10px; 
                margin-bottom: 15px; 
                box-shadow: 0 2px 8px rgba(0,0,0,0.08); 
                padding: 15px; 
                position: relative;
            }
            tr.card-red { border-left: 5px solid #dc2626; }   
            tr.card-blue { border-left: 5px solid #2563eb; }  
            tr.card-green { border-left: 5px solid #16a34a; } 
            td { 
                padding: 10px 0; 
                border-bottom: 1px solid #f0f0f0; 
                display: flex; 
                justify-content: space-between; 
                align-items: center; 
                text-align: right; 
                gap: 15px; 
            }
            td:last-child { border-bottom: none; justify-content: center; }
            td:before { 
                content: attr(data-label); 
                position: static; 
                font-weight: bold; 
                color: #555; 
                text-transform: uppercase; 
                font-size: 0.75em; 
                text-align: left;
                min-width: 30%; 
                flex-shrink: 0; 
            }
            td[data-label="Acción Requerida"] span { margin-bottom: 4px; }
        }
    </style>
</head>
<body class="bg-background min-h-screen p-4 sm:p-6 font-sans">

    <div class="max-w-[1800px] mx-auto">
        
        <div class="flex flex-col md:flex-row justify-between items-center mb-8 bg-white p-6 rounded-xl shadow-lg gap-4">
            <div>
                <h1 class="text-2xl md:text-3xl font-bold text-primary-dark"><i class="fas fa-chart-line mr-2"></i> Análisis de Infraestructura</h1>
                <p class="text-gray-500 text-sm md:text-base">Diagnóstico de actualización a Windows 11 Pro</p>
            </div>
            
            <form action="" method="GET" class="flex flex-col md:flex-row items-center gap-2 w-full md:w-auto">
                <div class="relative w-full md:w-64">
                    <select name="secretaria" class="w-full p-2 border border-gray-300 rounded-lg text-sm appearance-none cursor-pointer bg-gray-50 hover:bg-white transition" onchange="document.getElementById('dir_select').value=''; this.form.submit()">
                        <option value="">-- Ver Todas las Secretarías --</option>
                        <?php while($s = $resSec->fetch_assoc()): ?>
                            <option value="<?= htmlspecialchars($s['secretaria']) ?>" <?= ($filtroSecretaria == $s['secretaria']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($s['secretaria']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                    <div class="absolute right-3 top-2.5 pointer-events-none text-gray-500"><i class="fas fa-chevron-down"></i></div>
                </div>

                <div class="relative w-full md:w-64">
                    <select name="direccion" id="dir_select" class="w-full p-2 border border-gray-300 rounded-lg text-sm appearance-none cursor-pointer bg-gray-50 hover:bg-white transition" onchange="this.form.submit()">
                        <option value="">-- Ver Todas las Direcciones --</option>
                        <?php while($d = $resDir->fetch_assoc()): ?>
                            <option value="<?= htmlspecialchars($d['direccion']) ?>" <?= ($filtroDireccion == $d['direccion']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($d['direccion']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                    <div class="absolute right-3 top-2.5 pointer-events-none text-gray-500"><i class="fas fa-chevron-down"></i></div>
                </div>
                
                <?php if(!empty($filtroSecretaria) || !empty($filtroDireccion)): ?>
                    <a href="informe_equipos.php" class="text-gray-400 hover:text-red-500 transition ml-1" title="Limpiar Filtros">
                        <i class="fas fa-times-circle text-lg"></i>
                    </a>
                <?php endif; ?>
            </form>

            <a href="consultar_equipos.php" class="bg-gray-600 hover:bg-gray-700 text-white px-5 py-2 rounded-lg transition text-sm w-full md:w-auto text-center">
                <i class="fas fa-arrow-left mr-2"></i> Volver
            </a>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white p-6 rounded-xl shadow border-l-4 border-gray-500">
                <div class="flex justify-between items-start">
                    <div><p class="text-sm text-gray-500 uppercase font-semibold">Total Equipos</p><h3 class="text-3xl font-bold text-gray-800"><?= $totalEquipos ?></h3></div>
                    <div class="p-3 bg-gray-100 rounded-full text-gray-600"><i class="fas fa-desktop"></i></div>
                </div>
            </div>
            <div class="bg-white p-6 rounded-xl shadow border-l-4 border-red-500">
                <div class="flex justify-between items-start">
                    <div><p class="text-sm text-red-500 uppercase font-semibold">Obsoletos / Dañados</p><h3 class="text-3xl font-bold text-gray-800"><?= count($equiposCriticos) ?></h3></div>
                    <div class="p-3 bg-red-100 rounded-full text-red-500"><i class="fas fa-ban"></i></div>
                </div>
            </div>
            <div class="bg-white p-6 rounded-xl shadow border-l-4 border-blue-500">
                <div class="flex justify-between items-start">
                    <div><p class="text-sm text-blue-500 uppercase font-semibold">Candidatos a Mejora</p><h3 class="text-3xl font-bold text-gray-800"><?= count($equiposCandidatos) ?></h3></div>
                    <div class="p-3 bg-blue-100 rounded-full text-blue-500"><i class="fas fa-tools"></i></div>
                </div>
            </div>
            <div class="bg-white p-6 rounded-xl shadow border-l-4 border-green-500">
                <div class="flex justify-between items-start">
                    <div><p class="text-sm text-green-500 uppercase font-semibold">Óptimos</p><h3 class="text-3xl font-bold text-gray-800"><?= count($equiposOptimos) ?></h3></div>
                    <div class="p-3 bg-green-100 rounded-full text-green-600"><i class="fas fa-check-circle"></i></div>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-lg overflow-hidden border border-red-100 mb-10">
            <div class="bg-red-600 px-6 py-4 border-b border-red-700 flex justify-between items-center flex-wrap gap-2">
                <div class="flex items-center">
                    <h3 class="text-lg font-bold text-white"><i class="fas fa-ban mr-2"></i> Equipos Obsoletos / Dañados</h3>
                    <span class="ml-2 bg-red-800 text-white text-xs px-3 py-1 rounded-full"><?= count($equiposCriticos) ?></span>
                </div>
                <?php if(count($equiposCriticos) > 0): ?>
                <a href="imprimir_informe.php?tipo=criticos&secretaria=<?= urlencode($filtroSecretaria) ?>&direccion=<?= urlencode($filtroDireccion) ?>" target="_blank" class="bg-white text-red-600 hover:bg-gray-100 px-4 py-2 rounded-lg text-sm font-bold transition shadow-sm border border-red-200">
                    <i class="fas fa-print mr-2"></i> Imprimir Reporte
                </a>
                <?php endif; ?>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-gray-600">
                    <thead class="bg-red-50 text-xs uppercase font-semibold text-red-800">
                        <tr>
                            <th class="p-4">Inventario</th>
                            <th class="p-4">Equipo</th>
                            <th class="p-4">Ubicación / Usuario</th>
                            <th class="p-4">Hardware Actual</th>
                            <th class="p-4">Motivo</th>
                            <th class="p-4 text-center">Recomendación</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-red-100">
                        <?php if (count($equiposCriticos) > 0): foreach($equiposCriticos as $eq): ?>
                        <tr class="hover:bg-red-50 transition card-red">
                            <td data-label="Inventario" class="p-4 font-mono font-bold text-red-700">
                                <div class="w-full text-right"><?= $eq['numInventario'] ?></div>
                            </td>
                            
                            <td data-label="Equipo" class="p-4">
                                <div class="w-full text-right">
                                    <div class="font-bold text-gray-800"><?= $eq['tipoequipo'] ?></div>
                                    <div class="text-xs text-gray-500"><?= $eq['marca_modelo'] ?></div>
                                </div>
                            </td>

                            <td data-label="Ubicación" class="p-4">
                                <div class="w-full text-right">
                                    <div class="font-bold text-gray-800"><?= $eq['direccion'] ?></div>
                                    <div class="text-gray-500 text-xs"><?= $eq['usuariosEquipo'] ?></div>
                                </div>
                            </td>
                            
                            <td data-label="Hardware" class="p-4 text-xs">
                                <div class="w-full text-right">
                                    <div class="font-bold text-red-600"><?= $eq['procesador'] ?></div>
                                    <div>RAM: <?= $eq['ram'] ?> | Disco: <?= $eq['tipodisco_capa'] ?></div>
                                </div>
                            </td>
                            
                            <td data-label="Motivo" class="p-4 text-xs font-bold text-red-700">
                                <div class="w-full text-right">
                                    <span class="bg-red-50 p-2 rounded inline-block"><?= htmlspecialchars($eq['motivo_corto']) ?></span>
                                </div>
                            </td>
                            
                            <td data-label="Acción" class="p-4 text-center">
                                <div class="w-full flex justify-center md:justify-end lg:justify-center">
                                    <span class="text-xs font-bold text-white bg-red-500 px-3 py-1 rounded">Evaluar Reemplazo / Baja</span>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; else: ?>
                            <tr><td colspan="6" class="p-8 text-center text-gray-400">No hay equipos obsoletos o dañados encontrados.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-lg overflow-hidden border border-blue-100 mb-10">
            <div class="bg-blue-600 px-6 py-4 border-b border-blue-700 flex justify-between items-center flex-wrap gap-2">
                <div class="flex items-center">
                    <h3 class="text-lg font-bold text-white"><i class="fas fa-tools mr-2"></i> Candidatos para Actualizar</h3>
                    <span class="ml-2 bg-blue-800 text-white text-xs px-3 py-1 rounded-full"><?= count($equiposCandidatos) ?></span>
                </div>
                <?php if(count($equiposCandidatos) > 0): ?>
                <a href="imprimir_informe.php?tipo=candidatos&secretaria=<?= urlencode($filtroSecretaria) ?>&direccion=<?= urlencode($filtroDireccion) ?>" target="_blank" class="bg-white text-blue-600 hover:bg-gray-100 px-4 py-2 rounded-lg text-sm font-bold transition shadow-sm border border-blue-200">
                    <i class="fas fa-print mr-2"></i> Imprimir Reporte
                </a>
                <?php endif; ?>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-gray-600">
                    <thead class="bg-blue-50 text-xs uppercase font-semibold">
                        <tr>
                            <th class="p-4">Inventario</th>
                            <th class="p-4">Equipo</th>
                            <th class="p-4">Ubicación / Usuario</th>
                            <th class="p-4">Hardware Actual</th>
                            <th class="p-4 text-blue-800 bg-blue-100">Acción Requerida (Diagnóstico)</th>
                            <th class="p-4 text-center">Estatus</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php if (count($equiposCandidatos) > 0): foreach($equiposCandidatos as $eq): ?>
                        <tr class="hover:bg-blue-50 transition card-blue">
                            <td data-label="Inventario" class="p-4 font-mono font-bold text-blue-700">
                                <div class="w-full text-right"><?= $eq['numInventario'] ?></div>
                            </td>
                            
                            <td data-label="Equipo" class="p-4">
                                <div class="w-full text-right">
                                    <div class="font-bold text-gray-800"><?= $eq['tipoequipo'] ?></div>
                                    <div class="text-xs text-gray-500"><?= $eq['marca_modelo'] ?></div>
                                </div>
                            </td>

                            <td data-label="Ubicación" class="p-4">
                                <div class="w-full text-right">
                                    <div class="font-bold text-gray-800"><?= $eq['direccion'] ?></div>
                                    <div class="text-xs text-gray-500"><?= $eq['usuariosEquipo'] ?></div>
                                </div>
                            </td>
                            
                            <td data-label="Hardware" class="p-4 text-xs">
                                <div class="w-full text-right">
                                    <div class="font-bold text-gray-700"><?= $eq['procesador'] ?></div>
                                    <div><?= $eq['ram'] ?> | <?= $eq['tipodisco_capa'] ?></div>
                                    <div class="text-gray-400 italic">SO: <?= $eq['sistemaOperativo'] ?></div>
                                </div>
                            </td>
                            
                            <td data-label="Acción Requerida" class="p-4">
                                <div class="flex flex-col gap-1 items-end w-full">
                                    <?php foreach(explode('<br>', $eq['acciones_necesarias']) as $acc): ?>
                                        <span class="bg-yellow-100 text-yellow-800 px-2 py-1 rounded text-xs font-bold border border-yellow-200">
                                            <i class="fas fa-exclamation-circle mr-1"></i> <?= $acc ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            </td>

                            <td data-label="Estatus" class="p-4 text-center">
                                <div class="w-full flex justify-center md:justify-end lg:justify-center">
                                    <span class="bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded border border-blue-200">Programar Upgrade</span>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; else: ?>
                            <tr><td colspan="6" class="p-8 text-center text-gray-400">No hay equipos candidatos encontrados.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <div class="bg-white rounded-xl shadow-lg overflow-hidden border border-green-100 mb-10">
            <div class="bg-green-600 px-6 py-4 border-b border-green-700 flex justify-between items-center">
                <h3 class="text-lg font-bold text-white"><i class="fas fa-check-circle mr-2"></i> Equipos Óptimos / Listos</h3>
                <span class="bg-green-800 text-white text-xs px-3 py-1 rounded-full"><?= count($equiposOptimos) ?></span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-gray-600">
                    <thead class="bg-green-50 text-xs uppercase font-semibold text-green-800">
                        <tr>
                            <th class="p-4">Inventario</th>
                            <th class="p-4">Equipo</th>
                            <th class="p-4">Dirección / Usuario</th>
                            <th class="p-4">Hardware Validado</th>
                            <th class="p-4">S.O.</th>
                            <th class="p-4 text-center">Estado</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-green-100">
                        <?php if (count($equiposOptimos) > 0): foreach($equiposOptimos as $eq): ?>
                        <tr class="hover:bg-green-50 transition card-green">
                            <td data-label="Inventario" class="p-4 font-mono font-bold text-green-700">
                                <div class="w-full text-right"><?= $eq['numInventario'] ?></div>
                            </td>
                            
                            <td data-label="Equipo" class="p-4">
                                <div class="w-full text-right">
                                    <div class="font-bold text-gray-800"><?= $eq['tipoequipo'] ?></div>
                                    <div class="text-xs text-gray-500"><?= $eq['marca_modelo'] ?></div>
                                </div>
                            </td>

                            <td data-label="Ubicación" class="p-4">
                                <div class="w-full text-right">
                                    <div class="font-bold text-gray-800"><?= $eq['direccion'] ?></div>
                                    <div class="text-xs text-gray-500"><?= $eq['usuariosEquipo'] ?></div>
                                </div>
                            </td>
                            
                            <td data-label="Hardware" class="p-4 text-xs">
                                <div class="w-full text-right">
                                    <div class="font-bold text-gray-700"><?= $eq['procesador'] ?></div>
                                    <div class="text-gray-500"><?= $eq['ram'] ?> | <?= $eq['tipodisco_capa'] ?></div>
                                </div>
                            </td>
                            
                            <td data-label="S.O." class="p-4 text-xs font-bold text-green-700">
                                <div class="w-full text-right"><?= $eq['sistemaOperativo'] ?></div>
                            </td>
                            
                            <td data-label="Estado" class="p-4 text-center text-green-600">
                                <div class="w-full flex justify-center md:justify-end lg:justify-center">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; else: ?>
                            <tr><td colspan="6" class="p-8 text-center text-gray-400">Sin registros óptimos.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</body>
</html>
<?php $conn->close(); ?>