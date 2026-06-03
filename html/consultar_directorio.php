<?php 
require_once 'config.php'; 
session_start();

// 1. SEGURIDAD DE SESIÓN
if (!isset($_SESSION['usuario'])) { 
    header("Location: index.php"); 
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
    $registros_por_pagina = 20;
    $offset = ($pagina - 1) * $registros_por_pagina;

    // Construir WHERE
    $where_clause = "";
    if (!empty($search_term)) {
        $safe_term = $conn->real_escape_string($search_term);
        $where_clause = " WHERE 
            direccion LIKE '%$safe_term%' OR 
            area_departamento LIKE '%$safe_term%' OR 
            nombre_personal LIKE '%$safe_term%' OR 
            extension LIKE '%$safe_term%' OR 
            numero_directo LIKE '%$safe_term%'";
    }

    // Contar total
    $count_sql = "SELECT COUNT(id) AS total FROM directorio_ext" . $where_clause;
    $total_registros = $conn->query($count_sql)->fetch_assoc()['total'];
    $total_paginas = ceil($total_registros / $registros_por_pagina);

    // Consulta de datos
    $sql = "SELECT * FROM directorio_ext" . $where_clause . " ORDER BY id DESC LIMIT $registros_por_pagina OFFSET $offset";
    $result = $conn->query($sql);
    
    $datos = [];
    while($row = $result->fetch_assoc()) {
        $datos[] = $row;
    }

    echo json_encode([
        'data' => $datos,
        'meta' => [
            'pagina_actual' => $pagina,
            'total_paginas' => $total_paginas,
            'total_registros' => $total_registros,
            'es_admin' => $esAdmin
        ]
    ]);
    exit;
}
?>

<!-- ==========================================
     3. FRONTEND: VISTA HTML
     ========================================== -->
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Directorio Telefónico</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/style.css"> 
    <script src="js/sweetalert2.all.min.js"></script>
    <style>
        /* ESTILOS BASE */
        body { background-color: #d6d1ca; font-family: 'Segoe UI', sans-serif; margin: 0; }
        .main-container { padding: 20px; max-width: 1500px; margin: 0 auto; }
        
        /* HEADER Y TÍTULO */
        .header-top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .header-top h2 { color: #721538; margin: 0; display: flex; align-items: center; gap: 10px; }
        .badge-count { background: #e0e0e0; color: #555; font-size: 0.6em; padding: 2px 8px; border-radius: 12px; vertical-align: middle; }

        /* TOOLBAR (CONTENEDOR DEL BUSCADOR) */
        .toolbar { 
            background: #fff; padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; 
            box-shadow: 0 4px 6px rgba(0,0,0,0.08); 
            display: flex; flex-wrap: wrap; gap: 15px; align-items: center; justify-content: space-between;
        }

        /* --- CORRECCIÓN DEFINITIVA DEL BUSCADOR (USANDO SVG) --- */
        .search-box {
            position: relative; 
            flex-grow: 1; 
            max-width: 500px;
            /* Quitamos flex para evitar conflictos de renderizado con absolute */
            display: block; 
        }

        .search-icon-svg { 
            position: absolute; 
            left: 15px; 
            top: 50%; 
            transform: translateY(-50%); 
            width: 20px;  /* Tamaño fijo exacto */
            height: 20px; /* Tamaño fijo exacto */
            color: #999;
            z-index: 10;
            pointer-events: none;
        }

        .search-input {
            width: 100%; 
            height: 45px;
            /* Espacio exacto para el ícono + margen */
            padding-left: 50px !important; 
            padding-right: 15px;
            
            border: 1px solid #ccc; 
            border-radius: 25px; 
            font-size: 16px; 
            outline: none; 
            transition: border-color 0.3s, box-shadow 0.3s;
            box-sizing: border-box; 
        }
        
        .search-input:focus { 
            border-color: #721538; 
            box-shadow: 0 0 0 3px rgba(114, 21, 56, 0.1); 
        }

        /* LOADER */
        .loader { 
            border: 3px solid #f3f3f3; border-top: 3px solid #721538; border-radius: 50%; 
            width: 24px; height: 24px; animation: spin 0.8s linear infinite; display: none; margin-left: 10px;
        }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }

        /* TABLA (PC) */
        .table-container { background: white; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); overflow: hidden; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #721538; color: white; padding: 15px; text-align: left; text-transform: uppercase; font-size: 0.85em; letter-spacing: 1px; }
        td { padding: 12px 15px; border-bottom: 1px solid #eee; color: #333; font-size: 0.95em; vertical-align: middle; }
        tr:hover { background-color: #fff8e1; }

        /* BOTONES */
        .btn-action { padding: 6px 10px; border-radius: 4px; border: none; cursor: pointer; color: white; margin-right: 2px; }
        .btn-edit { background: #3498db; }
        .btn-save { background: #27ae60; display: none; }
        .btn-cancel { background: #95a5a6; display: none; }
        
        .btn-add, .btn-menu { 
            padding: 10px 20px; border-radius: 25px; font-weight: bold; text-decoration: none; display: inline-block; text-align: center;
        }
        .btn-add { background: #27ae60; color: white; margin-right: 5px; }
        .btn-add:hover { background: #219150; }
        .btn-menu { background: #555; color: white; }
        .btn-menu:hover { background: #333; }

        .input-edit { width: 100%; padding: 6px; border: 1px solid #721538; border-radius: 4px; box-sizing: border-box; }

        /* PAGINACIÓN */
        .paginacion-container { display: flex; justify-content: center; margin-top: 20px; gap: 5px; }
        .page-btn { padding: 8px 14px; border: 1px solid #ddd; background: white; cursor: pointer; border-radius: 4px; transition: 0.2s; }
        .page-btn:hover { background: #eee; }
        .page-btn.active { background: #721538; color: white; border-color: #721538; }

        /* --- VISTA MÓVIL (TARJETAS) --- */
        @media (max-width: 768px) {
            .table-container { background: transparent; box-shadow: none; }
            table, thead, tbody, th, td, tr { display: block; }
            thead tr { position: absolute; top: -9999px; left: -9999px; } 
            
            tr { 
                background: white; border-radius: 10px; margin-bottom: 15px; 
                box-shadow: 0 2px 8px rgba(0,0,0,0.08); border-left: 5px solid #721538; 
                padding: 15px; position: relative;
            }
            
            td { 
                border: none; padding: 8px 0; position: relative; padding-left: 40%; 
                text-align: right; border-bottom: 1px solid #f0f0f0; display: flex; justify-content: flex-end; align-items: center;
            }
            td:last-child { border-bottom: none; }
            
            td:before { 
                content: attr(data-label); 
                position: absolute; left: 0; top: 50%; transform: translateY(-50%);
                width: 35%; padding-right: 10px; font-weight: bold; color: #721538; 
                text-transform: uppercase; font-size: 0.75em; text-align: left;
            }

            .search-box { max-width: 100%; margin-bottom: 10px; }
            .btn-add, .btn-menu { width: 100%; margin-bottom: 8px; display: block; }
            .toolbar { flex-direction: column; align-items: stretch; }
        }
    </style>
</head>
<body>

<div class="main-container">
    <div class="header-top">
        <h2>
            📞 Directorio 
            <span class="badge-count" id="total-lbl">Cargando...</span>
            <div id="loading" class="loader"></div>
        </h2>
    </div>

    <div class="toolbar">
        <div class="search-box">
            <!-- CAMBIO: Usamos SVG en lugar de Emoji para alineación perfecta -->
            <svg class="search-icon-svg" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
            
            <input type="text" id="searchInput" class="search-input" placeholder="Buscar nombre, extensión, área...">
        </div>
        
        <div style="display: flex; gap: 5px; flex-wrap: wrap;">
            <?php if ($esAdmin): ?>
                <a href="registro_directorio.php" class="btn-add">+ Nuevo</a>
            <?php endif; ?>
            <a href="dashboard.php" class="btn-menu">Menú</a>
        </div>
    </div>

    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Dirección</th>
                    <th>Área / Depto</th>
                    <th>Nombre</th>
                    <th style="text-align:center;">Ext.</th>
                    <th style="text-align:center;">Directo</th>
                    <?php if ($esAdmin): ?><th style="text-align:center;">Acciones</th><?php endif; ?>
                </tr>
            </thead>
            <tbody id="tabla-resultados">
                <!-- AQUÍ SE INYECTAN LOS DATOS CON JS -->
            </tbody>
        </table>
    </div>

    <div class="paginacion-container" id="paginacion">
        <!-- BOTONES DE PAGINACIÓN -->
    </div>
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

        fetch(`consultar_directorio.php?ajax=1&q=${encodeURIComponent(terminoBusqueda)}&p=${paginaActual}`)
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
            tabla.innerHTML = '<tr><td colspan="6" style="text-align:center; padding:30px; color:#888;">No se encontraron resultados</td></tr>';
            return;
        }

        datos.forEach(row => {
            let accionesHtml = '';
            if (esAdmin) {
                accionesHtml = `
                    <td data-label="Acciones" style="text-align:center; justify-content: center;">
                        <button class="btn-action btn-edit" onclick="activarEdicion(${row.id})" title="Editar">✏️</button>
                        <button class="btn-action btn-save" onclick="guardarFila(${row.id})" title="Guardar">💾</button>
                        <button class="btn-action btn-cancel" onclick="cancelarEdicion(${row.id})" title="Cancelar">✕</button>
                    </td>
                `;
            }

            const tr = document.createElement('tr');
            tr.id = `fila_${row.id}`;
            tr.innerHTML = `
                <td data-label="Dirección" class="editable" data-campo="direccion">
                    <span class="view-val">${row.direccion}</span>
                    <input type="text" class="input-edit" style="display:none;" value="${row.direccion}">
                </td>
                <td data-label="Área" class="editable" data-campo="area_departamento">
                    <span class="view-val">${row.area_departamento}</span>
                    <input type="text" class="input-edit" style="display:none;" value="${row.area_departamento}">
                </td>
                <td data-label="Nombre" class="editable" data-campo="nombre_personal">
                    <span class="view-val" style="font-weight:bold;">${row.nombre_personal}</span>
                    <input type="text" class="input-edit" style="display:none;" value="${row.nombre_personal}">
                </td>
                <td data-label="Extensión" class="editable" data-campo="extension" style="text-align:center; justify-content: center;">
                    <span class="view-val" style="background:#fce4ec; padding:4px 8px; border-radius:4px; font-weight:bold; color:#721538;">${row.extension}</span>
                    <input type="text" class="input-edit" style="display:none; text-align:center;" value="${row.extension}">
                </td>
                <td data-label="Directo" class="editable" data-campo="numero_directo" style="text-align:center; justify-content: center;">
                    <span class="view-val">${row.numero_directo}</span>
                    <input type="text" class="input-edit" style="display:none; text-align:center;" value="${row.numero_directo}">
                </td>
                ${accionesHtml}
            `;
            tabla.appendChild(tr);
        });
    }

    // Renderizar Botones de Paginación
    function renderizarPaginacion(meta) {
        divPaginacion.innerHTML = '';
        const totalPag = meta.total_paginas;
        const actual = meta.pagina_actual;
        
        if (totalPag <= 1) return;

        if (actual > 1) crearBotonPag('«', actual - 1);

        let start = Math.max(1, actual - 2);
        let end = Math.min(totalPag, actual + 2);

        for (let i = start; i <= end; i++) {
            crearBotonPag(i, i, i === actual);
        }

        if (actual < totalPag) crearBotonPag('»', actual + 1);
    }

    function crearBotonPag(texto, paginaDestino, esActivo = false) {
        const btn = document.createElement('button');
        btn.innerText = texto;
        btn.className = `page-btn ${esActivo ? 'active' : ''}`;
        btn.onclick = () => {
            paginaActual = paginaDestino;
            cargarDatos();
        };
        divPaginacion.appendChild(btn);
    }

    // ==========================================
    // 4. LÓGICA DE EDICIÓN (JS)
    // ==========================================
    function activarEdicion(id) {
        const fila = document.getElementById('fila_' + id);
        fila.querySelectorAll('.view-val').forEach(el => el.style.display = 'none');
        fila.querySelectorAll('.input-edit').forEach(el => el.style.display = 'block');
        fila.querySelector('.btn-edit').style.display = 'none';
        fila.querySelector('.btn-save').style.display = 'inline-block';
        fila.querySelector('.btn-cancel').style.display = 'inline-block';
    }

    function cancelarEdicion(id) {
        const fila = document.getElementById('fila_' + id);
        fila.querySelectorAll('.view-val').forEach(el => el.style.display = 'block'); // Cambiado a block para evitar problemas flex
        fila.querySelectorAll('.input-edit').forEach(el => {
            el.style.display = 'none';
            el.value = el.previousElementSibling.innerText; 
        });
        fila.querySelector('.btn-edit').style.display = 'inline-block';
        fila.querySelector('.btn-save').style.display = 'none';
        fila.querySelector('.btn-cancel').style.display = 'none';
    }

    function guardarFila(id) {
        const fila = document.getElementById('fila_' + id);
        const datos = new FormData();
        datos.append('id', id);

        fila.querySelectorAll('.editable').forEach(td => {
            const campo = td.getAttribute('data-campo');
            const valor = td.querySelector('input').value;
            datos.append(campo, valor);
        });

        Swal.fire({
            title: 'Guardando...', didOpen: () => Swal.showLoading()
        });

        fetch('guardar_edicion_directorio.php', { method: 'POST', body: datos })
            .then(r => r.json())
            .then(data => {
                if(data.success) {
                    Swal.fire({ icon: 'success', title: '¡Actualizado!', timer: 1500, showConfirmButton: false });
                    cargarDatos();
                } else {
                    Swal.fire('Error', data.message || 'Error desconocido', 'error');
                }
            })
            .catch(err => Swal.fire('Error', 'Fallo de conexión', 'error'));
    }
</script>

</body>
</html>
<?php $conn->close(); ?>