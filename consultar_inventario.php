<?php 
require_once 'session_check.php';
require_once 'config.php'; 

if (!isset($_SESSION['usuario'])) { 
    header("Location: index.php"); 
    exit(); 
}

$conn = get_db_connection();
$rol_usuario = $_SESSION['rol'] ?? 'tecnico'; 
$esAdmin = ($rol_usuario === 'admin' || $rol_usuario === 'masterweb'); 

// 1. OBTENER LISTA ÚNICA DE PERSONAL PARA EL FILTRO (incluye histórico de bajas)
$lista_personal = [];
$res_pers = $conn->query("SELECT DISTINCT personal_asignado AS nombre FROM inventario_soporte WHERE personal_asignado IS NOT NULL AND personal_asignado <> '' AND personal_asignado <> 'STOCK' AND personal_asignado <> 'Sin Asignar'
UNION
SELECT DISTINCT ultimo_responsable AS nombre FROM inventario_soporte WHERE ultimo_responsable IS NOT NULL AND ultimo_responsable <> '' AND ultimo_responsable <> 'STOCK' AND ultimo_responsable <> 'Sin Asignar'
ORDER BY nombre ASC");
while($p = $res_pers->fetch_assoc()) {
    $lista_personal[] = $p['nombre'];
}

$tipos_opciones = [];
$res_tipos = $conn->query("SELECT id_tipo, nombre_tipo FROM tipo_bien_inventario ORDER BY nombre_tipo ASC");
while($t = $res_tipos->fetch_assoc()) {
    $tipos_opciones[] = $t;
}

// 1.5 BACKEND: PROCESAR ACCIÓN DE BAJA EN BASE DE DATOS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'bulk_baja') {
    ob_clean();
    header('Content-Type: application/json');
    
    if (!$esAdmin) {
        echo json_encode(['success' => false, 'message' => 'Permiso denegado. Se requieren permisos de administrador.']);
        $conn->close();
        exit();
    }
    
    $ids = isset($_POST['ids']) ? $_POST['ids'] : [];
    if (empty($ids)) {
        echo json_encode(['success' => false, 'message' => 'No se seleccionaron equipos.']);
        $conn->close();
        exit();
    }
    
    $ids_clean = array_filter(array_map('intval', $ids));
    if (empty($ids_clean)) {
        echo json_encode(['success' => false, 'message' => 'IDs de equipo inválidos.']);
        $conn->close();
        exit();
    }
    
    $in_clause = implode(',', $ids_clean);
    $motivo = isset($_POST['motivo']) ? $_POST['motivo'] : '';
    $safe_motivo = $conn->real_escape_string($motivo);
    
    $sql_update = "UPDATE inventario_soporte SET ultimo_responsable = IF(estatus <> 'Para Baja', personal_asignado, ultimo_responsable), estatus = 'Para Baja', personal_asignado = 'STOCK', fecha_baja = CURDATE(), motivo_baja = '$safe_motivo' WHERE id IN ($in_clause)";
    if ($conn->query($sql_update)) {
        echo json_encode(['success' => true, 'message' => 'Equipos dados de baja correctamente en el sistema.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al actualizar base de datos: ' . $conn->error]);
    }
    $conn->close();
    exit();
}

// 2. BACKEND: RESPUESTA AJAX (JSON)
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    
    $search_term = $_GET['q'] ?? '';
    $filtro_status = $_GET['s'] ?? 'ALL';
    $filtro_personal = $_GET['u'] ?? 'ALL'; 
    $pagina = isset($_GET['p']) && is_numeric($_GET['p']) ? (int)$_GET['p'] : 1;
    $registros_por_pagina = 15;
    $offset = ($pagina - 1) * $registros_por_pagina;

    $conditions = [];
    
    // Búsqueda general
    if (!empty($search_term)) {
        $safe_term = $conn->real_escape_string($search_term);
        $conditions[] = "(inv.num_inventario LIKE '%$safe_term%' OR 
                        inv.no_bien_mueble LIKE '%$safe_term%' OR 
                        tbi.nombre_tipo LIKE '%$safe_term%' OR 
                        inv.marca LIKE '%$safe_term%' OR 
                        inv.modelo LIKE '%$safe_term%' OR 
                        inv.num_serie LIKE '%$safe_term%' OR 
                        inv.descripcion LIKE '%$safe_term%' OR 
                        inv.personal_asignado LIKE '%$safe_term%' OR 
                        inv.nombre_ubicacion LIKE '%$safe_term%')";
    }

    // 1. Filtro por Estado/Stock
    if ($filtro_status === 'STOCK') {
        $conditions[] = "(inv.personal_asignado IS NULL OR inv.personal_asignado = '' OR inv.personal_asignado = 'STOCK' OR inv.personal_asignado = 'Sin Asignar') AND inv.estatus <> 'Para Baja'";
    } elseif ($filtro_status === 'STOCK_IDENTIFICADO') {
        $conditions[] = "(inv.personal_asignado IS NULL OR inv.personal_asignado = '' OR inv.personal_asignado = 'STOCK' OR inv.personal_asignado = 'Sin Asignar') AND (inv.estatus = 'IDENTIFICADO' OR inv.estatus IS NULL OR inv.estatus = '')";
    } elseif ($filtro_status === 'STOCK_NO_IDENTIFICADO') {
        $conditions[] = "(inv.personal_asignado IS NULL OR inv.personal_asignado = '' OR inv.personal_asignado = 'STOCK' OR inv.personal_asignado = 'Sin Asignar') AND inv.estatus = 'NO IDENTIFICADO'";
    } elseif ($filtro_status === 'BAJA') {
        $conditions[] = "inv.estatus = 'Para Baja'";
    } elseif ($filtro_status === 'ASIGNADO') {
        $conditions[] = "(inv.personal_asignado IS NOT NULL AND inv.personal_asignado <> '' AND inv.personal_asignado <> 'STOCK' AND inv.personal_asignado <> 'Sin Asignar') AND inv.estatus <> 'Para Baja'";
    } elseif ($filtro_status === 'INCONSISTENTE') {
        $conditions[] = "inv.personal_asignado IS NOT NULL 
                         AND inv.personal_asignado <> '' 
                         AND inv.personal_asignado <> 'STOCK' 
                         AND inv.personal_asignado <> 'Sin Asignar' 
                         AND inv.estatus <> 'Para Baja'
                         AND NOT EXISTS (
                             SELECT 1 FROM registros_ad r 
                             WHERE TRIM(REPLACE(CONCAT(r.nombres, ' ', COALESCE(r.apellido_paterno,''), ' ', COALESCE(r.apellido_materno,'')), '  ', ' ')) = inv.personal_asignado
                         )";
    }

    // 2. Filtro específico por personal
    if ($filtro_personal !== 'ALL' && $filtro_personal !== '' && $filtro_personal !== 'SIN_ASIGNAR' && $filtro_personal !== 'STOCK_IDENTIFICADO' && $filtro_personal !== 'STOCK_NO_IDENTIFICADO' && $filtro_personal !== 'BAJA' && $filtro_personal !== 'INCONSISTENTE') {
        $safe_user = $conn->real_escape_string($filtro_personal);
        if ($filtro_status === 'BAJA') {
            $conditions[] = "(inv.ultimo_responsable = '$safe_user' OR inv.personal_asignado = '$safe_user')";
        } else {
            $conditions[] = "inv.personal_asignado = '$safe_user'";
        }
    }

    $where_clause = count($conditions) > 0 ? " WHERE " . implode(" AND ", $conditions) : "";

    $count_sql = "SELECT COUNT(inv.id) AS total FROM inventario_soporte inv 
                  LEFT JOIN tipo_bien_inventario tbi ON inv.id_tipo_bien = tbi.id_tipo" . $where_clause;
    $resCount = $conn->query($count_sql);
    $total_registros = $resCount ? $resCount->fetch_assoc()['total'] : 0;
    $total_paginas = ceil($total_registros / $registros_por_pagina);

    $columnas = "inv.id, inv.num_inventario, inv.no_bien_mueble, tbi.nombre_tipo, inv.id_tipo_bien, inv.marca, inv.modelo, inv.num_serie, inv.descripcion, inv.personal_asignado, inv.ultimo_responsable, inv.fecha_baja, inv.motivo_baja, inv.nombre_ubicacion, inv.ruta_foto, inv.estatus, inv.descripcion_corta, inv.color, inv.no_inv_anterior, inv.area_asignacion, inv.oficio_entrega_sefiplan, inv.municipio";
    
    // Si es para PDF o para resguardo total, quitamos el límite de paginación
    $limit_clause = " LIMIT $registros_por_pagina OFFSET $offset";
    if (isset($_GET['all']) || isset($_GET['export'])) {
        $limit_clause = "";
    }

    $sql = "SELECT $columnas FROM inventario_soporte inv 
            LEFT JOIN tipo_bien_inventario tbi ON inv.id_tipo_bien = tbi.id_tipo 
            $where_clause ORDER BY inv.num_inventario DESC" . $limit_clause;

    $result = $conn->query($sql);
    $datos = [];
    if ($result) {
        while($row = $result->fetch_assoc()) { $datos[] = $row; }
    }

    echo json_encode([
        'data' => $datos,
        'meta' => ['pagina_actual' => $pagina, 'total_paginas' => $total_paginas, 'total_registros' => $total_registros]
    ]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Inventario Equipos Soporte</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="js/tailwindcss.js"></script>
    <link rel="stylesheet" href="css/all.min.css">
    <script src="js/sweetalert2.all.min.js"></script>
    <script src="js/session_timer.js"></script>
    <script src="js/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.25/jspdf.plugin.autotable.min.js"></script>
    <script src="js/xlsx.full.min.js"></script>
    <script src="js/exceljs.min.js"></script>
    <script src="js/jszip.min.js"></script>
    <!-- Fuentes Montserrat para jsPDF -->
    <script src="js/Montserrat-normal.js"></script>
    <script src="js/Montserrat-bold.js"></script>
    <script>
        const listaTiposGlobal = <?php echo json_encode($tipos_opciones); ?>;
        tailwind.config = {
            theme: { extend: { colors: { 'primary-dark': '#721538', 'primary-light': '#961e4b', 'background': '#d6d1ca' } } }
        }
    </script>
    <style>
        /* Estilos para inputs dentro de SweetAlert al editar */
        .swal-field-label { display: block; text-align: left; font-size: 0.75rem; font-weight: bold; color: #555; margin-bottom: 2px; text-transform: uppercase; }
        .swal-custom-input { width: 100% !important; margin: 0 0 12px 0 !important; font-size: 0.9rem !important; height: 40px !important; border: 1px solid #ccc; border-radius: 6px; padding: 0 10px; }
        .swal-custom-textarea { width: 100% !important; margin: 0 !important; font-size: 0.9rem !important; border: 1px solid #ccc; border-radius: 6px; padding: 10px; }
    </style>
</head>
<body class="p-4 sm:p-8 bg-background">

<div class="max-w-7xl mx-auto">
    <div class="flex flex-col sm:flex-row justify-between items-center mb-6">
        <h2 class="text-3xl font-bold text-primary-dark flex items-center gap-2">
            <i class="fas fa-laptop"></i> Inventario de Equipos 
            <span id="total-lbl" class="text-xs bg-gray-200 text-gray-600 px-3 py-1 rounded-full italic">...</span>
            <span id="seleccion-lbl" class="ml-2 text-xs bg-indigo-100 text-indigo-700 px-3 py-1 rounded-full font-bold hidden">0 seleccionados</span>
        </h2>
        <div id="loading" style="display:none;" class="animate-pulse text-primary-dark font-bold">
            <i class="fas fa-spinner fa-spin"></i> Cargando...
        </div>
    </div>

    <div class="bg-white p-4 rounded-xl shadow-md mb-6 flex flex-col lg:flex-row gap-4 items-center justify-between">
        <div class="relative w-full lg:flex-1">
            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400">
                <i class="fas fa-search"></i>
            </span>
            <input type="text" id="searchInput" placeholder="Buscar serie, modelo, inventario..." class="w-full pl-11 p-3 border border-gray-300 rounded-full focus:ring-2 focus:ring-primary-dark outline-none transition">
        </div>

        <div class="w-full lg:w-56">
            <select id="statusFilter" class="w-full p-3 border border-gray-300 rounded-full focus:ring-2 focus:ring-primary-dark outline-none bg-white cursor-pointer">
                <option value="ALL">📁 Todos los Equipos</option>
                <option value="STOCK" class="font-bold text-gray-700 bg-gray-100">📦 En STOCK (Todos)</option>
                <option value="STOCK_IDENTIFICADO" class="text-green-700 bg-green-50 font-medium">👉 En STOCK - Identificados</option>
                <option value="STOCK_NO_IDENTIFICADO" class="text-red-700 bg-red-50 font-medium">👉 En STOCK - No Identificados</option>
                <option value="ASIGNADO" class="text-blue-700 bg-blue-50 font-medium">👤 Equipos Asignados</option>
                <option value="INCONSISTENTE" class="text-orange-700 bg-orange-50 font-medium">⚠️ Ex-empleados / Inconsistentes</option>
                <option value="BAJA" class="font-bold text-red-600 bg-red-50">⛔ Equipos de BAJA</option>
            </select>
        </div>

        <div class="w-full lg:w-64">
            <select id="userFilter" class="w-full p-3 border border-gray-300 rounded-full focus:ring-2 focus:ring-primary-dark outline-none bg-white cursor-pointer">
                <option value="ALL">👤 Todos los responsables</option>
                <?php foreach($lista_personal as $nombre): 
                    if (strtoupper($nombre) === 'STOCK' || strtoupper($nombre) === 'SIN ASIGNAR') continue;
                ?>
                    <option value="<?= htmlspecialchars($nombre) ?>"><?= htmlspecialchars($nombre) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="relative w-full lg:w-auto flex justify-end">
            <button id="btnOpciones" class="bg-primary-dark hover:bg-primary-light text-white font-bold py-3 px-6 rounded-full shadow transition flex items-center gap-2 w-full lg:w-auto justify-center">
                <i class="fas fa-bars"></i> Opciones
            </button>

            <div id="dropdownOpciones" class="hidden absolute top-full mt-2 right-0 w-60 bg-white rounded-xl shadow-xl border border-gray-200 overflow-hidden z-50">
                <a href="registrar_inventario.php" class="block px-4 py-3 text-gray-700 hover:bg-indigo-50 hover:text-indigo-700 transition border-b border-gray-100 font-medium">
                    <i class="fas fa-plus w-6 text-center text-green-600"></i> Nuevo Registro
                </a>
                <button onclick="iniciarTraspaso()" class="w-full text-left px-4 py-3 text-gray-700 hover:bg-indigo-50 hover:text-indigo-700 transition border-b border-gray-100 font-medium">
                    <i class="fas fa-exchange-alt w-6 text-center text-blue-600"></i> Traspaso / Préstamo
                </button>
                <button onclick="iniciarResguardo()" class="w-full text-left px-4 py-3 text-gray-700 hover:bg-indigo-50 hover:text-indigo-700 transition border-b border-gray-100 font-medium">
                    <i class="fas fa-file-signature w-6 text-center text-purple-600"></i> Generar Resguardo
                </button>
                <button onclick="iniciarDictamenBaja()" class="w-full text-left px-4 py-3 text-gray-700 hover:bg-red-50 hover:text-red-700 transition border-b border-gray-100 font-medium">
                    <i class="fas fa-ban w-6 text-center text-red-600"></i> Generar Baja
                </button>
                <button onclick="exportarPDF()" class="w-full text-left px-4 py-3 text-gray-700 hover:bg-indigo-50 hover:text-indigo-700 transition border-b border-gray-100 font-medium">
                    <i class="fas fa-file-pdf w-6 text-center text-red-600"></i> Exportar PDF
                </button>
                <button onclick="exportarExcel()" class="w-full text-left px-4 py-3 text-gray-700 hover:bg-indigo-50 hover:text-indigo-700 transition border-b border-gray-100 font-medium">
                    <i class="fas fa-file-excel w-6 text-center text-green-600"></i> Exportar Excel
                </button>
                <a href="dashboard.php" class="block px-4 py-3 text-gray-700 hover:bg-gray-100 transition font-medium">
                    <i class="fas fa-home w-6 text-center text-gray-600"></i> Menú Principal
                </a>
            </div>
        </div>
    </div>

    <div class="bg-white shadow-lg rounded-xl overflow-hidden border border-gray-100 overflow-x-auto relative z-0">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-primary-dark text-white text-xs font-bold uppercase tracking-wider">
                <tr>
                    <th class="px-4 py-4 text-center w-12">
                        <input type="checkbox" id="selectAll" onclick="toggleSelectAll()" class="w-4 h-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 cursor-pointer">
                    </th>
                    <th class="px-4 py-4 text-center">Foto</th>
                    <th class="px-4 py-4 text-left">No. Bien Mueble</th>
                    <th class="px-4 py-4 text-left">Tipo / Marca</th>
                    <th class="px-4 py-4 text-left">Responsable</th>
                    <th class="px-4 py-4 text-left">Ubicación Física</th>
                    <th class="px-4 py-4 text-center">Acciones</th>
                </tr>
            </thead>
            <tbody id="tabla-resultados" class="text-sm divide-y divide-gray-100 bg-white"></tbody>
        </table>
    </div>

    <div id="paginacion-container" class="mt-8 flex flex-col sm:flex-row justify-between items-center gap-4 text-sm text-gray-600">
        <div id="paginacion-info" class="text-center sm:text-left"></div>
        <div id="paginacion-botones" class="flex items-center gap-1 flex-wrap justify-center"></div>
    </div>
</div>

<script>
    let paginaActual = 1;
    let terminoBusqueda = '';
    let filtroStatus = 'ALL';
    let filtroUsuario = 'ALL';
    let timeoutBusqueda = null;
    let datosActuales = []; 
    let datosOriginalesPagina = [];
    let equiposSeleccionadosMap = new Map(); // Variable para guardar selecciones globales
    let datosResponsableActual = null; // Para guardar los detalles del usuario filtrado
    const esAdmin = <?= $esAdmin ? 'true' : 'false' ?>;
    const BASE_URL_IMAGENES = '../inventario/';

    document.addEventListener('DOMContentLoaded', () => {
        cargarDatos();
        
        // Listener para búsqueda de texto
        document.getElementById('searchInput').addEventListener('input', (e) => {
            clearTimeout(timeoutBusqueda);
            terminoBusqueda = e.target.value;
            paginaActual = 1;
            timeoutBusqueda = setTimeout(() => { cargarDatos(); }, 300);
        });

        // Listener para el filtro de estado/stock
        document.getElementById('statusFilter').addEventListener('change', (e) => {
            filtroStatus = e.target.value;
            paginaActual = 1;
            equiposSeleccionadosMap.clear();
            actualizarContadorSeleccionados();

            // Si es un filtro de STOCK, inhabilitar o limpiar el filtro de responsables
            const userFilterEl = document.getElementById('userFilter');
            if (['STOCK', 'STOCK_IDENTIFICADO', 'STOCK_NO_IDENTIFICADO'].includes(filtroStatus)) {
                userFilterEl.value = 'ALL';
                filtroUsuario = 'ALL';
                userFilterEl.disabled = true;
                userFilterEl.classList.add('bg-gray-100', 'cursor-not-allowed');
            } else {
                userFilterEl.disabled = false;
                userFilterEl.classList.remove('bg-gray-100', 'cursor-not-allowed');
            }

            cargarDatos();
        });

        // Listener para el filtro de usuario
        document.getElementById('userFilter').addEventListener('change', (e) => {
            filtroUsuario = e.target.value;
            datosResponsableActual = null; // Limpiamos datos del responsable anterior
            paginaActual = 1;
            equiposSeleccionadosMap.clear(); // Limpiamos selecciones al cambiar de responsable
            actualizarContadorSeleccionados();

            // Si se selecciona un responsable, buscamos sus detalles
            if (filtroUsuario && filtroUsuario !== 'ALL') {
                fetch(`consultar_usuarios.php?ajax_details=1&nombre=${encodeURIComponent(filtroUsuario)}`)
                    .then(res => res.json())
                    .then(data => {
                        if (data.found) {
                            datosResponsableActual = data.details;
                        }
                    });
            }
            cargarDatos();
        });

        // Lógica del Menú de Hamburguesa
        const btnOpciones = document.getElementById('btnOpciones');
        const dropdownOpciones = document.getElementById('dropdownOpciones');

        btnOpciones.addEventListener('click', (e) => {
            e.stopPropagation();
            dropdownOpciones.classList.toggle('hidden');
        });

        document.addEventListener('click', (e) => {
            if (!dropdownOpciones.contains(e.target) && e.target !== btnOpciones) {
                dropdownOpciones.classList.add('hidden');
            }
        });

        // Autocompletado global para Personal Asignado en la edición
        fetch('consultar_usuarios.php?ajax_pdf=1')
            .then(res => res.json())
            .then(data => {
                const datalist = document.createElement('datalist');
                datalist.id = 'usuarios_sugeridos_inventario';
                data.forEach(u => {
                    if(u.nombre_completo) {
                        datalist.appendChild(new Option(u.nombre_completo, u.nombre_completo));
                    }
                });
                document.body.appendChild(datalist);
            }).catch(e => console.log('Error cargando usuarios:', e));
    });

    function cargarDatos() {
        document.getElementById('loading').style.display = 'block';
        const url = `consultar_inventario.php?ajax=1&q=${encodeURIComponent(terminoBusqueda)}&u=${encodeURIComponent(filtroUsuario)}&s=${encodeURIComponent(filtroStatus)}&p=${paginaActual}`;
        
        fetch(url)
            .then(res => res.json())
            .then(res => {
                datosOriginalesPagina = res.data;
                renderizarTabla(res.data);
                renderizarPaginacion(res.meta);
                document.getElementById('total-lbl').innerText = res.meta.total_registros;
            })
            .finally(() => document.getElementById('loading').style.display = 'none');
    }

    function renderizarTabla(datos) {
        let datosAMostrar = [...datos];
        if (terminoBusqueda === '') {
            // Obtener todos los elementos seleccionados en la sesión actual
            const seleccionados = Array.from(equiposSeleccionadosMap.values());
            // Filtrar los que no están presentes en la página actual para agregarlos
            const noPresentes = seleccionados.filter(sel => !datosAMostrar.some(d => Number(d.id) === Number(sel.id)));
            datosAMostrar = [...noPresentes, ...datosAMostrar];
            // Ordenar para dejar los seleccionados al principio de la tabla
            datosAMostrar.sort((a, b) => {
                const aSelected = equiposSeleccionadosMap.has(Number(a.id)) ? 1 : 0;
                const bSelected = equiposSeleccionadosMap.has(Number(b.id)) ? 1 : 0;
                return bSelected - aSelected;
            });
        }
        
        // Actualizar datosActuales para que el resto de funciones JS encuentren las filas inyectadas
        datosActuales = datosAMostrar;

        // Sincronizar checkbox selectAll
        const selectAllCheckbox = document.getElementById('selectAll');
        if (selectAllCheckbox) {
            const visibleCheckboxes = datosAMostrar.length > 0;
            const allVisibleSelected = visibleCheckboxes && datosAMostrar.every(row => equiposSeleccionadosMap.has(Number(row.id)));
            selectAllCheckbox.checked = allVisibleSelected;
        }

        const tabla = document.getElementById('tabla-resultados');
        tabla.innerHTML = datosAMostrar.length ? '' : '<tr><td colspan="7" class="p-10 text-center text-gray-500 italic">No se encontraron bienes para este filtro.</td></tr>';

        datosAMostrar.forEach(row => {
            let iconoFotoHTML = row.ruta_foto 
                ? `<button onclick="verImagen('${BASE_URL_IMAGENES}${row.ruta_foto}', '${row.no_bien_mueble || 'S/N'}')" class="text-indigo-600 hover:text-indigo-800 transition transform hover:scale-110"><i class="fas fa-image text-2xl"></i></button>`
                : `<span class="text-gray-300"><i class="fas fa-image text-2xl"></i></span>`;

            const tr = document.createElement('tr');
            tr.className = "hover:bg-[#fdf2f5] transition-colors duration-150 border-b border-gray-100";
            let isChecked = equiposSeleccionadosMap.has(Number(row.id)) ? 'checked' : '';
            
            // Renderizamos la fila, incluyendo el botón de edición si es administrador
            tr.innerHTML = `
                <td class="px-4 py-4 text-center">
                    <input type="checkbox" class="cb-equipo w-4 h-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 cursor-pointer" value="${row.id}" onchange="toggleSeleccion(this, ${row.id})" ${isChecked}>
                </td>
                <td class="px-4 py-4 text-center">${iconoFotoHTML}</td>
                <td class="px-4 py-4">
                    <span class="font-bold text-gray-800 block font-mono">#${row.no_bien_mueble || 'S/N'}</span>
                    <span class="text-xs text-gray-400 block mt-0.5 font-semibold">Inv: ${row.num_inventario || '---'}</span>
                </td>
                <td class="px-4 py-4">
                    <span class="text-xs text-gray-500 uppercase tracking-wide block mb-0.5 font-bold">${row.nombre_tipo || 'N/A'}</span>
                    <span class="text-sm font-semibold text-primary-dark">${row.marca || ''} ${row.modelo || ''}</span>
                </td>
                <td class="px-4 py-4">
                    ${row.personal_asignado && row.personal_asignado !== 'STOCK' && row.personal_asignado !== 'Sin Asignar' ? `
                        <button onclick="verEditarResponsable('${row.personal_asignado}')" class="font-bold text-gray-800 block hover:text-primary-dark hover:underline text-left transition focus:outline-none">
                            <i class="fas fa-user-circle text-gray-400 mr-1"></i> ${row.personal_asignado}
                        </button>
                    ` : `
                        <span class="text-gray-400 italic block text-xs font-semibold">
                            <i class="fas fa-box text-gray-300 mr-1"></i> EN STOCK
                        </span>
                        ${row.ultimo_responsable ? `
                            <span class="text-[11px] text-red-600 font-semibold block mt-0.5" title="Último usuario asignado antes de la baja">
                                <i class="fas fa-history text-red-400 mr-1"></i> Último: ${row.ultimo_responsable}
                            </span>
                        ` : ''}
                    `}
                    <span class="text-xs text-gray-500 block mt-1 font-medium">
                        Estatus: <span class="px-2 py-0.5 text-[10px] font-bold rounded-full ${row.estatus === 'Operativo' || row.estatus === 'Asignado' || row.estatus === 'IDENTIFICADO' ? 'bg-green-50 text-green-700 border border-green-100' : 'bg-red-50 text-red-700 border border-red-100'}">${row.estatus || 'Operativo'}</span>
                    </span>
                </td>
                <td class="px-4 py-4 font-medium text-gray-700 text-sm">
                    <div class="flex items-center gap-1">
                        <i class="fas fa-map-marker-alt text-gray-400 mr-1"></i>
                        <span>${row.nombre_ubicacion || '---'}</span>
                    </div>
                </td>
                <td class="px-4 py-4 text-center whitespace-nowrap space-x-1">
                    <button class="w-8 h-8 rounded-md bg-indigo-50 text-indigo-600 hover:bg-indigo-600 hover:text-white transition inline-flex items-center justify-center shadow-sm" onclick="verDetalles(${row.id})" title="Ver Detalles">
                        <i class="fas fa-eye"></i>
                    </button>
                    ${esAdmin ? `
                    <button class="w-8 h-8 rounded-md bg-blue-50 text-blue-600 hover:bg-blue-600 hover:text-white transition inline-flex items-center justify-center shadow-sm" onclick="editarBienCompleto(${row.id})" title="Editar Información">
                        <i class="fas fa-pencil-alt"></i>
                    </button>
                    ` : ''}
                </td>
            `;
            tabla.appendChild(tr);
        });
    }

    // --- FUNCIÓN RESTAURADA PARA EDITAR EL BIEN ---
    function editarBienCompleto(id) {
        const row = datosActuales.find(r => r.id == id);
        if(!row) return;

        let optionsTipo = listaTiposGlobal.map(t => 
            `<option value="${t.id_tipo}" ${t.id_tipo == row.id_tipo_bien ? 'selected' : ''}>${t.nombre_tipo}</option>`
        ).join('');

        let optionsEstatus = ['IDENTIFICADO', 'NO IDENTIFICADO', 'Para Baja'].map(e =>
            `<option value="${e}" ${e == (row.estatus || 'IDENTIFICADO') ? 'selected' : ''}>${e}</option>`
        ).join('');

        Swal.fire({
            title: '<div class="text-xl font-bold border-b pb-2">Editar Información del Bien</div>',
            html: `
                <div class="text-left mt-4">
                    <label class="swal-field-label">Número de Inventario</label>
                    <input id="swal-inv" class="swal-custom-input" value="${row.num_inventario}">
                    
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="swal-field-label">Tipo de Bien</label>
                            <select id="swal-tipo" class="swal-custom-input bg-white">${optionsTipo}</select>
                        </div>
                        <div>
                            <label class="swal-field-label">Estatus</label>
                            <select id="swal-estatus" class="swal-custom-input bg-white">${optionsEstatus}</select>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <div class="flex justify-between items-center mb-1">
                                <label class="swal-field-label !mb-0">Personal Asignado</label>
                                <button type="button" onclick="document.getElementById('swal-pers').value='STOCK'; document.getElementById('swal-ubi').value=''" class="text-[10px] text-blue-600 hover:text-blue-800 font-bold focus:outline-none transition">
                                    <i class="fas fa-box"></i> Enviar a Stock
                                </button>
                            </div>
                            <input id="swal-pers" class="swal-custom-input" list="usuarios_sugeridos_inventario" value="${row.personal_asignado || ''}" placeholder="Ej. Juan Pérez">
                        </div>
                        <div>
                            <label class="swal-field-label">Último Responsable (Baja)</label>
                            <input id="swal-ultimo-pers" class="swal-custom-input" list="usuarios_sugeridos_inventario" value="${row.ultimo_responsable || ''}" placeholder="Ej. Wendi Beatriz Batun">
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="swal-field-label">Área / Ubicación</label>
                            <input id="swal-ubi" class="swal-custom-input" value="${row.nombre_ubicacion || ''}">
                        </div>
                        <div>
                            <label class="swal-field-label">Número de Serie</label>
                            <input id="swal-serie" class="swal-custom-input" value="${row.num_serie}">
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="swal-field-label">Marca</label>
                            <input id="swal-marca" class="swal-custom-input" value="${row.marca}">
                        </div>
                        <div>
                            <label class="swal-field-label">Modelo</label>
                            <input id="swal-modelo" class="swal-custom-input" value="${row.modelo}">
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3 mb-2">
                        <div>
                            <label class="swal-field-label">Fecha de Baja</label>
                            <input id="swal-fecha-baja" type="date" class="swal-custom-input" value="${row.fecha_baja || ''}">
                        </div>
                        <div>
                            <label class="swal-field-label">Motivo de Baja</label>
                            <input id="swal-motivo-baja" class="swal-custom-input" value="${row.motivo_baja || ''}" placeholder="Ej. Daño irreparable en tarjeta madre">
                        </div>
                    </div>

                    <label class="swal-field-label">Descripción / Notas</label>
                    <textarea id="swal-desc" class="swal-custom-textarea" rows="3">${row.descripcion || ''}</textarea>
                </div>
            `,
            width: '600px',
            focusConfirm: false,
            showCancelButton: true,
            confirmButtonText: '<i class="fas fa-save mr-2"></i> Guardar Cambios',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#721538',
            didOpen: () => {
                const inputPers = document.getElementById('swal-pers');
                const inputUbi = document.getElementById('swal-ubi');
                inputPers.addEventListener('change', () => {
                    if(inputPers.value) {
                        fetch(`consultar_usuarios.php?ajax_details=1&nombre=${encodeURIComponent(inputPers.value)}`)
                            .then(r => r.json())
                            .then(d => {
                                if (d.found && d.details.area && !inputUbi.value) {
                                    inputUbi.value = d.details.area;
                                }
                            });
                    }
                });
            },
            preConfirm: () => {
                return {
                    id: id,
                    num_inventario: document.getElementById('swal-inv').value,
                    id_tipo_bien: document.getElementById('swal-tipo').value,
                    nombre_ubicacion: document.getElementById('swal-ubi').value,
                    marca: document.getElementById('swal-marca').value,
                    modelo: document.getElementById('swal-modelo').value,
                    num_serie: document.getElementById('swal-serie').value,
                    personal_asignado: document.getElementById('swal-pers').value,
                    ultimo_responsable: document.getElementById('swal-ultimo-pers').value,
                    fecha_baja: document.getElementById('swal-fecha-baja').value,
                    motivo_baja: document.getElementById('swal-motivo-baja').value,
                    estatus: document.getElementById('swal-estatus').value,
                    descripcion: document.getElementById('swal-desc').value
                }
            }
        }).then((result) => {
            if (result.isConfirmed) {
                const formData = new FormData();
                for (let key in result.value) { formData.append(key, result.value[key]); }

                fetch('actualizar_inventario.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if(data.success) {
                        Swal.fire('¡Éxito!', 'La información se ha actualizado correctamente.', 'success');
                        cargarDatos(); // Refresca la tabla automáticamente
                    } else {
                        Swal.fire('Error', data.message, 'error');
                    }
                });
            }
        });
    }

    function verDetalles(id) {
        const row = datosActuales.find(r => r.id == id);
        if(!row) return;

        const detallesHtml = `
            <div class="text-left text-sm text-gray-700 p-2">
                <div class="grid grid-cols-2 gap-4 mb-4 border-b pb-3">
                    <div>
                        <span class="block text-xs text-gray-400 uppercase font-bold">No. Bien Mueble</span>
                        <span class="font-extrabold text-lg text-primary-dark">#${row.no_bien_mueble || 'S/N'}</span>
                    </div>
                    <div>
                        <span class="block text-xs text-gray-400 uppercase font-bold">Responsable</span>
                        <span class="font-semibold text-gray-800 text-sm">${row.personal_asignado || 'STOCK'}</span>
                        ${row.ultimo_responsable ? `<span class="block text-xs text-red-600 font-semibold mt-0.5">(Último: ${row.ultimo_responsable})</span>` : ''}
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4 mb-3 border-b pb-3">
                    <div>
                        <span class="block text-xs text-gray-400 uppercase font-bold">Marca / Modelo</span>
                        <span class="font-medium text-gray-800">${row.marca || 'S/M'} - ${row.modelo || 'S/M'}</span>
                    </div>
                    <div>
                        <span class="block text-xs text-gray-400 uppercase font-bold">Ubicación</span>
                        <span class="font-medium text-gray-800">${row.nombre_ubicacion || 'S/A'}</span>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4 mb-3 border-b pb-3">
                    <div>
                        <span class="block text-xs text-gray-400 uppercase font-bold">Número de Serie</span>
                        <span class="font-mono bg-gray-100 px-2 py-0.5 rounded text-gray-800 border text-xs inline-block mt-0.5">${row.num_serie || 'S/S'}</span>
                    </div>
                    <div>
                        <span class="block text-xs text-gray-400 uppercase font-bold">Categoría / Tipo</span>
                        <span class="font-medium text-gray-800 uppercase text-xs">${row.nombre_tipo || 'N/A'}</span>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4 mb-3 border-b pb-3">
                    <div>
                        <span class="block text-xs text-gray-400 uppercase font-bold">Inventario Anterior</span>
                        <span class="font-medium text-gray-800">${row.no_inv_anterior || '---'}</span>
                    </div>
                    <div>
                        <span class="block text-xs text-gray-400 uppercase font-bold">Color</span>
                        <span class="font-medium text-gray-800">${row.color || '---'}</span>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4 mb-3 border-b pb-3">
                    <div>
                        <span class="block text-xs text-gray-400 uppercase font-bold">Área de Asignación</span>
                        <span class="font-medium text-gray-800 text-xs">${row.area_asignacion || '---'}</span>
                    </div>
                    <div>
                        <span class="block text-xs text-gray-400 uppercase font-bold">Oficio SEFIPLAN / Municipio</span>
                        <span class="font-medium text-gray-800 text-xs">${row.oficio_entrega_sefiplan || '---'} ${row.municipio ? `(Mpo: ${row.municipio})` : ''}</span>
                    </div>
                </div>

                ${row.estatus === 'Para Baja' || row.fecha_baja || row.motivo_baja ? `
                <div class="grid grid-cols-2 gap-4 mb-4 border-b pb-3 bg-red-50 p-2.5 rounded-lg border border-red-100">
                    <div><span class="block text-xs text-red-500 uppercase font-bold">Fecha de Baja</span><span class="font-semibold text-gray-800">${row.fecha_baja || 'No registrada'}</span></div>
                    <div><span class="block text-xs text-red-500 uppercase font-bold">Motivo de Baja</span><span class="font-semibold text-gray-800">${row.motivo_baja || 'No registrado'}</span></div>
                </div>
                ` : ''}

                ${row.descripcion_corta ? `
                <div class="bg-gray-50 p-2.5 rounded-lg border mb-3">
                    <span class="block text-[11px] text-gray-500 uppercase font-bold mb-1">Descripción Corta / Rubro:</span>
                    <span class="text-gray-800 font-semibold text-xs">${row.descripcion_corta}</span>
                </div>
                ` : ''}

                <div class="bg-[#f7fcf7] p-3.5 rounded-lg border border-green-100">
                    <span class="block text-xs text-green-700 uppercase font-bold mb-2">Descripción Completa / Características:</span>
                    <span class="whitespace-pre-line text-gray-800 italic text-xs leading-relaxed">${row.descripcion || 'Sin descripción adicional.'}</span>
                </div>
            </div>
        `;

        Swal.fire({
            title: '<div class="text-xl font-bold border-b pb-2"><i class="fas fa-info-circle text-primary-dark mr-2"></i> Detalles del Bien</div>',
            html: detallesHtml,
            width: '600px',
            confirmButtonText: 'Cerrar',
            confirmButtonColor: '#721538'
        });
    }

    function verImagen(ruta, num_bien) {
        Swal.fire({ title: `Evidencia: ${num_bien}`, imageUrl: ruta, imageWidth: '100%', confirmButtonText: 'Cerrar', confirmButtonColor: '#721538' });
    }

    function actualizarContadorSeleccionados() {
        const lbl = document.getElementById('seleccion-lbl');
        if (equiposSeleccionadosMap.size > 0) {
            lbl.innerText = `${equiposSeleccionadosMap.size} seleccionado(s)`;
            lbl.classList.remove('hidden');
        } else {
            lbl.classList.add('hidden');
        }
    }

    function toggleSeleccion(cb, id) {
        if (cb.checked) {
            const row = datosActuales.find(r => r.id == id);
            if (row) equiposSeleccionadosMap.set(Number(id), row);
        } else {
            equiposSeleccionadosMap.delete(Number(id));
            // Si el elemento no pertenece al set de datos originales de la página actual,
            // al desmarcarlo lo removemos de la tabla para que no quede huérfano.
            const belongsToPage = datosOriginalesPagina.some(d => Number(d.id) === Number(id));
            if (!belongsToPage) {
                renderizarTabla(datosOriginalesPagina);
                return;
            }
        }
        actualizarContadorSeleccionados();
        
        // Sincronizar selectAll
        const selectAllCheckbox = document.getElementById('selectAll');
        if (selectAllCheckbox) {
            const checkboxes = document.querySelectorAll('.cb-equipo');
            const allChecked = checkboxes.length > 0 && Array.from(checkboxes).every(c => c.checked);
            selectAllCheckbox.checked = allChecked;
        }
    }

    function toggleSelectAll() {
        const isChecked = document.getElementById('selectAll').checked;
        const checkboxes = document.querySelectorAll('.cb-equipo');
        checkboxes.forEach(cb => {
            cb.checked = isChecked;
            const id = Number(cb.value);
            if (isChecked) {
                const row = datosActuales.find(r => r.id == id);
                if (row) equiposSeleccionadosMap.set(id, row);
            } else {
                equiposSeleccionadosMap.delete(id);
            }
        });
        actualizarContadorSeleccionados();
        
        // Si desmarcamos todo y hay elementos prepended, llamamos a renderizarTabla para removerlos
        if (!isChecked) {
            renderizarTabla(datosOriginalesPagina);
        }
    }

    function iniciarTraspaso() {
        if (equiposSeleccionadosMap.size === 0) {
            Swal.fire({ icon: 'warning', title: 'Atención', text: 'Debes seleccionar al menos un equipo marcando su casilla a la izquierda.', confirmButtonColor: '#721538' });
            return;
        }
        const bienes = Array.from(equiposSeleccionadosMap.values());
        const tieneBajas = bienes.some(bien => bien.estatus === 'Para Baja');
        if (tieneBajas) {
            Swal.fire({ icon: 'error', title: 'Operación denegada', text: 'No se pueden transferir o prestar equipos que están dados de baja.', confirmButtonColor: '#721538' });
            return;
        }
        const ids = Array.from(equiposSeleccionadosMap.keys());
        window.location.href = `generar_traspaso.php?equipos=${ids.join(',')}`;
    }

    function namesMatch(userDetail, targetName) {
        if (!userDetail || !targetName) return false;
        const normalize = (str) => str.normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLowerCase().trim().replace(/[^a-z0-9\s]/g, "");
        const targetWords = normalize(targetName).split(/\s+/).filter(w => w.length > 0);
        const userFullName = `${userDetail.nombres || ''} ${userDetail.apellido_paterno || ''} ${userDetail.apellido_materno || ''}`;
        const userWords = normalize(userFullName).split(/\s+/).filter(w => w.length > 0);
        return targetWords.length === userWords.length && targetWords.every(word => userWords.includes(word));
    }

    function findUserInList(targetName, usersList) {
        if (!targetName) return null;
        
        const normalize = (str) => {
            return str.normalize("NFD")
                      .replace(/[\u0300-\u036f]/g, "")
                      .toLowerCase()
                      .trim()
                      .replace(/[^a-z0-9\s]/g, "");
        };

        const targetNormalized = normalize(targetName);
        const targetWords = targetNormalized.split(/\s+/).filter(w => w.length > 0);
        
        if (targetWords.length === 0) return null;

        // Intentar coincidencia exacta de todas las palabras
        for (const u of usersList) {
            const userFullName = `${u.nombres || ''} ${u.apellido_paterno || ''} ${u.apellido_materno || ''}`;
            const userNormalized = normalize(userFullName);
            const userWords = userNormalized.split(/\s+/).filter(w => w.length > 0);

            if (targetWords.length === userWords.length) {
                const allMatch = targetWords.every(word => userWords.includes(word));
                if (allMatch) return u;
            }
        }
        
        // Fallback: coincidencia parcial (si todas las palabras de búsqueda están en el nombre del usuario)
        for (const u of usersList) {
            const userFullName = `${u.nombres || ''} ${u.apellido_paterno || ''} ${u.apellido_materno || ''}`;
            const userNormalized = normalize(userFullName);
            const userWords = userNormalized.split(/\s+/).filter(w => w.length > 0);

            const allMatch = targetWords.every(word => userWords.includes(word));
            if (allMatch) return u;
        }

        return null;
    }

    async function iniciarResguardo() {
        const statusFilterVal = document.getElementById('statusFilter').value;
        const filtroPersonal = document.getElementById('userFilter').value;

        // Caso 1: Generación de resguardo masivo en ZIP (Todos los responsables)
        if (equiposSeleccionadosMap.size === 0 && (filtroPersonal === 'ALL' || !filtroPersonal)) {
            if (statusFilterVal === 'BAJA') {
                Swal.fire({ icon: 'error', title: 'Operación denegada', text: 'No se puede generar resguardo para equipos que están dados de baja.', confirmButtonColor: '#721538' });
                return;
            }
            if (['STOCK', 'STOCK_IDENTIFICADO', 'STOCK_NO_IDENTIFICADO'].includes(statusFilterVal)) {
                Swal.fire({ icon: 'warning', title: 'Atención', text: 'El resguardo se genera para equipos asignados a un responsable. El stock no tiene responsable asignado.', confirmButtonColor: '#721538' });
                return;
            }

            const confirmBatch = await Swal.fire({
                title: '¿Generar todos los resguardos?',
                text: 'Se descargará un archivo ZIP con los resguardos en formato PDF agrupados individualmente por cada responsable.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#721538',
                confirmButtonText: 'Sí, generar ZIP',
                cancelButtonText: 'Cancelar'
            });

            if (!confirmBatch.isConfirmed) return;

            Swal.fire({
                title: 'Obteniendo datos...',
                html: 'Espere mientras se recopilan los datos de los usuarios y equipos...',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading()
            });

            try {
                // 1. Obtener detalles de todos los usuarios
                const resUsers = await fetch('consultar_usuarios.php?ajax_pdf=1');
                const usersData = await resUsers.json();

                // 2. Obtener todos los equipos asignados activos
                const resInv = await fetch('consultar_inventario.php?ajax=1&all=1&s=ASIGNADO');
                const invData = await resInv.json();

                if (!invData.data || invData.data.length === 0) {
                    Swal.fire('Atención', 'No hay equipos asignados a ningún responsable actualmente.', 'info');
                    return;
                }

                // 3. Agrupar por responsable
                const groups = {};
                invData.data.forEach(item => {
                    const resp = (item.personal_asignado || '').trim();
                    if (resp && resp !== 'STOCK' && resp !== 'Sin Asignar' && resp !== 'STOCK_IDENTIFICADO' && resp !== 'STOCK_NO_IDENTIFICADO') {
                        if (!groups[resp]) groups[resp] = [];
                        groups[resp].push(item);
                    }
                });

                const responsblesList = Object.keys(groups);
                if (responsblesList.length === 0) {
                    Swal.fire('Atención', 'No hay equipos asignados a ningún responsable actualmente.', 'info');
                    return;
                }

                // 4. Generar PDFs y comprimir
                const zip = new JSZip();
                
                Swal.fire({
                    title: 'Generando resguardos...',
                    html: `Procesando resguardos en PDF... <br><b id="progress-zip">0</b> de ${responsblesList.length}`,
                    allowOutsideClick: false,
                    didOpen: () => Swal.showLoading()
                });

                let generatedCount = 0;
                
                const promises = responsblesList.map(async (respName) => {
                    const userDetail = findUserInList(respName, usersData) || {};
                    const datosTrabajador = {
                        nombre: respName,
                        cargo: userDetail.cargo || '_______________________________',
                        num_empleado: userDetail.num_empleado || '_______________________________',
                        area: userDetail.nombre_direccion || userDetail.area || groups[respName][0].nombre_ubicacion || '_______________________________',
                        jefe_inmediato: userDetail.jefe_inmediato || '_______________________________',
                        correo: userDetail.correo_electronico || userDetail.correo || '_______________________________',
                        telefono: userDetail.telefono || '_______________________________'
                    };

                    const doc = await generarHojaResguardo(datosTrabajador, groups[respName], false);
                    const pdfBlob = doc.output('blob');
                    const sanitizedName = respName.replace(/[^a-zA-Z0-9]/g, '_');
                    zip.file(`Resguardo_${sanitizedName}.pdf`, pdfBlob);

                    generatedCount++;
                    const progressEl = document.getElementById('progress-zip');
                    if (progressEl) progressEl.innerText = generatedCount;
                });

                await Promise.all(promises);

                Swal.fire({
                    title: 'Comprimiendo carpeta...',
                    text: 'Creando archivo ZIP...',
                    allowOutsideClick: false,
                    didOpen: () => Swal.showLoading()
                });

                const content = await zip.generateAsync({ type: 'blob' });
                const link = document.createElement('a');
                link.href = URL.createObjectURL(content);
                link.download = `Resguardos_Todos_${new Date().toISOString().slice(0,10)}.zip`;
                link.click();
                URL.revokeObjectURL(link.href);
                Swal.close();

            } catch (err) {
                console.error(err);
                Swal.fire('Error', 'Ocurrió un error al generar el archivo ZIP de resguardos.', 'error');
            }
            return;
        }

        // Caso 2: Resguardo individual/manual (lógica existente adaptada)
        if (equiposSeleccionadosMap.size > 0) {
            let bienes = Array.from(equiposSeleccionadosMap.values());
            const tieneBajas = bienes.some(bien => bien.estatus === 'Para Baja');
            if (tieneBajas) {
                Swal.fire({ icon: 'error', title: 'Operación denegada', text: 'No se puede generar resguardo para equipos que están dados de baja.', confirmButtonColor: '#721538' });
                return;
            }
            let responsable = '';
            bienes.forEach(bien => {
                if(!responsable && bien.personal_asignado && bien.personal_asignado !== 'STOCK') responsable = bien.personal_asignado;
            });

            if (responsable && (!datosResponsableActual || !namesMatch(datosResponsableActual, responsable))) {
                Swal.fire({ title: 'Obteniendo datos del responsable...', didOpen: () => Swal.showLoading() });
                fetch(`consultar_usuarios.php?ajax_details=1&nombre=${encodeURIComponent(responsable)}`)
                    .then(res => res.json())
                    .then(data => {
                        Swal.close();
                        datosResponsableActual = data.found ? data.details : null;
                        pedirDatosResguardo(bienes, responsable);
                    })
                    .catch(() => {
                        Swal.close();
                        pedirDatosResguardo(bienes, responsable);
                    });
            } else {
                pedirDatosResguardo(bienes, responsable || filtroPersonal);
            }
        } else {
            // Filtro por responsable seleccionado
            Swal.fire({ title: 'Obteniendo datos...', didOpen: () => Swal.showLoading() });
            
            // Primero aseguramos tener los datos del responsable
            fetch(`consultar_usuarios.php?ajax_details=1&nombre=${encodeURIComponent(filtroPersonal)}`)
                .then(res => res.json())
                .then(userData => {
                    datosResponsableActual = userData.found ? userData.details : null;
                    return fetch(`consultar_inventario.php?ajax=1&all=1&u=${encodeURIComponent(filtroPersonal)}&s=${encodeURIComponent(statusFilterVal)}`);
                })
                .then(res => res.json())
                .then(res => {
                    Swal.close();
                    if(res.data && res.data.length > 0) {
                        pedirDatosResguardo(res.data, filtroPersonal);
                    } else {
                        Swal.fire('Atención', 'Este usuario no tiene equipos asignados.', 'info');
                    }
                })
                .catch(() => {
                    Swal.close();
                    Swal.fire('Error', 'No se pudieron obtener los datos.', 'error');
                });
        }
    }

    function pedirDatosResguardo(bienes, responsableNombre) {
        let responsable = responsableNombre || '';
        let ubicacion = datosResponsableActual ? datosResponsableActual.area : '';
        let num_empleado = datosResponsableActual ? datosResponsableActual.num_empleado : '';
        let correo = datosResponsableActual ? datosResponsableActual.correo : '';
        let cargo = datosResponsableActual ? datosResponsableActual.cargo : '';
        let telefono = datosResponsableActual ? datosResponsableActual.telefono : '';
        let jefe_inmediato = datosResponsableActual ? (datosResponsableActual.jefe_inmediato || '') : '';

        // Recolectar datos de los equipos seleccionados
        if (!ubicacion) {
            bienes.forEach(bien => {
                if(!ubicacion && bien.nombre_ubicacion) ubicacion = bien.nombre_ubicacion;
            });
        }

        Swal.fire({
            title: '<div class="text-xl font-bold border-b pb-2">Datos del Resguardante</div>',
            html: `
                <div class="text-left mt-4 text-sm">
                    <label class="swal-field-label">Nombre del Resguardante</label>
                    <input id="resg-nombre" class="swal-custom-input" value="${responsable}">
                    
                    <label class="swal-field-label">Cargo</label>
                    <input id="resg-cargo" class="swal-custom-input" placeholder="Ej. Jefe de Departamento" value="${cargo || ''}">
                    
                    <label class="swal-field-label">Número de Empleado</label>
                    <input id="resg-num-emp" class="swal-custom-input" placeholder="Ej. 12345" value="${num_empleado || ''}">
                    
                    <label class="swal-field-label">Área o Departamento</label>
                    <input id="resg-area" class="swal-custom-input" value="${ubicacion || ''}">

                    <label class="swal-field-label">Jefe Inmediato</label>
                    <input id="resg-jefe" class="swal-custom-input" placeholder="Ej. Juan Pérez" value="${jefe_inmediato || ''}">
                    
                    <label class="swal-field-label">Correo Electrónico</label>
                    <input id="resg-correo" class="swal-custom-input" placeholder="Ej. correo@ejemplo.com" value="${correo || ''}">
                    
                    <label class="swal-field-label">Teléfono</label>
                    <input id="resg-telefono" class="swal-custom-input" placeholder="Ej. 984 123 4567" value="${telefono || ''}">
                </div>
            `,
            width: '600px',
            showCancelButton: true,
            confirmButtonText: '<i class="fas fa-file-signature"></i> Generar Resguardo',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#721538',
            preConfirm: () => {
                return {
                    nombre: document.getElementById('resg-nombre').value || '_______________________________',
                    cargo: document.getElementById('resg-cargo').value || '_______________________________',
                    num_empleado: document.getElementById('resg-num-emp').value || '_______________________________',
                    area: document.getElementById('resg-area').value || '_______________________________',
                    jefe_inmediato: document.getElementById('resg-jefe').value || '_______________________________',
                    correo: document.getElementById('resg-correo').value || '_______________________________',
                    telefono: document.getElementById('resg-telefono').value || '_______________________________'
                }
            }
        }).then((result) => {
            if (result.isConfirmed) {
                generarHojaResguardo(result.value, bienes);
            }
        });
    }

    function generarHojaResguardo(datosTrabajador, bienes, shouldSave = true) {
        // Función para limpiar acentos y evitar los '?' en el PDF
        const limpiarTexto = (texto) => {
            if (!texto) return '';
            return texto.toString();
        };

        const { jsPDF } = window.jspdf;
        const doc = new jsPDF('l', 'pt', 'letter');

        const logoGob = new Image();
        logoGob.src = 'img/logo_gobierno.png'; // Asume que tienes este logo en /img/

        const logoHacienda = new Image();
        logoHacienda.src = 'img/logo_hacienda.png'; // Asume que tienes este logo en /img/

        return Promise.all([
            new Promise((resolve) => { logoGob.onload = resolve; logoGob.onerror = resolve; }),
            new Promise((resolve) => { logoHacienda.onload = resolve; logoHacienda.onerror = resolve; })
        ]).then(() => {
            const marginLeft = 40;
            const marginRight = 40;
            const pageHeight = doc.internal.pageSize.getHeight();
            const pageWidth = doc.internal.pageSize.getWidth();
            let startY = 20;

            // Logo de Gobierno un poco más ancho y alto
            if (logoGob.width > 0) {
                doc.addImage(logoGob, 'PNG', marginLeft, startY, 130, 45);
            }
            startY += 50;

            doc.setFontSize(13);
            doc.setFont("Montserrat", "bold");
            doc.text("Anexo de resguardo de Bienes Muebles", pageWidth / 2, startY, { align: "center" });
            startY += 20;

            doc.setFontSize(10);
            doc.text("I.    DATOS DE DEPENDENCIA", marginLeft, startY);
            startY += 10;
            
            const fechaActual = new Date().toLocaleDateString('es-MX', { day: '2-digit', month: '2-digit', year: 'numeric' });

            doc.autoTable({
                startY: startY,
                head: [['Conceptos', 'Datos']],
                body: [
                    ['Unidad de adscripcion', 'Hacienda del Estado de Quintana Roo / SATQ'],
                    ['Ubicacion', limpiarTexto(datosTrabajador.area)],
                    ['Jefe inmediato', limpiarTexto(datosTrabajador.jefe_inmediato || '_______________________________')],
                    ['Fecha de elaboracion', fechaActual]
                ],
                theme: 'grid',
                headStyles: { fillColor: [240, 240, 240], textColor: [0, 0, 0], fontStyle: 'bold', halign: 'center' },
                styles: { font: 'Montserrat', fontSize: 9, cellPadding: 2.5, textColor: [0,0,0], lineColor: [0,0,0], lineWidth: 0.5 },
                columnStyles: { 0: { cellWidth: 150, fontStyle: 'bold' } }
            });
            startY = doc.lastAutoTable.finalY + 15;

            doc.setFont("Montserrat", "bold");
            doc.text("II.   DATOS DEL TRABAJADOR", marginLeft, startY);
            startY += 10;

            doc.autoTable({
                startY: startY,
                head: [['Conceptos', 'Datos']],
                body: [
                    ['Nombre del resguardante', limpiarTexto(datosTrabajador.nombre)],
                    ['Cargo', limpiarTexto(datosTrabajador.cargo)],
                    ['Numero de empleado', limpiarTexto(datosTrabajador.num_empleado)],
                    ['Area o Departamento', limpiarTexto(datosTrabajador.area)],
                    ['Correo electronico', limpiarTexto(datosTrabajador.correo)],
                    ['Telefono de contacto', limpiarTexto(datosTrabajador.telefono)]
                ],
                theme: 'grid',
                headStyles: { fillColor: [240, 240, 240], textColor: [0, 0, 0], fontStyle: 'bold', halign: 'center' },
                styles: { font: 'Montserrat', fontSize: 9, cellPadding: 2.5, textColor: [0,0,0], lineColor: [0,0,0], lineWidth: 0.5 },
                columnStyles: { 0: { cellWidth: 150, fontStyle: 'bold' } }
            });
            startY = doc.lastAutoTable.finalY + 20;

            doc.setFont("Montserrat", "bold");
            doc.text("III.  DETALLE DE BIENES EN RESGUARDO", marginLeft, startY);
            startY += 10;

            const tablaBienes = bienes.map((bien, index) => {
                const descripcion = `${bien.nombre_tipo || ''} ${bien.marca || ''} ${bien.modelo || ''} - ${bien.descripcion || ''}`.trim();
                return [
                    (index + 1).toString(),
                    limpiarTexto(descripcion),
                    bien.no_bien_mueble || 'S/N',
                    bien.num_serie || 'S/N',
                    'Buen Estado',
                    limpiarTexto(bien.nombre_ubicacion || datosTrabajador.area),
                    'N/A'
                ];
            });

            doc.autoTable({
                startY: startY,
                margin: { bottom: 85 },
                head: [['No.', 'Descripcion del bien', 'B.M', 'No. Serie', 'Estado', 'Ubicacion fisica', 'Observaciones']],
                body: tablaBienes,
                theme: 'grid',
                rowPageBreak: 'avoid',
                headStyles: { fillColor: [240, 240, 240], textColor: [0, 0, 0], fontStyle: 'bold', halign: 'center' },
                styles: { font: 'Montserrat', fontSize: 8, cellPadding: 3, textColor: [0,0,0], lineColor: [0,0,0], lineWidth: 0.5, valign: 'middle' },
                columnStyles: { 
                    0: { cellWidth: 25, halign: 'center' }, 
                    1: { cellWidth: 'auto' }, 
                    2: { cellWidth: 55, halign: 'center' }, 
                    3: { cellWidth: 85, halign: 'center' }, 
                    4: { cellWidth: 65, halign: 'center' }, 
                    5: { cellWidth: 150 }, 
                    6: { cellWidth: 65, halign: 'center' } 
                }
            });
            startY = doc.lastAutoTable.finalY + 20;

            doc.setFont("Montserrat", "bold");
            doc.setFontSize(10);
            doc.text("IV. DECLARACIÓN DE RESPONSABILIDAD", marginLeft, startY);
            startY += 15;
            
            doc.setFontSize(9);
            const declaracion = "El trabajador antes mencionado declara bajo protesta de decir verdad que recibe en resguardo personal los bienes descritos en el presente formato y se compromete a hacer uso adecuado de ellos, así como a devolverlos en buen estado cuando le sea requerido.";
            const splitText = doc.splitTextToSize(declaracion, doc.internal.pageSize.getWidth() - (marginLeft * 2));
            doc.text(splitText, marginLeft, startY);
            
            startY += (splitText.length * 12) + 20;

            // Si no hay suficiente espacio para las firmas (alto aprox. 140 pt) en la página actual antes del pie de página, agregamos una nueva
            if (startY > (pageHeight - 195)) {
                doc.addPage();
                startY = 40;
            }

            doc.setFont("Montserrat", "bold");
            doc.setFontSize(10);
            doc.text("V. FIRMAS", marginLeft, startY);
            startY += 10;

            doc.autoTable({
                startY: startY,
                head: [['Nombre y Firma del Resguardante', 'Vo. Bo. Jefe Inmediato', 'Responsable de Inventario']],
                body: [
                    [`\n\n\n${limpiarTexto(datosTrabajador.nombre)}`, `\n\n\n${limpiarTexto(datosTrabajador.jefe_inmediato)}`, '\n\n\nLeydi del Pilar Ulloa Ramirez'],
                    [`Fecha: ${fechaActual}`, `Fecha: ${fechaActual}`, `Fecha: ${fechaActual}`]
                ],
                theme: 'grid',
                tableLineWidth: 1.5,
                tableLineColor: [0, 0, 0],
                headStyles: { fillColor: [240, 240, 240], textColor: [0, 0, 0], fontStyle: 'bold', halign: 'center' },
                styles: { font: 'Montserrat', fontSize: 9, cellPadding: 5, textColor: [0,0,0], lineColor: [0,0,0], lineWidth: 1.0, halign: 'center', valign: 'bottom' }
            });

            // Dibujar pie de página (Logo Hacienda, dirección y numeración de página) en cada una de las hojas
            const totalPages = doc.internal.getNumberOfPages();
            for (let i = 1; i <= totalPages; i++) {
                doc.setPage(i);
                
                // Logo Hacienda con proporción alargada para que no se vea comprimido
                if (logoHacienda.width > 0) {
                    doc.addImage(logoHacienda, 'PNG', pageWidth - marginRight - 160, pageHeight - 65, 160, 40);
                }
    
                doc.setFontSize(8);
                doc.setFont("Montserrat", "normal");
                doc.setTextColor(100);
                const footerText = "Calle 1a sur esquina av. 15  Col. centro\nPlaya del Carmen, Quintana Roo\n01 (984) 87 303 23\nwww.satq.qroo.gob.mx";
                // Texto de dirección a la izquierda
                doc.text(footerText, marginLeft, pageHeight - 55, { align: 'left' });
                
                // Paginación "Página X de Y" alineado a la derecha en la parte inferior
                doc.text(`Página ${i} de ${totalPages}`, pageWidth - marginRight, pageHeight - 15, { align: 'right' });
            }

            if (shouldSave) {
                doc.save(`Resguardo_${limpiarTexto(datosTrabajador.nombre).replace(/\s+/g, '_')}_${fechaActual.replace(/\//g, '')}.pdf`);
            }
            return doc;
        });
    }

    function iniciarDictamenBaja() {
        if (equiposSeleccionadosMap.size === 0) {
            Swal.fire({ icon: 'warning', title: 'Atención', text: 'Debes seleccionar al menos un equipo marcando su casilla a la izquierda.', confirmButtonColor: '#721538' });
            return;
        }

        Swal.fire({
            title: 'Dictamen Técnico de Baja',
            text: 'Ingresa el diagnóstico o motivo general por el que se dan de baja estos equipos:',
            input: 'textarea',
            inputPlaceholder: 'Ej. Equipo obsoleto que no admite reparación o actualización, daño irreparable en tarjeta madre...',
            showCancelButton: true,
            confirmButtonText: '<i class="fas fa-file-pdf mr-2"></i> Generar Dictamen',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#721538'
        }).then((result) => {
            if (result.isConfirmed) {
                const motivo = result.value || 'Falla grave de hardware / Obsolescencia técnica.';
                const bienes = Array.from(equiposSeleccionadosMap.values());
                generarPDFBaja(bienes, motivo);
            }
        });
    }

    function generarPDFBaja(bienes, motivo) {
        Swal.fire({ title: 'Generando PDF...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF('p', 'pt', 'letter');
        const pageWidth = doc.internal.pageSize.getWidth();
        const pageHeight = doc.internal.pageSize.getHeight();
        const marginLeft = 40;
        const marginRight = 40;
        
        const logoGob = new Image();
        logoGob.src = 'img/logo_gobierno.png';
        const logoHacienda = new Image();
        logoHacienda.src = 'img/logo_hacienda.png';

        Promise.all([
            new Promise((resolve) => { logoGob.onload = resolve; logoGob.onerror = resolve; }),
            new Promise((resolve) => { logoHacienda.onload = resolve; logoHacienda.onerror = resolve; })
        ]).then(() => {
            let startY = 40;
            if(logoGob.width > 0) doc.addImage(logoGob, 'PNG', marginLeft, startY, 130, 45);
            startY += 75;

            doc.setFontSize(14);
            doc.setFont("Montserrat", "bold");
            doc.setTextColor(114, 21, 56);
            doc.text("DICTAMEN TÉCNICO DE BAJA DE EQUIPOS", pageWidth / 2, startY, { align: "center" });
            startY += 25;

            const fechaActual = new Date().toLocaleDateString('es-MX', { day: '2-digit', month: '2-digit', year: 'numeric' });
            doc.setFontSize(10);
            doc.setFont("Montserrat", "normal");
            doc.setTextColor(50);
            doc.text(`Fecha de Emisión: ${fechaActual}`, pageWidth - marginRight, startY, { align: 'right' });
            startY += 20;
            
            doc.setTextColor(0);
            const parrafo = "A través del presente documento, el área de Soporte Técnico emite el diagnóstico correspondiente a los siguientes bienes, determinando que NO SON APTOS para continuar en operación debido a las condiciones técnicas especificadas:";
            const splitText = doc.splitTextToSize(parrafo, pageWidth - (marginLeft * 2));
            doc.text(splitText, marginLeft, startY);
            startY += (splitText.length * 12) + 15;

            const tablaBienes = bienes.map((bien, index) => [
                (index + 1).toString(),
                bien.no_bien_mueble || 'S/N',
                bien.num_serie || 'S/N',
                `${bien.nombre_tipo || ''} ${bien.marca || ''} ${bien.modelo || ''}`.trim(),
                motivo
            ]);

            doc.autoTable({
                startY: startY,
                head: [['No.', 'Inventario (B.M)', 'No. Serie', 'Equipo', 'Diagnóstico Técnico']],
                body: tablaBienes,
                theme: 'grid',
                headStyles: { fillColor: [114, 21, 56], textColor: [255, 255, 255], fontStyle: 'bold', halign: 'center' },
                styles: { font: 'Montserrat', fontSize: 8, cellPadding: 4, textColor: [0,0,0], lineColor: [200,200,200], lineWidth: 0.5, valign: 'middle' },
                columnStyles: { 0: { cellWidth: 25, halign: 'center' }, 4: { cellWidth: 150 } }
            });

            let finalY = doc.lastAutoTable.finalY + 50;
            if (finalY > pageHeight - 100) { doc.addPage(); finalY = 60; }

            doc.setFont("Montserrat", "bold");
            doc.setFontSize(10);
            doc.line(marginLeft + 40, finalY, marginLeft + 220, finalY);
            doc.text("Realizó Diagnóstico", marginLeft + 130, finalY + 15, { align: 'center' });
            doc.setFont("Montserrat", "normal");
            doc.setFontSize(9);
            doc.text("Soporte Técnico - SATQ", marginLeft + 130, finalY + 28, { align: 'center' });

            doc.setFont("Montserrat", "bold");
            doc.setFontSize(10);
            doc.line(pageWidth - marginRight - 220, finalY, pageWidth - marginRight - 40, finalY);
            doc.text("Vo. Bo. / Autorización", pageWidth - marginRight - 130, finalY + 15, { align: 'center' });
            doc.setFont("Montserrat", "normal");
            doc.setFontSize(9);
            doc.text("Titular del Área / Enlace", pageWidth - marginRight - 130, finalY + 28, { align: 'center' });

            if(logoHacienda.width > 0) doc.addImage(logoHacienda, 'PNG', pageWidth - marginRight - 160, pageHeight - 65, 160, 40);
            doc.setFontSize(8);
            doc.setTextColor(100);
            doc.text("Hacienda del Estado de Quintana Roo\nSATQ\nwww.satq.qroo.gob.mx", marginLeft, pageHeight - 40, { align: 'left' });

            doc.save(`Dictamen_Baja_${fechaActual.replace(/\//g, '')}.pdf`);
            
            // Actualizar base de datos: Llamada AJAX para dar de baja los equipos en el sistema
            const ids = bienes.map(b => b.id);
            const formData = new FormData();
            formData.append('accion', 'bulk_baja');
            formData.append('motivo', motivo);
            ids.forEach(id => formData.append('ids[]', id));
            
            fetch('consultar_inventario.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                Swal.close();
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: '¡Dado de baja!',
                        text: 'Los equipos se han marcado como "Para Baja" en el sistema y se ha descargado el dictamen.',
                        confirmButtonColor: '#721538'
                    }).then(() => {
                        // Limpiar selección
                        equiposSeleccionadosMap.clear();
                        actualizarContadorSeleccionados();
                        document.getElementById('selectAll').checked = false;
                        // Recargar tabla
                        cargarDatos();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error de base de datos',
                        text: data.message,
                        confirmButtonColor: '#721538'
                    });
                }
            })
            .catch(err => {
                console.error(err);
                Swal.close();
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Error de comunicación al dar de baja en el sistema.',
                    confirmButtonColor: '#721538'
                });
            });
        });
    }

    function exportarPDF() {
        const busquedaActual = document.getElementById('searchInput').value;
        const personalActual = document.getElementById('userFilter').value;

        const limpiarTexto = (texto) => {
            if (!texto) return '';
            return texto.toString();
        };
        
        Swal.fire({ title: 'Generando PDF...', text: 'Preparando documento...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
        
        fetch(`consultar_inventario.php?ajax=1&export=pdf&q=${encodeURIComponent(busquedaActual)}&u=${encodeURIComponent(personalActual)}&s=${encodeURIComponent(filtroStatus)}`)
            .then(res => res.json())
            .then(res => {
                const { jsPDF } = window.jspdf;
                const doc = new jsPDF('landscape');
            doc.setFont("Montserrat", "normal");
                doc.setFontSize(16);
                doc.setTextColor(114, 21, 56);
                doc.text("Reporte de Inventario - Soporte Técnico", 14, 15);
                
                if (personalActual) {
                    doc.setFontSize(10);
                    doc.setTextColor(80, 80, 80);
                    doc.text(`Filtrado por responsable: ${limpiarTexto(personalActual)}`, 14, 21);
                }
                
                const columnas = ["No. Bien Mueble", "Tipo", "Marca", "Modelo", "Serie", "Personal", "Último Resp.", "Fecha Baja", "Motivo Baja", "Ubicacion", "Estatus", "Descripcion"];
                const filas = res.data.map(item => [
                    item.no_bien_mueble || 'S/N', 
                    limpiarTexto(item.nombre_tipo) || 'N/A', 
                    item.marca, 
                    item.modelo, 
                    item.num_serie, 
                    limpiarTexto(item.personal_asignado) || 'STOCK', 
                    limpiarTexto(item.ultimo_responsable) || '-', 
                    item.fecha_baja || '-',
                    limpiarTexto(item.motivo_baja) || '-',
                    limpiarTexto(item.nombre_ubicacion) || '', 
                    item.estatus || 'Operativo',
                    limpiarTexto(item.descripcion) || '-'
                ]);

                doc.autoTable({
                    head: [columnas],
                    body: filas,
                    startY: personalActual ? 26 : 25,
                    theme: 'grid',
                    headStyles: { fillColor: [114, 21, 56] },
                    styles: { fontSize: 7, font: 'Montserrat' }
                });

                doc.save(`Inventario_${new Date().toISOString().slice(0,10)}.pdf`);
                Swal.close();
            })
            .catch(() => Swal.fire('Error', 'No se pudo generar el archivo', 'error'));
    }

    function exportarExcel() {
        const dropdown = document.getElementById('dropdownOpciones');
        if (dropdown) dropdown.classList.add('hidden');

        const busquedaActual = document.getElementById('searchInput').value;
        const personalActual = document.getElementById('userFilter').value;
        
        Swal.fire({ title: 'Generando Excel...', text: 'Preparando documento...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
        
        fetch(`consultar_inventario.php?ajax=1&export=excel&q=${encodeURIComponent(busquedaActual)}&u=${encodeURIComponent(personalActual)}&s=${encodeURIComponent(filtroStatus)}`)
            .then(res => res.json())
            .then(async res => {
                const workbook = new ExcelJS.Workbook();
                const worksheet = workbook.addWorksheet('Inventario');
                worksheet.views = [
                    { state: 'frozen', xSplit: 0, ySplit: 5, activeCell: 'A6', showGridLines: true }
                ];

                // 1. Títulos y Metadatos
                worksheet.mergeCells('A1:L1');
                const titleRow = worksheet.getRow(1);
                titleRow.getCell(1).value = "REPORTE DE INVENTARIO - SOPORTE TÉCNICO";
                titleRow.height = 35;
                titleRow.getCell(1).font = { name: 'Segoe UI', size: 16, bold: true, color: { argb: 'FF721538' } };
                titleRow.getCell(1).alignment = { vertical: 'middle', horizontal: 'left' };

                worksheet.mergeCells('A2:L2');
                const filterRow = worksheet.getRow(2);
                filterRow.getCell(1).value = personalActual ? `Responsable: ${personalActual}` : "Todos los responsables";
                filterRow.height = 20;
                filterRow.getCell(1).font = { name: 'Segoe UI', size: 11, italic: true, color: { argb: 'FF555555' } };
                filterRow.getCell(1).alignment = { vertical: 'middle', horizontal: 'left' };

                worksheet.mergeCells('A3:L3');
                const dateRow = worksheet.getRow(3);
                const now = new Date();
                dateRow.getCell(1).value = `Fecha de Generación: ${now.toLocaleDateString()} ${now.toLocaleTimeString()}`;
                dateRow.height = 20;
                dateRow.getCell(1).font = { name: 'Segoe UI', size: 10, italic: true, color: { argb: 'FF777777' } };
                dateRow.getCell(1).alignment = { vertical: 'middle', horizontal: 'left' };

                // Fila 4 vacía
                worksheet.getRow(4).height = 10;

                // Fila 5: Encabezados
                const headers = [
                    "No. Bien Mueble", "Tipo de Bien", "Marca", "Modelo", "No. Serie", 
                    "Personal Asignado", "Último Responsable", "Fecha de Baja", 
                    "Motivo de Baja", "Ubicación", "Estatus", "Descripción"
                ];
                const headerRow = worksheet.getRow(5);
                headerRow.values = headers;
                headerRow.height = 28;

                headerRow.eachCell((cell) => {
                    cell.font = { name: 'Segoe UI', size: 11, bold: true, color: { argb: 'FFFFFFFF' } };
                    cell.fill = {
                        type: 'pattern',
                        pattern: 'solid',
                        fgColor: { argb: 'FF721538' } // Guinda institucional
                    };
                    cell.alignment = { vertical: 'middle', horizontal: 'center', wrapText: true };
                    cell.border = {
                        top: { style: 'medium', color: { argb: 'FF721538' } },
                        bottom: { style: 'medium', color: { argb: 'FF5A102C' } },
                        left: { style: 'thin', color: { argb: 'FF8A244E' } },
                        right: { style: 'thin', color: { argb: 'FF8A244E' } }
                    };
                });

                // 2. Filas de Datos
                let startRow = 6;
                res.data.forEach((item, index) => {
                    const rowData = [
                        item.no_bien_mueble || 'S/N',
                        item.nombre_tipo || 'N/A',
                        item.marca || '',
                        item.modelo || '',
                        item.num_serie || 'S/N',
                        item.personal_asignado || 'STOCK',
                        item.ultimo_responsable || '-',
                        item.fecha_baja || '-',
                        item.motivo_baja || '-',
                        item.nombre_ubicacion || '',
                        item.estatus || 'Operativo',
                        item.descripcion || ''
                    ];

                    const dataRow = worksheet.getRow(startRow);
                    dataRow.values = rowData;
                    dataRow.height = 22;

                    const estatus = (item.estatus || 'IDENTIFICADO').toUpperCase();
                    let rowBgColor = '';
                    let rowTextColor = 'FF333333';

                    if (estatus === 'PARA BAJA') {
                        rowBgColor = 'FFFEE2E2'; // Rojo suave
                    } else if (estatus === 'NO IDENTIFICADO') {
                        rowBgColor = 'FFFFFBEB'; // Amarillo suave
                    } else {
                        rowBgColor = (index % 2 === 0) ? 'FFFFFFFF' : 'FFF9FAFB'; // Gris alterno / blanco
                    }

                    dataRow.eachCell((cell, colNumber) => {
                        cell.font = { name: 'Segoe UI', size: 10, color: { argb: rowTextColor } };
                        cell.fill = {
                            type: 'pattern',
                            pattern: 'solid',
                            fgColor: { argb: rowBgColor }
                        };
                        cell.border = {
                            top: { style: 'thin', color: { argb: 'FFE5E7EB' } },
                            bottom: { style: 'thin', color: { argb: 'FFE5E7EB' } },
                            left: { style: 'thin', color: { argb: 'FFE5E7EB' } },
                            right: { style: 'thin', color: { argb: 'FFE5E7EB' } }
                        };

                        // Alineaciones
                        let horizontalAlign = 'left';
                        if ([1, 5, 8, 11].includes(colNumber)) {
                            horizontalAlign = 'center';
                        }
                        cell.alignment = { vertical: 'middle', horizontal: horizontalAlign, wrapText: true };

                        // Estilo particular de Estatus
                        if (colNumber === 11) {
                            if (estatus === 'PARA BAJA') {
                                cell.font = { name: 'Segoe UI', size: 10, bold: true, color: { argb: 'FFB91C1C' } };
                            } else if (estatus === 'NO IDENTIFICADO') {
                                cell.font = { name: 'Segoe UI', size: 10, bold: true, color: { argb: 'FFD97706' } };
                            } else {
                                cell.font = { name: 'Segoe UI', size: 10, bold: true, color: { argb: 'FF047857' } };
                            }
                        }
                    });

                    startRow++;
                });

                // 3. Ajuste de Ancho de Columnas
                worksheet.columns.forEach((col, colIdx) => {
                    let maxLen = 10;
                    if (headers[colIdx]) {
                        maxLen = Math.max(maxLen, headers[colIdx].toString().length);
                    }
                    for (let r = 6; r < startRow; r++) {
                        const cellVal = worksheet.getCell(r, colIdx + 1).value;
                        if (cellVal) {
                            maxLen = Math.max(maxLen, cellVal.toString().length);
                        }
                    }
                    col.width = Math.max(12, Math.min(maxLen + 4, 45));
                });

                // Habilitar filtros automáticos en la cabecera
                worksheet.autoFilter = `A5:L${startRow - 1}`;

                // Nombre de archivo dinámico
                let filename = 'Inventario';
                if (personalActual === 'BAJA') {
                    filename = 'Inventario_Bajas';
                } else if (personalActual === 'SIN_ASIGNAR') {
                    filename = 'Inventario_Stock';
                } else if (personalActual) {
                    filename = `Inventario_${personalActual.replace(/[^a-zA-Z0-9]/g, '_')}`;
                }

                const buffer = await workbook.xlsx.writeBuffer();
                const blob = new Blob([buffer], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
                const link = document.createElement('a');
                link.href = URL.createObjectURL(blob);
                link.download = `${filename}_${new Date().toISOString().slice(0,10)}.xlsx`;
                link.click();
                URL.revokeObjectURL(link.href);
                Swal.close();
            })
            .catch((e) => {
                console.error(e);
                Swal.fire('Error', 'No se pudo generar el archivo Excel', 'error');
            });
    }

    function verEditarResponsable(nombre) {
        Swal.fire({
            title: 'Cargando datos...',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading()
        });

        fetch(`consultar_usuarios.php?ajax_details=1&nombre=${encodeURIComponent(nombre)}`)
            .then(res => res.json())
            .then(data => {
                Swal.close();
                if (!data.found) {
                    Swal.fire('Atención', 'No se encontraron datos para este responsable en la base de datos de usuarios.', 'info');
                    return;
                }
                
                const details = data.details;
                
                let htmlContent = `
                    <div class="text-left mt-4 text-sm">
                        <input type="hidden" id="usr-id" value="${details.id || 0}">
                        <input type="hidden" id="usr-id-dir" value="${details.id_direccion || 0}">
                        <input type="hidden" id="usr-oficio" value="${details.num_oficio || ''}">
                        <input type="hidden" id="usr-usuario" value="${details.usuario || ''}">
                        <input type="hidden" id="usr-pass" value="${details.contrasena || ''}">

                        <div class="grid grid-cols-2 gap-3 mb-2">
                            <div>
                                <label class="swal-field-label">Nombres</label>
                                <input id="usr-nombres" class="swal-custom-input" value="${details.nombres || ''}" ${esAdmin ? '' : 'readonly'}>
                            </div>
                            <div>
                                <label class="swal-field-label">Primer Apellido</label>
                                <input id="usr-pat" class="swal-custom-input" value="${details.apellido_paterno || ''}" ${esAdmin ? '' : 'readonly'}>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3 mb-2">
                            <div>
                                <label class="swal-field-label">Segundo Apellido</label>
                                <input id="usr-mat" class="swal-custom-input" value="${details.apellido_materno || ''}" ${esAdmin ? '' : 'readonly'}>
                            </div>
                            <div>
                                <label class="swal-field-label">Número de Empleado</label>
                                <input id="usr-num-emp" class="swal-custom-input" value="${details.num_empleado || ''}" ${esAdmin ? '' : 'readonly'}>
                            </div>
                        </div>

                        <label class="swal-field-label">Cargo</label>
                        <input id="usr-cargo" class="swal-custom-input" value="${details.cargo || ''}" ${esAdmin ? '' : 'readonly'}>

                        <label class="swal-field-label">Jefe Inmediato</label>
                        <input id="usr-jefe" class="swal-custom-input" value="${details.jefe_inmediato || ''}" ${esAdmin ? '' : 'readonly'}>

                        <label class="swal-field-label">Área o Departamento</label>
                        <input class="swal-custom-input bg-gray-50 text-gray-500 cursor-not-allowed" value="${details.area || 'Sin asignar'}" readonly>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="swal-field-label">Correo Electrónico</label>
                                <input id="usr-correo" class="swal-custom-input" value="${details.correo || ''}" ${esAdmin ? '' : 'readonly'}>
                            </div>
                            <div>
                                <label class="swal-field-label">Teléfono</label>
                                <input id="usr-telefono" class="swal-custom-input" value="${details.telefono || ''}" ${esAdmin ? '' : 'readonly'}>
                            </div>
                        </div>
                    </div>
                `;

                Swal.fire({
                    title: `<div class="text-xl font-bold border-b pb-2"><i class="fas fa-user-circle text-primary-dark mr-1"></i> Detalles del Responsable</div>`,
                    html: htmlContent,
                    width: '600px',
                    showCancelButton: true,
                    cancelButtonText: 'Cerrar',
                    confirmButtonText: esAdmin ? '<i class="fas fa-save mr-1"></i> Guardar Cambios' : 'Aceptar',
                    confirmButtonColor: '#721538',
                    preConfirm: () => {
                        if (!esAdmin) return null;
                        return {
                            id: document.getElementById('usr-id').value,
                            id_direccion: document.getElementById('usr-id-dir').value,
                            num_oficio: document.getElementById('usr-oficio').value,
                            nombres: document.getElementById('usr-nombres').value,
                            apellido_paterno: document.getElementById('usr-pat').value,
                            apellido_materno: document.getElementById('usr-mat').value,
                            usuario: document.getElementById('usr-usuario').value,
                            contrasena: document.getElementById('usr-pass').value,
                            cargo: document.getElementById('usr-cargo').value,
                            jefe_inmediato: document.getElementById('usr-jefe').value,
                            correo_electronico: document.getElementById('usr-correo').value,
                            telefono: document.getElementById('usr-telefono').value
                        }
                    }
                }).then((result) => {
                    if (result.isConfirmed && result.value) {
                        const data = new FormData();
                        for (let key in result.value) {
                            data.append(key, result.value[key]);
                        }
                        
                        Swal.fire({ title: 'Guardando...', didOpen: () => Swal.showLoading() });
                        fetch('actualizar_usuario.php', { method: 'POST', body: data })
                            .then(r => r.json())
                            .then(d => {
                                if (d.success) {
                                    Swal.fire({ icon: 'success', title: '¡Actualizado!', timer: 1000, showConfirmButton: false }).then(() => {
                                        cargarDatos();
                                    });
                                } else {
                                    Swal.fire('Error', d.message, 'error');
                                }
                            })
                            .catch(() => Swal.fire('Error', 'No se pudo guardar la información del usuario.', 'error'));
                    }
                });
            })
            .catch(() => {
                Swal.close();
                Swal.fire('Error', 'No se pudieron obtener los detalles del usuario.', 'error');
            });
    }

    function renderizarPaginacion(meta) {
        const infoContainer = document.getElementById('paginacion-info');
        const botonesContainer = document.getElementById('paginacion-botones');
        const divPaginacion = document.getElementById('paginacion-container');

        if (infoContainer) infoContainer.innerHTML = '';
        if (botonesContainer) botonesContainer.innerHTML = '';
        if (!divPaginacion) return;

        if (meta.total_registros == 0) {
            divPaginacion.classList.add('hidden');
            return;
        }
        
        divPaginacion.classList.remove('hidden');

        const registrosPorPagina = 15;
        const inicio = (meta.pagina_actual - 1) * registrosPorPagina + 1;
        const fin = Math.min(meta.pagina_actual * registrosPorPagina, meta.total_registros);
        if (infoContainer) {
            infoContainer.innerText = `Mostrando ${inicio} a ${fin} de ${meta.total_registros} resultados`;
        }

        if (meta.total_paginas <= 1) return;

        const crearBoton = (texto, pagina, activo = false, deshabilitado = false) => {
            const btn = document.createElement('button');
            btn.type = "button";
            btn.innerHTML = texto;
            btn.disabled = deshabilitado;

            let clases = "px-3 py-1 rounded-md border transition-colors text-sm font-medium ";
            if (activo) {
                clases += "bg-primary-dark text-white border-primary-dark shadow-sm cursor-default";
            } else if (deshabilitado) {
                clases += "bg-transparent text-gray-400 border-transparent cursor-default";
            } else {
                clases += "bg-white text-gray-600 border-gray-200 hover:bg-gray-50";
            }
            btn.className = clases;

            if (!deshabilitado && !activo) {
                btn.onclick = () => { paginaActual = pagina; cargarDatos(); window.scrollTo({ top: 0, behavior: 'smooth' }); };
            }
            return btn;
        };

        const total = meta.total_paginas;
        const actual = meta.pagina_actual;
        const rango = 1;

        if (actual > 1) { botonesContainer.appendChild(crearBoton('<i class="fas fa-chevron-left text-xs"></i>', actual - 1)); }
        for (let i = 1; i <= total; i++) {
            if (i === 1 || i === total || (i >= actual - rango && i <= actual + rango)) {
                botonesContainer.appendChild(crearBoton(i, i, i === actual));
            } else if (i === actual - rango - 1 || i === actual + rango + 1) {
                botonesContainer.appendChild(crearBoton('...', null, false, true));
            }
        }
        if (actual < total) { botonesContainer.appendChild(crearBoton('<i class="fas fa-chevron-right text-xs"></i>', actual + 1)); }
    }
</script>
</body>
</html>