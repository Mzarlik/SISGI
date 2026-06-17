<?php 
require_once 'session_check.php';
require_once 'config.php'; 

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
    
    $resCount = $conn->query($count_sql);
    $total_registros = $resCount ? $resCount->fetch_assoc()['total'] : 0;
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
    }
</style>

<div class="px-4 sm:px-8 max-w-7xl mx-auto space-y-6">
    <div class="flex flex-col sm:flex-row justify-between items-center mb-6">
        <h2 class="text-3xl font-bold text-primary-dark flex items-center gap-2">
            <i class="fas fa-history"></i> Historial de Salidas
            <span class="text-xs bg-gray-200 text-gray-600 px-3 py-1 rounded-full italic" id="total-lbl">Cargando...</span>
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
            <input type="text" id="searchInput" placeholder="Buscar material, motivo o usuario..." class="w-full pl-11 p-3 border border-gray-300 rounded-full focus:ring-2 focus:ring-primary-dark outline-none transition">
        </div>
           
        <div class="flex gap-2 w-full lg:w-auto justify-end">
            <a href="salida_material.php" class="bg-orange-600 hover:bg-orange-700 text-white font-bold py-2.5 px-6 rounded-full shadow transition flex items-center gap-2 justify-center">
                <i class="fas fa-sign-out-alt"></i> Registrar Salida
            </a>
            <a href="consultar_stock.php" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2.5 px-6 rounded-full shadow transition flex items-center gap-2 justify-center">
                <i class="fas fa-boxes"></i> Consultar Stock
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
                    <th class="px-6 py-4 text-left">Fecha</th>
                    <th class="px-6 py-4 text-left">Material</th>
                    <th class="px-6 py-4 text-center">Unidades Retiradas</th>
                    <th class="px-6 py-4 text-left">Motivo/Destino</th>
                    <th class="px-6 py-4 text-left">Registrado por</th>
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
            tr.className = "hover:bg-[#fdf2f5] transition-colors duration-150 border-b border-gray-100";
            tr.innerHTML = `
                <td data-label="Fecha" class="px-6 py-4 font-mono text-xs font-semibold text-gray-700">
                    ${fechaVis} <br> <span class="font-normal text-gray-500">${horaVis}</span>
                </td>

                <td data-label="Material" class="px-6 py-4">
                    ${materialHtml}
                </td>

                <td data-label="Cantidad" class="px-6 py-4 text-center font-bold text-red-600">
                    ${row.cantidad}
                </td>
                
                <td data-label="Motivo/Destino" class="px-6 py-4 italic text-gray-600">
                    ${row.descripcion_salida || 'Sin motivo registrado'}
                </td>
                
                <td data-label="Usuario" class="px-6 py-4 uppercase text-xs font-semibold text-gray-600">
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
        btn.type = "button";
        btn.innerHTML = texto;
        
        let clases = "w-8 h-8 flex items-center justify-center rounded-md border transition-all text-sm font-medium ";
        if (esActivo) {
            clases += "bg-[#721538] text-white shadow-md border-[#721538] scale-105 cursor-default";
        } else {
            clases += "bg-white text-gray-600 border-gray-200 hover:bg-gray-50 hover:border-gray-300 hover:text-primary-dark cursor-pointer";
        }
        btn.className = clases;
        
        btn.onclick = () => {
            paginaActual = paginaDestino;
            cargarDatos();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        };
        divPaginacion.appendChild(btn);
    }
</script>
</div>
</main>
</body>
</html>
<?php $conn->close(); ?>