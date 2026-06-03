<?php
require_once 'config.php';
session_start();

// 1. SEGURIDAD: Solo Admin
if (!isset($_SESSION['usuario']) || ($_SESSION['rol'] ?? '') !== 'admin') {
    header("Location: index.php");
    exit();
}

$conn = get_db_connection();
if (!$conn) { die("Conexión fallida."); }

// ==========================================
// 2. BACKEND AJAX (JSON)
// ==========================================
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json; charset=utf-8');
    
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

    $sql_total = "SELECT COUNT(*) AS total FROM Adq_equipos" . $where_clause;
    $stmt_total = $conn->prepare($sql_total);
    if (!empty($params)) { $stmt_total->bind_param($types, ...$params); }
    $stmt_total->execute();
    $total_registros = $stmt_total->get_result()->fetch_assoc()["total"];
    $stmt_total->close();

    $total_paginas = ceil($total_registros / $registros_por_pagina);

    // Si detectamos la instrucción 'export=all', omitimos la paginación para traer todo el Excel/PDF
    if (isset($_GET['export']) && $_GET['export'] === 'all') {
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

// 3. INCLUIR HEADER GLOBAL
include 'header.php';
?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

<div class="max-w-7xl mx-auto px-4 pb-12">
    <div class="flex flex-col md:flex-row justify-between items-center mb-6 mt-4 gap-4">
        <div>
            <h2 class="text-3xl font-bold text-primary-dark"><i class="fas fa-shopping-cart mr-2"></i>Adquisiciones SISGI</h2>
            <p class="text-sm text-gray-500">Historial y control de compras de hardware</p>
        </div>
        <div class="flex gap-3">
            <button onclick="exportarExcel()" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-5 rounded-xl shadow-md transition flex items-center gap-2">
                <i class="fas fa-file-excel"></i> Excel
            </button>
            <button onclick="exportarPDF()" class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-5 rounded-xl shadow-md transition flex items-center gap-2">
                <i class="fas fa-file-pdf"></i> PDF
            </button>
        </div>
    </div>

    <div class="bg-white p-4 rounded-xl shadow-sm mb-6 border border-gray-100">
        <div class="relative">
            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400">
                <i class="fas fa-search"></i>
            </span>
            <input type="text" id="searchInput" placeholder="Buscar por marca, modelo, capítulo..." 
                   class="w-full pl-11 p-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary-dark outline-none transition">
        </div>
    </div>

    <div class="bg-white shadow-lg rounded-xl overflow-hidden border border-gray-100 overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-primary-dark text-white text-xs font-bold uppercase tracking-wider">
                <tr>
                    <th class="px-6 py-4 text-left">Equipo</th>
                    <th class="px-6 py-4 text-left">Especificaciones</th>
                    <th class="px-6 py-4 text-center">Capítulo</th>
                    <th class="px-6 py-4 text-right">Precio</th>
                </tr>
            </thead>
            <tbody id="tabla-resultados" class="divide-y divide-gray-100 bg-white text-sm"></tbody>
        </table>
    </div>

    <div id="paginacion" class="mt-8 flex justify-center gap-2"></div>
</div>

<script>
    let paginaActual = 1;
    let terminoBusqueda = '';
    let timeoutBusqueda = null;

    document.addEventListener('DOMContentLoaded', () => {
        cargarDatos();
        
        document.getElementById('searchInput').addEventListener('input', (e) => {
            clearTimeout(timeoutBusqueda);
            terminoBusqueda = e.target.value;
            paginaActual = 1;
            timeoutBusqueda = setTimeout(() => { cargarDatos(); }, 300);
        });
    });

    async function cargarDatos() {
        // Mostramos cargando solo la primera vez para no ser intrusivos
        if(paginaActual === 1 && terminoBusqueda === '') {
            Swal.fire({ title: 'Cargando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
        }
        
        try {
            const res = await fetch(`consultar_adq_equipos.php?ajax=1&q=${encodeURIComponent(terminoBusqueda)}&p=${paginaActual}`);
            const json = await res.json();
            
            renderizarTabla(json.data);
            renderizarPaginacion(json.meta);
            Swal.close();
        } catch (error) {
            console.error(error);
            Swal.fire('Error', 'No se pudieron cargar los datos de adquisiciones.', 'error');
        }
    }

    function renderizarTabla(datos) {
        const tabla = document.getElementById('tabla-resultados');
        tabla.innerHTML = datos.length ? '' : '<tr><td colspan="4" class="p-10 text-center text-gray-500 italic">No se encontraron registros.</td></tr>';

        datos.forEach(row => {
            tabla.innerHTML += `
                <tr class="hover:bg-gray-50 transition">
                    <td class="px-6 py-4">
                        <span class="font-bold text-gray-800 block uppercase">${row.tipo}</span>
                        <span class="text-xs text-gray-500">${row.marca} - ${row.modelo}</span>
                    </td>
                    <td class="px-6 py-4">
                        <div class="text-xs text-gray-600 mb-1"><b>RAM:</b> ${row.memoria_ram || 'N/A'} | <b>Disco:</b> ${row.capacidad_almacenamiento || 'N/A'}</div>
                        <div class="text-xs text-gray-500 italic">${row.detalles || 'Sin detalles extra'}</div>
                    </td>
                    <td class="px-6 py-4 text-center">
                        <span class="px-3 py-1 bg-indigo-50 text-indigo-700 rounded-full text-xs font-bold border border-indigo-100">${row.capitulos || 'N/A'}</span>
                    </td>
                    <td class="px-6 py-4 text-right font-bold text-green-700">
                        ${row.precio ? '$' + parseFloat(row.precio).toFixed(2) : 'N/A'}
                    </td>
                </tr>
            `;
        });
    }

    function renderizarPaginacion(meta) {
        const div = document.getElementById('paginacion');
        div.innerHTML = '';
        if (meta.total_paginas <= 1) return;

        for (let i = 1; i <= meta.total_paginas; i++) {
            const btn = document.createElement('button');
            btn.innerText = i;
            btn.className = `w-10 h-10 rounded-xl font-bold border transition ${i === meta.pagina_actual ? 'bg-primary-dark text-white shadow-md' : 'bg-white text-gray-600 hover:bg-gray-50'}`;
            btn.onclick = () => { paginaActual = i; cargarDatos(); };
            div.appendChild(btn);
        }
    }

    // ==========================================
    // FUNCIONES DE EXPORTACIÓN (CORREGIDAS)
    // ==========================================
    
    function exportarPDF() {
        Swal.fire({ title: 'Generando PDF...', text: 'Preparando documento...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
        
        // Usamos export=all para traer la data sin paginación
        fetch(`consultar_adq_equipos.php?ajax=1&export=all&q=${encodeURIComponent(terminoBusqueda)}`)
        .then(res => res.json())
        .then(res => {
            if (!res.data || res.data.length === 0) {
                Swal.fire('Atención', 'No hay datos para exportar con este filtro.', 'warning');
                return;
            }
            
            // Validamos que el jsPDF esté cargando desde el header.php
            if (!window.jspdf || !window.jspdf.jsPDF) {
                throw new Error("La librería jsPDF no está cargada. Revisa tu header.php");
            }

            const { jsPDF } = window.jspdf;
            const doc = new jsPDF('landscape');
            doc.setFontSize(16); 
            doc.setTextColor(114, 21, 56);
            doc.text("Reporte de Adquisiciones de Equipos (SISGI)", 14, 15);
            
            const columnas = ["Tipo", "Marca", "Modelo", "Almacenamiento", "RAM", "Detalles", "Capítulo", "Precio"];
            const filas = res.data.map(row => [
                row.tipo, row.marca, row.modelo, row.capacidad_almacenamiento || 'N/A', 
                row.memoria_ram || 'N/A', row.detalles || '-', row.capitulos || '-', 
                row.precio ? `$${parseFloat(row.precio).toFixed(2)}` : 'N/A'
            ]);

            doc.autoTable({ 
                head: [columnas], 
                body: filas, 
                startY: 25, 
                theme: 'grid', 
                headStyles: { fillColor: [114, 21, 56] }, 
                styles: { fontSize: 8 } 
            });
            
            doc.save(`Adquisiciones_${new Date().toISOString().slice(0,10)}.pdf`);
            Swal.close();
        })
        .catch(err => {
            console.error("Error técnico al exportar PDF:", err);
            Swal.fire('Error', 'Falla al crear el PDF. Asegúrate de tener "jspdf.plugin.autotable.min.js" en tu carpeta de JS.', 'error');
        });
    }

    function exportarExcel() {
        Swal.fire({ title: 'Generando Excel...', text: 'Estructurando columnas...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
        
        fetch(`consultar_adq_equipos.php?ajax=1&export=all&q=${encodeURIComponent(terminoBusqueda)}`) 
        .then(res => res.json())
        .then(res => {
            if (!res.data || res.data.length === 0) {
                Swal.fire('Atención', 'No hay datos para exportar con este filtro.', 'warning');
                return;
            }
            
            // Validamos que SheetJS esté cargado
            if (typeof XLSX === 'undefined') {
                throw new Error("La librería XLSX no está cargada.");
            }

            // Formateamos los datos para que el Excel quede limpio
            const datosExcel = res.data.map(row => ({
                "Tipo de Equipo": row.tipo, 
                "Marca": row.marca, 
                "Modelo": row.modelo,
                "Almacenamiento": row.capacidad_almacenamiento || 'N/A', 
                "Memoria RAM": row.memoria_ram || 'N/A',
                "Detalles Adicionales": row.detalles || 'N/A', 
                "Capítulo Presupuestal": row.capitulos || 'N/A',
                "Precio ($)": row.precio ? parseFloat(row.precio) : 0 
            }));
            
            const hoja = XLSX.utils.json_to_sheet(datosExcel);
            const libro = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(libro, hoja, "Adquisiciones");
            
            XLSX.writeFile(libro, `Adquisiciones_SISGI_${new Date().toISOString().slice(0,10)}.xlsx`);
            Swal.close();
        })
        .catch(err => {
            console.error("Error técnico al exportar Excel:", err);
            Swal.fire('Error', 'Falla al crear el Excel. Revisa tu conexión o asegúrate de descargar la librería local.', 'error');
        });
    }
</script>