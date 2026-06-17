<?php 
require_once 'session_check.php';
require_once 'config.php'; 

// 1. SEGURIDAD DE SESIÓN
if (!isset($_SESSION['usuario'])) { 
    header("Location: login.php"); 
    exit(); 
}

if ($_SESSION['rol'] === 'redes') {
    header("Location: dashboard.php?error=acceso_denegado");
    exit();
}

$conn = get_db_connection();
$rol_usuario = $_SESSION['rol'] ?? 'tecnico'; 
$esAdmin = ($rol_usuario === 'admin' || $rol_usuario === 'masterweb'); 

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
    if (!empty($search_term)) {
        $safe_term = $conn->real_escape_string($search_term);
        $where_clause = " WHERE 
            tipo LIKE '%$safe_term%' OR 
            marca LIKE '%$safe_term%' OR 
            modelo LIKE '%$safe_term%' OR 
            descripcion LIKE '%$safe_term%'";
    }

    // Contar total
    $count_sql = "SELECT COUNT(id) AS total FROM stock_material" . $where_clause;
    $resCount = $conn->query($count_sql);
    $total_registros = $resCount ? $resCount->fetch_assoc()['total'] : 0;
    $total_paginas = ceil($total_registros / $registros_por_pagina);

    // Consulta de datos
    $sql = "SELECT * FROM stock_material" . $where_clause . " ORDER BY fecha_alta DESC, id DESC LIMIT $registros_por_pagina OFFSET $offset";
    $result = $conn->query($sql);
    
    $datos = [];
    if ($result) {
        while($row = $result->fetch_assoc()) {
            $datos[] = $row;
        }
    }

    echo json_encode([
        'data' => $datos,
        'meta' => [
            'pagina_actual' => $pagina,
            'total_paginas' => $total_paginas,
            'total_registros' => $total_registros
        ]
    ]);
    exit;
}
include 'header.php';
?>
<style>
    .input-edit {
        width: 100%; padding: 6px 10px; border: 1px solid #721538; border-radius: 6px; box-sizing: border-box; font-size: 0.9rem;
    }
    .stock-bajo {
        color: #c0392b; font-weight: bold; background: #fadbd8; padding: 2px 6px; border-radius: 4px;
    }
    .stock-ok {
        color: #27ae60; font-weight: bold;
    }
    @media (max-width: 768px) {
        thead { display: none; }
        table, tbody, tr, td { display: block; width: 100%; white-space: normal !important; }
        tr {
            background: white; border-radius: 10px; margin-bottom: 15px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08); border-left: 5px solid #721538;
            padding: 15px; position: relative;
        }
        td { border: none; padding: 8px 0; border-bottom: 1px solid #f0f0f0; display: flex; justify-content: space-between; align-items: center; gap: 15px; min-height: 35px; }
        td:last-child { border-bottom: none; justify-content: center; padding-top: 15px; }
        td:before { content: attr(data-label); font-weight: bold; color: #721538; text-transform: uppercase; font-size: 0.75em; text-align: left; width: 35%; flex-shrink: 0; position: static; }
        td > .view-val, td > .input-edit, td > input { text-align: right; width: 60%; word-wrap: break-word; overflow-wrap: break-word; word-break: break-word; display: block; }
    }
</style>

<div class="px-4 sm:px-8 max-w-7xl mx-auto space-y-6">
    <div class="flex flex-col sm:flex-row justify-between items-center mb-6">
        <h2 class="text-3xl font-bold text-primary-dark flex items-center gap-2">
            <i class="fas fa-boxes"></i> Material en Existencia
            <span class="text-xs bg-gray-200 text-gray-600 px-3 py-1 rounded-full italic" id="total-lbl">...</span>
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
            <input type="text" id="searchInput" placeholder="Buscar tipo, marca, modelo..." class="w-full pl-11 p-3 border border-gray-300 rounded-full focus:ring-2 focus:ring-primary-dark outline-none transition">
        </div>
           
        <div class="flex gap-2 w-full lg:w-auto justify-end">
            <a href="registrar_material.php" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2.5 px-6 rounded-full shadow transition flex items-center gap-2 justify-center">
                <i class="fas fa-plus"></i> Registrar
            </a>
            <a href="salida_material.php" class="bg-orange-600 hover:bg-orange-700 text-white font-bold py-2.5 px-6 rounded-full shadow transition flex items-center gap-2 justify-center">
                <i class="fas fa-sign-out-alt"></i> Salida
            </a>
            <a href="consultar_salidas.php" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2.5 px-6 rounded-full shadow transition flex items-center gap-2 justify-center">
                <i class="fas fa-list"></i> Historial
            </a>
            <a href="dashboard.php" class="bg-gray-700 hover:bg-gray-800 text-white font-bold py-2.5 px-6 rounded-full shadow transition flex items-center gap-2 justify-center">
                <i class="fas fa-th"></i> Menú
            </a>
        </div>
    </div>

    <div class="bg-white shadow-lg rounded-xl overflow-hidden border border-gray-100 overflow-x-auto relative z-0">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-primary-dark text-white text-xs font-bold uppercase tracking-wider">
                <tr>
                    <th class="px-6 py-4 text-left">Tipo</th>
                    <th class="px-6 py-4 text-left">Marca</th>
                    <th class="px-6 py-4 text-left">Modelo</th>
                    <th class="px-6 py-4 text-left">Descripción</th>
                    <th class="px-6 py-4 text-center">Unidades</th>
                    <th class="px-6 py-4 text-center">Fecha Alta</th>
                    <?php if($esAdmin): ?>
                        <th class="px-6 py-4 text-center">Acciones</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody id="tabla-resultados" class="text-sm divide-y divide-gray-100 bg-white">
                <!-- DATOS CARGADOS VIA JS -->
            </tbody>
        </table>
    </div>

    <div id="paginacion" class="mt-8 flex justify-center gap-2 flex-wrap"></div>
</div>

<script>
    // Variables Globales
    let paginaActual = 1;
    let terminoBusqueda = '';
    const esAdmin = <?= $esAdmin ? 'true' : 'false' ?>; 
    let timeoutBusqueda = null;

    // Elementos DOM
    const tabla = document.getElementById('tabla-resultados');
    const inputBusqueda = document.getElementById('searchInput');
    const divPaginacion = document.getElementById('paginacion');
    const loader = document.getElementById('loading');
    const totalLbl = document.getElementById('total-lbl');

    // Inicializar
    document.addEventListener('DOMContentLoaded', () => {
        cargarDatos();
        
        inputBusqueda.addEventListener('input', (e) => {
            clearTimeout(timeoutBusqueda);
            terminoBusqueda = e.target.value;
            paginaActual = 1; 
            
            timeoutBusqueda = setTimeout(() => {
                cargarDatos();
            }, 300); 
        });
    });

    // Función AJAX principal
    function cargarDatos() {
        if(loader) loader.style.display = 'inline-block';
        tabla.style.opacity = '0.5';

        fetch(`consultar_stock.php?ajax=1&q=${encodeURIComponent(terminoBusqueda)}&p=${paginaActual}`)
            .then(res => res.json())
            .then(res => {
                renderizarTabla(res.data);
                renderizarPaginacion(res.meta);
                if(totalLbl) totalLbl.innerText = `${res.meta.total_registros}`;
            })
            .catch(err => console.error('Error:', err))
            .finally(() => {
                if(loader) loader.style.display = 'none';
                tabla.style.opacity = '1';
            });
    }

    // Renderizar Filas
    function renderizarTabla(datos) {
        tabla.innerHTML = '';
        
        if (datos.length === 0) {
            const colspan = esAdmin ? 7 : 6; 
            tabla.innerHTML = `<tr><td colspan="${colspan}" style="text-align:center; padding:30px; color:#888;">No se encontraron materiales.</td></tr>`;
            return;
        }

        datos.forEach(row => {
            const unidades = parseInt(row.unidades);
            const claseStock = unidades < 5 ? 'stock-bajo' : 'stock-ok';
            let accionesHtml = '';

            if (esAdmin) {
                accionesHtml = `
                    <td data-label="Acciones" class="px-6 py-4 text-center whitespace-nowrap">
                        <button class="btn-edit w-8 h-8 rounded border border-gray-300 text-gray-500 hover:text-blue-600 hover:border-blue-600 transition inline-flex items-center justify-center cursor-pointer" onclick="activarEdicion(${row.id})" title="Editar"><i class="fas fa-pencil-alt"></i></button>
                        <button class="btn-save w-8 h-8 rounded border border-green-300 text-green-500 hover:text-green-600 hover:border-green-600 transition inline-flex items-center justify-center cursor-pointer" style="display:none;" onclick="guardarFila(${row.id})" title="Guardar"><i class="fas fa-save"></i></button>
                        <button class="btn-cancel w-8 h-8 rounded border border-red-300 text-red-500 hover:text-red-600 hover:border-red-600 transition inline-flex items-center justify-center cursor-pointer" style="display:none;" onclick="cancelarEdicion(${row.id})" title="Cancelar"><i class="fas fa-times"></i></button>
                    </td>
                `;
            }

            const marcaVis = row.marca ? row.marca : '-';
            const modeloVis = row.modelo ? row.modelo : '-';

            const tr = document.createElement('tr');
            tr.id = `fila_${row.id}`;
            tr.className = "hover:bg-[#fdf2f5] transition-colors duration-150 border-b border-gray-100";
            tr.innerHTML = `
                <td data-label="Tipo" class="editable px-6 py-4" data-campo="tipo">
                    <span class="view-val" style="font-weight:bold;">${row.tipo}</span>
                    <input type="text" class="input-edit" style="display:none;" value="${row.tipo}">
                </td>

                <td data-label="Marca" class="editable px-6 py-4" data-campo="marca">
                    <span class="view-val">${marcaVis}</span>
                    <input type="text" class="input-edit" style="display:none;" value="${row.marca || ''}">
                </td>

                <td data-label="Modelo" class="editable px-6 py-4" data-campo="modelo">
                    <span class="view-val">${modeloVis}</span>
                    <input type="text" class="input-edit" style="display:none;" value="${row.modelo || ''}">
                </td>

                <td data-label="Descripción" class="editable px-6 py-4" data-campo="descripcion">
                    <span class="view-val font-serif italic text-gray-500">${row.descripcion}</span>
                    <input type="text" class="input-edit" style="display:none;" value="${row.descripcion}">
                </td>

                <td data-label="Unidades" class="editable px-6 py-4 text-center" data-campo="unidades">
                    <span class="view-val ${claseStock}">${row.unidades}</span>
                    <input type="number" class="input-edit" style="display:none; text-align:center;" value="${row.unidades}">
                </td>

                <td data-label="Fecha Alta" class="editable px-6 py-4 text-center" data-campo="fecha_alta">
                    <span class="view-val">${row.fecha_alta}</span>
                    <input type="date" class="input-edit" style="display:none;" value="${row.fecha_alta}">
                </td>

                ${accionesHtml}
            `;
            tabla.appendChild(tr);
        });
    }

    // --- PAGINACIÓN INTELIGENTE ---
    function renderizarPaginacion(meta) {
        divPaginacion.innerHTML = '';
        const totalPag = meta.total_paginas;
        const actual = parseInt(meta.pagina_actual);
        
        if (totalPag <= 1) return;

        // Función auxiliar para crear botones
        const crearBtn = (html, page, isActive = false, isDisabled = false) => {
            const btn = document.createElement('button');
            btn.innerHTML = html;
            btn.className = 'page-btn';
            
            if (isActive) btn.classList.add('active');
            if (isDisabled) {
                btn.classList.add('disabled');
                btn.disabled = true;
            } else {
                btn.onclick = () => { paginaActual = page; cargarDatos(); };
            }
            divPaginacion.appendChild(btn);
        };

        // Botón Anterior
        if (actual > 1) {
            crearBtn('<i class="fas fa-chevron-left"></i>', actual - 1);
        }

        // Lógica de rangos
        const range = 2; 
        
        for (let i = 1; i <= totalPag; i++) {
            if (i === 1 || i === totalPag || (i >= actual - range && i <= actual + range)) {
                crearBtn(i, i, i === actual);
            } 
            else if (i === actual - range - 1 || i === actual + range + 1) {
                crearBtn('...', null, false, true);
            }
        }

        // Botón Siguiente
        if (actual < totalPag) {
            crearBtn('<i class="fas fa-chevron-right"></i>', actual + 1);
        }
    }

    function activarEdicion(id) {
        const fila = document.getElementById('fila_' + id);
        fila.querySelectorAll('.view-val').forEach(el => el.style.display = 'none');
        fila.querySelectorAll('.input-edit').forEach(el => el.style.display = 'block');
        fila.querySelector('.btn-edit').style.display = 'none';
        fila.querySelector('.btn-save').style.display = 'inline-block';
        fila.querySelector('.btn-cancel').style.display = 'inline-block';
    }

    function cancelarEdicion(id) { cargarDatos(); }

    function guardarFila(id) {
        const fila = document.getElementById('fila_' + id);
        const datos = new FormData();
        datos.append('id', id);

        fila.querySelectorAll('.editable').forEach(td => {
            const campo = td.getAttribute('data-campo');
            const input = td.querySelector('input');
            if(input) datos.append(campo, input.value);
        });

        Swal.fire({ title: 'Actualizando stock...', didOpen: () => Swal.showLoading() });

        fetch('actualizar_stock.php', { method: 'POST', body: datos })
            .then(r => r.json())
            .then(data => {
                if(data.success) {
                    Swal.fire({ icon: 'success', title: '¡Inventario Actualizado!', timer: 1500, showConfirmButton: false });
                    cargarDatos();
                } else {
                    Swal.fire('Error', data.message || 'Error desconocido', 'error');
                }
            })
            .catch(err => Swal.fire('Error', 'Fallo de conexión', 'error'));
    }
</script>
</div>
</main>
</body>
</html>
<?php $conn->close(); ?>