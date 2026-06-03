<?php
require_once 'session_check.php';
require_once 'config.php';
session_start();

// 1. SEGURIDAD: Verifica si el usuario está logueado y si su rol está permitido
$roles_permitidos = ['admin', 'tecnico'];

if (!isset($_SESSION['usuario']) || !in_array($_SESSION['rol'] ?? '', $roles_permitidos)) {
    header("Location: dashboard.php?error=permiso_denegado");
    exit();
}

$rol_usuario = $_SESSION['rol'] ?? 'tecnico';
$esAdmin = ($rol_usuario === 'admin');
$puedeEditar = ($rol_usuario === 'admin' || $rol_usuario === 'tecnico');

$conn = get_db_connection();

// ==========================================
// 2. BACKEND: RESPUESTA AJAX (JSON)
// ==========================================
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    
    $search_term = $_GET['q'] ?? '';
    $pagina = isset($_GET['p']) && is_numeric($_GET['p']) ? (int)$_GET['p'] : 1;
    $registros_por_pagina = 10;
    $offset = ($pagina - 1) * $registros_por_pagina;

    // Construir WHERE
    $where_clause = "";
    $params = [];
    $types = "";

    if (!empty($search_term)) {
        $like_term = "%" . $search_term . "%";
        $where_clause = " WHERE ubicacion LIKE ? OR nombre_carpeta LIKE ? OR ip_servidor LIKE ? OR usuario LIKE ?";
        $params = [$like_term, $like_term, $like_term, $like_term];
        $types = "ssss";
    }

    // Contar total
    $sql_total = "SELECT COUNT(*) AS total FROM carpetas_nas" . $where_clause;
    $stmt_total = $conn->prepare($sql_total);
    if (!empty($params)) {
        $stmt_total->bind_param($types, ...$params);
    }
    $stmt_total->execute();
    $total_registros = $stmt_total->get_result()->fetch_assoc()["total"];
    $stmt_total->close();

    $total_paginas = ceil($total_registros / $registros_por_pagina);

    // Consulta de datos
    $sql = "SELECT * FROM carpetas_nas $where_clause ORDER BY id ASC LIMIT ?, ?";
    
    $params[] = $offset;
    $params[] = $registros_por_pagina;
    $types .= "ii";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $datos = [];
    while($row = $result->fetch_assoc()) {
        $datos[] = $row;
    }
    $stmt->close();

    echo json_encode([
        'data' => $datos,
        'meta' => [
            'pagina_actual' => $pagina,
            'total_paginas' => $total_paginas,
            'total_registros' => $total_registros,
            'puede_editar' => $puedeEditar,
            'es_admin' => $esAdmin
        ]
    ]);
    exit;
}
include 'header.php';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Carpetas</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- TAILWIND & LIBRERÍAS -->
    <script src="js/tailwindcss.js"></script>
    <script src="js/sweetalert2.all.min.js"></script>
    <link rel="stylesheet" href="css/all.min.css">

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'primary-dark': '#721538',
                        'primary-light': '#961e4b',
                        'background': '#d6d1ca',
                        'nas-blue': '#721538',
                    }
                }
            }
        }
    </script>
    <style>
        body { font-family: 'Segoe UI', sans-serif; }
        .table-row-hover:hover { background-color: #fff8e1; }
        
        .editable-input {
            width: 100%; padding: 6px; border: 1px solid #721538;
            border-radius: 4px; box-sizing: border-box; font-size: 0.9rem;
        }
        .btn-action-sm {
            padding: 6px 10px; font-size: 0.75rem;
            line-height: 1; border-radius: 4px; transition: all 0.15s;
        }

        /* --- CORRECCIÓN BUSCADOR --- */
        .search-input { padding-left: 50px !important; }
        .search-icon-svg {
            position: absolute; left: 15px; top: 50%; transform: translateY(-50%);
            width: 20px; height: 20px; color: #9ca3af; pointer-events: none; z-index: 10;
        }

        /* --- VISTA MÓVIL (TARJETAS FLEXBOX) --- */
        @media (max-width: 768px) {
            thead { display: none; }
            table, tbody, tr, td { display: block; width: 100%; }
            
            tr {
                background: white; margin-bottom: 1rem; border-radius: 0.75rem;
                box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
                border-left: 6px solid #2c3e50; /* Azul oscuro para distinguir NAS */
                padding: 0; overflow: hidden;
            }
            
            td {
                display: flex; justify-content: space-between; align-items: center;
                text-align: right; padding: 12px 16px; border-bottom: 1px solid #f3f4f6;
                min-height: 50px;
            }
            
            td:last-child {
                border-bottom: none; justify-content: center; background-color: #f9fafb; padding: 15px;
            }

            td::before {
                content: attr(data-label); font-weight: 700; color: #2c3e50;
                text-transform: uppercase; font-size: 0.75rem; text-align: left;
                margin-right: 15px; white-space: nowrap;
            }

            td .view-val {
                text-align: right; word-break: break-word; max-width: 65%;
            }
            
            .search-container { flex-direction: column; }
        }
    </style>
</head>
<body class="bg-background min-h-screen p-4 sm:p-8">

    <div class="max-w-[1600px] mx-auto">
        
        <!-- HEADER -->
        <div class="flex flex-col sm:flex-row justify-between items-center mb-6">
            <h1 class="text-3xl font-bold text-nas-blue flex items-center gap-2 mb-4 sm:mb-0">
                <i class="fas fa-network-wired"></i> Carpetas NAS 
                <span id="total-lbl" class="text-xs bg-white text-gray-600 border px-3 py-1 rounded-full align-middle font-normal shadow-sm">Cargando...</span>
            </h1>
            <div id="loading" style="display:none;" class="text-nas-blue font-bold animate-pulse">
                <i class="fas fa-spinner fa-spin"></i>
            </div>
        </div>

        <!-- TOOLBAR -->
        <div class="bg-white p-4 rounded-xl shadow-md mb-6 flex flex-col sm:flex-row gap-4 items-center justify-between">
            
            <!-- BUSCADOR -->
            <div class="relative w-full sm:max-w-lg search-container">
                <svg class="search-icon-svg" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
                <input type="text" id="searchInput"
                       placeholder="Buscar carpeta, IP, usuario..."
                       class="search-input w-full p-3 border border-gray-300 rounded-full focus:outline-none focus:ring-2 focus:ring-nas-blue focus:border-transparent transition-shadow shadow-sm">
            </div>

            <!-- BOTONES -->
            <div class="flex gap-2 w-full sm:w-auto justify-end">
                <button onclick="cargarDatos()" class="w-10 h-10 bg-gray-100 text-gray-600 rounded-full hover:bg-gray-200 border border-gray-200 flex items-center justify-center transition" title="Recargar"><i class="fas fa-sync-alt"></i></button>

                <?php if ($puedeEditar): ?>
                    <a href="registro_nas.php" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-6 rounded-full shadow transition-transform transform hover:scale-105 flex items-center justify-center">
                        <i class="fas fa-plus mr-2"></i> Nueva
                    </a>
                <?php endif; ?>
                <a href="dashboard.php" class="bg-gray-700 hover:bg-gray-800 text-white font-bold py-2 px-6 rounded-full shadow transition-transform transform hover:scale-105 flex items-center justify-center">
                    Menú
                </a>
            </div>
        </div>

        <!-- TABLA -->
        <div class="overflow-hidden shadow-lg rounded-xl border border-gray-100 bg-white">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-primary-dark">
                    <tr>
                        <!-- ID OCULTO EN CABECERA -->
                        <th class="px-6 py-4 text-left text-xs font-bold text-white uppercase tracking-wider">Ubicación</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-white uppercase tracking-wider">Nombre Carpeta</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-white uppercase tracking-wider">IP Servidor</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-white uppercase tracking-wider">Usuario</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-white uppercase tracking-wider">Password</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-white uppercase tracking-wider">Observaciones</th>
                        <?php if ($puedeEditar): ?>
                            <th class="px-6 py-4 text-center text-xs font-bold text-white uppercase tracking-wider">Acciones</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody id="tabla-resultados" class="bg-white divide-y divide-gray-100 text-sm">
                    <!-- DATOS CARGADOS VIA JS -->
                </tbody>
            </table>
        </div>

        <!-- PAGINACIÓN -->
        <div id="paginacion" class="mt-8 flex justify-center gap-2 flex-wrap">
            <!-- PAGINACION CARGADA VIA JS -->
        </div>

    </div>

    <script>
        // --- VARIABLES GLOBALES ---
        let paginaActual = 1;
        let terminoBusqueda = '';
        let timeoutBusqueda = null;
        // Obtenemos valores de PHP
        const esAdmin = <?= $esAdmin ? 'true' : 'false' ?>;
        const puedeEditar = <?= $puedeEditar ? 'true' : 'false' ?>;

        // --- ELEMENTOS DOM ---
        const tabla = document.getElementById('tabla-resultados');
        const inputBusqueda = document.getElementById('searchInput');
        const divPaginacion = document.getElementById('paginacion');
        const loader = document.getElementById('loading');
        const totalLbl = document.getElementById('total-lbl');

        // --- INICIALIZACIÓN ---
        document.addEventListener('DOMContentLoaded', () => {
            cargarDatos();
            
            inputBusqueda.addEventListener('input', (e) => {
                clearTimeout(timeoutBusqueda);
                terminoBusqueda = e.target.value;
                paginaActual = 1; 
                timeoutBusqueda = setTimeout(() => { cargarDatos(); }, 300); 
            });
        });

        // --- FUNCIÓN AJAX PRINCIPAL ---
        function cargarDatos() {
            loader.style.display = 'block';
            tabla.style.opacity = '0.5';

            fetch(`consultar_nas.php?ajax=1&q=${encodeURIComponent(terminoBusqueda)}&p=${paginaActual}`)
                .then(res => res.json())
                .then(res => {
                    renderizarTabla(res.data);
                    renderizarPaginacion(res.meta);
                    totalLbl.innerText = res.meta.total_registros;
                })
                .catch(err => console.error('Error:', err))
                .finally(() => {
                    loader.style.display = 'none';
                    tabla.style.opacity = '1';
                });
        }

        // --- RENDERIZADO DE TABLA ---
        function renderizarTabla(datos) {
            tabla.innerHTML = '';

            if (datos.length === 0) {
                tabla.innerHTML = '<tr><td colspan="7" class="px-6 py-10 text-center text-gray-500 italic">No se encontraron carpetas.</td></tr>';
                return;
            }

            datos.forEach(row => {
                let accionesHtml = '';
                if (puedeEditar) {
                    let btnEliminar = '';
                    if (esAdmin) {
                        btnEliminar = `<button class="btn-action-sm btn-cancel bg-red-400 text-white hover:bg-red-500 shadow-sm" onclick="eliminarFila(${row.id})"><i class="fas fa-trash"></i></button>`;
                    }
                    
                    accionesHtml = `
                        <td data-label="Acciones" class="px-6 py-4 text-center whitespace-nowrap space-x-1">
                            <button class="btn-action-sm btn-edit bg-blue-500 text-white hover:bg-blue-600 shadow-sm" onclick="editarFila(${row.id})"><i class="fas fa-pencil-alt"></i></button>
                            <button class="btn-action-sm btn-save bg-green-500 text-white hover:bg-green-600 shadow-sm" onclick="guardarFila(${row.id})" style="display:none;"><i class="fas fa-save"></i></button>
                            <button class="btn-action-sm btn-cancel bg-gray-400 text-white hover:bg-gray-500 shadow-sm" onclick="cancelarEdicion(${row.id})" style="display:none;"><i class="fas fa-times"></i></button>
                            ${btnEliminar}
                        </td>
                    `;
                }

                const tr = document.createElement('tr');
                tr.className = 'table-row-hover transition-colors duration-150';
                tr.id = `fila_${row.id}`;
                
                // NOTA: No generamos columna ID visualmente, pero usamos el ID en los botones
                tr.innerHTML = `
                    <td data-label="Ubicación" class="px-6 py-4 text-gray-700 editable-cell" data-campo="ubicacion">
                        <span class="view-val">${row.ubicacion}</span>
                        <input type="text" class="editable-input hidden">
                    </td>
                    
                    <td data-label="Nombre Carpeta" class="px-6 py-4 editable-cell" data-campo="nombre_carpeta">
                        <span class="view-val font-bold text-nas-blue">${row.nombre_carpeta}</span>
                        <input type="text" class="editable-input hidden">
                    </td>
                    
                    <td data-label="IP Servidor" class="px-6 py-4 text-gray-600 font-mono editable-cell" data-campo="ip_servidor">
                        <span class="view-val">${row.ip_servidor}</span>
                        <input type="text" class="editable-input hidden">
                    </td>

                    <td data-label="Usuario" class="px-6 py-4 text-gray-700 editable-cell" data-campo="usuario">
                        <span class="view-val">${row.usuario}</span>
                        <input type="text" class="editable-input hidden">
                    </td>
                    
                    <td data-label="Password" class="px-6 py-4 text-gray-700 font-mono editable-cell" 
                        data-campo="password" data-real-value="${row.password || ''}">
                        <div class="flex items-center gap-2 justify-end sm:justify-start" style="width:100%">
                            <span class="view-val tracking-widest text-gray-400 outline-none" id="pass_txt_${row.id}">••••••</span>
                            <input type="text" class="editable-input hidden">
                            <button onclick="togglePass(${row.id})" class="text-gray-400 hover:text-nas-blue transition-colors focus:outline-none p-1 ml-auto sm:ml-2" title="Ver contraseña">
                                <i class="fas fa-eye" id="pass_icon_${row.id}"></i>
                            </button>
                        </div>
                    </td>

                    <td data-label="Observaciones" class="px-6 py-4 text-gray-500 italic editable-cell" data-campo="observaciones">
                        <span class="view-val">${row.observaciones || ''}</span>
                        <input type="text" class="editable-input hidden">
                    </td>
                    
                    ${accionesHtml}
                `;
                tabla.appendChild(tr);
            });
        }

        // --- PAGINACIÓN ---
        function renderizarPaginacion(meta) {
            divPaginacion.innerHTML = '';
            const totalPag = meta.total_paginas;
            const actual = meta.pagina_actual;
            if (totalPag <= 1) return;

            const crearBtn = (texto, pag, activo) => {
                const btn = document.createElement('button');
                btn.innerHTML = texto;
                btn.className = `w-10 h-10 flex items-center justify-center text-sm font-bold border rounded-full transition-all duration-200 ${activo ? 'bg-nas-blue text-white border-nas-blue transform scale-110' : 'text-gray-600 border-gray-300 hover:bg-nas-blue hover:text-white hover:border-nas-blue'}`;
                btn.onclick = () => { paginaActual = pag; cargarDatos(); };
                divPaginacion.appendChild(btn);
            };

            if (actual > 1) crearBtn('<i class="fas fa-chevron-left"></i>', actual - 1, false);
            let start = Math.max(1, actual - 2);
            let end = Math.min(totalPag, actual + 2);
            for (let i = start; i <= end; i++) crearBtn(i, i, i === actual);
            if (actual < totalPag) crearBtn('<i class="fas fa-chevron-right"></i>', actual + 1, false);
        }

        // --- FUNCIONES LÓGICAS ---
        function togglePass(id) {
            const fila = document.getElementById('fila_' + id);
            const celdaPass = fila.querySelector('[data-campo="password"]');
            const spanTexto = document.getElementById('pass_txt_' + id);
            const icono = document.getElementById('pass_icon_' + id);
            const passReal = celdaPass.getAttribute('data-real-value');

            // Si está editando, no hacemos toggle
            if (!celdaPass.querySelector('.editable-input').classList.contains('hidden')) return;

            if (spanTexto.textContent.includes('•')) {
                spanTexto.textContent = passReal;
                spanTexto.classList.remove('tracking-widest', 'text-gray-400');
                spanTexto.classList.add('text-nas-blue', 'font-bold');
                icono.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                spanTexto.textContent = '••••••';
                spanTexto.classList.add('tracking-widest', 'text-gray-400');
                spanTexto.classList.remove('text-nas-blue', 'font-bold');
                icono.classList.replace('fa-eye-slash', 'fa-eye');
            }
        }

        function editarFila(id) {
            const fila = document.getElementById('fila_' + id);
            fila.querySelector('.btn-edit').classList.add('hidden'); // Ocultar
            if(fila.querySelector('.btn-cancel').parentNode.querySelector('.btn-cancel')) { // Mostrar botones en móviles puede requerir selectores específicos si la estructura cambia
                fila.querySelectorAll('.btn-save, .btn-cancel').forEach(b => {
                    b.classList.remove('hidden');
                    b.style.display = 'inline-block';
                });
            }

            fila.querySelectorAll('.editable-cell').forEach(cell => {
                const viewVal = cell.querySelector('.view-val');
                const input = cell.querySelector('.editable-input');
                const campo = cell.getAttribute('data-campo');
                let valorActual = (campo === 'password') ? cell.getAttribute('data-real-value') : viewVal.textContent.trim();
                
                if(campo === 'password') cell.querySelector('button').style.display = 'none'; // Ocultar ojo

                input.value = valorActual;
                viewVal.classList.add('hidden');
                input.classList.remove('hidden');
                input.style.display = 'inline-block';
            });
        }

        function cancelarEdicion(id) {
            const fila = document.getElementById('fila_' + id);
            fila.querySelector('.btn-edit').classList.remove('hidden');
            fila.querySelectorAll('.btn-save, .btn-cancel').forEach(b => {
                b.classList.add('hidden');
                b.style.display = 'none';
            });

            fila.querySelectorAll('.editable-cell').forEach(cell => {
                const viewVal = cell.querySelector('.view-val');
                const input = cell.querySelector('.editable-input');
                const campo = cell.getAttribute('data-campo');

                if (campo === 'password') {
                    const spanTexto = document.getElementById('pass_txt_' + id);
                    spanTexto.textContent = '••••••';
                    spanTexto.classList.add('tracking-widest', 'text-gray-400');
                    spanTexto.classList.remove('text-nas-blue', 'font-bold');
                    document.getElementById('pass_icon_' + id).classList.replace('fa-eye-slash', 'fa-eye');
                    cell.querySelector('button').style.display = 'inline-block';
                }

                viewVal.classList.remove('hidden');
                input.classList.add('hidden');
                input.style.display = 'none';
            });
        }

        function guardarFila(id) {
            const fila = document.getElementById('fila_' + id);
            const datos = new FormData();
            datos.append('id', id);
            fila.querySelectorAll('.editable-input').forEach(input => {
                const campo = input.closest('td').getAttribute('data-campo');
                datos.append(campo, input.value);
            });
            
            Swal.fire({ title: 'Guardando...', didOpen: () => Swal.showLoading() });

            // Necesitarás actualizar_nas.php
            fetch('actualizar_nas.php', { method: 'POST', body: datos })
            .then(r => r.json())
            .then(data => {
                if(data.success) {
                    Swal.fire({icon: 'success', title: '¡Actualizado!', toast: true, position: 'top-end', showConfirmButton: false, timer: 1500});
                    cargarDatos();
                } else {
                    Swal.fire('Error', data.message || 'Error desconocido', 'error');
                }
            })
            .catch(err => Swal.fire('Error', 'Fallo de conexión', 'error'));
        }

        function eliminarFila(id) {
            Swal.fire({
                title: '¿Eliminar carpeta?', text: "No podrás revertir esto", icon: 'warning',
                showCancelButton: true, confirmButtonColor: '#d33', cancelButtonColor: '#3085d6', confirmButtonText: 'Sí, eliminar'
            }).then((result) => {
                if (result.isConfirmed) {
                    const datos = new FormData(); datos.append('id', id);
                    // Necesitarás eliminar_nas.php
                    fetch('eliminar_nas.php', { method: 'POST', body: datos })
                    .then(r => r.json())
                    .then(data => {
                        if(data.success) {
                            Swal.fire('Eliminado', 'La carpeta ha sido eliminada.', 'success');
                            cargarDatos();
                        } else {
                            Swal.fire('Error', data.message, 'error');
                        }
                    });
                }
            });
        }
    </script>
</body>
</html>
<?php $conn->close(); ?>