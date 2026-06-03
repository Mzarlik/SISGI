<?php 
require_once 'config.php'; 
session_start();

// 1. SEGURIDAD DE SESIÓN
if (!isset($_SESSION['usuario'])) { 
    header("Location: login.php"); 
    exit(); 
}

if ($_SESSION['rol'] === 'redes') {
    // Si es redes, lo regresamos al dashboard con un mensaje de error opcional
    header("Location: dashboard.php?error=acceso_denegado");
    exit();
}

$conn = get_db_connection();
$rol_usuario = $_SESSION['rol'] ?? 'tecnico'; 
// Esta variable PHP es CRÍTICA para controlar la vista
$esAdmin = ($rol_usuario === 'admin' || $rol_usuario === 'masterweb'); 

// ==========================================
// 2. BACKEND: RESPUESTA AJAX (JSON)
// (Sin cambios, ya que la lógica de permisos está en el frontend)
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
    $total_registros = $conn->query($count_sql)->fetch_assoc()['total'];
    $total_paginas = ceil($total_registros / $registros_por_pagina);

    // Consulta de datos
    $sql = "SELECT * FROM stock_material" . $where_clause . " ORDER BY fecha_alta DESC, id DESC LIMIT $registros_por_pagina OFFSET $offset";
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
            'es_admin' => $esAdmin // Este dato es irrelevante ahora, ya que usamos la variable global JS
        ]
    ]);
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Inventario Stock</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/style.css"> 
    <script src="js/sweetalert2.all.min.js"></script>
    <style>
        /* --- ESTILOS IDÉNTICOS A LA REFERENCIA --- */
        body { background-color: #d6d1ca; font-family: 'Segoe UI', sans-serif; margin: 0; }
        .main-container { padding: 20px; max-width: 1500px; margin: 0 auto; }
        
        /* HEADER */
        .header-top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .header-top h2 { color: #721538; margin: 0; display: flex; align-items: center; gap: 10px; }
        .badge-count { background: #e0e0e0; color: #555; font-size: 0.6em; padding: 2px 8px; border-radius: 12px; vertical-align: middle; }

        /* TOOLBAR & BUSCADOR */
        .toolbar { 
            background: #fff; padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; 
            box-shadow: 0 4px 6px rgba(0,0,0,0.08); 
            display: flex; flex-wrap: wrap; gap: 15px; align-items: center; justify-content: space-between;
        }

        .search-box { position: relative; flex-grow: 1; max-width: 500px; display: block; }
        .search-icon-svg { 
            position: absolute; left: 15px; top: 50%; transform: translateY(-50%); 
            width: 20px; height: 20px; color: #999; z-index: 10; pointer-events: none;
        }
        .search-input {
            width: 100%; height: 45px; padding-left: 50px !important; padding-right: 15px;
            border: 1px solid #ccc; border-radius: 25px; font-size: 16px; outline: none; 
            transition: border-color 0.3s, box-shadow 0.3s; box-sizing: border-box; 
        }
        .search-input:focus { border-color: #721538; box-shadow: 0 0 0 3px rgba(114, 21, 56, 0.1); }

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
        .btn-add { background: green; color: white; margin-right: 5px; } 
        .btn-add:hover { background: #d35400; }
        .btn-menu { background: #555; color: white; }
        .btn-menu:hover { background: #333; }

        .input-edit { width: 100%; padding: 6px; border: 1px solid #721538; border-radius: 4px; box-sizing: border-box; }

        /* ALERTAS STOCK */
        .stock-bajo { color: #c0392b; font-weight: bold; background: #fadbd8; padding: 2px 6px; border-radius: 4px; }
        .stock-ok { color: #27ae60; font-weight: bold; }

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
            📦 Material en existencia
            <span class="badge-count" id="total-lbl">Cargando...</span>
            <div id="loading" class="loader"></div>
        </h2>
    </div>

    <div class="toolbar">
        <div class="search-box">
            <svg class="search-icon-svg" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
            <input type="text" id="searchInput" class="search-input" placeholder="Buscar tipo, marca, modelo...">
        </div>
           
        <div style="display: flex; gap: 5px; flex-wrap: wrap;">
            <a href="registrar_material.php" class="btn-add">+ Registrar</a>
            <a href="salida_material.php" class="btn-add">Salida de material</a>
            <a href="consultar_salidas.php" class="btn-add">Consultar salida</a>
            <a href="dashboard.php" class="btn-menu">Menu principal</a>
        </div>



    </div>

    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Tipo</th>
                    <th>Marca</th>
                    <th>Modelo</th>
                    <th>Descripción</th>
                    <th style="text-align:center;">Unidades</th>
                    <th style="text-align:center;">Fecha Alta</th>
                    <?php if($esAdmin): ?>
                        <th style="text-align:center;">Acciones</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody id="tabla-resultados">
                </tbody>
        </table>
    </div>

    <div class="paginacion-container" id="paginacion">
        </div>
</div>

<script>
    // Variables Globales
    let paginaActual = 1;
    let terminoBusqueda = '';
    
    // CRÍTICO: La variable 'esAdmin' se define aquí
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

    // Función AJAX principal (sin cambios)
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
            // Ajustamos el colspan para que sea 6 si no hay columna de acciones, o 7 si sí la hay.
            const colspan = esAdmin ? 7 : 6; 
            tabla.innerHTML = `<tr><td colspan="${colspan}" style="text-align:center; padding:30px; color:#888;">No se encontraron materiales.</td></tr>`;
            return;
        }

        datos.forEach(row => {
            // Lógica visual para stock bajo
            const unidades = parseInt(row.unidades);
            const claseStock = unidades < 5 ? 'stock-bajo' : 'stock-ok';

            // CAMBIO 2: Inicializar accionesHtml vacío y llenarlo solo si es Admin
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

            // Verificación si marca/modelo están vacíos para mostrar guión
            const marcaVis = row.marca ? row.marca : '-';
            const modeloVis = row.modelo ? row.modelo : '-';

            const tr = document.createElement('tr');
            tr.id = `fila_${row.id}`;
            tr.innerHTML = `
                <td data-label="Tipo" class="editable" data-campo="tipo">
                    <span class="view-val" style="font-weight:bold;">${row.tipo}</span>
                    <input type="text" class="input-edit" style="display:none;" value="${row.tipo}">
                </td>

                <td data-label="Marca" class="editable" data-campo="marca">
                    <span class="view-val">${marcaVis}</span>
                    <input type="text" class="input-edit" style="display:none;" value="${row.marca || ''}">
                </td>

                <td data-label="Modelo" class="editable" data-campo="modelo">
                    <span class="view-val">${modeloVis}</span>
                    <input type="text" class="input-edit" style="display:none;" value="${row.modelo || ''}">
                </td>

                <td data-label="Descripción" class="editable" data-campo="descripcion">
                    <span class="view-val" style="font-style:italic; color:#555;">${row.descripcion}</span>
                    <input type="text" class="input-edit" style="display:none;" value="${row.descripcion}">
                </td>

                <td data-label="Unidades" class="editable" data-campo="unidades" style="text-align:center; justify-content: center;">
                    <span class="view-val ${claseStock}">${row.unidades}</span>
                    <input type="number" class="input-edit" style="display:none; text-align:center;" value="${row.unidades}">
                </td>

                <td data-label="Fecha Alta" class="editable" data-campo="fecha_alta" style="text-align:center; justify-content: center;">
                    <span class="view-val">${row.fecha_alta}</span>
                    <input type="date" class="input-edit" style="display:none;" value="${row.fecha_alta}">
                </td>

                ${accionesHtml}
            `;
            tabla.appendChild(tr);
        });
    }

    // El resto de las funciones JS (renderizarPaginacion, crearBotonPag, activarEdicion, cancelarEdicion, guardarFila)
    // No necesitan cambios, pero debes asegurarte de que si llaman a una función de edición, 
    // el usuario ya haya pasado por la comprobación de rol.
    // Como los botones solo se muestran a admins, las llamadas a esas funciones son seguras.
    
    function renderizarPaginacion(meta) {
            divPaginacion.innerHTML = '';
            const totalPag = meta.total_paginas;
            const actual = meta.pagina_actual;
            if (totalPag <= 1) return;

            const crearBtn = (texto, pag, activo) => {
                const btn = document.createElement('button');
                btn.innerHTML = texto;
                btn.className = `w-8 h-8 flex items-center justify-center text-sm font-medium rounded-md transition-all duration-200 focus:outline-none ${activo ? 'bg-primary-dark text-white shadow-md border border-primary-dark transform scale-105' : 'bg-white text-gray-600 border border-gray-200 hover:bg-gray-50 hover:border-gray-300 hover:text-primary-dark'}`;
                btn.onclick = () => { paginaActual = pag; cargarDatos(); };
                divPaginacion.appendChild(btn);
            };

            const delta = 1;
            if (actual > 1) crearBtn('<i class="fas fa-chevron-left text-xs"></i>', actual - 1, false);

            for (let i = 1; i <= totalPag; i++) {
                if (i === 1 || i === totalPag || (i >= actual - delta && i <= actual + delta)) {
                    crearBtn(i, i, i === actual);
                } else if (i === actual - delta - 1 || i === actual + delta + 1) {
                    crearBtn('...', null, false, true); 
                }
            }

            if (actual < totalPag) crearBtn('<i class="fas fa-chevron-right text-xs"></i>', actual + 1, false);
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

    function activarEdicion(id) {
        // Esta función solo es llamada si el botón existe (es decir, esAdmin es true)
        const fila = document.getElementById('fila_' + id);
        fila.querySelectorAll('.view-val').forEach(el => el.style.display = 'none');
        fila.querySelectorAll('.input-edit').forEach(el => el.style.display = 'block');
        fila.querySelector('.btn-edit').style.display = 'none';
        fila.querySelector('.btn-save').style.display = 'inline-block';
        fila.querySelector('.btn-cancel').style.display = 'inline-block';
    }

    function cancelarEdicion(id) {
        // Función de cancelar sin cambios
        cargarDatos();
    }

    function guardarFila(id) {
        // Función de guardar sin cambios
        const fila = document.getElementById('fila_' + id);
        const datos = new FormData();
        datos.append('id', id);

        fila.querySelectorAll('.editable').forEach(td => {
            const campo = td.getAttribute('data-campo');
            const input = td.querySelector('input');
            if(input) {
                datos.append(campo, input.value);
            }
        });

        Swal.fire({
            title: 'Actualizando stock...', didOpen: () => Swal.showLoading()
        });

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

</body>
</html>
<?php $conn->close(); ?>