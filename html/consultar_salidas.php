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
// Solo los administradores suelen poder editar o eliminar registros de movimientos.
$esAdmin = ($rol_usuario === 'admin' || $rol_usuario === 'masterweb'); 

// ==========================================
// 2. BACKEND: RESPUESTA AJAX (JSON)
// ==========================================
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    
    $search_term = $_GET['q'] ?? '';
    $pagina = isset($_GET['p']) && is_numeric($_GET['p']) ? (int)$_GET['p'] : 1;
    $registros_por_pagina = 15;
    $offset = ($pagina - 1) * $registros_por_pagina;
    
    // Filtro principal: SOLO SALIDAS (asumiendo que las salidas son cantidades positivas en 'cantidad' o que solo registras salidas)
    // Usaremos un alias 'ms' para movimientos_stock y 'sm' para stock_material
    $base_where = " WHERE ms.cantidad > 0 "; 
    $where_clause = $base_where;

    // Construir WHERE para la búsqueda
    if (!empty($search_term)) {
        $safe_term = $conn->real_escape_string($search_term);
        $where_clause .= " AND (
            sm.tipo LIKE '%$safe_term%' OR 
            sm.marca LIKE '%$safe_term%' OR 
            sm.modelo LIKE '%$safe_term%' OR 
            ms.descripcion_salida LIKE '%$safe_term%' OR
            ms.usuario LIKE '%$safe_term%'
        )";
    }

    // Contar total
    $count_sql = "SELECT COUNT(ms.id) AS total FROM movimientos_stock ms
                  JOIN stock_material sm ON ms.material_id = sm.id" . $where_clause;
    
    $total_registros = $conn->query($count_sql)->fetch_assoc()['total'];
    $total_paginas = ceil($total_registros / $registros_por_pagina);

    // Consulta de datos (MODIFICADO: Se agrega sm.descripcion)
    $sql = "SELECT 
                ms.id as movimiento_id, 
                ms.cantidad, 
                ms.descripcion_salida, 
                ms.usuario, 
                ms.fecha_movimiento,
                sm.tipo, 
                sm.marca, 
                sm.modelo,
                sm.descripcion  
            FROM movimientos_stock ms
            JOIN stock_material sm ON ms.material_id = sm.id" . $where_clause . " 
            ORDER BY ms.fecha_movimiento DESC 
            LIMIT $registros_por_pagina OFFSET $offset";
            
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
            'total_registros' => $total_registros
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
    <title>Historial de Salidas</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/style.css"> 
    <script src="js/sweetalert2.all.min.js"></script>
    <link rel="stylesheet" href="css/all.min.css">
    
    <style>
        /* --- ESTILOS VISUALES COPIADOS DE CONSULTAR_STOCK.PHP --- */
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
        
        /* BOTONES DE BARRA DE HERRAMIENTAS */
        .btn-add, .btn-menu { 
            padding: 10px 20px; border-radius: 25px; font-weight: bold; text-decoration: none; display: inline-block; text-align: center;
        }
        .btn-add { background: green; color: white; margin-right: 5px; } 
        .btn-add:hover { background: #d35400; }
        .btn-menu { background: #555; color: white; }
        .btn-menu:hover { background: #333; }

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
            <i class="fas fa-history"></i> Historial de Salidas
            <span class="badge-count" id="total-lbl">Cargando...</span>
            <div id="loading" class="loader"></div>
        </h2>
    </div>

    <div class="toolbar">
        <div class="search-box">
            <svg class="search-icon-svg" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
            <input type="text" id="searchInput" class="search-input" placeholder="Buscar material, motivo o usuario...">
        </div>
        
        <div style="display: flex; gap: 5px; flex-wrap: wrap;">
            <a href="salida_material.php" class="btn-add">Registrar Salida</a>
            <a href="consultar_stock.php" class="btn-add">Consultar Stock</a>
            <a href="dashboard.php" class="btn-menu">Menú</a>
        </div>
    </div>

    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Material</th>
                    <th style="text-align:center;">Unidades Retiradas</th>
                    <th>Motivo/Destino</th>
                    <th>Registrado por</th>
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

        fetch(`consultar_salidas.php?ajax=1&q=${encodeURIComponent(terminoBusqueda)}&p=${paginaActual}`)
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

    // Renderizar Filas (MODIFICADO: Muestra Tipo, Marca, Modelo y Descripción)
    function renderizarTabla(datos) {
        tabla.innerHTML = '';
        const colspan = 5; // Total de columnas de la tabla

        if (datos.length === 0) {
            tabla.innerHTML = `<tr><td colspan="${colspan}" style="text-align:center; padding:30px; color:#888;">No se encontraron registros de salidas.</td></tr>`;
            return;
        }

        datos.forEach(row => {
            // Formateo de fecha y hora
            const fechaHora = row.fecha_movimiento ? row.fecha_movimiento.split(' ') : ['', ''];
            const fechaVis = fechaHora[0];
            const horaVis = fechaHora[1];
            
            // Construir el HTML del material para mostrarlo como una lista
            const materialHtml = `
                <strong>${row.tipo}</strong>
                <br>Marca: ${row.marca || 'N/A'} | Modelo: ${row.modelo || 'N/A'}
                <br><span style="font-size: 0.85em; color: #555;">Desc.: ${row.descripcion || 'Sin especificar'}</span>
            `;

            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td data-label="Fecha" style="font-size: 0.9em; font-weight: bold;">
                    ${fechaVis} <br> <span style="font-weight: normal; color: #555;">${horaVis}</span>
                </td>

                <td data-label="Material">
                    ${materialHtml}
                </td>

                <td data-label="Cantidad" style="text-align:center; font-weight:bold; color:#e74c3c;">
                    ${row.cantidad}
                </td>
                
                <td data-label="Motivo/Destino" style="font-style:italic;">
                    ${row.descripcion_salida || 'Sin motivo registrado'}
                </td>
                
                <td data-label="Usuario" style="text-transform:uppercase; font-size: 0.8em;">
                    ${row.usuario}
                </td>
            `;
            tabla.appendChild(tr);
        });
    }

    // Renderizar Botones de Paginación (Copiado de consultar_stock.php)
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
</script>

</body>
</html>
<?php $conn->close(); ?>