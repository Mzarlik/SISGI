<?php
// Inicia la sesión
require_once 'config.php';
session_start();

// 1. Verificar Sesión
if (!isset($_SESSION['usuario'])) {
    header("Location: index.php");
    exit();
}

// --- PERMISOS UNIVERSALES ---
// Forzamos a true para que cualquier usuario logueado pueda ver y usar las funciones
$puedeEditar = true; 
$esAdmin = true; 
$rol_usuario = $_SESSION['rol'] ?? 'usuario';
// ----------------------------

// 2. Conexión a la Base de Datos
$conn = get_db_connection();
if (!$conn) { die("Conexión fallida."); }

// ==========================================
// 3. BACKEND: RESPUESTA AJAX (JSON)
// ==========================================
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    
    $search_term = $_GET['q'] ?? '';
    $pagina = isset($_GET['p']) && is_numeric($_GET['p']) ? (int)$_GET['p'] : 1;
    $registros_por_pagina = 10;
    $offset = ($pagina - 1) * $registros_por_pagina;

    $where_clause = "";
    $params = [];
    $types = "";

    if (!empty($search_term)) {
        $like_term = "%" . $search_term . "%";
        $where_clause = " WHERE Dirección LIKE ? OR Area LIKE ? OR Correo LIKE ? OR Password LIKE ?";
        $params = [$like_term, $like_term, $like_term, $like_term];
        $types = "ssss";
    }

    // Contar total
    $sql_total = "SELECT COUNT(*) AS total FROM cuentas_office" . $where_clause;
    $stmt_total = $conn->prepare($sql_total);
    if (!empty($params)) {
        $stmt_total->bind_param($types, ...$params);
    }
    $stmt_total->execute();
    $total_registros = $stmt_total->get_result()->fetch_assoc()["total"];
    $stmt_total->close();

    $total_paginas = ceil($total_registros / $registros_por_pagina);

    // Consulta de datos
    $sql = "SELECT id, Dirección, Area, Correo, Password, Conectados FROM cuentas_office $where_clause ORDER BY id ASC LIMIT ?, ?";
    
    $params_data = array_merge($params, [$offset, $registros_por_pagina]);
    $types_data = $types . "ii";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types_data, ...$params_data);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $datos = [];
    while($row = $result->fetch_assoc()) {
        $row['Conectados'] = $row['Conectados'] ?? 0;
        $datos[] = $row;
    }
    $stmt->close();

    echo json_encode([
        'data' => $datos,
        'meta' => [
            'pagina_actual' => $pagina,
            'total_paginas' => $total_paginas,
            'total_registros' => $total_registros,
            'es_admin' => $esAdmin,
            'puede_editar' => $puedeEditar
        ]
    ]);
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Consultar Licencias Office</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="js/tailwindcss.js"></script>
    <script src="js/sweetalert2.all.min.js"></script>

    
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
        .table-row-hover:hover { background-color: #fff8e1; }
        .row-disabled { background-color: #f3f4f6 !important; color: #9ca3af !important; }
        .editable-input { width: 100%; padding: 4px; border: 1px solid #721538; border-radius: 4px; }
        .btn-action-sm { padding: 6px 10px; font-size: 0.75rem; border-radius: 4px; transition: all 0.15s; }

        @media (max-width: 768px) {
            thead { display: none; }
            table, tbody, tr, td { display: block; width: 100%; }
            tr { background: white; margin-bottom: 1rem; border-radius: 0.75rem; border-left: 6px solid #721538; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); }
            td { display: flex; justify-content: space-between; align-items: center; padding: 12px 16px; border-bottom: 1px solid #f3f4f6; }
            td::before { content: attr(data-label); font-weight: 700; color: #721538; font-size: 0.75rem; text-align: left; }
        }
    </style>
</head>
<body class="bg-background min-h-screen p-4 sm:p-8">

    <div class="max-w-7xl mx-auto">
        
        <div class="flex flex-col sm:flex-row justify-between items-center mb-6">
            <h1 class="text-3xl font-bold text-primary-dark flex items-center gap-2 mb-4 sm:mb-0">
                <i class="fab fa-windows"></i> Licencias Microsoft 365
                <span id="total-lbl" class="text-xs bg-gray-200 text-gray-600 px-3 py-1 rounded-full">...</span>
            </h1>
            <div id="loading" style="display:none;" class="text-primary-dark font-bold animate-pulse">
                <i class="fas fa-spinner fa-spin"></i>
            </div>
        </div>

        <div class="bg-white p-4 rounded-xl shadow-md mb-6 flex flex-col sm:flex-row gap-4 items-center justify-between">
            <div class="relative w-full sm:max-w-lg">
                <input type="text" id="searchInput" placeholder="Buscar por Dirección, Área, Correo..." class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-full focus:ring-2 focus:ring-primary-dark outline-none transition-shadow shadow-sm">
            </div>

            <div class="flex gap-2 w-full sm:w-auto justify-end">
                <a href="exportar_reporte.php?tipo=licencias" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-2 px-6 rounded-full shadow flex items-center">
                    <i class="fas fa-file-excel mr-2"></i> Exportar
                </a>
                <?php if ($esAdmin): ?>
                    <a href="registro_licencia.php" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-6 rounded-full shadow flex items-center">
                        <i class="fas fa-plus mr-2"></i> Nuevo
                    </a>
                <?php endif; ?>
                <a href="dashboard.php" class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-6 rounded-full shadow">Menú</a>
            </div>
        </div>

        <div class="overflow-hidden shadow-lg rounded-xl border border-gray-100 bg-white">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-primary-dark">
                    <tr>
                        <th class="px-6 py-4 text-left text-xs font-bold text-white uppercase tracking-wider">Dirección</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-white uppercase tracking-wider">Área</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-white uppercase tracking-wider">Correo</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-white uppercase tracking-wider">Conexiones</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-white uppercase tracking-wider">Contraseña</th>
                        <?php if ($puedeEditar): ?>
                            <th class="px-6 py-4 text-center text-xs font-bold text-white uppercase tracking-wider">Acciones</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody id="tabla-resultados" class="bg-white divide-y divide-gray-100 text-sm"></tbody>
            </table>
        </div>

        <div id="paginacion" class="mt-8 flex justify-center gap-2 flex-wrap"></div>

    </div>

    <script>
        let paginaActual = 1;
        let terminoBusqueda = '';
        
        // Inyectamos el permiso desde PHP a JS
        const puedeEditar = <?= json_encode($puedeEditar) ?>; 
        
        const tabla = document.getElementById('tabla-resultados');
        const inputBusqueda = document.getElementById('searchInput');
        const divPaginacion = document.getElementById('paginacion');
        const loader = document.getElementById('loading');
        const totalLbl = document.getElementById('total-lbl');

        document.addEventListener('DOMContentLoaded', () => {
            cargarDatos();
            inputBusqueda.addEventListener('input', (e) => {
                terminoBusqueda = e.target.value;
                paginaActual = 1; 
                clearTimeout(window.searchTimeout);
                window.searchTimeout = setTimeout(() => { cargarDatos(); }, 300); 
            });
        });

        function cargarDatos() {
            if(loader) loader.style.display = 'inline-block';
            tabla.style.opacity = '0.5';

            fetch(`consultar_licencias.php?ajax=1&q=${encodeURIComponent(terminoBusqueda)}&p=${paginaActual}`)
                .then(res => res.json())
                .then(res => {
                    renderizarTabla(res.data);
                    renderizarPaginacion(res.meta);
                    if(totalLbl) totalLbl.innerText = res.meta.total_registros;
                })
                .finally(() => {
                    if(loader) loader.style.display = 'none';
                    tabla.style.opacity = '1';
                });
        }

        function renderizarTabla(datos) {
            tabla.innerHTML = '';
            if (datos.length === 0) {
                tabla.innerHTML = '<tr><td colspan="6" class="px-6 py-10 text-center text-gray-500 italic">No se encontraron resultados.</td></tr>';
                return;
            }

            datos.forEach(row => {
                let accionesHtml = '';
                let estadoClase = 'table-row-hover transition-colors duration-150';
                const activos = parseInt(row.Conectados) || 0;
                let barraColor = (activos >= 10) ? 'bg-red-500' : (activos >= 8 ? 'bg-yellow-500' : 'bg-primary-dark');
                
                if (activos >= 10) estadoClase = 'row-disabled';

                if (puedeEditar) {
                    accionesHtml = `
                        <td data-label="Acciones" class="px-6 py-4 text-center whitespace-nowrap space-x-1">
                            <button class="btn-action-sm bg-blue-500 text-white hover:bg-blue-600 btn-edit" onclick="editarFila(${row.id})"><i class="fas fa-pencil-alt"></i></button>
                            <button class="btn-action-sm bg-green-500 text-white hover:bg-green-600 hidden btn-save" onclick="guardarFila(${row.id})"><i class="fas fa-save"></i></button>
                            <button class="btn-action-sm bg-gray-400 text-white hover:bg-gray-500 hidden btn-cancel" onclick="cancelarEdicion(${row.id})"><i class="fas fa-times"></i></button>
                        </td>
                    `;
                }

                const tr = document.createElement('tr');
                tr.className = estadoClase;
                tr.id = `fila_${row.id}`;
                tr.innerHTML = `
                    <td data-label="Dirección" class="px-6 py-4 editable-cell" data-campo="Dirección">
                        <span class="view-val">${row.Dirección}</span>
                        <input type="text" class="editable-input hidden">
                    </td>
                    <td data-label="Área" class="px-6 py-4 editable-cell" data-campo="Area">
                        <span class="view-val">${row.Area}</span>
                        <input type="text" class="editable-input hidden">
                    </td>
                    <td data-label="Correo" class="px-6 py-4 editable-cell" data-campo="Correo">
                        <span class="view-val text-primary-dark font-medium">${row.Correo}</span>
                        <input type="text" class="editable-input hidden">
                    </td>
                    <td data-label="Conexiones" class="px-6 py-4 text-center editable-cell" data-campo="Conectados">
                        <div class="view-val">
                            <b>${activos}</b> / 10
                            <div class="w-full bg-gray-200 rounded-full h-1 mt-1"><div class="${barraColor} h-1 rounded-full" style="width: ${(Math.min(activos, 10)/10)*100}%"></div></div>
                        </div>
                        <input type="number" class="editable-input hidden w-16 text-center" value="${activos}">
                    </td>
                    <td data-label="Contraseña" class="px-6 py-4 editable-cell" data-campo="Password" data-real-value="${row.Password}">
                        <div class="flex items-center gap-2">
                            <span class="view-val" id="pass_txt_${row.id}">••••••••</span>
                            <input type="text" class="editable-input hidden">
                            <button onclick="togglePass(${row.id})" class="text-gray-400 hover:text-primary-dark"><i class="fas fa-eye" id="pass_icon_${row.id}"></i></button>
                        </div>
                    </td>
                    ${accionesHtml}
                `;
                tabla.appendChild(tr);
            });
        }

        // --- TU FUNCIÓN DE PAGINACIÓN PERSONALIZADA ---
        function renderizarPaginacion(meta) {
            divPaginacion.innerHTML = '';
            const totalPag = meta.total_paginas;
            const actual = meta.pagina_actual;
            if (totalPag <= 1) return;

            const crearBtn = (texto, pag, activo) => {
                const btn = document.createElement('button');
                btn.innerHTML = texto;
                btn.className = `w-8 h-8 flex items-center justify-center text-sm font-medium rounded-md transition-all duration-200 focus:outline-none ${activo ? 'bg-primary-dark text-white shadow-md border border-primary-dark transform scale-105' : 'bg-white text-gray-600 border border-gray-200 hover:bg-gray-50 hover:border-gray-300 hover:text-primary-dark'}`;
                if (pag !== null) btn.onclick = () => { paginaActual = pag; cargarDatos(); };
                divPaginacion.appendChild(btn);
            };

            const delta = 1;
            if (actual > 1) crearBtn('<i class="fas fa-chevron-left text-xs"></i>', actual - 1, false);

            for (let i = 1; i <= totalPag; i++) {
                if (i === 1 || i === totalPag || (i >= actual - delta && i <= actual + delta)) {
                    crearBtn(i, i, i === actual);
                } else if (i === actual - delta - 1 || i === actual + delta + 1) {
                    crearBtn('...', null, false); 
                }
            }

            if (actual < totalPag) crearBtn('<i class="fas fa-chevron-right text-xs"></i>', actual + 1, false);
        }

        // --- FUNCIONES DE INTERACCIÓN ---
        function editarFila(id) {
            const fila = document.getElementById('fila_' + id);
            fila.classList.remove('row-disabled');
            fila.querySelector('.btn-edit').classList.add('hidden');
            fila.querySelector('.btn-save').classList.remove('hidden');
            fila.querySelector('.btn-cancel').classList.remove('hidden');
            
            fila.querySelectorAll('.editable-cell').forEach(cell => {
                const view = cell.querySelector('.view-val');
                const input = cell.querySelector('.editable-input');
                if (input) {
                    view.classList.add('hidden');
                    input.classList.remove('hidden');
                    input.value = (cell.dataset.campo === 'Password') ? cell.dataset.realValue : view.innerText.split(' /')[0];
                }
            });
        }

        function cancelarEdicion() { cargarDatos(); }

        function guardarFila(id) {
            const fila = document.getElementById('fila_' + id);
            const datos = new FormData();
            datos.append('id', id);
            fila.querySelectorAll('.editable-input').forEach(input => {
                const campo = input.closest('td').getAttribute('data-campo');
                datos.append(campo, input.value);
            });

            Swal.fire({ title: 'Guardando...', didOpen: () => Swal.showLoading() });
            fetch('actualizar_licencia.php', { method: 'POST', body: datos })
            .then(r => r.json())
            .then(data => {
                if(data.success) {
                    Swal.fire({icon: 'success', title: '¡Actualizado!', toast: true, position: 'top-end', showConfirmButton: false, timer: 1500});
                    cargarDatos();
                } else { Swal.fire('Error', data.message, 'error'); }
            });
        }

        function togglePass(id) {
            const cell = document.querySelector(`#fila_${id} [data-campo="Password"]`);
            const span = document.getElementById(`pass_txt_${id}`);
            const icon = document.getElementById(`pass_icon_${id}`);
            if (span.textContent.includes('•')) {
                span.textContent = cell.dataset.realValue;
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                span.textContent = '••••••••';
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        }
    </script>
</body>
</html>