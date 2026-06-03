<?php
// consultar_licencias.php
require_once 'session_check.php';
require_once 'config.php';
session_start();

// Ocultar errores técnicos
error_reporting(0);
ini_set('display_errors', 0);

// 1. SEGURIDAD: Verifica si el usuario está logueado y si su rol está permitido
$roles_permitidos = ['admin', 'tecnico', 'redes'];

if (!isset($_SESSION['usuario']) || !in_array($_SESSION['rol'] ?? '', $roles_permitidos)) {
    header("Location: index.php"); // Redirige si no está logueado o su rol no está en la lista
    exit();
}

$conn = get_db_connection();
$conn->set_charset("utf8mb4");

// --- PRE-CARGA DE DIRECCIONES (CATÁLOGO) ---
$listaDirecciones = [];
$resDir = $conn->query("SELECT id_direcciones, nombres_direcciones FROM Direcciones ORDER BY nombres_direcciones ASC");
if ($resDir) {
    while($r = $resDir->fetch_assoc()) { $listaDirecciones[] = $r; }
}

// ==========================================
// BACKEND AJAX (Tabla Principal)
// ==========================================
if (isset($_GET['ajax'])) {
    ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    
    try {
        $search = $_GET['q'] ?? '';
        $page = (int)($_GET['p'] ?? 1);
        $limit = (int)($_GET['limit'] ?? 10);
        $offset = ($page - 1) * $limit;
        
        $sortCol = $_GET['sort'] ?? 'id';
        $sortOrd = ($_GET['order'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';
        
        $sqlSort = "t1.id"; 
        if ($sortCol === 'Conectados') $sqlSort = "CAST(t1.Conectados AS UNSIGNED)";
        if ($sortCol === 'Area') $sqlSort = "t1.Area";
        if ($sortCol === 'Correo') $sqlSort = "t1.Correo";
        if ($sortCol === 'Dirección') $sqlSort = "COALESCE(t2.nombres_direcciones, t1.`Dirección`)";

        $where = "WHERE 1=1";
        if (!empty($search)) {
            $s = $conn->real_escape_string($search);
            $where .= " AND (t1.`Dirección` LIKE '%$s%' OR t2.nombres_direcciones LIKE '%$s%' OR t3.nombres LIKE '%$s%' OR t1.Area LIKE '%$s%' OR t1.Correo LIKE '%$s%')";
        }

        $sqlCount = "SELECT COUNT(*) as total FROM cuentas_office t1 
                     LEFT JOIN Direcciones t2 ON t1.id_direccion = t2.id_direcciones 
                     LEFT JOIN Secretarias t3 ON t2.id_secretaria = t3.id_secretaria $where";
        $totalRes = $conn->query($sqlCount);
        $total_registros = $totalRes ? $totalRes->fetch_assoc()['total'] : 0;
        
        $sumRes = $conn->query("SELECT SUM(CAST(Conectados AS UNSIGNED)) as t FROM cuentas_office");
        $total_conexiones = $sumRes ? $sumRes->fetch_assoc()['t'] : 0;

        $sql = "SELECT t1.id, t1.`Dirección` as direccion_vieja, t1.id_direccion, t1.Area, t1.Correo, t1.Password, t1.Conectados,
                    t2.nombres_direcciones as direccion_nueva, t3.nombres as Secretaria
                FROM cuentas_office t1 
                LEFT JOIN Direcciones t2 ON t1.id_direccion = t2.id_direcciones 
                LEFT JOIN Secretarias t3 ON t2.id_secretaria = t3.id_secretaria
                $where ORDER BY $sqlSort $sortOrd LIMIT $offset, $limit";
                
        $res = $conn->query($sql);
        $data = [];
        while($row = $res->fetch_assoc()) {
            if (!empty($row['direccion_nueva'])) {
                $row['Dirección'] = $row['direccion_nueva'];
                $row['id_dir_real'] = $row['id_direccion'];
            } else {
                $row['Dirección'] = $row['direccion_vieja'];
                $row['id_dir_real'] = 0;
            }
            $data[] = $row;
        }

        echo json_encode([
            'data' => $data,
            'meta' => [
                'total_registros' => $total_registros,
                'total_conexiones' => $total_conexiones,
                'pages' => ($limit > 0) ? ceil($total_registros / $limit) : 1,
                'page' => $page
            ]
        ]);
        
    } catch (Exception $e) { echo json_encode(['error' => true, 'message' => $e->getMessage()]); }
    exit;
}

// ==========================================
// BACKEND AJAX (Equipos en Modal de Estadísticas)
// ==========================================
if (isset($_GET['ajax_equipos_modal'])) {
    ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    try {
        // ACTUALIZACIÓN: Se agregó la tabla Direcciones para obtener la Ubicación y el Área
        $sql = "SELECT t1.Correo, 
                       COALESCE(t2.nombres_direcciones, t1.`Dirección`) as DireccionReal,
                       t1.Area,
                       GROUP_CONCAT(e.numInventario SEPARATOR ',') as inventarios
                FROM cuentas_office t1 
                LEFT JOIN Direcciones t2 ON t1.id_direccion = t2.id_direcciones
                JOIN equiposbd e ON t1.id = e.id_cuenta_office
                GROUP BY t1.id, t1.Correo, DireccionReal, t1.Area
                ORDER BY t1.Correo ASC";
                
        $res = $conn->query($sql);
        $data = [];
        if($res) {
            while($row = $res->fetch_assoc()) {
                $data[] = $row;
            }
        }
        echo json_encode(['data' => $data]);
    } catch (Exception $e) { 
        echo json_encode(['error' => true, 'message' => $e->getMessage()]); 
    }
    exit;
}

include 'header.php';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Licencias Office 365</title>
    <script src="js/tailwindcss.js"></script>
    <script src="js/sweetalert2.all.min.js"></script>
    <script src="js/jspdf.umd.min.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'brand': '#721538',       
                        'brand-light': '#942f54', 
                    }
                }
            }
        }
    </script>
    <style>
        .editable-input { width: 100%; padding: 6px 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.9em; }
        .editable-input:focus { outline: 2px solid #721538; border-color: transparent; }
        .modal-overlay { background-color: rgba(0, 0, 0, 0.5); backdrop-filter: blur(4px); }
        .chart-container { position: relative; height: 300px; width: 100%; }

        @media (max-width: 768px) {
            thead { display: none; }
            table, tbody, tr, td { display: block; width: 100%; white-space: normal !important; }
            tr { background: white; border-radius: 10px; margin-bottom: 15px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); border-left: 5px solid #721538; padding: 15px; position: relative; }
            td { padding: 10px 0; border-bottom: 1px solid #f0f0f0; display: flex; justify-content: space-between; align-items: center; gap: 15px; min-height: 40px; }
            td:last-child { border-bottom: none; justify-content: center; padding-top: 15px; }
            td:before { content: attr(data-label); position: static; text-align: left; font-weight: bold; color: #721538; text-transform: uppercase; font-size: 0.75em; width: 35%; min-width: 100px; flex-shrink: 0; }
            td > div { text-align: right; width: 60%; word-wrap: break-word; overflow-wrap: break-word; word-break: break-word; }
            .progress-container { width: 100% !important; display: flex; flex-direction: column; align-items: flex-end; }
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen p-4 md:p-8 font-sans text-gray-800">

<div class="max-w-7xl mx-auto space-y-6">
    <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-6 pb-2">
        <div>
            <h1 class="text-3xl font-bold text-brand flex items-center gap-3">
                <i class="fab fa-microsoft text-brand-light"></i> Licencias Office 365
            </h1>
            <p class="text-gray-500 text-sm mt-1 ml-1">
                Gestión Institucional | Total Licencias: <span id="badge-total" class="font-bold">...</span>
            </p>
        </div>
        
        <div class="flex gap-3">
             <button onclick="abrirModalStats()" class="bg-white border border-brand text-brand hover:bg-brand hover:text-white px-5 py-3 rounded-xl shadow-sm transition flex items-center gap-2 font-bold">
                <i class="fas fa-chart-pie"></i> Ver Estadísticas
            </button>

            <div id="semaforo-box" class="flex items-center gap-3 px-4 py-3 rounded-xl shadow-sm border border-gray-200 bg-white">
                <span id="semaforo-icon" class="text-xl">🟢</span>
                <div class="flex flex-col leading-none">
                    <span class="text-[10px] uppercase text-gray-400 font-bold">Vigencia</span>
                    <span id="semaforo-text" class="text-xs font-bold text-gray-700">...</span>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white p-3 rounded-t-xl shadow-sm border-b border-gray-100 flex flex-col md:flex-row gap-3 justify-between items-center mt-4">
        <div class="relative w-full md:w-96">
            <i class="fas fa-search absolute left-4 top-1/2 transform -translate-y-1/2 text-brand-light opacity-50"></i>
            <input type="text" id="inputBusqueda" placeholder="Buscar dirección, área, correo..." 
                   class="w-full pl-10 pr-4 py-2.5 bg-gray-50 border border-gray-200 rounded-lg focus:bg-white focus:ring-2 focus:ring-brand focus:border-transparent transition-all placeholder-gray-400 text-sm">
        </div>
        
        <div class="flex gap-2 w-full md:w-auto">
            <button onclick="exportarPDF()" class="bg-red-600 hover:bg-red-700 text-white font-medium py-2 px-4 rounded-lg shadow-md transition flex items-center justify-center gap-2 text-sm">
                <i class="fas fa-file-pdf"></i> PDF
            </button>
            <a href="registro_licencia.php" class="bg-brand hover:bg-brand-light text-white font-medium py-2 px-5 rounded-lg shadow-md transition flex items-center justify-center gap-2 text-sm">
                <i class="fas fa-plus"></i> <span class="hidden sm:inline">Nueva</span>
            </a>
            <a href="dashboard.php" class="bg-white border border-gray-300 text-gray-600 hover:bg-gray-50 font-medium py-2 px-4 rounded-lg transition flex items-center justify-center gap-2 text-sm shadow-sm">
                <i class="fas fa-bars"></i>
            </a>
        </div>
    </div>

    <div class="bg-white rounded-b-xl shadow-lg overflow-hidden border border-gray-200">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-brand text-white text-xs uppercase tracking-wider">
                        <th class="p-4 font-semibold border-r border-brand-light/30">Ubicación / Área</th>
                        <th class="p-4 font-semibold border-r border-brand-light/30">Cuenta de Correo</th>
                        <th onclick="cambiarOrden('Conectados')" class="p-4 font-semibold text-center w-56 border-r border-brand-light/30 cursor-pointer hover:bg-brand-light transition select-none group">
                            Activaciones <i class="fas fa-sort ml-2 text-white/50"></i>
                        </th>
                        <th class="p-4 font-semibold w-48 border-r border-brand-light/30">Contraseña</th>
                        <th class="p-4 font-semibold text-center w-32">Acciones</th>
                    </tr>
                </thead>
                <tbody id="tabla-body" class="divide-y divide-gray-100 text-sm text-gray-700"></tbody>
            </table>
        </div>
        <div id="loading" class="hidden p-8 text-center text-brand"><i class="fas fa-circle-notch fa-spin fa-2x"></i></div>
        <div id="error-msg" class="hidden p-8 text-center text-red-500 font-bold"></div>
        <div id="paginacion" class="p-4 flex flex-wrap justify-center gap-1 bg-gray-50 border-t border-gray-200"></div>
    </div>
</div>

<div id="modalStats" class="fixed inset-0 modal-overlay hidden z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-5xl h-[90vh] overflow-y-auto flex flex-col">
        <div class="flex justify-between items-center p-6 border-b border-gray-100 bg-gray-50 rounded-t-2xl sticky top-0 z-10">
            <div>
                <h3 class="text-2xl font-bold text-brand">📊 Panel de Inteligencia</h3>
                <p class="text-sm text-gray-500">Análisis y vinculación de equipos de Office 365</p>
            </div>
            <button onclick="cerrarModalStats()" class="text-gray-400 hover:text-red-500 transition text-2xl"><i class="fas fa-times"></i></button>
        </div>

        <div class="p-6 space-y-8 flex-grow">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="p-5 bg-blue-50 rounded-xl border border-blue-100">
                    <div class="text-blue-500 mb-2"><i class="fas fa-server text-2xl"></i></div>
                    <div class="text-2xl font-bold text-gray-800" id="kpi-capacidad">...</div>
                    <div class="text-xs text-gray-500 uppercase tracking-wide font-bold">Instalaciones Posibles</div>
                </div>
                <div class="p-5 bg-green-50 rounded-xl border border-green-100">
                    <div class="text-green-500 mb-2"><i class="fas fa-check-circle text-2xl"></i></div>
                    <div class="text-2xl font-bold text-gray-800" id="kpi-usadas">...</div>
                    <div class="text-xs text-gray-500 uppercase tracking-wide font-bold">Instalaciones Usadas</div>
                </div>
                <div class="p-5 bg-red-50 rounded-xl border border-red-100">
                    <div class="text-red-500 mb-2"><i class="fas fa-exclamation-triangle text-2xl"></i></div>
                    <div class="text-2xl font-bold text-gray-800" id="kpi-desperdicio">...</div>
                    <div class="text-xs text-gray-500 uppercase tracking-wide font-bold">Cuentas Sin Uso</div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm">
                    <h4 class="font-bold text-gray-700 mb-4 text-center">Aprovechamiento Global</h4>
                    <div class="chart-container flex justify-center">
                        <canvas id="chartDona"></canvas>
                    </div>
                </div>
                <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm">
                    <h4 class="font-bold text-gray-700 mb-4 text-center">Top Consumo por Secretaría</h4>
                    <div class="chart-container">
                        <canvas id="chartBarras"></canvas>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="bg-gray-50 p-4 border-b border-gray-200 flex flex-col sm:flex-row justify-between items-center gap-3">
                    <h4 class="font-bold text-gray-700 flex items-center gap-2">
                        <i class="fas fa-network-wired text-brand"></i> Equipos Vinculados por Licencia
                    </h4>
                    
                    <div class="relative w-full sm:w-80">
                        <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 opacity-70 text-sm"></i>
                        <input type="text" id="buscadorModal" placeholder="Buscar ubicación, área, correo o inventario..." 
                               class="w-full pl-9 pr-3 py-2 bg-white border border-gray-300 rounded-lg focus:bg-white focus:ring-2 focus:ring-brand focus:border-transparent transition-all placeholder-gray-400 text-sm shadow-sm">
                    </div>
                </div>
                
                <div class="overflow-x-auto max-h-72 overflow-y-auto">
                    <table class="w-full text-left text-sm border-collapse">
                        <thead class="sticky top-0 bg-white shadow-sm z-10">
                            <tr class="text-gray-500 uppercase tracking-wider text-xs bg-gray-50/90 backdrop-blur-sm">
                                <th class="p-4 border-b font-semibold w-1/3">Ubicación / Área</th>
                                <th class="p-4 border-b font-semibold w-1/3">Cuenta de Correo</th>
                                <th class="p-4 border-b font-semibold w-1/3">Números de Inventario</th>
                            </tr>
                        </thead>
                        <tbody id="tabla-modal-equipos" class="divide-y divide-gray-100">
                            <tr><td colspan="3" class="p-6 text-center text-gray-400"><i class="fas fa-spinner fa-spin mr-2"></i> Cargando vinculaciones...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
    let paginaActual = 1;
    let busqueda = '';
    let ordenColumna = 'id';
    let ordenDireccion = 'ASC';
    let datosEquiposModal = []; 
    
    const listaDirecciones = <?php echo json_encode($listaDirecciones); ?>;

    document.addEventListener('DOMContentLoaded', () => {
        actualizarSemaforo(); 
        cargarDatos();
        
        document.getElementById('inputBusqueda').addEventListener('input', (e) => {
            busqueda = e.target.value; paginaActual = 1;
            clearTimeout(window.searchTimeout); window.searchTimeout = setTimeout(cargarDatos, 300);
        });

        // ACTUALIZACIÓN: Buscador interno del Modal ahora incluye Área y Dirección
        document.getElementById('buscadorModal').addEventListener('input', (e) => {
            const termino = e.target.value.toLowerCase();
            const filtrados = datosEquiposModal.filter(row => {
                const correo = (row.Correo || '').toLowerCase();
                const inventarios = (row.inventarios || '').toLowerCase();
                const direccion = (row.DireccionReal || '').toLowerCase();
                const area = (row.Area || '').toLowerCase();
                
                return correo.includes(termino) || 
                       inventarios.includes(termino) || 
                       direccion.includes(termino) || 
                       area.includes(termino);
            });
            dibujarTablaModal(filtrados);
        });
    });

    let myChartDona = null;
    let myChartBarras = null;

    function abrirModalStats() {
        document.getElementById('modalStats').classList.remove('hidden');
        document.getElementById('buscadorModal').value = ''; 
        cargarEstadisticas();
        cargarEquiposVinculados();
    }

    function cerrarModalStats() {
        document.getElementById('modalStats').classList.add('hidden');
    }

    function cargarEquiposVinculados() {
        fetch('consultar_licencias.php?ajax_equipos_modal=1')
        .then(r => r.json())
        .then(res => {
            if(res.data) {
                datosEquiposModal = res.data; 
                dibujarTablaModal(datosEquiposModal); 
            } else {
                dibujarTablaModal([]);
            }
        })
        .catch(err => console.error("Error cargando vinculaciones:", err));
    }

    // ACTUALIZACIÓN: Renderizado de la tabla con la nueva columna de Ubicación/Área
    function dibujarTablaModal(datosFiltrados) {
        const tbody = document.getElementById('tabla-modal-equipos');
        tbody.innerHTML = '';
        
        if(datosFiltrados.length === 0) {
            tbody.innerHTML = '<tr><td colspan="3" class="p-6 text-center text-gray-500 italic">No se encontraron resultados para tu búsqueda.</td></tr>';
            return;
        }

        datosFiltrados.forEach(row => {
            let enlaces = row.inventarios.split(',').map(inv => {
                let numInv = inv.trim();
                return `<a href="consultar_equipos.php?q=${numInv}" target="_blank" 
                           class="inline-block bg-blue-50 text-blue-700 hover:bg-blue-600 hover:text-white border border-blue-200 hover:border-blue-600 px-3 py-1.5 rounded-md text-xs font-mono transition-all shadow-sm mr-2 mb-2">
                           <i class="fas fa-search mr-1"></i> ${numInv}
                        </a>`;
            }).join('');

            tbody.innerHTML += `
                <tr class="hover:bg-gray-50 transition-colors">
                    <td class="p-4 align-top">
                        <div class="font-bold text-gray-800 text-sm mb-1">${row.DireccionReal || 'Sin Dirección'}</div>
                        <div class="inline-block bg-gray-100 text-gray-500 text-[10px] uppercase px-2 py-0.5 rounded border border-gray-200 tracking-wide">${row.Area || ''}</div>
                    </td>
                    <td class="p-4 font-medium text-gray-700 align-top">
                        <div class="flex items-center gap-2"><i class="far fa-envelope text-brand-light opacity-60"></i> ${row.Correo}</div>
                    </td>
                    <td class="p-4 align-top">
                        <div class="flex flex-wrap">${enlaces}</div>
                    </td>
                </tr>
            `;
        });
    }

    function cargarEstadisticas() {
        fetch('api_stats_licencias.php')
        .then(r => r.json())
        .then(data => {
            document.getElementById('kpi-capacidad').innerText = data.global.total_capacidad;
            document.getElementById('kpi-usadas').innerText = data.global.usadas;
            document.getElementById('kpi-desperdicio').innerText = data.global.cuentas_fantasma;
            renderCharts(data);
        })
        .catch(e => console.error("Error cargando stats:", e));
    }

    function renderCharts(data) {
        const ctxDona = document.getElementById('chartDona').getContext('2d');
        if(myChartDona) myChartDona.destroy();
        myChartDona = new Chart(ctxDona, {
            type: 'doughnut',
            data: {
                labels: ['Usadas', 'Libres'],
                datasets: [{
                    data: [data.global.usadas, data.global.libres],
                    backgroundColor: ['#721538', '#e5e7eb'], 
                    hoverOffset: 4
                }]
            }
        });

        const labels = Object.keys(data.secretarias);
        const valores = labels.map(k => data.secretarias[k].usadas);
        const ctxBarras = document.getElementById('chartBarras').getContext('2d');
        if(myChartBarras) myChartBarras.destroy();
        myChartBarras = new Chart(ctxBarras, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Dispositivos Conectados',
                    data: valores,
                    backgroundColor: '#942f54',
                    borderRadius: 5
                }]
            },
            options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true } } }
        });
    }

    function cambiarOrden(columna) {
        if (ordenColumna === columna) ordenDireccion = (ordenDireccion === 'ASC') ? 'DESC' : 'ASC';
        else { ordenColumna = columna; ordenDireccion = 'ASC'; }
        cargarDatos(); 
    }

    function cargarDatos() {
        const loader = document.getElementById('loading');
        const tbody = document.getElementById('tabla-body');
        const errorMsg = document.getElementById('error-msg');
        
        loader.classList.remove('hidden'); tbody.style.opacity = '0.5'; errorMsg.classList.add('hidden');
        
        fetch(`consultar_licencias.php?ajax=1&q=${encodeURIComponent(busqueda)}&p=${paginaActual}&sort=${ordenColumna}&order=${ordenDireccion}`)
        .then(r => r.json())
        .then(res => {
            if(res.error) throw new Error(res.message);
            document.getElementById('badge-total').innerText = res.meta.total_registros;
            renderTabla(res.data);
            renderPaginacion(res.meta);
        })
        .catch(err => { tbody.innerHTML = ''; errorMsg.innerText = err.message; errorMsg.classList.remove('hidden'); })
        .finally(() => { loader.classList.add('hidden'); tbody.style.opacity = '1'; });
    }

    function renderTabla(data) {
        const tbody = document.getElementById('tabla-body'); tbody.innerHTML = '';
        if(data.length === 0) { tbody.innerHTML = '<tr><td colspan="5" class="p-8 text-center text-gray-500 italic">Sin resultados</td></tr>'; return; }

        data.forEach(row => {
            const tr = document.createElement('tr');
            tr.className = "hover:bg-gray-50 group border-b border-gray-100 transition";
            const id = row.id; tr.id = `fila_${id}`;
            const conectadosStr = String(row.Conectados || "0");
            const usados = parseInt(conectadosStr.split('/')[0]) || 0;
            const dirReal = row.id_dir_real || 0; 
            let color = usados >= 10 ? 'bg-red-500' : (usados >= 8 ? 'bg-yellow-500' : 'bg-brand');
            let pct = Math.min(100, (usados / 10) * 100);

            tr.innerHTML = `
                <td data-label="Ubicación" class="p-4 align-top editable-cell">
                    <div class="view-val w-full">
                        <div class="font-bold text-gray-800 text-sm mb-1">${row.Dirección}</div>
                        <div class="inline-block bg-gray-100 text-gray-500 text-[10px] uppercase px-2 py-0.5 rounded border border-gray-200 tracking-wide">${row.Area}</div>
                    </div>
                    <div class="hidden edit-inputs space-y-2 w-full">
                        <select class="editable-input" id="edit_dir_${id}"></select>
                        <input type="text" class="editable-input" value="${row.Area}" id="edit_area_${id}">
                    </div>
                </td>
                
                <td data-label="Correo" class="p-4 align-top editable-cell">
                    <div class="view-val text-blue-600 font-medium text-sm w-full"><i class="far fa-envelope text-gray-300 mr-1"></i>${row.Correo}</div>
                    <div class="hidden edit-inputs w-full"><input type="text" class="editable-input" value="${row.Correo}" id="edit_correo_${id}"></div>
                </td>
                
                <td data-label="Activaciones" class="p-4 align-middle text-center editable-cell">
                    <div class="view-val progress-container mx-auto">
                        <div class="flex justify-between text-xs mb-1 w-full"><span class="text-gray-400">Uso</span><span class="font-bold">${row.Conectados}</span></div>
                        <div class="w-full bg-gray-200 rounded-full h-1.5"><div class="${color} h-1.5 rounded-full" style="width: ${pct}%"></div></div>
                    </div>
                    <div class="hidden edit-inputs"><input type="text" class="editable-input w-20 text-center mx-auto" value="${row.Conectados}" id="edit_conectados_${id}"></div>
                </td>
                
                <td data-label="Contraseña" class="p-4 align-middle">
                    <div class="view-val flex items-center gap-2 bg-gray-50 px-3 py-2 rounded-lg border border-gray-200 w-full md:w-32">
                        <input type="password" value="${row.Password}" readonly class="bg-transparent border-none w-full text-xs outline-none text-gray-600 font-mono tracking-widest" id="pass_input_${id}">
                        <button onclick="togglePass(${id})" class="text-gray-400 hover:text-brand"><i class="fas fa-eye"></i></button>
                    </div>
                    <div class="hidden edit-inputs w-full"><input type="text" class="editable-input font-mono text-xs" value="${row.Password}" id="edit_pass_${id}"></div>
                </td>
                
                <td data-label="Acciones" class="p-4 align-middle text-center">
                    <div class="btn-group-view flex justify-center gap-2">
                        <button onclick="modoEdicion(${id}, ${dirReal})" class="w-8 h-8 rounded border border-gray-300 text-gray-500 hover:text-brand hover:border-brand transition"><i class="fas fa-pencil-alt"></i></button>
                        <button onclick="eliminarFila(${id})" class="w-8 h-8 rounded border border-gray-300 text-gray-500 hover:text-red-500 hover:border-red-500 transition"><i class="fas fa-trash-alt"></i></button>
                    </div>
                    <div class="hidden btn-group-edit flex flex-col gap-2">
                        <button onclick="guardarCambios(${id})" class="bg-green-600 text-white py-1 px-3 rounded text-xs shadow">Guardar</button>
                        <button onclick="cancelarEdicion(${id})" class="bg-white border border-gray-300 text-gray-600 py-1 px-3 rounded text-xs">Cancelar</button>
                    </div>
                </td>
            `;
            tbody.appendChild(tr);
        });
    }

    function modoEdicion(id, currentDirId) {
        const tr = document.getElementById(`fila_${id}`);
        tr.querySelectorAll('.view-val').forEach(el => el.classList.add('hidden'));
        tr.querySelectorAll('.edit-inputs').forEach(el => el.classList.remove('hidden'));
        tr.querySelector('.btn-group-view').classList.add('hidden');
        tr.querySelector('.btn-group-edit').classList.remove('hidden');
        
        const select = document.getElementById(`edit_dir_${id}`);
        select.innerHTML = '<option value="">-- Seleccionar --</option>';
        if(!currentDirId || currentDirId == 0) select.innerHTML = '<option value="">⚠️ Actualizar Dato Viejo...</option>';

        listaDirecciones.forEach(d => {
            let opt = document.createElement('option');
            opt.value = d.id_direcciones; opt.text = d.nombres_direcciones;
            if(d.id_direcciones == currentDirId) opt.selected = true;
            select.appendChild(opt);
        });
        document.getElementById(`edit_pass_${id}`).value = document.getElementById(`pass_input_${id}`).value;
        tr.classList.add('bg-blue-50/50', 'border-brand-light');
    }

    function cancelarEdicion(id) { cargarDatos(); }

    function guardarCambios(id) {
        const fd = new FormData();
        fd.append('id', id);
        fd.append('direccion', document.getElementById(`edit_dir_${id}`).value);
        fd.append('area', document.getElementById(`edit_area_${id}`).value);
        fd.append('correo', document.getElementById(`edit_correo_${id}`).value);
        fd.append('conectados', document.getElementById(`edit_conectados_${id}`).value);
        fd.append('password', document.getElementById(`edit_pass_${id}`).value);

        Swal.fire({ title: 'Guardando...', didOpen: () => Swal.showLoading() });
        fetch('actualizar_licencia.php', { method: 'POST', body: fd }).then(r => r.json()).then(d => {
            if(d.success) { Swal.fire({ icon: 'success', title: '¡Guardado!', timer: 1500, showConfirmButton: false }); cargarDatos(); }
            else Swal.fire('Error', d.message, 'error');
        }).catch(err => Swal.fire('Error', err.message, 'error'));
    }
    
    function eliminarFila(id) {
        Swal.fire({ title: '¿Eliminar?', icon: 'warning', showCancelButton: true, confirmButtonColor: '#721538', confirmButtonText: 'Sí, eliminar' }).then((r) => {
            if(r.isConfirmed) {
                const fd = new FormData(); fd.append('id', id);
                fetch('eliminar_licencia.php', { method: 'POST', body: fd }).then(r => r.json()).then(d => {
                    if(d.success) { cargarDatos(); Swal.fire({ title: 'Eliminado', icon: 'success', timer: 1500, showConfirmButton: false }); }
                    else Swal.fire('Error', d.message, 'error');
                });
            }
        });
    }

    function actualizarSemaforo() {
        const hoy = new Date();
        const dias = Math.ceil((new Date(hoy.getFullYear(), 7, 1) - hoy) / 86400000);
        const icon = document.getElementById('semaforo-icon'); const text = document.getElementById('semaforo-text');
        
        if (dias < 0) { icon.innerHTML="🔴"; text.innerHTML="Vencido"; text.classList.add('text-red-700'); }
        else if (dias <= 60) { icon.innerHTML="⚠️"; text.innerHTML=`Renovar (${dias}d)`; text.classList.add('text-yellow-700'); }
        else { icon.innerHTML="✅"; text.innerHTML="Vigente"; text.classList.add('text-green-700'); }
    }

    function togglePass(id) { const i = document.getElementById(`pass_input_${id}`); i.type = i.type === 'password' ? 'text' : 'password'; }
    
    function renderPaginacion(meta) {
        const divPaginacion = document.getElementById('paginacion');
        divPaginacion.innerHTML = '';
        const totalPag = meta.pages;
        const actual = meta.page;
        
        if (totalPag <= 1) return;

        const crearBtn = (texto, pag, activo) => {
            const btn = document.createElement('button');
            btn.innerHTML = texto;
            btn.className = `w-8 h-8 flex items-center justify-center text-sm font-medium rounded-md transition-all duration-200 focus:outline-none ${activo ? 'bg-brand text-white shadow-md border border-brand transform scale-105' : 'bg-white text-gray-600 border border-gray-200 hover:bg-gray-50 hover:border-gray-300 hover:text-brand'}`;
            btn.onclick = () => { paginaActual = pag; cargarDatos(); };
            divPaginacion.appendChild(btn);
        };

        const delta = 1;
        if (actual > 1) crearBtn('<i class="fas fa-chevron-left text-xs"></i>', actual - 1, false);

        for (let i = 1; i <= totalPag; i++) {
            if (i === 1 || i === totalPag || (i >= actual - delta && i <= actual + delta)) {
                crearBtn(i, i, i === actual);
            } else if (i === actual - delta - 1 || i === actual + delta + 1) {
                const dots = document.createElement('span');
                dots.className = "w-8 h-8 flex items-center justify-center text-gray-400";
                dots.innerHTML = '...';
                divPaginacion.appendChild(dots);
            }
        }

        if (actual < totalPag) crearBtn('<i class="fas fa-chevron-right text-xs"></i>', actual + 1, false);
    }
    
    async function exportarPDF() {
        Swal.fire({ title: 'Generando PDF...', didOpen: () => Swal.showLoading() });
        try {
            const url = `consultar_licencias.php?ajax=1&q=${encodeURIComponent(busqueda)}&limit=10000`;
            const res = await fetch(url);
            const json = await res.json();
            
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF();
            doc.text("Reporte Licencias", 14, 20);
            doc.autoTable({ startY: 25, head: [['Secretaría', 'Ubicación', 'Correo', 'Uso']], body: json.data.map(row => [row.Secretaria || '', row.Dirección + " " + row.Area, row.Correo, row.Conectados]) });
            doc.save("Reporte.pdf");
            Swal.close();
        } catch (e) { Swal.fire('Error PDF', '', 'error'); }
    }
</script>
</body>
</html>