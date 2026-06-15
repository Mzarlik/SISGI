<?php
// consultar_equipos.php
require_once 'session_check.php';
require_once 'config.php';

// 1. Verificar Sesión
if (!isset($_SESSION['usuario'])) {
    header("Location: index.php");
    exit();
}

// Rol y Permisos
$rol_usuario = $_SESSION['rol'] ?? 'tecnico';
$esAdmin = ($rol_usuario === 'admin');
$puedeEditar = ($rol_usuario === 'admin' || $rol_usuario === 'tecnico');

if ($_SESSION['rol'] === 'redes') {
    header("Location: dashboard.php?error=acceso_denegado");
    exit();
}

// 2. Conexión
$conn = get_db_connection();
if (!$conn) { die("Conexión fallida."); }

// 3. Paginación y Búsqueda
$registrosPorPagina = 20;
$paginaActual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
if ($paginaActual < 1) $paginaActual = 1;
$inicio = ($paginaActual - 1) * $registrosPorPagina;

$terminoBusqueda = isset($_GET['q']) ? trim($_GET['q']) : '';
$filtroTipo = isset($_GET['tipo']) ? $_GET['tipo'] : 'computo'; 

// --- MODIFIED LOGIC ---
// We will query the new inventory table `inventario_soporte`
$whereClauses = [];

// Exclude furniture, show only technology items
$furniture_types = "'Silla', 'Escritorio', 'Mueble', 'Archivero', 'Silla de oficina', 'Escritorio de oficina'";
$whereClauses[] = "tbi.nombre_tipo NOT IN ($furniture_types)";

if ($filtroTipo === 'computo') {
    $whereClauses[] = "tbi.nombre_tipo NOT LIKE '%Impresora%'"; 
} elseif ($filtroTipo === 'impresora') {
    $whereClauses[] = "tbi.nombre_tipo LIKE '%Impresora%'";
}

if (!empty($terminoBusqueda)) {
    $bs = $conn->real_escape_string($terminoBusqueda);
    // Adapted search fields for `inventario_soporte`
    $whereClauses[] = "(inv.num_inventario LIKE '%$bs%' OR 
                        inv.marca LIKE '%$bs%' OR 
                        inv.modelo LIKE '%$bs%' OR 
                        inv.num_serie LIKE '%$bs%' OR 
                        inv.descripcion LIKE '%$bs%' OR 
                        inv.personal_asignado LIKE '%$bs%' OR 
                        inv.nombre_ubicacion LIKE '%$bs%' OR
                        tbi.nombre_tipo LIKE '%$bs%')";
}

$whereSQL = "";
if (count($whereClauses) > 0) {
    $whereSQL = "WHERE " . implode(' AND ', $whereClauses);
}

// 4. CONSULTA (MODIFIED)
$sql = "SELECT inv.id, inv.num_inventario, inv.marca, inv.modelo, inv.descripcion, inv.personal_asignado, inv.nombre_ubicacion, inv.estatus, tbi.nombre_tipo as tipoequipo
        FROM inventario_soporte inv
        LEFT JOIN tipo_bien_inventario tbi ON inv.id_tipo_bien = tbi.id_tipo
        $whereSQL 
        ORDER BY inv.id DESC 
        LIMIT $inicio, $registrosPorPagina";

$result = $conn->query($sql);

// Total para paginación (MODIFIED)
$sql_total = "SELECT COUNT(inv.id) AS total 
              FROM inventario_soporte inv
              LEFT JOIN tipo_bien_inventario tbi ON inv.id_tipo_bien = tbi.id_tipo
              $whereSQL";
$result_total = $conn->query($sql_total);
$totalRegistros = $result_total ? $result_total->fetch_assoc()['total'] : 0;
$totalPaginas = ceil($totalRegistros / $registrosPorPagina);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Consultar Equipos</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> 
    <script src="js/sweetalert2.all.min.js"></script> 
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
        body { font-family: 'Segoe UI', sans-serif; }
        .input-table { 
            width: 100%; 
            min-width: 100px; 
            background: #fff; 
            border: 1px solid #ccc; 
            border-radius: 4px; 
            padding: 4px 8px; 
            font-size: 0.85rem; 
        }
        select.input-table {
            cursor: pointer;
            background-color: #ffffec;
        }
        .input-table:focus { 
            border-color: #721538; 
            outline: none; 
            box-shadow: 0 0 0 2px rgba(114, 21, 56, 0.1); 
        }
        .tab-active { background-color: #721538; color: white; border-color: #721538; }
        .tab-inactive { background-color: white; color: #555; border-color: #e5e7eb; }
        .tab-inactive:hover { background-color: #f9fafb; color: #721538; }

        /* --- RESPONSIVIDAD TIPO TARJETA (CORREGIDO PARA CORREOS LARGOS) --- */
        @media (max-width: 768px) {
            /* 1. Ocultar encabezados de tabla */
            thead { display: none; }

            /* 2. Bloques principales y RESET de whitespace */
            table, tbody, tr, td { 
                display: block; 
                width: 100%; 
                white-space: normal !important; /* CRÍTICO: Permite saltos de línea */
            }

            /* 3. Estilo de Tarjeta para la fila */
            tr { 
                background: white; 
                border-radius: 10px; 
                margin-bottom: 15px; 
                box-shadow: 0 2px 8px rgba(0,0,0,0.08); 
                border-left: 5px solid #721538; 
                padding: 15px; 
                position: relative;
            }

            /* 4. Celdas individuales - FLEXBOX AJUSTADO */
            td { 
                padding: 10px 0; 
                border-bottom: 1px solid #f0f0f0; 
                
                display: flex; 
                justify-content: space-between; /* Separa etiqueta y contenido */
                align-items: center;
                gap: 15px; /* Espacio de seguridad entre etiqueta y texto */
                min-height: 40px;
            }
            
            td:last-child { 
                border-bottom: none; 
                justify-content: center; 
                padding-top: 15px;
            }
            
            /* 5. Etiqueta (Label - Izquierda) */
            td:before { 
                content: attr(data-label); 
                position: static; 
                text-align: left;
                font-weight: bold; 
                color: #721538; 
                text-transform: uppercase; 
                font-size: 0.75em; 
                
                /* Ancho fijo para que estén alineadas verticalmente */
                width: 35%; 
                min-width: 100px;
                flex-shrink: 0; /* Prohibido encogerse */
            }

            /* 6. Contenido (Derecha - Spans y Inputs) */
            td > span, td > input, td > select, td > div {
                text-align: right;
                width: 60%; /* Toma el resto del espacio */
                
                /* MAGIA PARA EL CORREO: Romper palabras largas */
                word-wrap: break-word; 
                overflow-wrap: break-word; 
                word-break: break-word; 
                
                display: block; /* Asegura que respete el ancho */
            }

            /* Resetear estilos sticky */
            .sticky { position: static !important; }
        }
    </style>
</head>
<body class="bg-background min-h-screen p-4 sm:p-6">

    <div class="max-w-[1900px] mx-auto bg-white shadow-2xl rounded-xl overflow-hidden flex flex-col h-full min-h-[85vh]">
        
        <div class="p-6 border-b border-gray-200 flex flex-col lg:flex-row justify-between items-center gap-4 bg-white">
            <div class="flex flex-col sm:flex-row items-center gap-6">
                <h2 class="text-2xl font-bold text-primary-dark flex items-center gap-3">
                    <i class="fas fa-desktop"></i> Inventario Completo
                </h2>
                
                <div class="inline-flex rounded-md shadow-sm" role="group">
                    <a href="?tipo=computo&q=<?= urlencode($terminoBusqueda) ?>" class="px-4 py-2 text-sm font-medium border rounded-l-lg transition <?= $filtroTipo == 'computo' ? 'tab-active' : 'tab-inactive' ?>">
                        <i class="fas fa-laptop mr-2"></i> Cómputo
                    </a>
                    <a href="?tipo=impresora&q=<?= urlencode($terminoBusqueda) ?>" class="px-4 py-2 text-sm font-medium border-t border-b transition <?= $filtroTipo == 'impresora' ? 'tab-active' : 'tab-inactive' ?>">
                        <i class="fas fa-print mr-2"></i> Impresoras
                    </a>
                    <a href="?tipo=todos&q=<?= urlencode($terminoBusqueda) ?>" class="px-4 py-2 text-sm font-medium border rounded-r-lg transition <?= $filtroTipo == 'todos' ? 'tab-active' : 'tab-inactive' ?>">
                        <i class="fas fa-layer-group mr-2"></i> Todo
                    </a>
                </div>
            </div>

            <div class="flex flex-col sm:flex-row gap-3 w-full lg:w-auto items-center">
                <form action="" method="GET" class="flex w-full sm:w-72 shadow-sm">
                    <input type="hidden" name="tipo" value="<?= htmlspecialchars($filtroTipo) ?>">
                    <div class="relative w-full">
                        <input type="text" name="q" class="w-full h-10 pl-10 pr-10 rounded-l-lg border border-gray-300 focus:outline-none focus:border-primary-dark text-sm" placeholder="Buscar..." value="<?= htmlspecialchars($terminoBusqueda) ?>">
                        <div class="absolute left-3 top-0 h-10 flex items-center text-gray-400 pointer-events-none"><i class="fas fa-search"></i></div>
                        <?php if($terminoBusqueda): ?>
                            <a href="consultar_equipos.php?tipo=<?= $filtroTipo ?>" class="absolute right-2 top-0 h-10 flex items-center text-gray-400 hover:text-red-500 px-2">✕</a>
                        <?php endif; ?>
                    </div>
                    <button type="submit" class="h-10 px-5 bg-primary-dark text-white rounded-r-lg hover:bg-primary-light text-sm">Buscar</button>
                </form>

                <div class="flex gap-2 w-full sm:w-auto justify-center">
                    <button onclick="window.location.reload()" class="h-10 w-10 bg-gray-50 text-gray-600 rounded-lg hover:bg-gray-100 border border-gray-200 flex items-center justify-center"><i class="fas fa-sync-alt"></i></button>
                    
                    <?php if($esAdmin || $_SESSION['rol'] == 'tecnico'): ?>
                        <a href="informe_equipos.php" class="h-10 px-4 bg-blue-600 text-white rounded-lg hover:bg-blue-700 flex items-center gap-2 text-sm transition shadow-sm">
                            <i class="fas fa-chart-pie"></i> Informes
                        </a>
                        <a href="registro_equipos.php" class="h-10 px-4 bg-green-600 text-white rounded-lg hover:bg-green-700 flex items-center gap-2 text-sm transition shadow-sm"><i class="fas fa-plus"></i> Alta</a>
                    <?php endif; ?>
                    
                    <a href="dashboard.php" class="h-10 px-4 bg-gray-800 text-white rounded-lg hover:bg-gray-900 flex items-center gap-2 text-sm transition shadow-sm"><i class="fas fa-th-large"></i> Menú</a>
                </div>
            </div>
        </div>

        <div class="flex-grow pb-20 p-2 sm:p-4 w-full">
            <div class="overflow-x-auto rounded-xl border border-gray-200 shadow-sm bg-white">
                <table class="w-full text-left border-collapse min-w-max">
                    <thead>
                        <tr class="bg-primary-dark text-white text-xs uppercase tracking-wider">
                            <th class="p-4 font-semibold min-w-[150px]">Secretaría</th>
                            <th class="p-4 font-semibold min-w-[150px]">Dirección</th>
                            <th class="p-4 font-semibold">Usuario Dom</th>
                            <th class="p-4 font-semibold">Usuario Real</th>
                            <th class="p-4 font-semibold text-yellow-300">Cuenta Office</th>
                            
                            <th class="p-4 font-semibold">Tipo</th>
                            <th class="p-4 font-semibold">Modelo</th>
                            <th class="p-4 font-semibold">CPU</th>
                            <th class="p-4 font-semibold">RAM</th>
                            <th class="p-4 font-semibold">Disco</th>
                            <th class="p-4 font-semibold">OS</th>
                            <th class="p-4 font-semibold">Acceso</th>
                            
                            <th class="p-4 font-semibold">Antivirus</th>
                            <th class="p-4 font-semibold">Estatus</th>
                            <th class="p-4 font-semibold min-w-[150px]">Observaciones</th>
                            <th class="p-4 font-semibold">Inventario</th>
                            <th class="p-4 font-semibold text-purple-300">Registrado Por</th> <th class="p-4 font-semibold">IP</th>
                            <th class="p-4 font-semibold text-center sticky right-0 z-10 bg-primary-dark shadow-[-4px_0_6px_-1px_rgba(0,0,0,0.1)]">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="text-xs text-gray-700 divide-y divide-gray-100 bg-white">
                        <?php if($result->num_rows > 0): while($row = $result->fetch_assoc()): $id = $row['id']; ?>
                        <tr id="fila_<?= $id ?>" class="hover:bg-gray-50 transition duration-150 group">
                            
                            <td data-label="Secretaría" class="p-3"><span class="editable text-gray-400">N/A</span></td>
                            <td data-label="Dirección" class="p-3"><span class="editable"><?= htmlspecialchars($row['nombre_ubicacion'] ?? 'N/A') ?></span></td>
                            <td data-label="Usuario Dom" class="p-3"><span class="editable text-gray-400">N/A</span></td>
                            <td data-label="Usuario Real" class="p-3"><span class="editable"><?= htmlspecialchars($row['personal_asignado'] ?? 'STOCK') ?></span></td>
                            
                            <td data-label="Cuenta Office" class="p-3"><span class="editable text-gray-400">N/A</span></td>

                            <td data-label="Tipo" class="p-3"><span class="editable"><?= htmlspecialchars($row['tipoequipo'] ?? 'N/A') ?></span></td>
                            <td data-label="Modelo" class="p-3"><span class="editable"><?= htmlspecialchars($row['marca'] . ' ' . $row['modelo']) ?></span></td>
                            <td data-label="Procesador" class="p-3"><span class="editable text-gray-400">En Desc.</span></td>
                            <td data-label="RAM" class="p-3"><span class="editable text-gray-400">En Desc.</span></td>
                            <td data-label="Disco" class="p-3"><span class="editable text-gray-400">En Desc.</span></td>
                            <td data-label="S.O." class="p-3"><span class="editable text-gray-400">En Desc.</span></td>
                            <td data-label="Acceso" class="p-3"><span class="editable text-gray-400">N/A</span></td>

                            <td data-label="Antivirus" class="p-3"><span class="editable text-gray-400">N/A</span></td>
                            
                            <?php 
                                $estatus = $row['estatus'] ?? 'N/A';
                                $colorEstatus = 'text-gray-600';
                                if($estatus == 'Operativo' || $estatus == 'En Stock' || $estatus == 'Asignado') $colorEstatus = 'text-green-600 font-bold';
                                if($estatus == 'Dañado' || $estatus == 'Para Baja') $colorEstatus = 'text-red-600 font-bold';
                                if($estatus == 'En Mantenimiento') $colorEstatus = 'text-yellow-600 font-bold';
                            ?>
                            <td data-label="Estatus" class="p-3 <?= $colorEstatus ?>"><span class="editable"><?= htmlspecialchars($estatus) ?></span></td>
                            
                            <td data-label="Observaciones" class="p-3 italic text-gray-500 truncate max-w-[200px]" title="<?= htmlspecialchars($row['descripcion']) ?>">
                                <span class="editable"><?= htmlspecialchars($row['descripcion']) ?></span>
                            </td>
                            <td data-label="Inventario" class="p-3 font-bold text-gray-800"><span class="editable"><?= htmlspecialchars($row['num_inventario']) ?></span></td>
                            
                            <td data-label="Registrado Por" class="p-3 font-medium text-purple-700 bg-purple-50 rounded-md">
                                <span class="text-gray-400">N/A</span>
                            </td>

                            <td data-label="IP" class="p-3"><span class="editable text-gray-400">N/A</span></td>
                            
                            <td data-label="Acciones" class="p-3 text-center sticky right-0 bg-white group-hover:bg-gray-50 border-l border-gray-100 shadow-[-4px_0_6px_-1px_rgba(0,0,0,0.05)]">
                                <div class="flex justify-center gap-2">
                                    <a href="consultar_inventario.php?q=<?= urlencode($row['num_inventario']) ?>" class="text-blue-600 hover:text-blue-800 hover:bg-blue-50 p-1.5 rounded transition" title="Gestionar en Inventario">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr><td colspan="20" class="p-12 text-center text-gray-400 italic bg-gray-50">No se encontraron equipos.</td></tr> <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if($totalPaginas > 1): ?>
        <div class="p-4 border-t border-gray-200 bg-gray-50 flex flex-wrap justify-center gap-1 fixed bottom-0 w-full lg:w-[calc(100%-2rem)] max-w-[1900px] z-20">
            <?php 
            $rango = 3;
            $desde = max(1, $paginaActual - $rango);
            $hasta = min($totalPaginas, $paginaActual + $rango);
            $params = "&tipo=" . urlencode($filtroTipo) . "&q=" . urlencode($terminoBusqueda);

            if($paginaActual > 1) echo '<a href="?pagina='.($paginaActual-1).$params.'" class="px-3 py-1 bg-white border border-gray-300 rounded text-xs text-gray-600 hover:bg-gray-100">Anterior</a>';
            
            for($i=$desde; $i<=$hasta; $i++): 
                $activeClass = ($i == $paginaActual) ? 'bg-primary-dark text-white border-primary-dark' : 'bg-white text-gray-600 border-gray-300 hover:bg-gray-100';
            ?>
                <a href="?pagina=<?= $i . $params ?>" class="px-3 py-1 border rounded text-xs font-medium transition <?= $activeClass ?>"><?= $i ?></a>
            <?php endfor; ?>
            
            <?php if($paginaActual < $totalPaginas) echo '<a href="?pagina='.($paginaActual+1).$params.'" class="px-3 py-1 bg-white border border-gray-300 rounded text-xs text-gray-600 hover:bg-gray-100">Siguiente</a>'; ?>
        </div>
        <?php endif; ?>
    </div>

    <script>
    // The inline editing functionality has been removed because this page now
    // displays data from the `inventario_soporte` table.
    // All management for this inventory should be done from `consultar_inventario.php`.
    </script>
</body>
</html>
<?php $conn->close(); ?>