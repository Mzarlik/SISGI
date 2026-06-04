<?php 
require_once 'session_check.php';
require_once 'config.php'; 

if (!isset($_SESSION['usuario'])) { 
    header("Location: login.php"); 
    exit(); 
}

$conn = get_db_connection();
$rol_usuario = $_SESSION['rol'] ?? 'tecnico'; 
$esAdmin = ($rol_usuario === 'admin' || $rol_usuario === 'masterweb'); 

// 1. OBTENER LISTA ÚNICA DE PERSONAL PARA EL FILTRO
$lista_personal = [];
$res_pers = $conn->query("SELECT DISTINCT personal_asignado FROM inventario_soporte WHERE personal_asignado IS NOT NULL AND personal_asignado <> '' ORDER BY personal_asignado ASC");
while($p = $res_pers->fetch_assoc()) {
    $lista_personal[] = $p['personal_asignado'];
}

$tipos_opciones = [];
$res_tipos = $conn->query("SELECT id_tipo, nombre_tipo FROM tipo_bien_inventario ORDER BY nombre_tipo ASC");
while($t = $res_tipos->fetch_assoc()) {
    $tipos_opciones[] = $t;
}

// 2. BACKEND: RESPUESTA AJAX (JSON)
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    
    $search_term = $_GET['q'] ?? '';
    $filtro_personal = $_GET['u'] ?? ''; 
    $pagina = isset($_GET['p']) && is_numeric($_GET['p']) ? (int)$_GET['p'] : 1;
    $registros_por_pagina = 10;
    $offset = ($pagina - 1) * $registros_por_pagina;

    $conditions = [];
    
    // Búsqueda general
    if (!empty($search_term)) {
        $safe_term = $conn->real_escape_string($search_term);
        $conditions[] = "(inv.num_inventario LIKE '%$safe_term%' OR 
                        tbi.nombre_tipo LIKE '%$safe_term%' OR 
                        inv.marca LIKE '%$safe_term%' OR 
                        inv.modelo LIKE '%$safe_term%' OR 
                        inv.num_serie LIKE '%$safe_term%' OR 
                        inv.descripcion LIKE '%$safe_term%' OR 
                        inv.personal_asignado LIKE '%$safe_term%' OR 
                        inv.ubicacion LIKE '%$safe_term%')";
    }

    // Filtro específico por personal
    if (!empty($filtro_personal)) {
        $safe_user = $conn->real_escape_string($filtro_personal);
        $conditions[] = "inv.personal_asignado = '$safe_user'";
    }

    $where_clause = count($conditions) > 0 ? " WHERE " . implode(" AND ", $conditions) : "";

    $count_sql = "SELECT COUNT(inv.id) AS total FROM inventario_soporte inv 
                  LEFT JOIN tipo_bien_inventario tbi ON inv.id_tipo_bien = tbi.id_tipo" . $where_clause;
    $total_registros = $conn->query($count_sql)->fetch_assoc()['total'];
    $total_paginas = ceil($total_registros / $registros_por_pagina);

    $columnas = "inv.id, inv.num_inventario, tbi.nombre_tipo, inv.id_tipo_bien, inv.marca, inv.modelo, inv.num_serie, inv.descripcion, inv.personal_asignado, inv.ubicacion, inv.ruta_foto";
    
    $sql = "SELECT $columnas FROM inventario_soporte inv 
            LEFT JOIN tipo_bien_inventario tbi ON inv.id_tipo_bien = tbi.id_tipo 
            $where_clause ORDER BY inv.num_inventario DESC LIMIT $registros_por_pagina OFFSET $offset";

    $result = $conn->query($sql);
    $datos = [];
    while($row = $result->fetch_assoc()) { $datos[] = $row; }

    echo json_encode([
        'data' => $datos,
        'meta' => ['pagina_actual' => $pagina, 'total_paginas' => $total_paginas, 'total_registros' => $total_registros]
    ]);
    exit;
}
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
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.25/jspdf.plugin.autotable.min.js"></script>
    <script>
        const listaTiposGlobal = <?php echo json_encode($tipos_opciones); ?>;
        tailwind.config = {
            theme: { extend: { colors: { 'primary-dark': '#721538', 'primary-light': '#961e4b', 'background': '#d6d1ca' } } }
        }
    </script>
    <style>
        /* Estilos para inputs dentro de SweetAlert al editar */
        .swal-field-label { display: block; text-align: left; font-size: 0.75rem; font-weight: bold; color: #555; margin-bottom: 2px; text-transform: uppercase; }
        .swal-custom-input { width: 100% !important; margin: 0 0 12px 0 !important; font-size: 0.9rem !important; height: 40px !important; border: 1px solid #ccc; border-radius: 6px; padding: 0 10px; }
        .swal-custom-textarea { width: 100% !important; margin: 0 !important; font-size: 0.9rem !important; border: 1px solid #ccc; border-radius: 6px; padding: 10px; }
    </style>
</head>
<body class="p-4 sm:p-8 bg-background">

<div class="max-w-7xl mx-auto">
    <div class="flex flex-col sm:flex-row justify-between items-center mb-6">
        <h2 class="text-3xl font-bold text-primary-dark flex items-center gap-2">
            <i class="fas fa-laptop"></i> Inventario de Equipos 
            <span id="total-lbl" class="text-xs bg-gray-200 text-gray-600 px-3 py-1 rounded-full italic">...</span>
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
            <input type="text" id="searchInput" placeholder="Buscar serie, modelo, inventario..." class="w-full pl-11 p-3 border border-gray-300 rounded-full focus:ring-2 focus:ring-primary-dark outline-none transition">
        </div>

        <div class="w-full lg:w-64">
            <select id="userFilter" class="w-full p-3 border border-gray-300 rounded-full focus:ring-2 focus:ring-primary-dark outline-none bg-white cursor-pointer">
                <option value="">👤 Todos los responsables</option>
                <?php foreach($lista_personal as $nombre): ?>
                    <option value="<?= htmlspecialchars($nombre) ?>"><?= htmlspecialchars($nombre) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="relative w-full lg:w-auto flex justify-end">
            <button id="btnOpciones" class="bg-primary-dark hover:bg-primary-light text-white font-bold py-3 px-6 rounded-full shadow transition flex items-center gap-2 w-full lg:w-auto justify-center">
                <i class="fas fa-bars"></i> Opciones
            </button>

            <div id="dropdownOpciones" class="hidden absolute top-full mt-2 right-0 w-60 bg-white rounded-xl shadow-xl border border-gray-200 overflow-hidden z-50">
                <a href="registrar_inventario.php" class="block px-4 py-3 text-gray-700 hover:bg-indigo-50 hover:text-indigo-700 transition border-b border-gray-100 font-medium">
                    <i class="fas fa-plus w-6 text-center text-green-600"></i> Nuevo Registro
                </a>
                <button onclick="iniciarTraspaso()" class="w-full text-left px-4 py-3 text-gray-700 hover:bg-indigo-50 hover:text-indigo-700 transition border-b border-gray-100 font-medium">
                    <i class="fas fa-exchange-alt w-6 text-center text-blue-600"></i> Traspaso / Préstamo
                </button>
                <button onclick="exportarPDF()" class="w-full text-left px-4 py-3 text-gray-700 hover:bg-indigo-50 hover:text-indigo-700 transition border-b border-gray-100 font-medium">
                    <i class="fas fa-file-pdf w-6 text-center text-red-600"></i> Exportar PDF
                </button>
                <a href="dashboard.php" class="block px-4 py-3 text-gray-700 hover:bg-gray-100 transition font-medium">
                    <i class="fas fa-home w-6 text-center text-gray-600"></i> Menú Principal
                </a>
            </div>
        </div>
    </div>

    <div class="bg-white shadow-lg rounded-xl overflow-hidden border border-gray-100 overflow-x-auto relative z-0">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-primary-dark text-white text-xs font-bold uppercase tracking-wider">
                <tr>
                    <th class="px-4 py-4 text-center w-12">
                        <input type="checkbox" id="selectAll" onclick="toggleSelectAll()" class="w-4 h-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 cursor-pointer">
                    </th>
                    <th class="px-4 py-4 text-center">Foto</th>
                    <th class="px-4 py-4 text-left">Inventario</th>
                    <th class="px-4 py-4 text-left">Tipo / Marca</th>
                    <th class="px-4 py-4 text-left">Responsable</th>
                    <th class="px-4 py-4 text-center">Acciones</th>
                </tr>
            </thead>
            <tbody id="tabla-resultados" class="text-sm divide-y divide-gray-100 bg-white"></tbody>
        </table>
    </div>

    <div id="paginacion" class="mt-8 flex justify-center gap-2"></div>
</div>

<script>
    let paginaActual = 1;
    let terminoBusqueda = '';
    let filtroUsuario = '';
    let timeoutBusqueda = null;
    let datosActuales = []; 
    const esAdmin = <?= $esAdmin ? 'true' : 'false' ?>;
    const BASE_URL_IMAGENES = '../inventario/';

    document.addEventListener('DOMContentLoaded', () => {
        cargarDatos();
        
        // Listener para búsqueda de texto
        document.getElementById('searchInput').addEventListener('input', (e) => {
            clearTimeout(timeoutBusqueda);
            terminoBusqueda = e.target.value;
            paginaActual = 1;
            timeoutBusqueda = setTimeout(() => { cargarDatos(); }, 300);
        });

        // Listener para el filtro de usuario
        document.getElementById('userFilter').addEventListener('change', (e) => {
            filtroUsuario = e.target.value;
            paginaActual = 1;
            cargarDatos();
        });

        // Lógica del Menú de Hamburguesa
        const btnOpciones = document.getElementById('btnOpciones');
        const dropdownOpciones = document.getElementById('dropdownOpciones');

        btnOpciones.addEventListener('click', (e) => {
            e.stopPropagation();
            dropdownOpciones.classList.toggle('hidden');
        });

        document.addEventListener('click', (e) => {
            if (!dropdownOpciones.contains(e.target) && e.target !== btnOpciones) {
                dropdownOpciones.classList.add('hidden');
            }
        });
    });

    function cargarDatos() {
        document.getElementById('loading').style.display = 'block';
        const url = `consultar_inventario.php?ajax=1&q=${encodeURIComponent(terminoBusqueda)}&u=${encodeURIComponent(filtroUsuario)}&p=${paginaActual}`;
        
        fetch(url)
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
        tabla.innerHTML = datos.length ? '' : '<tr><td colspan="6" class="p-10 text-center text-gray-500 italic">No se encontraron bienes para este filtro.</td></tr>';

        datos.forEach(row => {
            let iconoFotoHTML = row.ruta_foto 
                ? `<button onclick="verImagen('${BASE_URL_IMAGENES}${row.ruta_foto}', '${row.num_inventario}')" class="text-indigo-600 hover:text-indigo-800 transition transform hover:scale-110"><i class="fas fa-image text-2xl"></i></button>`
                : `<span class="text-gray-300"><i class="fas fa-image text-2xl"></i></span>`;

            const tr = document.createElement('tr');
            tr.className = "hover:bg-gray-50 transition-colors";
            
            // Renderizamos la fila, incluyendo el botón de edición si es administrador
            tr.innerHTML = `
                <td class="px-4 py-4 text-center">
                    <input type="checkbox" class="cb-equipo w-4 h-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 cursor-pointer" value="${row.id}">
                </td>
                <td class="px-4 py-4 text-center">${iconoFotoHTML}</td>
                <td class="px-4 py-4 font-bold text-primary-dark">${row.num_inventario}</td>
                <td class="px-4 py-4">
                    <span class="text-xs text-gray-400 uppercase block font-semibold">${row.nombre_tipo || 'N/A'}</span>
                    <span class="font-medium text-gray-800">${row.marca}</span>
                </td>
                <td class="px-4 py-4 font-medium text-gray-700">
                    <i class="fas fa-user-circle text-gray-400 mr-1"></i> ${row.personal_asignado || 'STOCK'}
                </td>
                <td class="px-4 py-4 text-center whitespace-nowrap space-x-1">
                    <button class="bg-indigo-100 text-indigo-700 p-2 rounded shadow hover:bg-indigo-200 transition" onclick="verDetalles(${row.id})" title="Ver Detalles">
                        <i class="fas fa-eye"></i>
                    </button>
                    ${esAdmin ? `
                    <button class="bg-blue-500 text-white p-2 rounded shadow hover:bg-blue-600 transition" onclick="editarBienCompleto(${row.id})" title="Editar Información">
                        <i class="fas fa-pencil-alt"></i>
                    </button>
                    ` : ''}
                </td>
            `;
            tabla.appendChild(tr);
        });
    }

    // --- FUNCIÓN RESTAURADA PARA EDITAR EL BIEN ---
    function editarBienCompleto(id) {
        const row = datosActuales.find(r => r.id == id);
        if(!row) return;

        let optionsTipo = listaTiposGlobal.map(t => 
            `<option value="${t.id_tipo}" ${t.id_tipo == row.id_tipo_bien ? 'selected' : ''}>${t.nombre_tipo}</option>`
        ).join('');

        Swal.fire({
            title: '<div class="text-xl font-bold border-b pb-2">Editar Información del Bien</div>',
            html: `
                <div class="text-left mt-4">
                    <label class="swal-field-label">Número de Inventario</label>
                    <input id="swal-inv" class="swal-custom-input" value="${row.num_inventario}">
                    
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="swal-field-label">Tipo de Bien</label>
                            <select id="swal-tipo" class="swal-custom-input bg-white">${optionsTipo}</select>
                        </div>
                        <div>
                            <label class="swal-field-label">Ubicación</label>
                            <input id="swal-ubi" class="swal-custom-input" value="${row.ubicacion}">
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="swal-field-label">Marca</label>
                            <input id="swal-marca" class="swal-custom-input" value="${row.marca}">
                        </div>
                        <div>
                            <label class="swal-field-label">Modelo</label>
                            <input id="swal-modelo" class="swal-custom-input" value="${row.modelo}">
                        </div>
                    </div>

                    <label class="swal-field-label">Número de Serie</label>
                    <input id="swal-serie" class="swal-custom-input" value="${row.num_serie}">

                    <label class="swal-field-label">Personal Asignado</label>
                    <input id="swal-pers" class="swal-custom-input" value="${row.personal_asignado || ''}">

                    <label class="swal-field-label">Descripción / Notas</label>
                    <textarea id="swal-desc" class="swal-custom-textarea" rows="3">${row.descripcion || ''}</textarea>
                </div>
            `,
            width: '600px',
            focusConfirm: false,
            showCancelButton: true,
            confirmButtonText: '<i class="fas fa-save mr-2"></i> Guardar Cambios',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#721538',
            preConfirm: () => {
                return {
                    id: id,
                    num_inventario: document.getElementById('swal-inv').value,
                    id_tipo_bien: document.getElementById('swal-tipo').value,
                    ubicacion: document.getElementById('swal-ubi').value,
                    marca: document.getElementById('swal-marca').value,
                    modelo: document.getElementById('swal-modelo').value,
                    num_serie: document.getElementById('swal-serie').value,
                    personal_asignado: document.getElementById('swal-pers').value,
                    descripcion: document.getElementById('swal-desc').value
                }
            }
        }).then((result) => {
            if (result.isConfirmed) {
                const formData = new FormData();
                for (let key in result.value) { formData.append(key, result.value[key]); }

                fetch('actualizar_inventario.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if(data.success) {
                        Swal.fire('¡Éxito!', 'La información se ha actualizado correctamente.', 'success');
                        cargarDatos(); // Refresca la tabla automáticamente
                    } else {
                        Swal.fire('Error', data.message, 'error');
                    }
                });
            }
        });
    }

    function verDetalles(id) {
        const row = datosActuales.find(r => r.id == id);
        if(!row) return;

        const detallesHtml = `
            <div class="text-left text-sm text-gray-700 p-2">
                <div class="grid grid-cols-2 gap-4 mb-4 border-b pb-3">
                    <div><span class="block text-xs text-gray-400 uppercase">Inventario</span><span class="font-bold text-lg text-primary-dark">${row.num_inventario}</span></div>
                    <div><span class="block text-xs text-gray-400 uppercase">Responsable</span><span class="font-semibold text-gray-800">${row.personal_asignado || 'STOCK'}</span></div>
                </div>
                <div class="grid grid-cols-2 gap-4 mb-3 border-b pb-3">
                    <div><span class="block text-xs text-gray-400 uppercase">Marca / Modelo</span><span class="font-medium text-gray-800">${row.marca} - ${row.modelo}</span></div>
                    <div><span class="block text-xs text-gray-400 uppercase">Ubicación</span><span class="font-medium text-gray-800">${row.ubicacion}</span></div>
                </div>
                <div class="mb-4">
                    <span class="block text-xs text-gray-400 uppercase">Número de Serie</span>
                    <span class="font-mono bg-gray-100 px-2 py-1 rounded text-gray-800 border">${row.num_serie}</span>
                </div>
                <div class="bg-[#f7fcf7] p-4 rounded-lg border">
                    <span class="block text-xs text-gray-500 uppercase font-bold mb-2">Descripción y Notas:</span>
                    <span class="whitespace-pre-line text-gray-800 italic">${row.descripcion || 'Sin descripción adicional.'}</span>
                </div>
            </div>
        `;

        Swal.fire({
            title: '<div class="text-xl font-bold border-b pb-2"><i class="fas fa-info-circle text-primary-dark mr-2"></i> Detalles del Bien</div>',
            html: detallesHtml,
            width: '600px',
            confirmButtonText: 'Cerrar',
            confirmButtonColor: '#721538'
        });
    }

    function verImagen(ruta, num_inv) {
        Swal.fire({ title: `Evidencia: ${num_inv}`, imageUrl: ruta, imageWidth: '100%', confirmButtonText: 'Cerrar', confirmButtonColor: '#721538' });
    }

    function toggleSelectAll() {
        const isChecked = document.getElementById('selectAll').checked;
        const checkboxes = document.querySelectorAll('.cb-equipo');
        checkboxes.forEach(cb => cb.checked = isChecked);
    }

    function iniciarTraspaso() {
        const checkboxesSeleccionados = document.querySelectorAll('.cb-equipo:checked');
        if (checkboxesSeleccionados.length === 0) {
            Swal.fire({ icon: 'warning', title: 'Atención', text: 'Debes seleccionar al menos un equipo marcando su casilla a la izquierda.', confirmButtonColor: '#4f46e5' });
            return;
        }
        const ids = Array.from(checkboxesSeleccionados).map(cb => cb.value);
        window.location.href = `generar_traspaso.php?equipos=${ids.join(',')}`;
    }

    function exportarPDF() {
        const busquedaActual = document.getElementById('searchInput').value;
        const personalActual = document.getElementById('userFilter').value;
        
        Swal.fire({ title: 'Generando PDF...', text: 'Preparando documento...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
        
        fetch(`consultar_inventario.php?ajax=1&export=pdf&q=${encodeURIComponent(busquedaActual)}&u=${encodeURIComponent(personalActual)}`)
            .then(res => res.json())
            .then(res => {
                const { jsPDF } = window.jspdf;
                const doc = new jsPDF('landscape');
                doc.setFontSize(16);
                doc.setTextColor(114, 21, 56);
                doc.text("Reporte de Inventario - Soporte Técnico", 14, 15);
                
                if (personalActual) {
                    doc.setFontSize(10);
                    doc.setTextColor(80, 80, 80);
                    doc.text(`Filtrado por responsable: ${personalActual}`, 14, 21);
                }
                
                const columnas = ["Inventario", "Tipo", "Marca", "Modelo", "Serie", "Personal", "Ubicación", "Descripción"];
                const filas = res.data.map(item => [
                    item.num_inventario, 
                    item.nombre_tipo || 'N/A', 
                    item.marca, 
                    item.modelo, 
                    item.num_serie, 
                    item.personal_asignado || 'STOCK', 
                    item.ubicacion, 
                    item.descripcion || '-'
                ]);

                doc.autoTable({
                    head: [columnas],
                    body: filas,
                    startY: personalActual ? 26 : 25,
                    theme: 'grid',
                    headStyles: { fillColor: [114, 21, 56] },
                    styles: { fontSize: 8 }
                });

                doc.save(`Inventario_${new Date().toISOString().slice(0,10)}.pdf`);
                Swal.close();
            })
            .catch(() => Swal.fire('Error', 'No se pudo generar el archivo', 'error'));
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
</script>
</body>
</html>