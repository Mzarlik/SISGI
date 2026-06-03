<?php
require_once 'config.php';
session_start();

// 1. SEGURIDAD
if (!isset($_SESSION['usuario'])) {
    header("Location: index.php");
    exit();
}

$puedeEditar = true; 

$conn = get_db_connection();
if (!$conn) { die("Conexión fallida."); }

// ==========================================
// 2. BACKEND AJAX (API INTERNA)
// ==========================================
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    
    // Parámetros básicos
    $search = $_GET['q'] ?? '';
    $page = isset($_GET['p']) ? (int)$_GET['p'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10; // Límite dinámico (PDF vs Tabla)
    $offset = ($page - 1) * $limit;

    // Parámetros de Ordenamiento (Sorting)
    $sortCol = $_GET['sort'] ?? 'id';     // Columna por defecto
    $sortOrd = $_GET['order'] ?? 'ASC';   // Orden por defecto

    // Lista blanca de columnas permitidas para ordenar (Seguridad)
    $allowedCols = ['id', 'Conectados']; 
    if (!in_array($sortCol, $allowedCols)) {
        $sortCol = 'id';
    }
    // Validar dirección
    $sortOrd = (strtoupper($sortOrd) === 'DESC') ? 'DESC' : 'ASC';

    // Construcción del WHERE
    $where = "WHERE 1=1";
    $params = [];
    $types = "";

    if (!empty($search)) {
        $where .= " AND (Dirección LIKE ? OR Area LIKE ? OR Correo LIKE ?)";
        $term = "%$search%";
        $params = [$term, $term, $term];
        $types = "sss";
    }

    // A. Total Registros (Para paginación)
    $sqlCount = "SELECT COUNT(*) as total FROM cuentas_office $where";
    $stmt = $conn->prepare($sqlCount);
    if(!empty($params)) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $total_registros = $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();

    // B. Total Conexiones (Para Dashboard)
    $sqlSum = "SELECT SUM(Conectados) as total_conexiones FROM cuentas_office";
    $stmtSum = $conn->prepare($sqlSum);
    $stmtSum->execute();
    $rowSum = $stmtSum->get_result()->fetch_assoc();
    $total_conexiones = $rowSum['total_conexiones'] ?? 0;
    $stmtSum->close();

    // C. Datos de la Tabla (Con Ordenamiento)
    $orderByClause = $sortCol;
    if ($sortCol === 'Conectados') {
        $orderByClause = "CAST(Conectados AS UNSIGNED)";
    }

    $sql = "SELECT id, Dirección, Area, Correo, Password, Conectados 
            FROM cuentas_office $where 
            ORDER BY $orderByClause $sortOrd 
            LIMIT ?, ?";
    
    $params[] = $offset; $params[] = $limit; 
    $types .= "ii";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    
    $data = [];
    while($row = $res->fetch_assoc()) {
        $row['Conectados'] = $row['Conectados'] ?? 0;
        $data[] = $row;
    }

    echo json_encode([
        'data' => $data,
        'meta' => [
            'total_registros' => $total_registros,
            'total_conexiones' => $total_conexiones,
            'pages' => ceil($total_registros / $limit),
            'page' => $page,
            'sort' => $sortCol,
            'order' => $sortOrd
        ]
    ]);
    exit;
    
}
include 'header.php';
?>

<!DOCTYPE html>
<html lang="es">
<body class="bg-gray-100 min-h-screen p-4 md:p-8 font-sans text-gray-800">

<div class="max-w-7xl mx-auto space-y-6">

    <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-6 pb-2">
        <div>
            <h1 class="text-3xl font-bold text-brand flex items-center gap-3">
                <i class="fab fa-microsoft text-brand-light"></i> Licencias Office 365
            </h1>
            <p class="text-gray-500 text-sm mt-1 ml-1">
                Gestión Institucional | Licencias totales: <span id="badge-total" class="font-bold text-gray-800">0</span>
            </p>
        </div>
        
        <div class="flex flex-col sm:flex-row gap-4 w-full lg:w-auto">
            <div class="flex items-center gap-4 px-6 py-4 rounded-xl shadow-sm border border-gray-200 border-t-4 border-t-blue-500 bg-white w-full sm:w-auto">
                <div class="p-3 bg-blue-50 rounded-full text-blue-600"><i class="fas fa-network-wired text-xl"></i></div>
                <div class="flex flex-col">
                    <span class="text-[10px] font-bold uppercase text-gray-400 tracking-wider">Activas</span>
                    <div class="flex items-baseline gap-1">
                        <span id="stat-conexiones" class="text-2xl font-bold text-gray-800">0</span>
                        <span class="text-xs text-gray-400">totales</span>
                    </div>
                </div>
            </div>
            <div id="semaforo-box" class="flex items-center gap-4 px-6 py-4 rounded-xl shadow-sm border border-gray-200 border-t-4 border-t-brand bg-white w-full sm:w-auto">
                <div id="semaforo-icon" class="text-3xl">🟢</div>
                <div class="flex flex-col">
                    <span class="text-[10px] font-bold uppercase text-gray-400 tracking-wider">Vigencia</span>
                    <span id="semaforo-text" class="font-bold text-gray-700 text-sm">Calculando...</span>
                    <span class="text-xs text-gray-400">1 de Agosto</span>
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
                        
                        <th onclick="cambiarOrden('Conectados')" class="p-4 font-semibold text-center w-56 border-r border-brand-light/30 cursor-pointer hover:bg-brand-light transition select-none group" title="Clic para ordenar">
                            Activaciones
                            <i id="icon-sort-Conectados" class="fas fa-sort ml-2 text-white/50 group-hover:text-white transition"></i>
                        </th>
                        
                        <th class="p-4 font-semibold w-48 border-r border-brand-light/30">Contraseña</th>
                        <th class="p-4 font-semibold text-center w-32">Acciones</th>
                    </tr>
                </thead>
                <tbody id="tabla-body" class="divide-y divide-gray-100 text-sm text-gray-700"></tbody>
            </table>
        </div>
        <div id="loading" class="hidden p-8 text-center text-brand"><i class="fas fa-circle-notch fa-spin fa-2x"></i></div>
        <div id="paginacion" class="p-4 flex justify-center gap-2 bg-gray-50 border-t border-gray-200"></div>
    </div>

</div>

<script>
    let paginaActual = 1;
    let busqueda = '';
    
    // Variables para ordenamiento
    let ordenColumna = 'id'; // Columna por defecto
    let ordenDireccion = 'ASC'; // Dirección por defecto

    const permisoEditar = <?= $puedeEditar ? 'true' : 'false' ?>;

    document.addEventListener('DOMContentLoaded', () => {
        actualizarSemaforo(); 
        cargarDatos();
        document.getElementById('inputBusqueda').addEventListener('input', (e) => {
            busqueda = e.target.value; paginaActual = 1;
            clearTimeout(window.searchTimeout); window.searchTimeout = setTimeout(cargarDatos, 300);
        });
    });

    // --- FUNCIÓN DE ORDENAMIENTO ---
    function cambiarOrden(columna) {
        // Alternar orden si es la misma columna
        if (ordenColumna === columna) {
            ordenDireccion = (ordenDireccion === 'ASC') ? 'DESC' : 'ASC';
        } else {
            ordenColumna = columna;
            ordenDireccion = 'ASC'; // Nueva columna empieza ascendente
        }

        // Actualizar iconos visuales
        // 1. Resetear todos
        document.getElementById('icon-sort-Conectados').className = 'fas fa-sort ml-2 text-white/50 group-hover:text-white transition';
        
        // 2. Poner icono activo
        if(columna === 'Conectados') {
            const icon = document.getElementById('icon-sort-Conectados');
            icon.className = `fas fa-sort-${ordenDireccion === 'ASC' ? 'up' : 'down'} ml-2 text-white`;
        }

        cargarDatos(); // Recargar tabla con nuevo orden
    }

    // --- CARGAR DATOS (AJAX) ---
    function cargarDatos() {
        const loader = document.getElementById('loading');
        const tbody = document.getElementById('tabla-body');
        loader.classList.remove('hidden'); tbody.style.opacity = '0.5';
        
        // Enviamos sort y order en la URL
        const url = `consultar_licencias.php?ajax=1&q=${encodeURIComponent(busqueda)}&p=${paginaActual}&sort=${ordenColumna}&order=${ordenDireccion}`;

        fetch(url)
            .then(r => r.json())
            .then(res => {
                document.getElementById('badge-total').innerText = res.meta.total_registros;
                document.getElementById('stat-conexiones').innerText = res.meta.total_conexiones;
                renderTabla(res.data);
                renderPaginacion(res.meta);
            })
            .catch(e => console.error(e))
            .finally(() => { loader.classList.add('hidden'); tbody.style.opacity = '1'; });
    }

    // --- GENERAR PDF ---
    async function exportarPDF() {
        const toast = Swal.mixin({ toast: true, position: 'top-end', showConfirmButton: false, timer: 3000 });
        toast.fire({ icon: 'info', title: 'Generando PDF...' });

        try {
            // Pedimos todos los datos (respetando orden actual y filtros)
            const url = `consultar_licencias.php?ajax=1&q=${encodeURIComponent(busqueda)}&limit=10000&sort=${ordenColumna}&order=${ordenDireccion}`;
            const res = await fetch(url);
            const json = await res.json();
            const datos = json.data;

            if(datos.length === 0) { Swal.fire('Atención', 'No hay datos', 'warning'); return; }

            const { jsPDF } = window.jspdf;
            const doc = new jsPDF();

            // Encabezado PDF
            const fecha = new Date().toLocaleDateString();
            doc.setFontSize(16);
            doc.setTextColor(114, 21, 56);
            doc.text("Reporte de Licencias Office 365", 14, 20);
            
            doc.setFontSize(10);
            doc.setTextColor(100);
            doc.text(`Fecha: ${fecha} | Ordenado por: ${ordenColumna === 'Conectados' ? 'Activaciones' : 'ID'} (${ordenDireccion})`, 14, 28);

            // Tabla PDF
            doc.autoTable({
                startY: 35,
                head: [['Ubicación', 'Correo', 'Activaciones']], 
                body: datos.map(row => [
                    row.Dirección + "\n" + row.Area, 
                    row.Correo,
                    row.Conectados 
                ]),
                theme: 'grid',
                headStyles: { 
                    fillColor: [114, 21, 56], 
                    textColor: 255,
                    fontStyle: 'bold',
                    halign: 'center'
                },
                columnStyles: {
                    0: { cellWidth: 80 }, 
                    1: { cellWidth: 80 }, 
                    2: { halign: 'center', fontStyle: 'bold' }
                },
                styles: { fontSize: 9, cellPadding: 3 },
                alternateRowStyles: { fillColor: [249, 250, 251] }
            });

            doc.save(`Reporte_Licencias_${fecha.replace(/\//g, '-')}.pdf`);

        } catch (error) {
            console.error(error);
            Swal.fire('Error', 'No se pudo generar el PDF', 'error');
        }
    }

    // --- RENDERIZADO TABLA ---
    function renderTabla(data) {
        const tbody = document.getElementById('tabla-body'); tbody.innerHTML = '';
        if(data.length === 0) { tbody.innerHTML = '<tr><td colspan="5" class="p-8 text-center text-gray-500 italic">Sin resultados</td></tr>'; return; }

        data.forEach(row => {
            const tr = document.createElement('tr');
            tr.className = "hover:bg-gray-50 group border-b border-gray-100 transition";
            tr.id = `fila_${row.id}`;
            const usados = parseInt(row.Conectados);
            let color = usados >= 10 ? 'bg-red-500' : (usados >= 8 ? 'bg-yellow-500' : 'bg-brand');
            let pct = Math.min(100, (usados / 10) * 100);

            tr.innerHTML = `
                <td class="p-4 align-top editable-cell">
                    <div class="view-val">
                        <div class="font-bold text-gray-800 text-sm mb-1">${row.Dirección}</div>
                        <div class="inline-block bg-gray-100 text-gray-500 text-[10px] uppercase px-2 py-0.5 rounded border border-gray-200 tracking-wide">${row.Area}</div>
                    </div>
                    <div class="hidden edit-inputs space-y-2">
                        <input type="text" class="editable-input" value="${row.Dirección}" id="edit_dir_${row.id}">
                        <input type="text" class="editable-input" value="${row.Area}" id="edit_area_${row.id}">
                    </div>
                </td>
                <td class="p-4 align-top editable-cell">
                    <div class="view-val text-blue-600 font-medium text-sm flex gap-2"><i class="far fa-envelope text-gray-300"></i> ${row.Correo}</div>
                    <div class="hidden edit-inputs"><input type="text" class="editable-input" value="${row.Correo}" id="edit_correo_${row.id}"></div>
                </td>
                <td class="p-4 align-middle text-center editable-cell">
                    <div class="view-val w-full max-w-[140px] mx-auto">
                        <div class="flex justify-between text-xs mb-1"><span class="text-gray-400">Uso</span><span class="font-bold">${usados}/10</span></div>
                        <div class="w-full bg-gray-200 rounded-full h-1.5"><div class="${color} h-1.5 rounded-full" style="width: ${pct}%"></div></div>
                    </div>
                    <div class="hidden edit-inputs"><input type="number" class="editable-input w-20 text-center mx-auto" value="${usados}" id="edit_conectados_${row.id}"></div>
                </td>
                <td class="p-4 align-middle">
                    <div class="view-val flex items-center gap-2 bg-gray-50 px-3 py-2 rounded-lg border border-gray-200 w-32">
                        <input type="password" value="${row.Password}" readonly class="bg-transparent border-none w-full text-xs outline-none text-gray-600 font-mono tracking-widest" id="pass_input_${row.id}">
                        <button onclick="togglePass(${row.id})" class="text-gray-400 hover:text-brand"><i class="fas fa-eye"></i></button>
                    </div>
                    <div class="hidden edit-inputs"><input type="text" class="editable-input font-mono text-xs" value="${row.Password}" id="edit_pass_${row.id}"></div>
                </td>
                <td class="p-4 align-middle text-center">
                    <div class="btn-group-view flex justify-center gap-2">
                        <button onclick="modoEdicion(${row.id})" class="w-8 h-8 rounded border border-gray-300 text-gray-500 hover:text-brand hover:border-brand transition flex items-center justify-center"><i class="fas fa-pencil-alt text-xs"></i></button>
                        <button onclick="eliminarFila(${row.id})" class="w-8 h-8 rounded border border-gray-300 text-gray-500 hover:text-red-500 hover:border-red-500 transition flex items-center justify-center"><i class="fas fa-trash-alt text-xs"></i></button>
                    </div>
                    <div class="hidden btn-group-edit flex flex-col gap-2">
                        <button onclick="guardarCambios(${row.id})" class="bg-green-600 text-white py-1 px-3 rounded text-xs shadow">Guardar</button>
                        <button onclick="cancelarEdicion(${row.id})" class="bg-white border border-gray-300 text-gray-600 py-1 px-3 rounded text-xs">Cancelar</button>
                    </div>
                </td>
            `;
            tbody.appendChild(tr);
        });
    }

    function renderPaginacion(meta) {
        const div = document.getElementById('paginacion'); div.innerHTML = '';
        if(meta.pages <= 1) return;
        for(let i=1; i<=meta.pages; i++) {
            const btn = document.createElement('button'); btn.innerText = i;
            btn.className = `w-8 h-8 flex items-center justify-center rounded text-sm transition ${i === meta.page ? 'bg-brand text-white shadow' : 'bg-white border border-gray-300 text-gray-600 hover:bg-gray-100'}`;
            btn.onclick = () => { paginaActual = i; cargarDatos(); };
            div.appendChild(btn);
        }
    }

    // --- UTILIDADES ---
    function actualizarSemaforo() {
        const hoy = new Date();
        const dias = Math.ceil((new Date(hoy.getFullYear(), 7, 1) - hoy) / (86400000));
        const box = document.getElementById('semaforo-box'); const icon = document.getElementById('semaforo-icon'); const text = document.getElementById('semaforo-text');
        
        if (dias < 0) { box.classList.add('border-t-red-500', 'bg-red-50'); icon.innerHTML="🔴"; text.innerHTML="Vencido"; text.classList.add('text-red-700'); }
        else if (dias <= 60) { box.classList.add('border-t-yellow-500', 'bg-yellow-50'); icon.innerHTML="⚠️"; text.innerHTML=`Renovar (${dias}d)`; text.classList.add('text-yellow-700'); }
        else { box.classList.add('border-t-green-500', 'bg-green-50'); icon.innerHTML="✅"; text.innerHTML="Vigente"; text.classList.add('text-green-700'); }
    }

    function togglePass(id) { const i = document.getElementById(`pass_input_${id}`); i.type = i.type === 'password' ? 'text' : 'password'; }
    function modoEdicion(id) {
        const tr = document.getElementById(`fila_${id}`);
        tr.querySelectorAll('.view-val').forEach(el => el.classList.add('hidden'));
        tr.querySelectorAll('.edit-inputs').forEach(el => el.classList.remove('hidden'));
        tr.querySelector('.btn-group-view').classList.add('hidden');
        tr.querySelector('.btn-group-edit').classList.remove('hidden');
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

        Swal.fire({ title: 'Guardando...', didOpen: () => Swal.showLoading(), confirmButtonColor: '#721538' });
        fetch('actualizar_licencia.php', { method: 'POST', body: fd }).then(r => r.json()).then(d => {
            if(d.success) { Swal.fire({ icon: 'success', title: '¡Guardado!', timer: 1500, showConfirmButton: false }); cargarDatos(); }
            else Swal.fire({ title: 'Error', text: d.message, icon: 'error', confirmButtonColor: '#721538' });
        });
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
</script>
</body>
</html>