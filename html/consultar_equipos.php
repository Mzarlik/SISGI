<?php
// Incluir la configuración de conexión a la base de datos
require_once 'config.php';
session_start();

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
    // Si es redes, lo regresamos al dashboard con un mensaje de error opcional
    header("Location: dashboard.php?error=acceso_denegado");
    exit();
}

// 2. Conexión a la Base de Datos
$conn = get_db_connection();
if (!$conn) { die("Conexión fallida."); }

// 3. Paginación y Búsqueda
$registrosPorPagina = 20;
$paginaActual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
if ($paginaActual < 1) $paginaActual = 1;
$inicio = ($paginaActual - 1) * $registrosPorPagina;

// Filtros
$terminoBusqueda = isset($_GET['q']) ? trim($_GET['q']) : '';
$filtroTipo = isset($_GET['tipo']) ? $_GET['tipo'] : 'computo'; // Default: solo cómputo

$whereClauses = [];

// Lógica de Tipo (Ocultar impresoras por defecto)
if ($filtroTipo === 'computo') {
    $whereClauses[] = "tipoequipo NOT LIKE '%Impresora%'"; 
} elseif ($filtroTipo === 'impresora') {
    $whereClauses[] = "tipoequipo LIKE '%Impresora%'";
}

// Lógica de Búsqueda
if (!empty($terminoBusqueda)) {
    $bs = $conn->real_escape_string($terminoBusqueda);
    $whereClauses[] = "(direccion LIKE '%$bs%' OR usuarioDominio LIKE '%$bs%' OR usuariosEquipo LIKE '%$bs%' OR numInventario LIKE '%$bs%' OR direccionIP LIKE '%$bs%')";
}

$whereSQL = "";
if (count($whereClauses) > 0) {
    $whereSQL = "WHERE " . implode(' AND ', $whereClauses);
}

// Consultas
$sql = "SELECT * FROM equiposbd $whereSQL ORDER BY id DESC LIMIT $inicio, $registrosPorPagina";
$result = $conn->query($sql);

$sql_total = "SELECT COUNT(*) AS total FROM equiposbd $whereSQL";
$result_total = $conn->query($sql_total);
$totalRegistros = $result_total->fetch_assoc()['total'];
$totalPaginas = ceil($totalRegistros / $registrosPorPagina);
include 'header.php';

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
        .input-table { width: 100%; background: #fff; border: 1px solid #ccc; border-radius: 4px; padding: 4px 8px; font-size: 0.85rem; }
        .input-table:focus { border-color: #721538; outline: none; box-shadow: 0 0 0 2px rgba(114, 21, 56, 0.1); }
        .tab-active { background-color: #721538; color: white; border-color: #721538; }
        .tab-inactive { background-color: white; color: #555; border-color: #e5e7eb; }
        .tab-inactive:hover { background-color: #f9fafb; color: #721538; }
    </style>
</head>
<body class="bg-background min-h-screen p-4 sm:p-6">

    <div class="max-w-[1900px] mx-auto bg-white shadow-2xl rounded-xl overflow-hidden flex flex-col h-full min-h-[85vh]">
        
        <!-- ENCABEZADO -->
        <div class="p-6 border-b border-gray-200 flex flex-col lg:flex-row justify-between items-center gap-4 bg-white">
            <div class="flex flex-col sm:flex-row items-center gap-6">
                <h2 class="text-2xl font-bold text-primary-dark flex items-center gap-3">
                    <i class="fas fa-desktop"></i> Inventario
                </h2>
                
                <!-- PESTAÑAS DE FILTRO -->
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
                        <a href="registro_equipos.php" class="h-10 px-4 bg-green-600 text-white rounded-lg hover:bg-green-700 flex items-center gap-2 text-sm transition shadow-sm"><i class="fas fa-plus"></i> Alta</a>
                    <?php endif; ?>
                    <a href="dashboard.php" class="h-10 px-4 bg-gray-800 text-white rounded-lg hover:bg-gray-900 flex items-center gap-2 text-sm transition shadow-sm"><i class="fas fa-th-large"></i> Menú</a>
                </div>
            </div>
        </div>

        <div id="mensaje-global" class="text-center font-bold my-2"></div>

        <!-- TABLA -->
        <div class="overflow-x-auto flex-grow">
            <table class="w-full text-left border-collapse whitespace-nowrap">
                <thead>
                    <tr class="bg-primary-dark text-white text-xs uppercase tracking-wider">
                        <th class="p-4 font-semibold sticky left-0 z-10 bg-primary-dark">ID</th>
                        <th class="p-4 font-semibold">Secretaría</th>
                        <th class="p-4 font-semibold">Dirección</th>
                        <th class="p-4 font-semibold">Usuario Dom</th>
                        <th class="p-4 font-semibold">Usuario Real</th>
                        <th class="p-4 font-semibold">Tipo</th>
                        <th class="p-4 font-semibold">Modelo</th>
                        <th class="p-4 font-semibold">CPU</th>
                        <th class="p-4 font-semibold">RAM</th>
                        <th class="p-4 font-semibold">Disco</th>
                        <th class="p-4 font-semibold">OS</th>
                        <th class="p-4 font-semibold">Acceso</th>
                        <th class="p-4 font-semibold">Inventario</th>
                        <th class="p-4 font-semibold">IP</th>
                        <th class="p-4 font-semibold text-center sticky right-0 z-10 bg-primary-dark">Acciones</th>
                    </tr>
                </thead>
                <tbody class="text-xs text-gray-700 divide-y divide-gray-100 bg-white">
                    <?php if($result->num_rows > 0): while($row = $result->fetch_assoc()): $id = $row['id']; ?>
                    <tr id="fila_<?= $id ?>" class="hover:bg-gray-50 transition duration-150 group">
                        <td class="p-4 font-bold text-gray-400 sticky left-0 bg-white group-hover:bg-gray-50 border-r border-gray-100"><?= $id ?></td>
                        
                        <td class="p-3 celda-editable max-w-[200px] truncate" title="<?= htmlspecialchars($row['secretaria']) ?>" data-campo="secretaria"><span class="editable"><?= htmlspecialchars($row['secretaria']) ?></span></td>
                        <td class="p-3 celda-editable max-w-[200px] truncate" title="<?= htmlspecialchars($row['direccion']) ?>" data-campo="direccion"><span class="editable"><?= htmlspecialchars($row['direccion']) ?></span></td>
                        <td class="p-3 celda-editable font-bold text-primary-dark" data-campo="usuarioDominio"><span class="editable"><?= htmlspecialchars($row['usuarioDominio']) ?></span></td>
                        <td class="p-3 celda-editable" data-campo="usuariosEquipo"><span class="editable"><?= htmlspecialchars($row['usuariosEquipo']) ?></span></td>
                        <td class="p-3 celda-editable" data-campo="tipoequipo"><span class="editable"><?= htmlspecialchars($row['tipoequipo']) ?></span></td>
                        <td class="p-3 celda-editable" data-campo="marca_modelo"><span class="editable"><?= htmlspecialchars($row['marca_modelo']) ?></span></td>
                        <td class="p-3 celda-editable" data-campo="procesador"><span class="editable"><?= htmlspecialchars($row['procesador']) ?></span></td>
                        <td class="p-3 celda-editable" data-campo="ram"><span class="editable"><?= htmlspecialchars($row['ram']) ?></span></td>
                        <td class="p-3 celda-editable" data-campo="tipodisco_capa"><span class="editable"><?= htmlspecialchars($row['tipodisco_capa']) ?></span></td>
                        <td class="p-3 celda-editable" data-campo="sistemaOperativo"><span class="editable"><?= htmlspecialchars($row['sistemaOperativo']) ?></span></td>
                        <td class="p-3 celda-editable" data-campo="nivelAccesoEquipo"><span class="editable"><?= htmlspecialchars($row['nivelAccesoEquipo']) ?></span></td>
                        <td class="p-3 celda-editable font-bold text-gray-800" data-campo="numInventario"><span class="editable"><?= htmlspecialchars($row['numInventario']) ?></span></td>
                        <td class="p-3 celda-editable font-mono text-blue-600" data-campo="direccionIP"><span class="editable"><?= htmlspecialchars($row['direccionIP']) ?></span></td>
                        
                        <td class="p-3 text-center sticky right-0 bg-white group-hover:bg-gray-50 border-l border-gray-100">
                            <div class="flex justify-center gap-2">
                                <?php if($puedeEditar): ?>
                                    <button class="btn-edit text-blue-600 hover:text-blue-800 hover:bg-blue-50 p-1.5 rounded transition" onclick="habilitarEdicion(<?= $id ?>)"><i class="fas fa-pencil-alt"></i></button>
                                    <button class="btn-save hidden text-green-600 hover:text-green-800 hover:bg-green-50 p-1.5 rounded transition" id="guardar_<?= $id ?>" onclick="guardarCambios(<?= $id ?>)"><i class="fas fa-check"></i></button>
                                <?php endif; ?>
                                <?php if($esAdmin): ?>
                                    <button class="text-red-400 hover:text-red-600 hover:bg-red-50 p-1.5 rounded transition" onclick="eliminar(<?= $id ?>)"><i class="fas fa-trash-alt"></i></button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr><td colspan="15" class="p-12 text-center text-gray-400 italic bg-gray-50">No se encontraron equipos.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- PAGINACIÓN CORREGIDA -->
        <?php if($totalPaginas > 1): ?>
        <div class="p-4 border-t border-gray-200 bg-gray-50 flex flex-wrap justify-center gap-1 sticky bottom-0">
            <?php 
            $rango = 3;
            $desde = max(1, $paginaActual - $rango);
            $hasta = min($totalPaginas, $paginaActual + $rango);
            
            $params = "&tipo=" . urlencode($filtroTipo) . "&q=" . urlencode($terminoBusqueda);

            if($paginaActual > 1) echo '<a href="?pagina='.($paginaActual-1).$params.'" class="px-3 py-1 bg-white border border-gray-300 rounded text-xs text-gray-600 hover:bg-gray-100">Anterior</a>';
            
            if($desde > 1) echo '<span class="px-2 py-1 text-gray-400 text-xs">...</span>';

            for($i=$desde; $i<=$hasta; $i++): 
                $activeClass = ($i == $paginaActual) ? 'bg-primary-dark text-white border-primary-dark' : 'bg-white text-gray-600 border-gray-300 hover:bg-gray-100';
            ?>
                <a href="?pagina=<?= $i . $params ?>" class="px-3 py-1 border rounded text-xs font-medium transition <?= $activeClass ?>"><?= $i ?></a>
            <?php endfor; ?>
            
            <?php if($hasta < $totalPaginas) echo '<span class="px-2 py-1 text-gray-400 text-xs">...</span>';

            if($paginaActual < $totalPaginas) echo '<a href="?pagina='.($paginaActual+1).$params.'" class="px-3 py-1 bg-white border border-gray-300 rounded text-xs text-gray-600 hover:bg-gray-100">Siguiente</a>';
            ?>
        </div>
        <?php endif; ?>

    </div>

    <script>
    function habilitarEdicion(id) { // Acepta el ID directamente
        const fila = document.getElementById('fila_' + id);
        // ... resto del script igual que antes ...
        fila.querySelector('.btn-edit').classList.add('hidden');
        fila.querySelector('.btn-save').classList.remove('hidden');
        fila.querySelector('.btn-save').style.display = 'inline-block';

        fila.querySelectorAll('.celda-editable').forEach(td => {
            const val = td.querySelector('.editable').textContent;
            td.innerHTML = `<input type="text" class="input-table" name="${td.dataset.campo}" value="${val}">`;
        });
    }
    
    // (Asegúrate de que las funciones guardarCambios y eliminar también estén aquí, tal como en el código anterior)
    function guardarCambios(id) {
        const fila = document.getElementById('fila_' + id);
        const datos = new FormData();
        datos.append('id', id);
        fila.querySelectorAll('.input-table').forEach(input => {
            datos.append(input.name, input.value);
        });

        fetch('guardar_equipo.php', { method: 'POST', body: datos })
        .then(r => r.json())
        .then(d => {
            if(d.success) {
                Swal.fire({icon:'success', title:'Actualizado', toast:true, position:'top-end', showConfirmButton:false, timer:1500});
                setTimeout(() => location.reload(), 500);
            } else {
                Swal.fire('Error', d.message, 'error');
            }
        });
    }

    function eliminar(id) {
        Swal.fire({
            title: '¿Eliminar equipo?', text: "Irreversible.", icon: 'warning', showCancelButton: true, confirmButtonColor: '#d33'
        }).then((result) => {
            if (result.isConfirmed) {
                const fd = new FormData(); fd.append('id', id);
                fetch('eliminar_equipo.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(d => {
                    if(d.success) Swal.fire('Eliminado', '', 'success').then(() => location.reload());
                    else Swal.fire('Error', d.message, 'error');
                });
            }
        });
    }
    </script>
</body>
</html>
<?php $conn->close(); ?>