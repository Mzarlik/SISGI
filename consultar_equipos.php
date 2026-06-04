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

$whereClauses = [];

if ($filtroTipo === 'computo') {
    $whereClauses[] = "e.tipoequipo NOT LIKE '%Impresora%'"; 
} elseif ($filtroTipo === 'impresora') {
    $whereClauses[] = "e.tipoequipo LIKE '%Impresora%'";
}

if (!empty($terminoBusqueda)) {
    $bs = $conn->real_escape_string($terminoBusqueda);
    // Agregamos e.registrado_por a la búsqueda por si quieren buscar por el nombre del técnico
    $whereClauses[] = "(e.direccion LIKE '%$bs%' OR e.usuarioDominio LIKE '%$bs%' OR e.usuariosEquipo LIKE '%$bs%' OR e.numInventario LIKE '%$bs%' OR e.direccionIP LIKE '%$bs%' OR co.Correo LIKE '%$bs%' OR e.observaciones LIKE '%$bs%' OR e.registrado_por LIKE '%$bs%')";
}

$whereSQL = "";
if (count($whereClauses) > 0) {
    $whereSQL = "WHERE " . implode(' AND ', $whereClauses);
}

// 4. CONSULTA
$sql = "SELECT e.*, co.Correo AS nombre_cuenta_office 
        FROM equiposbd e
        LEFT JOIN cuentas_office co ON e.id_cuenta_office = co.id
        $whereSQL 
        ORDER BY e.id DESC 
        LIMIT $inicio, $registrosPorPagina";

$result = $conn->query($sql);

// Total para paginación
$sql_total = "SELECT COUNT(*) AS total 
              FROM equiposbd e
              LEFT JOIN cuentas_office co ON e.id_cuenta_office = co.id
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
                            
                            <td data-label="Secretaría" class="p-3 celda-editable" data-campo="secretaria"><span class="editable"><?= htmlspecialchars($row['secretaria']) ?></span></td>
                            <td data-label="Dirección" class="p-3 celda-editable" data-campo="direccion"><span class="editable"><?= htmlspecialchars($row['direccion']) ?></span></td>
                            <td data-label="Usuario Dom" class="p-3 celda-editable font-bold text-primary-dark" data-campo="usuarioDominio"><span class="editable"><?= htmlspecialchars($row['usuarioDominio']) ?></span></td>
                            <td data-label="Usuario Real" class="p-3 celda-editable" data-campo="usuariosEquipo"><span class="editable"><?= htmlspecialchars($row['usuariosEquipo']) ?></span></td>
                            
                            <td data-label="Cuenta Office" class="p-3 celda-editable font-medium text-blue-600" 
                                data-campo="id_cuenta_office" 
                                data-valor-original="<?= $row['id_cuenta_office'] ?>">
                                <span class="editable"><?= htmlspecialchars($row['nombre_cuenta_office'] ?? '-- Sin Asignar --') ?></span>
                            </td>

                            <td data-label="Tipo" class="p-3 celda-editable" data-campo="tipoequipo"><span class="editable"><?= htmlspecialchars($row['tipoequipo']) ?></span></td>
                            <td data-label="Modelo" class="p-3 celda-editable" data-campo="marca_modelo"><span class="editable"><?= htmlspecialchars($row['marca_modelo']) ?></span></td>
                            <td data-label="Procesador" class="p-3 celda-editable" data-campo="procesador"><span class="editable"><?= htmlspecialchars($row['procesador']) ?></span></td>
                            <td data-label="RAM" class="p-3 celda-editable" data-campo="ram"><span class="editable"><?= htmlspecialchars($row['ram']) ?></span></td>
                            <td data-label="Disco" class="p-3 celda-editable" data-campo="tipodisco_capa"><span class="editable"><?= htmlspecialchars($row['tipodisco_capa']) ?></span></td>
                            <td data-label="S.O." class="p-3 celda-editable" data-campo="sistemaOperativo"><span class="editable"><?= htmlspecialchars($row['sistemaOperativo']) ?></span></td>
                            <td data-label="Acceso" class="p-3 celda-editable" data-campo="nivelAccesoEquipo"><span class="editable"><?= htmlspecialchars($row['nivelAccesoEquipo']) ?></span></td>

                            <td data-label="Antivirus" class="p-3 celda-editable" data-campo="antivirus_eset"><span class="editable"><?= htmlspecialchars($row['antivirus_eset']) ?></span></td>
                            
                            <?php 
                                $estatus = $row['estatus_equipo'];
                                $colorEstatus = 'text-gray-600';
                                if($estatus == 'Operativo') $colorEstatus = 'text-green-600 font-bold';
                                if($estatus == 'Dañado' || $estatus == 'Baja') $colorEstatus = 'text-red-600 font-bold';
                                if($estatus == 'Para revisión') $colorEstatus = 'text-yellow-600 font-bold';
                            ?>
                            <td data-label="Estatus" class="p-3 celda-editable <?= $colorEstatus ?>" data-campo="estatus_equipo"><span class="editable"><?= htmlspecialchars($estatus) ?></span></td>
                            
                            <td data-label="Observaciones" class="p-3 celda-editable italic text-gray-500 truncate max-w-[200px]" data-campo="observaciones" title="<?= htmlspecialchars($row['observaciones']) ?>">
                                <span class="editable"><?= htmlspecialchars($row['observaciones']) ?></span>
                            </td>
                            <td data-label="Inventario" class="p-3 celda-editable font-bold text-gray-800" data-campo="numInventario"><span class="editable"><?= htmlspecialchars($row['numInventario']) ?></span></td>
                            
                            <td data-label="Registrado Por" class="p-3 font-medium text-purple-700 bg-purple-50 rounded-md">
                                <?= htmlspecialchars($row['registrado_por'] ?? 'No registrado') ?>
                            </td>

                            <td data-label="IP" class="p-3 celda-editable font-mono text-blue-600" data-campo="direccionIP"><span class="editable"><?= htmlspecialchars($row['direccionIP']) ?></span></td>
                            
                            <td data-label="Acciones" class="p-3 text-center sticky right-0 bg-white group-hover:bg-gray-50 border-l border-gray-100 shadow-[-4px_0_6px_-1px_rgba(0,0,0,0.05)]">
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
    let listaAreas = [];
    let listaCuentas = [];

    document.addEventListener('DOMContentLoaded', () => {
        fetch('obtener_areas.php').then(r => r.json()).then(data => { if (data.success) listaAreas = data.data; });
        fetch('obtener_cuentas_office.php').then(r => r.json()).then(data => { if (data.success) listaCuentas = data.data; });
    });

    function habilitarEdicion(id) {
        const fila = document.getElementById('fila_' + id);
        fila.querySelector('.btn-edit').classList.add('hidden');
        fila.querySelector('.btn-save').classList.remove('hidden');

        const celdaSec = fila.querySelector('[data-campo="secretaria"]');
        const celdaDir = fila.querySelector('[data-campo="direccion"]');
        const valorSecActual = celdaSec.innerText.trim();
        const valorDirActual = celdaDir.innerText.trim();

        fila.querySelectorAll('.celda-editable').forEach(td => {
            const campo = td.dataset.campo;
            const valorTexto = td.innerText.trim();
            const valorID = td.dataset.valorOriginal || valorTexto; 

            let inputHtml = '';

            // --- LÓGICA DE INPUTS ---
            if (campo === 'secretaria') {
                inputHtml = generarSelectSecretarias(valorTexto, id);
            } else if (campo === 'direccion') {
                inputHtml = `<select class="input-table" name="direccion" id="select_dir_${id}"></select>`;
            } else if (campo === 'id_cuenta_office') {
                inputHtml = generarSelectOffice(valorID);
            } else if (campo === 'tipoequipo') {
                inputHtml = generarSelectEstatico('tipoequipo', valorTexto, ['PC', 'Laptop', 'AiO', 'Servidor', 'Impresora']);
            } else if (campo === 'nivelAccesoEquipo') {
                inputHtml = generarSelectEstatico('nivelAccesoEquipo', valorTexto, ['Usuario', 'Administrador']);
            } 
            else if (campo === 'antivirus_eset') {
                inputHtml = generarSelectEstatico('antivirus_eset', valorTexto, ['No', 'Si', 'N/A']);
            } else if (campo === 'estatus_equipo') {
                inputHtml = generarSelectEstatico('estatus_equipo', valorTexto, ['Operativo', 'Para Revisión', 'Dañado', 'Baja']);
            } else if (campo === 'observaciones') {
                inputHtml = `<input type="text" class="input-table" name="${campo}" value="${valorTexto}" placeholder="...">`;
            }
            else {
                inputHtml = `<input type="text" class="input-table" name="${campo}" value="${valorTexto}">`;
            }

            td.innerHTML = inputHtml;
        });

        actualizarSelectDireccion(id, valorSecActual, valorDirActual);
    }

    function generarSelectOffice(idActual) {
        let options = `<option value="">-- Sin Asignar --</option>`;
        listaCuentas.forEach(c => {
            const selected = (c.id == idActual) ? 'selected' : '';
            options += `<option value="${c.id}" ${selected}>${c.Correo} [${c.Conectados || 0}]</option>`;
        });
        return `<select class="input-table" name="id_cuenta_office">${options}</select>`;
    }

    function generarSelectSecretarias(valorActual, rowId) {
        let options = `<option value="">-- Seleccionar --</option>`;
        listaAreas.forEach(area => {
            const selected = (area.nombre_secretaria === valorActual) ? 'selected' : '';
            options += `<option value="${area.nombre_secretaria}" ${selected}>${area.nombre_secretaria}</option>`;
        });
        return `<select class="input-table" name="secretaria" onchange="cambioSecretaria(${rowId}, this.value)">${options}</select>`;
    }

    function generarSelectEstatico(name, valorActual, opciones) {
        let options = '';
        opciones.forEach(op => {
            const selected = (op === valorActual) ? 'selected' : '';
            options += `<option value="${op}" ${selected}>${op}</option>`;
        });
        return `<select class="input-table" name="${name}">${options}</select>`;
    }

    function cambioSecretaria(id, nuevaSecretaria) {
        actualizarSelectDireccion(id, nuevaSecretaria, '');
    }

    function actualizarSelectDireccion(id, nombreSecretaria, valorPreseleccionado) {
        const selectDir = document.getElementById(`select_dir_${id}`);
        if (!selectDir) return;
        selectDir.innerHTML = '<option value="">-- Seleccionar --</option>';

        const areaData = listaAreas.find(a => a.nombre_secretaria === nombreSecretaria);

        if (areaData && areaData.direcciones) {
            areaData.direcciones.forEach(dir => {
                const selected = (dir.nombre_direcciones === valorPreseleccionado) ? 'selected' : '';
                const option = document.createElement('option');
                option.value = dir.nombre_direcciones;
                option.textContent = dir.nombre_direcciones;
                if (selected) option.selected = true;
                selectDir.appendChild(option);
            });
        }
    }

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
        })
        .catch(err => Swal.fire('Error', 'No se pudo guardar.', 'error'));
    }

    function eliminar(id) {
        Swal.fire({
            title: '¿Eliminar equipo?', text: "Esta acción es irreversible.", icon: 'warning', showCancelButton: true, confirmButtonColor: '#d33'
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