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

    $where_clause = "";
    if (!empty($search_term)) {
        $safe_term = $conn->real_escape_string($search_term);
        $where_clause = " WHERE 
            num_inventario LIKE '%$safe_term%' OR 
            tipo LIKE '%$safe_term%' OR 
            marca LIKE '%$safe_term%' OR 
            modelo LIKE '%$safe_term%' OR 
            num_serie LIKE '%$safe_term%' OR 
            descripcion LIKE '%$safe_term%' OR 
            personal_asignado LIKE '%$safe_term%' OR 
            ubicacion LIKE '%$safe_term%'";
    }

    $count_sql = "SELECT COUNT(id) AS total FROM inventario_soporte" . $where_clause;
    $total_registros = $conn->query($count_sql)->fetch_assoc()['total'];
    $total_paginas = ceil($total_registros / $registros_por_pagina);

    $columnas = "id, num_inventario, tipo, marca, modelo, num_serie, descripcion, personal_asignado, ubicacion";
    
    if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
        $sql = "SELECT $columnas FROM inventario_soporte $where_clause ORDER BY num_inventario DESC";
    } else {
        $sql = "SELECT $columnas FROM inventario_soporte $where_clause ORDER BY num_inventario DESC LIMIT $registros_por_pagina OFFSET $offset";
    }

    $result = $conn->query($sql);
    $datos = [];
    while($row = $result->fetch_assoc()) { $datos[] = $row; }

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
    <title>Inventario Equipos Soporte</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="js/tailwindcss.js"></script>
    <link rel="stylesheet" href="css/all.min.css">
    <script src="js/sweetalert2.all.min.js"></script>
    <script src="js/jspdf.umd.min.js"></script>


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
        body { background-color: #d6d1ca; font-family: 'Segoe UI', sans-serif; }
        .table-row-hover:hover { background-color: #fff8e1; }
        .editable-input { width: 100%; padding: 4px; border: 1px solid #721538; border-radius: 4px; font-size: 0.875rem; }
        
        @media (max-width: 768px) {
            thead { display: none; }
            tr { display: block; background: white; margin-bottom: 1rem; border-radius: 0.75rem; border-left: 6px solid #721538; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); }
            td { display: flex; justify-content: space-between; align-items: center; padding: 12px 16px; border-bottom: 1px solid #f3f4f6; }
            td::before { content: attr(data-label); font-weight: 700; color: #721538; font-size: 0.75rem; text-transform: uppercase; }
        }
    </style>
</head>
<body class="bg-background min-h-screen p-4 sm:p-8">

<div class="max-w-7xl mx-auto">
    <div class="flex flex-col sm:flex-row justify-between items-center mb-6">
        <h2 class="text-3xl font-bold text-primary-dark flex items-center gap-2 mb-4 sm:mb-0">
            <i class="fas fa-laptop"></i> Inventario de Equipos
            <span id="total-lbl" class="text-xs bg-gray-200 text-gray-600 px-3 py-1 rounded-full italic">...</span>
        </h2>
        <div id="loading" style="display:none;" class="text-primary-dark font-bold animate-pulse">
            <i class="fas fa-spinner fa-spin"></i>
        </div>
    </div>

    <div class="bg-white p-4 rounded-xl shadow-md mb-6 flex flex-col lg:flex-row gap-4 items-center justify-between">
        <div class="relative w-full lg:max-w-md">
            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400">
                <i class="fas fa-search"></i>
            </span>
            <input type="text" id="searchInput" placeholder="Buscar por serie, marca, modelo..." class="w-full pl-11 p-3 border border-gray-300 rounded-full focus:ring-2 focus:ring-primary-dark outline-none transition shadow-sm">
        </div>

        <div class="flex flex-wrap gap-2 w-full lg:w-auto justify-end">
            <button onclick="exportarPDF()" class="bg-red-700 hover:bg-red-800 text-white font-bold py-2 px-6 rounded-xl shadow transition flex items-center justify-center flex-1 sm:flex-none">
                <i class="fas fa-file-pdf mr-2"></i> Exportar PDF
            </button>
            <a href="registrar_inventario.php" class="bg-primary-dark hover:bg-primary-light text-white font-bold py-2 px-6 rounded-full shadow transition flex items-center justify-center flex-1 sm:flex-none">
                <i class="fas fa-plus mr-2"></i> Nuevo
            </a>
            <a href="dashboard.php" class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-6 rounded-full shadow transition flex items-center justify-center flex-1 sm:flex-none">
                Menú
            </a>
        </div>
    </div>

    <div class="overflow-hidden shadow-lg rounded-xl border border-gray-100 bg-white">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-primary-dark text-white">
                <tr>
                    <th class="px-4 py-4 text-left text-xs font-bold uppercase tracking-wider">Inventario</th>
                    <th class="px-4 py-4 text-left text-xs font-bold uppercase tracking-wider">Tipo/Marca</th>
                    <th class="px-4 py-4 text-left text-xs font-bold uppercase tracking-wider">Modelo/Serie</th>
                    <th class="px-4 py-4 text-left text-xs font-bold uppercase tracking-wider">Asignado</th>
                    <th class="px-4 py-4 text-left text-xs font-bold uppercase tracking-wider">Ubicación</th>
                    <th class="px-4 py-4 text-left text-xs font-bold uppercase tracking-wider">Descripción</th>
                    <?php if($esAdmin): ?>
                        <th class="px-4 py-4 text-center text-xs font-bold uppercase tracking-wider">Acciones</th>
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
    let timeoutBusqueda = null;
    const esAdmin = <?= $esAdmin ? 'true' : 'false' ?>;

    document.addEventListener('DOMContentLoaded', () => {
        cargarDatos();
        document.getElementById('searchInput').addEventListener('input', (e) => {
            clearTimeout(timeoutBusqueda);
            terminoBusqueda = e.target.value;
            paginaActual = 1;
            timeoutBusqueda = setTimeout(() => { cargarDatos(); }, 300);
        });
    });

    function cargarDatos() {
        document.getElementById('loading').style.display = 'block';
        fetch(`consultar_inventario.php?ajax=1&q=${encodeURIComponent(terminoBusqueda)}&p=${paginaActual}`)
            .then(res => res.json())
            .then(res => {
                renderizarTabla(res.data);
                renderizarPaginacion(res.meta);
                document.getElementById('total-lbl').innerText = res.meta.total_registros;
            })
            .finally(() => document.getElementById('loading').style.display = 'none');
    }

    function renderizarTabla(datos) {
        const tabla = document.getElementById('tabla-resultados');
        tabla.innerHTML = datos.length ? '' : '<tr><td colspan="7" class="p-10 text-center text-gray-500 italic">No se encontraron equipos.</td></tr>';

        datos.forEach(row => {
            const tr = document.createElement('tr');
            tr.className = 'table-row-hover transition-colors duration-150';
            tr.id = `fila_${row.id}`;
            tr.innerHTML = `
                <td data-label="Inventario" class="px-4 py-4 font-bold editable-cell" data-campo="num_inventario">
                    <span class="view-val">${row.num_inventario}</span>
                    <input type="text" class="editable-input hidden" value="${row.num_inventario}">
                </td>
                <td data-label="Tipo/Marca" class="px-4 py-4">
                    <div class="editable-cell" data-campo="tipo"><span class="view-val text-xs text-gray-500 uppercase">${row.tipo}</span><input type="text" class="editable-input hidden" value="${row.tipo}"></div>
                    <div class="editable-cell font-medium" data-campo="marca"><span class="view-val">${row.marca}</span><input type="text" class="editable-input hidden" value="${row.marca}"></div>
                </td>
                <td data-label="Modelo/Serie" class="px-4 py-4 font-mono">
                    <div class="editable-cell" data-campo="modelo"><span class="view-val text-primary-dark">${row.modelo}</span><input type="text" class="editable-input hidden" value="${row.modelo}"></div>
                    <div class="editable-cell" data-campo="num_serie"><span class="view-val text-xs text-gray-400">${row.num_serie}</span><input type="text" class="editable-input hidden" value="${row.num_serie}"></div>
                </td>
                <td data-label="Asignado" class="px-4 py-4 editable-cell" data-campo="personal_asignado">
                    <span class="view-val">${row.personal_asignado || 'STOCK'}</span>
                    <input type="text" class="editable-input hidden" value="${row.personal_asignado || ''}">
                </td>
                <td data-label="Ubicación" class="px-4 py-4 editable-cell" data-campo="ubicacion">
                    <span class="view-val text-xs uppercase">${row.ubicacion}</span>
                    <input type="text" class="editable-input hidden" value="${row.ubicacion}">
                </td>
                <td data-label="Descripción" class="px-4 py-4 italic text-gray-500 editable-cell" data-campo="descripcion">
                    <span class="view-val text-xs">${row.descripcion || '-'}</span>
                    <input type="text" class="editable-input hidden" value="${row.descripcion || ''}">
                </td>
                ${esAdmin ? `
                <td data-label="Acciones" class="px-4 py-4 text-center whitespace-nowrap space-x-1">
                    <button class="btn-edit bg-blue-500 text-white p-2 rounded shadow hover:bg-blue-600 transition" onclick="activarEdicion(${row.id})"><i class="fas fa-pencil-alt"></i></button>
                    <button class="btn-save bg-green-500 text-white p-2 rounded shadow hover:bg-green-600 hidden transition" onclick="guardarFila(${row.id})"><i class="fas fa-save"></i></button>
                    <button class="btn-cancel bg-gray-400 text-white p-2 rounded shadow hover:bg-gray-500 hidden transition" onclick="cargarDatos()"><i class="fas fa-times"></i></button>
                </td>` : ''}
            `;
            tabla.appendChild(tr);
        });
    }

    function activarEdicion(id) {
        const fila = document.getElementById('fila_' + id);
        fila.querySelector('.btn-edit').classList.add('hidden');
        fila.querySelector('.btn-save').classList.remove('hidden');
        fila.querySelector('.btn-cancel').classList.remove('hidden');
        
        fila.querySelectorAll('.editable-cell').forEach(cell => {
            cell.querySelector('.view-val').classList.add('hidden');
            cell.querySelector('.editable-input').classList.remove('hidden');
        });
    }

    function guardarFila(id) {
        const fila = document.getElementById('fila_' + id);
        const datos = new FormData();
        datos.append('id', id);

        fila.querySelectorAll('.editable-cell').forEach(cell => {
            const campo = cell.getAttribute('data-campo');
            const input = cell.querySelector('.editable-input');
            if(campo && input) { datos.append(campo, input.value); }
        });

        Swal.fire({ title: 'Actualizando...', didOpen: () => Swal.showLoading() });
        
        fetch('actualizar_inventario.php', { method: 'POST', body: datos })
            .then(r => r.json())
            .then(data => {
                if(data.success) {
                    Swal.fire({ icon: 'success', title: '¡Actualizado!', timer: 1000, showConfirmButton: false });
                    cargarDatos();
                } else {
                    Swal.fire('Error', data.message, 'error');
                }
            })
            .catch(() => Swal.fire('Error', 'Fallo de conexión', 'error'));
    }

    function renderizarPaginacion(meta) {
        const div = document.getElementById('paginacion');
        div.innerHTML = '';
        if (meta.total_paginas <= 1) return;
        for (let i = 1; i <= meta.total_paginas; i++) {
            const btn = document.createElement('button');
            btn.innerText = i;
            btn.className = `w-10 h-10 rounded-full font-bold border transition ${i === meta.pagina_actual ? 'bg-primary-dark text-white' : 'bg-white text-gray-600 hover:bg-primary-light hover:text-white'}`;
            btn.onclick = () => { paginaActual = i; cargarDatos(); };
            div.appendChild(btn);
        }
    }

    function exportarPDF() {
        Swal.fire({ title: 'Generando PDF...', text: 'Obteniendo toda la información...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
        fetch(`consultar_inventario.php?ajax=1&export=pdf&q=${encodeURIComponent(terminoBusqueda)}`)
            .then(res => res.json())
            .then(res => {
                const { jsPDF } = window.jspdf;
                const doc = new jsPDF('landscape');
                doc.setFontSize(16);
                doc.setTextColor(114, 21, 56);
                doc.text("Reporte Completo de Inventario", 14, 15);
                
                const columnas = ["Inventario", "Tipo", "Marca", "Modelo", "Serie", "Personal", "Ubicación", "Descripción"];
                const filas = res.data.map(item => [item.num_inventario, item.tipo, item.marca, item.modelo, item.num_serie, item.personal_asignado || 'STOCK', item.ubicacion, item.descripcion || '-']);

                doc.autoTable({
                    head: [columnas],
                    body: filas,
                    startY: 25,
                    theme: 'grid',
                    headStyles: { fillColor: [114, 21, 56] },
                    styles: { fontSize: 8 }
                });

                doc.save(`Reporte_Inventario_${new Date().toLocaleDateString().replace(/\//g,'-')}.pdf`);
                Swal.close();
            })
            .catch(() => Swal.fire('Error', 'No se pudo generar el archivo', 'error'));
    }
</script>
</body>
</html>
<?php $conn->close(); ?>