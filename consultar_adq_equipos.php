<?php
require_once 'session_check.php';
require_once 'config.php';
session_start();

// 1. SEGURIDAD: Verifica si el usuario está logueado y si tiene rol de 'admin'
if (!isset($_SESSION['usuario']) || ($_SESSION['rol'] ?? '') !== 'admin') {
    header("Location: index.php"); // Redirige si no es admin o no está logueado
    exit();
}

$conn = get_db_connection();
if (!$conn) { die("Conexión fallida."); }

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
    $params = [];
    $types = "";

    if (!empty($search_term)) {
        $like_term = "%" . $search_term . "%";
        $where_clause = " WHERE tipo LIKE ? OR marca LIKE ? OR modelo LIKE ? OR detalles LIKE ? OR capitulos LIKE ?";
        $params = [$like_term, $like_term, $like_term, $like_term, $like_term];
        $types = "sssss";
    }

    // Contar total
    $sql_total = "SELECT COUNT(*) AS total FROM Adq_equipos" . $where_clause;
    $stmt_total = $conn->prepare($sql_total);
    if (!empty($params)) {
        $stmt_total->bind_param($types, ...$params);
    }
    $stmt_total->execute();
    $total_registros = $stmt_total->get_result()->fetch_assoc()["total"];
    $stmt_total->close();

    $total_paginas = ceil($total_registros / $registros_por_pagina);

    // Consulta de datos
    if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
        $sql = "SELECT * FROM Adq_equipos $where_clause ORDER BY id_adquisicion DESC";
        $stmt = $conn->prepare($sql);
        if (!empty($params)) { $stmt->bind_param($types, ...$params); }
    } else {
        $sql = "SELECT * FROM Adq_equipos $where_clause ORDER BY id_adquisicion DESC LIMIT ?, ?";
        $stmt = $conn->prepare($sql);
        $params_pag = $params;
        $params_pag[] = $offset;
        $params_pag[] = $registros_por_pagina;
        $types_pag = $types . "ii";
        $stmt->bind_param($types_pag, ...$params_pag);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $datos = [];
    while($row = $result->fetch_assoc()) { $datos[] = $row; }
    $stmt->close();

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
    <title>Consultar Adquisiciones | SISGI</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="js/tailwindcss.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="css/all.min.css">
    <script src="js/jspdf.umd.min.js"></script>
    <script src="js/jspdf.plugin.autotable.min.js"></script>
    <script src="js/xlsx.full.min.js"></script>
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
        .search-input { padding-left: 50px !important; }
        .search-icon-svg { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); width: 20px; height: 20px; color: #9ca3af; z-index: 10; }

        @media (max-width: 768px) {
            thead { display: none; }
            tr { display: block; background: white; margin-bottom: 1rem; border-radius: 0.75rem; border-left: 6px solid #721538; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); }
            td { display: flex; justify-content: space-between; align-items: center; padding: 12px 16px; border-bottom: 1px solid #f3f4f6; }
            td::before { content: attr(data-label); font-weight: 700; color: #721538; font-size: 0.75rem; text-transform: uppercase; }
        }
                    /* Fuerza a la tabla a respetar estrictamente los anchos asignados */
            table { table-layout: fixed; width: 100%; }

            /* Manejo para que los textos largos no empujen las columnas */
            .celda-detalles {
                max-width: 280px; /* Limita el ancho de la columna de detalles */
                white-space: normal; /* Permite saltos de línea normales */
                word-break: break-word; /* Rompe palabras exageradamente largas si es necesario */
            }
    </style>
</head>
<body class="bg-background min-h-screen p-4 sm:p-8">

    <div class="max-w-7xl mx-auto">
        <div class="flex flex-col sm:flex-row justify-between items-center mb-6">
            <h1 class="text-3xl font-bold text-primary-dark flex items-center gap-2 mb-4 sm:mb-0">
                <i class="fas fa-desktop"></i> Adquisiciones de Equipos de Computo y Suministros informáticos
                <span id="total-lbl" class="text-xs bg-gray-200 text-gray-600 px-3 py-1 rounded-full italic">...</span>
            </h1>
            <div id="loading" style="display:none;" class="text-primary-dark font-bold animate-pulse">
                <i class="fas fa-spinner fa-spin"></i>
            </div>
        </div>

        <div class="bg-white p-4 rounded-xl shadow-md mb-6 flex flex-col lg:flex-row gap-4 items-center justify-between">
            <div class="relative w-full lg:max-w-md">
                <svg class="search-icon-svg" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
                <input type="text" id="searchInput" placeholder="Buscar por tipo, marca, modelo o capítulo..." class="search-input w-full p-3 border border-gray-300 rounded-full focus:ring-2 focus:ring-primary-dark outline-none transition shadow-sm">
            </div>

                    <div class="relative w-full lg:w-auto flex justify-end">
            <button id="btnOpciones" class="bg-primary-dark hover:bg-primary-light text-white font-bold py-3 px-6 rounded-full shadow transition flex items-center gap-2 w-full lg:w-auto justify-center">
                <i class="fas fa-bars"></i> Opciones
            </button>

            <div id="dropdownOpciones" class="hidden absolute top-full mt-2 right-0 w-56 bg-white rounded-xl shadow-xl border border-gray-200 py-2 z-50">
                
                <a href="ad_equipos.php" class="block w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 hover:text-green-600 transition-colors font-medium">
                    <i class="fas fa-plus w-6 text-center text-green-500 mr-2"></i> Nuevo Equipo
                </a>

                <a href="solicitud_equipos.php" class="block w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 hover:text-blue-600 transition-colors font-medium">
                    <i class="fas fa-file-signature w-6 text-center text-blue-500 mr-2"></i> Solicitud
                </a>

                <div class="h-px bg-gray-200 my-1"></div>

                <button type="button" onclick="exportarExcel()" class="block w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 hover:text-green-700 transition-colors font-medium cursor-pointer">
                    <i class="fas fa-file-excel w-6 text-center text-green-600 mr-2"></i> Exportar a Excel
                </button>

                <button type="button" onclick="exportarTodoPDF()" class="block w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 hover:text-red-700 transition-colors font-medium cursor-pointer">
                    <i class="fas fa-file-pdf w-6 text-center text-red-600 mr-2"></i> Exportar a PDF
                </button>

                <div class="h-px bg-gray-200 my-1"></div>

                <a href="dashboard.php" class="block w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 hover:text-gray-900 transition-colors font-medium">
                    <i class="fas fa-home w-6 text-center text-gray-500 mr-2"></i> Menú Principal
                </a>
            </div>

        </div>
    </div>

        <div class="overflow-hidden shadow-lg rounded-xl border border-gray-100 bg-white">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-primary-dark">
                        <tr>
                            <th class="w-[8%] px-4 py-4 text-left text-xs font-bold text-white uppercase tracking-wider">Imagen</th>
                            <th class="w-[10%] px-4 py-4 text-left text-xs font-bold text-white uppercase tracking-wider">Tipo</th>
                            <th class="w-[15%] px-4 py-4 text-left text-xs font-bold text-white uppercase tracking-wider">Marca/Modelo</th>
                            <th class="w-[12%] px-4 py-4 text-left text-xs font-bold text-white uppercase tracking-wider">Almacenamiento</th>
                            <th class="w-[8%] px-4 py-4 text-left text-xs font-bold text-white uppercase tracking-wider">RAM</th>
                            <th class="w-[25%] px-4 py-4 text-left text-xs font-bold text-white uppercase tracking-wider">Detalles</th>
                            <th class="w-[8%] px-4 py-4 text-left text-xs font-bold text-white uppercase tracking-wider">Capítulo</th>
                            <th class="w-[10%] px-4 py-4 text-left text-xs font-bold text-white uppercase tracking-wider">Precio</th>
                            <th class="w-[10%] px-4 py-4 text-center text-xs font-bold text-white uppercase tracking-wider">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="tabla-resultados" class="bg-white divide-y divide-gray-100 text-sm"></tbody>
                </table>
            </div>
        </div>
        <div id="paginacion" class="mt-8 flex justify-center gap-2 flex-wrap"></div>
    </div>

    <div id="modalDetalle" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-60 hidden p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl overflow-hidden animate__animated animate__fadeIn">
            <div class="bg-primary-dark text-white p-4 flex justify-between items-center">
                <h3 class="font-bold text-lg">Especificaciones:</h3>
                <button onclick="cerrarModal()" class="text-white hover:text-gray-300 text-2xl">&times;</button>
            </div>
            <div class="p-6 overflow-y-auto max-h-[80vh]">
                <div id="modalContent" class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    </div>
            </div>
        </div>
    </div>

    <script>
let paginaActual = 1;
let terminoBusqueda = '';
let timeoutBusqueda = null;
let datosActuales = []; // Variable global para el modal y reportes

document.addEventListener('DOMContentLoaded', () => {
    cargarDatos();
    
    // Búsqueda con retraso (debounce) unificada
    document.getElementById('searchInput').addEventListener('input', (e) => {
        clearTimeout(timeoutBusqueda);
        terminoBusqueda = e.target.value;
        paginaActual = 1;
        timeoutBusqueda = setTimeout(() => { cargarDatos(); }, 300);
    });

    // Funcionalidad del botón de Opciones
    const btnOpciones = document.getElementById('btnOpciones');
    const dropdownOpciones = document.getElementById('dropdownOpciones');

    if (btnOpciones && dropdownOpciones) {
        btnOpciones.addEventListener('click', (e) => {
            e.stopPropagation(); // Evita que el window.onclick lo cierre de inmediato
            dropdownOpciones.classList.toggle('hidden');
        });
    }
});

function cargarDatos() {
    document.getElementById('loading').style.display = 'block';
    fetch(`consultar_adq_equipos.php?ajax=1&q=${encodeURIComponent(terminoBusqueda)}&p=${paginaActual}`)
        .then(res => res.json())
        .then(res => {
            datosActuales = res.data; 
            renderizarTabla(res.data);
            renderizarPaginacion(res.meta);
            document.getElementById('total-lbl').innerText = res.meta.total_registros;
        })
        .finally(() => document.getElementById('loading').style.display = 'none');
}

function renderizarTabla(datos) {
    const tabla = document.getElementById('tabla-resultados');
    tabla.innerHTML = datos.length ? '' : '<tr><td colspan="9" class="p-10 text-center text-gray-500 italic">No hay adquisiciones registradas.</td></tr>';
    
    datos.forEach((row, index) => {
        const tr = document.createElement('tr');
        tr.className = 'table-row-hover transition-colors duration-150';
        
        const imagenSrc = row.ruta_imagen ? row.ruta_imagen : 'img/placeholder-equipo.png';

        tr.innerHTML = `
            <td data-label="Imagen" class="px-4 py-2">
                <div class="w-16 h-16 flex items-center justify-center overflow-hidden rounded-md border border-gray-200 bg-gray-50">
                    <img src="${imagenSrc}" alt="Imagen" class="w-full h-full object-cover" onerror="this.src='img/placeholder-equipo.png'">
                </div>
            </td>
            <td data-label="Tipo" class="px-4 py-4 font-bold text-primary-dark break-words">${row.tipo}</td>
            <td data-label="Marca/Modelo" class="px-4 py-4 break-words">
                <div class="font-medium">${row.marca}</div>
                <div class="text-xs text-gray-500">${row.modelo}</div>
            </td>
            <td data-label="Almacenamiento" class="px-4 py-4 break-words">${row.capacidad_almacenamiento || 'N/A'}</td>
            <td data-label="RAM" class="px-4 py-4 break-words">${row.memoria_ram || 'N/A'}</td>
            <td data-label="Detalles" class="px-4 py-4 text-xs celda-detalles">${row.detalles || 'N/A'}</td>
            <td data-label="Capítulo" class="px-4 py-4 font-medium text-gray-700">${row.capitulos || 'N/A'}</td>
            <td data-label="Precio" class="px-4 py-4 font-bold text-green-700 whitespace-nowrap">
            $${parseFloat(row.precio).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
            </td>
            <td class="px-4 py-4 text-center flex justify-center gap-2">
                <button type="button" onclick="verDetalles(${index})" class="text-green-600 hover:text-green-800 transition bg-green-100 hover:bg-green-200 p-2 rounded-full" title="Visualizar">
                    <i class="fas fa-eye"></i>
                </button>
                <a href="editar_adq_equipos.php?id=${row.id_adquisicion}" class="text-blue-600 hover:text-blue-800 transition bg-blue-100 hover:bg-blue-200 p-2 rounded-full" title="Editar">
                    <i class="fas fa-edit"></i>
                </a>
            </td>
        `;
        tabla.appendChild(tr);
    });
}

function renderizarPaginacion(meta) {
    const div = document.getElementById('paginacion');
    div.innerHTML = '';
    if (meta.total_paginas <= 1) return;
    const total = meta.total_paginas;
    const actual = meta.pagina_actual;
    const rango = 1; 

    const crearBoton = (p, texto = p, activo = false, deshabilitado = false) => {
        const btn = document.createElement('button');
        btn.type = "button";
        btn.innerText = texto;
        btn.disabled = deshabilitado;
        let clases = "w-10 h-10 flex items-center justify-center font-bold border transition-all rounded-lg shadow-sm ";
        if (activo) { clases += "bg-primary-dark text-white border-primary-dark shadow-md"; } 
        else if (deshabilitado) { clases += "bg-transparent text-gray-400 border-transparent cursor-default"; } 
        else { clases += "bg-white text-gray-700 border-gray-200 hover:bg-gray-50 hover:border-gray-300"; }
        btn.className = clases;
        if (!deshabilitado && !activo) {
            btn.onclick = () => { paginaActual = p; cargarDatos(); window.scrollTo({ top: 0, behavior: 'smooth' }); };
        }
        return btn;
    };

    if (actual > 1) { const prev = crearBoton(actual - 1, ''); prev.innerHTML = '<i class="fas fa-chevron-left text-xs"></i>'; div.appendChild(prev); }
    div.appendChild(crearBoton(1, '1', actual === 1));
    if (actual > rango + 2) { div.appendChild(crearBoton(0, '...', false, true)); }
    for (let i = Math.max(2, actual - rango); i <= Math.min(total - 1, actual + rango); i++) { div.appendChild(crearBoton(i, i, actual === i)); }
    if (actual < total - rango - 1) { div.appendChild(crearBoton(0, '...', false, true)); }
    if (total > 1) { div.appendChild(crearBoton(total, total, actual === total)); }
    if (actual < total) { const next = crearBoton(actual + 1, ''); next.innerHTML = '<i class="fas fa-chevron-right text-xs"></i>'; div.appendChild(next); }
}

function exportarTodoPDF() {
    // Ocultar el menú desplegable al hacer clic
    document.getElementById('dropdownOpciones').classList.add('hidden');
    
    Swal.fire({ title: 'Generando PDF...', text: 'Preparando reporte...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
    fetch(`consultar_adq_equipos.php?ajax=1&export=pdf&q=${encodeURIComponent(terminoBusqueda)}`)
        .then(res => res.json())
        .then(res => {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF('landscape');
            doc.setFontSize(16);
            doc.setTextColor(114, 21, 56);
            doc.text("Reporte de Adquisiciones de Equipos", 14, 15);
            const columnas = ["Tipo", "Marca", "Modelo", "Almacenamiento", "RAM", "Detalles", "Capítulo", "Precio"];
            const filas = res.data.map(row => [
                row.tipo, row.marca, row.modelo, row.capacidad_almacenamiento || 'N/A', 
                row.memoria_ram || 'N/A', row.detalles || 'N/A', row.capitulos || 'N/A',
                `$${parseFloat(row.precio).toLocaleString('en-US', { minimumFractionDigits: 2 })}`
            ]);
            doc.autoTable({ head: [columnas], body: filas, startY: 25, theme: 'grid', headStyles: { fillColor: [114, 21, 56] }, styles: { fontSize: 7 } });
            doc.save(`Reporte_Equipos_${new Date().getTime()}.pdf`);
            Swal.close();
        }).catch((err) => {
            console.error(err);
            Swal.fire('Error', 'No se pudo generar el reporte PDF', 'error');
        });
}

function exportarExcel() {
    // Ocultar el menú desplegable al hacer clic
    document.getElementById('dropdownOpciones').classList.add('hidden');

    Swal.fire({ title: 'Generando Excel...', text: 'Preparando datos...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
    fetch(`consultar_adq_equipos.php?ajax=1&export=pdf&q=${encodeURIComponent(terminoBusqueda)}`) 
    .then(res => res.json())
    .then(res => {
        if (!res.data || res.data.length === 0) { Swal.fire('Atención', 'No hay datos para exportar', 'warning'); return; }
        const datosExcel = res.data.map(row => ({
            "Tipo": row.tipo, "Marca": row.marca, "Modelo": row.modelo,
            "Almacenamiento": row.capacidad_almacenamiento || 'N/A', "RAM": row.memoria_ram || 'N/A',
            "Detalles": row.detalles || 'N/A', "Capítulo": row.capitulos || 'N/A',
            "Precio": parseFloat(row.precio) 
        }));
        const hoja = XLSX.utils.json_to_sheet(datosExcel);
        const libro = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(libro, hoja, "Adquisiciones");
        XLSX.writeFile(libro, `Reporte_Equipos_${new Date().getTime()}.xlsx`);
        Swal.close();
    }).catch((err) => {
        console.error(err);
        Swal.fire('Error', 'No se pudo generar el Excel', 'error');
    });
}

function verDetalles(index) {
    const item = datosActuales[index];
    const modal = document.getElementById('modalDetalle');
    const content = document.getElementById('modalContent');
    const imagenSrc = item.ruta_imagen ? item.ruta_imagen : 'img/placeholder-equipo.png';

    content.innerHTML = `
        <div class="col-span-1 md:col-span-2 text-center">
            <img src="${imagenSrc}" class="w-full max-h-64 object-contain rounded-lg border shadow-sm mx-auto" onerror="this.src='img/placeholder-equipo.png'">
        </div>
        <div>
            <p class="text-sm text-gray-500 font-bold uppercase">Tipo</p>
            <p class="text-lg font-semibold text-primary-dark mb-3">${item.tipo}</p>
            <p class="text-sm text-gray-500 font-bold uppercase">Marca / Modelo</p>
            <p class="text-lg mb-3">${item.marca} - ${item.modelo}</p>
            <p class="text-sm text-gray-500 font-bold uppercase">Precio</p>
            <p class="text-lg text-green-700 font-bold">$${parseFloat(item.precio).toLocaleString()}</p>
        </div>
        <div>
            <p class="text-sm text-gray-500 font-bold uppercase">Almacenamiento / RAM</p>
            <p class="mb-3">${item.capacidad_almacenamiento || 'N/A'} / ${item.memoria_ram || 'N/A'}</p>
            <p class="text-sm text-gray-500 font-bold uppercase">Capítulo</p>
            <p class="mb-3">${item.capitulos}</p>
            <p class="text-sm text-gray-500 font-bold uppercase">Detalles</p>
            <p class="text-sm italic">${item.detalles || 'Sin detalles'}</p>
        </div>
    `;
    modal.classList.remove('hidden');
}

function cerrarModal() {
    document.getElementById('modalDetalle').classList.add('hidden');
}

// Manejador global de clics para cerrar modales o dropdowns al hacer clic fuera
window.onclick = function(event) {
    // Cerrar Modal
    if (event.target == document.getElementById('modalDetalle')) {
        cerrarModal();
    }
    
    // Cerrar dropdown si se hace clic fuera
    const dropdownOpciones = document.getElementById('dropdownOpciones');
    const btnOpciones = document.getElementById('btnOpciones');
    
    if (dropdownOpciones && !dropdownOpciones.classList.contains('hidden')) {
        if (!dropdownOpciones.contains(event.target) && !btnOpciones.contains(event.target)) {
            dropdownOpciones.classList.add('hidden');
        }
    }
}
    </script>
</body>
</html>
<?php $conn->close(); ?>