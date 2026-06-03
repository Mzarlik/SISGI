<?php
require_once 'session_check.php';
require_once 'config.php';
session_start();

if (!isset($_SESSION['usuario'])) {
    header("Location: index.php");
    exit();
}

if ($_SESSION['rol'] === 'redes') {
    // Si es redes, lo regresamos al dashboard con un mensaje de error opcional
    header("Location: dashboard.php?error=acceso_denegado");
    exit();
}

$conn = get_db_connection();
if (!$conn) { die("Conexión fallida."); }

$areas = [];
$res_areas = $conn->query("SELECT * FROM areas_dntics ORDER BY nombre_area ASC");
if ($res_areas) {
    while ($row = $res_areas->fetch_assoc()) { $areas[] = $row; }
}

if (isset($_GET['ajax_equipos'])) {
    header('Content-Type: application/json');
    $search = $_GET['q'] ?? '';
    $sql = "SELECT * FROM Adq_equipos WHERE tipo LIKE ? OR marca LIKE ? OR modelo LIKE ? OR detalles LIKE ? ORDER BY id_adquisicion DESC";
    $stmt = $conn->prepare($sql);
    $like = "%$search%";
    $stmt->bind_param("ssss", $like, $like, $like, $like);
    $stmt->execute();
    $result = $stmt->get_result();
    $datos = [];
    while($row = $result->fetch_assoc()) { $datos[] = $row; }
    echo json_encode($datos);
    exit;
}
include 'header.php';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Solicitud Multiequipo | SISGI</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="js/tailwindcss.js"></script>
    <script src="js/sweetalert2.all.min.js"></script>
    <link rel="stylesheet" href="css/all.min.css">
    <script src="js/jspdf.umd.min.js"></script>
    <script src="js/jspdf.plugin.autotable.min.js"></script> <script src="js/xlsx.full.min.js"></script> <script>
        tailwind.config = {
            theme: { extend: { colors: { 'primary-dark': '#721538', 'primary-light': '#961e4b', 'background': '#d6d1ca' } } }
        }
    </script>
    <script>
        tailwind.config = {
            theme: { extend: { colors: { 'primary-dark': '#721538', 'primary-light': '#961e4b', 'background': '#d6d1ca' } } }
        }
    </script>
</head>
<body class="bg-background min-h-screen p-4 sm:p-8">

    <div class="max-w-6xl mx-auto">
        <div class="flex flex-col sm:flex-row justify-between items-center mb-8">
            <h1 class="text-3xl font-bold text-primary-dark flex items-center gap-2">
                <i class="fas fa-list-check"></i> Nueva Solicitud
            </h1>
            <div class="flex gap-2 mt-4 sm:mt-0">
                <a href="dashboard.php" class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-6 rounded-full shadow transition">Menú</a>
                <button onclick="location.reload()" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-6 rounded-full shadow transition">Limpiar Todo</button>
            </div>
        </div>

        <div class="bg-white p-6 rounded-2xl shadow-xl mb-6 grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label class="block text-sm font-bold text-primary-dark mb-2">1. Área Destino:</label>
<select id="selectArea" class="w-full p-3 border border-gray-300 rounded-xl outline-none focus:ring-2 focus:ring-primary-dark">
                <option value="">-- Seleccione un área --</option>
        <?php foreach($areas as $a): ?>
            <option value="<?= $a['nombre_area'] ?>" data-representante="<?= $a['Representantes_areas'] ?>">
                <?= $a['nombre_area'] ?>
            </option>
        <?php endforeach; ?>
</select>
            </div>
            <div>
                <label class="block text-sm font-bold text-primary-dark mb-2">2. Tipo de Documento:</label>
                <select id="selectDoc" onchange="toggleEquipos()" class="w-full p-3 border border-gray-300 rounded-xl outline-none focus:ring-2 focus:ring-primary-dark">
                    <option value="">-- Seleccione tipo --</option>
                    <option value="Solicitud de equipamiento informatico">Solicitud de equipamiento y suministros informático</option>
                    <option value="Reporte de solicitud">Reporte de solicitud</option>
                    <option value="Prediales">Prediales</option>
                </select>
            </div>
        </div>

        <div id="seccionEquipos" style="display:none;" class="bg-white p-6 rounded-2xl shadow-xl animate-fade-in mb-6">
            <div class="flex flex-col md:flex-row justify-between items-center mb-4 gap-4">
                <h2 class="text-xl font-bold text-gray-700">3. Buscar y Agregar Equipos</h2>
                <input type="text" id="busquedaEquipo" oninput="buscarEquipos()" placeholder="Buscar equipo..." class="p-2 border rounded-lg w-full md:w-80">
            </div>
            <div class="overflow-x-auto border rounded-xl max-h-80 overflow-y-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50 sticky top-0">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-bold text-primary-dark uppercase">Equipo</th>
                            <th class="px-4 py-3 text-left text-xs font-bold text-primary-dark uppercase">Especificaciones</th>
                            <th class="px-4 py-3 text-center text-xs font-bold text-primary-dark uppercase">Cantidad</th>
                            <th class="px-4 py-3 text-center text-xs font-bold text-primary-dark uppercase">Acción</th>
                        </tr>
                    </thead>
                    <tbody id="listaEquipos" class="divide-y divide-gray-100"></tbody>
                </table>
            </div>
        </div>

        <div id="resumenContenedor" style="display:none;" class="bg-white p-6 rounded-2xl shadow-xl border-t-4 border-primary-dark">
            <h3 class="text-xl font-bold text-primary-dark mb-4 flex items-center gap-2">
                <i class="fas fa-clipboard-list"></i> Equipos en la Solicitud
            </h3>
            
            <div class="overflow-x-auto">
                <table class="min-w-full border">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="p-2 text-left text-xs font-bold">Cant.</th>
                            <th class="p-2 text-left text-xs font-bold">Descripción</th>
                            <th class="p-2 text-left text-xs font-bold">Detalles Técnicos</th>
                            <th class="p-2 text-center text-xs font-bold">Quitar</th>
                        </tr>
                    </thead>
                    <tbody id="tablaResumen">
                        </tbody>
                </table>
            </div>

            <div class="flex flex-wrap gap-3 mt-8 justify-center">
                <button onclick="exportarExcel()" class="bg-green-700 hover:bg-green-800 text-white font-bold py-3 px-8 rounded-xl shadow transition flex items-center">
                    <i class="fas fa-file-excel mr-2"></i> Excel
                </button>
                <button onclick="exportarPDF()" class="bg-red-700 hover:bg-red-800 text-white font-bold py-3 px-8 rounded-xl shadow transition flex items-center">
                    <i class="fas fa-file-pdf mr-2"></i> PDF con Firmas
                </button>
            </div>
        </div>
    </div>

    <script>
        // ESTA ES LA LISTA DONDE SE IRÁN GUARDANDO LOS EQUIPOS
        let carritoEquipos = [];

        function toggleEquipos() {
            const seccion = document.getElementById('seccionEquipos');
            seccion.style.display = (document.getElementById('selectDoc').value !== "") ? 'block' : 'none';
            buscarEquipos();
        }

 function buscarEquipos() {
    const q = document.getElementById('busquedaEquipo').value;
    fetch(`solicitud_equipos.php?ajax_equipos=1&q=${encodeURIComponent(q)}`)
        .then(res => res.json())
        .then(data => {
            const tbody = document.getElementById('listaEquipos');
            tbody.innerHTML = '';
            data.forEach(item => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td class="px-4 py-3 text-sm font-bold">
                        ${item.tipo}<br>
                        <span class="text-xs font-normal text-gray-500">${item.marca} - ${item.modelo}</span>
                    </td>
                    <td class="px-4 py-3 text-xs text-gray-500">
                        <div><strong>RAM/Disco:</strong> ${item.memoria_ram || 'N/A'} / ${item.capacidad_almacenamiento || 'N/A'}</div>
                        <div class="mt-1 italic text-primary-dark">
                            <strong>Detalles:</strong> ${item.detalles || 'Sin detalles adicionales'}
                        </div>
                    </td>
                    <td class="px-4 py-3 text-center">
                        <input type="number" id="qty_${item.id_adquisicion}" value="1" min="1" class="w-14 border rounded text-center p-1">
                    </td>
                    <td class="px-4 py-3 text-center">
                        <button onclick='agregarALista(${JSON.stringify(item)})' class="bg-primary-dark text-white px-3 py-1 rounded-lg text-xs hover:bg-primary-light">
                            Añadir
                        </button>
                    </td>
                `;
                tbody.appendChild(tr);
            });
        });
}

        function agregarALista(item) {
            const cantidad = parseInt(document.getElementById(`qty_${item.id_adquisicion}`).value);
            
            // Añadir al arreglo (puedes agregar lógica para sumar si el ID ya existe)
            carritoEquipos.push({
                ...item,
                cantidad_solicitada: cantidad
            });

            actualizarTablaResumen();
            
            Swal.fire({
                title: 'Agregado',
                text: `${item.tipo} añadido a la lista`,
                icon: 'success',
                timer: 700,
                showConfirmButton: false
            });
        }

function actualizarTablaResumen() {
    const contenedor = document.getElementById('resumenContenedor');
    const tbody = document.getElementById('tablaResumen');
    
    // Si el carrito está vacío, ocultamos la sección
    if (carritoEquipos.length === 0) {
        contenedor.style.display = 'none';
        return;
    }

    contenedor.style.display = 'block';
    tbody.innerHTML = '';

    // Recorremos el arreglo para enlistar los equipos seleccionados
    carritoEquipos.forEach((equipo, index) => {
        const tr = document.createElement('tr');
        tr.className = "border-b text-sm table-row-hover";
        tr.innerHTML = `
            <td class="p-2 font-bold text-center">${equipo.cantidad_solicitada}</td>
            <td class="p-2">
                <div class="font-bold">${equipo.tipo}</div>
                <div class="text-xs text-gray-500">${equipo.marca} ${equipo.modelo}</div>
            </td>
            <td class="p-2 text-xs text-gray-600">
                <div><strong>RAM/Disco:</strong> ${equipo.memoria_ram || 'N/A'} / ${equipo.capacidad_almacenamiento || 'N/A'}</div>
                <div class="italic text-primary-light"><strong>Detalles:</strong> ${equipo.detalles || 'Sin detalles'}</div>
            </td>
            <td class="p-2 text-center">
                <button onclick="quitarDeLista(${index})" class="text-red-600 hover:text-red-800 p-2 transition-colors">
                    <i class="fas fa-trash-can"></i>
                </button>
            </td>
        `;
        tbody.appendChild(tr);
    });
}

function quitarDeLista(index) {
    // Elimina el elemento del arreglo usando su posición
    carritoEquipos.splice(index, 1);
    // Refresca la tabla visualmente
    actualizarTablaResumen();
}

function validar() {
    const select = document.getElementById('selectArea');
    const area = select.value;
    // Obtenemos el texto del atributo data-representante de la opción seleccionada
    const representante = select.options[select.selectedIndex].getAttribute('data-representante');
    
    const doc = document.getElementById('selectDoc').value;

    if (!area) {
        Swal.fire('Atención', 'Por favor selecciona el Área Destino', 'warning');
        return null;
    }
    if (!doc) {
        Swal.fire('Atención', 'Selecciona el Tipo de Documento', 'warning');
        return null;
    }
    if (carritoEquipos.length === 0) {
        Swal.fire('Atención', 'Agrega al menos un equipo a la lista', 'warning');
        return null;
    }
    
    // Retornamos también el nombre del representante
    return { area, doc, representante };
}

       function exportarPDF() {
    const config = validar();
    if (!config) return;

    const { jsPDF } = window.jspdf;
    const doc = new jsPDF();
    
    // 1. Título y Encabezado
    doc.setTextColor(114, 21, 56);
    doc.setFontSize(14);
    doc.text("SOLICITUD DE EQUIPAMIENTO INFORMÁTICO", 105, 20, { align: 'center' });
    
    doc.setTextColor(0, 0, 0);
    doc.setFontSize(10);
    doc.text(`Área Solicitante: ${config.area}`, 20, 35);
    doc.text(`Tipo de Trámite: ${config.doc}`, 20, 41);
    doc.text(`Fecha: ${new Date().toLocaleDateString()}`, 20, 47);

    // 2. Tabla de Equipos
// 2. Tabla de Equipos y Limpieza de Texto
    const filas = carritoEquipos.map(e => {
        // Limpiamos el texto de espacios sin salto (NBSP) y saltos de línea raros que rompen el PDF
        let detallesLimpios = (e.detalles || 'N/A')
            .replace(/\u00A0/g, ' ') // Cambia espacios sin salto por espacios normales
            .replace(/[\r\n\t]+/g, ' ') // Quita tabulaciones y saltos de línea extraños
            .trim();

        return [
            e.cantidad_solicitada,
            `${e.tipo} (${e.marca} ${e.modelo})`,
            `Capítulo: ${e.capitulos || 'N/A'}\nRAM: ${e.memoria_ram || 'N/A'} | Disco: ${e.capacidad_almacenamiento || 'N/A'}\nDetalles: ${detallesLimpios}`
        ];
    });

    doc.autoTable({
        startY: 55,
        margin: { left: 15, right: 15 }, // Forzamos los márgenes laterales del documento
        tableWidth: 'auto', // Obliga a la tabla a no salirse de los márgenes
        head: [['Cant.', 'Descripción del Equipo', 'Especificaciones / Capítulos / Detalles']],
        body: filas,
        theme: 'grid',
        headStyles: { fillColor: [114, 21, 56] },
        styles: { fontSize: 8, cellPadding: 2, overflow: 'linebreak' },
        columnStyles: { 
            0: { cellWidth: 15, halign: 'center' }, 
            1: { cellWidth: 50 } 
            // Eliminamos la regla de la columna 2. Al no tener ancho fijo, 
            // tomará automáticamente todo el espacio restante disponible y respetará el salto de línea.
        }
    });

    // 3. BLOQUE DE FIRMAS (Estructura de la imagen)
    let finalY = doc.lastAutoTable.finalY + 35;
    
    // Evitar que las firmas se corten al final de la hoja
    if (finalY > 230) {
        doc.addPage();
        finalY = 35;
    }

    doc.setDrawColor(0, 0, 0); 
    doc.setLineWidth(0.4);
    doc.setFontSize(9);

    // --- FIRMA IZQUIERDA: REALIZÓ DIAGNÓSTICO (FIJO: MANUEL ALEJANDRO LOZANO REYES) ---
    doc.line(20, finalY, 90, finalY);
    doc.setFont(undefined, 'bold');
    doc.text("REALIZÓ DIAGNÓSTICO", 55, finalY + 5, { align: 'center' });
    doc.setFont(undefined, 'normal');
    doc.text("Manuel Alejandro Lozano Reyes", 55, finalY + 10, { align: 'center' });
    doc.text("Coordinador de Soporte Técnico", 55, finalY + 14, { align: 'center' });

    // --- FIRMA DERECHA: SOLICITA (DINÁMICO SEGÚN EL ÁREA) ---
    doc.line(120, finalY, 190, finalY);
    doc.setFont(undefined, 'bold');
    doc.text("SOLICITA", 155, finalY + 5, { align: 'center' });
    doc.setFont(undefined, 'normal');
    doc.text(config.representante || "NOMBRE NO ASIGNADO", 155, finalY + 10, { align: 'center' });
    doc.text(`Responsable de ${config.area}`, 155, finalY + 14, { align: 'center' });

    // --- FIRMA INFERIOR: AUTORIZA (FIJO: ALEJANDRO CAMBRANO) ---
    const bottomY = finalY + 35;
    doc.line(65, bottomY, 145, bottomY);
    doc.setFont(undefined, 'bold');
    doc.text("AUTORIZA", 105, bottomY + 5, { align: 'center' });
    doc.setFont(undefined, 'normal');
    doc.text("Ing. Alejandro Cambrano Uicab", 105, bottomY + 10, { align: 'center' });
    doc.text("Dirección de Nuevas Tecnologías de la", 105, bottomY + 14, { align: 'center' });
    doc.text("Información y Comunicaciones", 105, bottomY + 18, { align: 'center' });

    doc.save(`Solicitud_${config.area}.pdf`);
}

        function exportarExcel() {
    const config = validar();
    if (!config) return;

    // 1. Definición de encabezados y metadatos (Siguiendo la lógica del PDF)
    const rows = [
        ["SOLICITUD DE EQUIPAMIENTO Y SUMINISTROS INFORMÁTICOS"],
        [`Área Solicitante: ${config.area}`],
        [`Tipo de Trámite: ${config.doc}`],
        [`Fecha: ${new Date().toLocaleDateString()}`],
        [], // Fila en blanco
        ["CANT.", "DESCRIPCIÓN DEL EQUIPO", "ESPECIFICACIONES / CAPÍTULOS / DETALLES"] // Encabezados de tabla
    ];

    // 2. Mapeo de datos (Igual al de la función exportarPDF)
    carritoEquipos.forEach(e => {
        const descripcionEquipo = `${e.tipo} (${e.marca} ${e.modelo})`;
        
        // Combinamos las especificaciones en una sola cadena de texto para la celda de Excel
        const especificaciones = `Capítulo: ${e.capitulos || 'N/A'} | RAM: ${e.memoria_ram || 'N/A'} | Disco: ${e.capacidad_almacenamiento || 'N/A'} | Detalles: ${e.detalles || 'N/A'}`;

        rows.push([
            e.cantidad_solicitada, 
            descripcionEquipo, 
            especificaciones
        ]);
    });

    // 3. Generación del archivo
    const ws = XLSX.utils.aoa_to_sheet(rows);
    const wb = XLSX.utils.book_new();

    // Opcional: Ajustar el ancho de las columnas para que se lea mejor
    ws['!cols'] = [
        { wch: 10 }, // Cantidad
        { wch: 40 }, // Descripción
        { wch: 80 }  // Especificaciones (más ancha)
    ];

    XLSX.utils.book_append_sheet(wb, ws, "Solicitud");
    XLSX.writeFile(wb, `Solicitud_${config.area}.xlsx`);
}
    </script>
</body>
</html>
<?php $conn->close(); ?>