<?php
require_once 'session_check.php';
require_once 'config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. SEGURIDAD DE SESIÓN
if (!isset($_SESSION['usuario'])) {
    header("Location: index.php");
    exit();
}

$conn = get_db_connection();

// --- AJAX ENDPOINT: BUSCAR BIENES ---
if (isset($_GET['buscar_bienes'])) {
    ob_clean();
    header('Content-Type: application/json');
    $q = isset($_GET['q']) ? $conn->real_escape_string($_GET['q']) : '';
    
    $where = "WHERE 1=1";
    $furniture_types = "'Silla', 'Escritorio', 'Mueble', 'Archivero', 'Silla de oficina', 'Escritorio de oficina'";
    $where .= " AND tbi.nombre_tipo NOT IN ($furniture_types)";
    $where .= " AND tbi.nombre_tipo NOT LIKE '%Impresora%'";
    
    if (!empty($q)) {
        $where .= " AND (inv.num_inventario LIKE '%$q%' OR inv.no_bien_mueble LIKE '%$q%' OR inv.marca LIKE '%$q%' OR inv.modelo LIKE '%$q%' OR inv.num_serie LIKE '%$q%' OR inv.descripcion LIKE '%$q%' OR tbi.nombre_tipo LIKE '%$q%')";
    }
    
    $sql = "SELECT inv.id, inv.num_inventario, inv.no_bien_mueble, tbi.nombre_tipo as nombre_tipo, inv.marca, inv.modelo, inv.num_serie, inv.personal_asignado 
            FROM inventario_soporte inv
            LEFT JOIN tipo_bien_inventario tbi ON inv.id_tipo_bien = tbi.id_tipo
            $where 
            ORDER BY inv.id DESC LIMIT 15";
            
    $res = $conn->query($sql);
    $bienes = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $bienes[] = $row;
        }
    }
    echo json_encode($bienes);
    $conn->close();
    exit;
}

// --- AJAX ENDPOINT: ACTUALIZAR PROPIETARIO ---
if (isset($_POST['actualizar_propietario'])) {
    ob_clean();
    header('Content-Type: application/json');
    
    $ids = isset($_POST['ids']) ? $_POST['ids'] : [];
    $nuevo_propietario = isset($_POST['nuevo_propietario']) ? trim($_POST['nuevo_propietario']) : '';
    
    if (empty($ids) || empty($nuevo_propietario)) {
        echo json_encode(['status' => 'error', 'message' => 'Parámetros incompletos.']);
        $conn->close();
        exit;
    }
    
    $ids_clean = array_filter(array_map('intval', $ids));
    if (empty($ids_clean)) {
        echo json_encode(['status' => 'error', 'message' => 'IDs no válidos.']);
        $conn->close();
        exit;
    }
    
    $in_clause = implode(',', $ids_clean);
    
    // Validar que ninguno de los equipos esté de baja
    $sql_check = "SELECT COUNT(*) as total FROM inventario_soporte WHERE id IN ($in_clause) AND estatus = 'Para Baja'";
    $res_check = $conn->query($sql_check);
    if ($res_check && $res_check->fetch_assoc()['total'] > 0) {
        echo json_encode(['status' => 'error', 'message' => 'No se pueden transferir o prestar equipos que están dados de baja.']);
        $conn->close();
        exit;
    }
    
    $nuevo_prop_escaped = $conn->real_escape_string($nuevo_propietario);
    
    $sql_update = "UPDATE inventario_soporte SET personal_asignado = '$nuevo_prop_escaped' WHERE id IN ($in_clause)";
    if ($conn->query($sql_update)) {
        echo json_encode(['status' => 'success', 'message' => 'Propietario actualizado correctamente.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Error al actualizar base de datos: ' . $conn->error]);
    }
    
    $conn->close();
    exit;
}

// 2. RECIBIR Y VALIDAR IDs DE LOS EQUIPOS
$equipos_ids_raw = $_GET['equipos'] ?? '';
$ids_array = array_filter(array_map('intval', explode(',', $equipos_ids_raw)));

if (empty($ids_array)) {
    die("<div style='text-align:center; padding: 50px; font-family: sans-serif;'>
            <h2>Error: No se seleccionaron equipos válidos.</h2>
            <a href='consultar_inventario.php'>Regresar al inventario</a>
         </div>");
}

// 3. CONSULTAR LA INFORMACIÓN DE LOS EQUIPOS SELECCIONADOS
$in_clause = implode(',', $ids_array);
$sql = "SELECT inv.id, inv.num_inventario, inv.no_bien_mueble, tbi.nombre_tipo, inv.marca, inv.modelo, inv.num_serie, inv.personal_asignado 
        FROM inventario_soporte inv 
        LEFT JOIN tipo_bien_inventario tbi ON inv.id_tipo_bien = tbi.id_tipo 
        WHERE inv.id IN ($in_clause)";

$result = $conn->query($sql);
$equipos = [];
$entregaDefault = "";
while ($row = $result->fetch_assoc()) {
    $equipos[] = $row;
    if (empty($entregaDefault) && !empty($row['personal_asignado']) && strtoupper($row['personal_asignado']) !== 'STOCK') {
        $entregaDefault = $row['personal_asignado'];
    }
}

if (empty($entregaDefault)) {
    $entregaDefault = "Nombre del Responsable - Área de Sistemas SATQ";
}

if (empty($equipos)) {
    die("Error: No se encontraron los registros en la base de datos.");
}
include 'header.php';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Generar Traspaso Oficial</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="js/tailwindcss.js"></script>
    <link rel="stylesheet" href="css/all.min.css">
    <script src="js/sweetalert2.all.min.js"></script>
    <script src="js/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>
    
    <!-- Fuentes Montserrat para jsPDF -->
    <script src="js/Montserrat-normal.js"></script>
    <script src="js/Montserrat-bold.js"></script>
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
        
        let equiposSeleccionados = <?php echo json_encode($equipos); ?>;
    </script>
    <style>
        body { background-color: #d6d1ca; font-family: 'Segoe UI', sans-serif; }
    </style>
</head>
<body class="p-4 sm:p-8">

<div class="max-w-6xl mx-auto">
    <div class="flex flex-col sm:flex-row justify-between items-center mb-6">
        <h2 class="text-3xl font-bold text-primary-dark flex items-center gap-2 mb-4 sm:mb-0">
            <i class="fas fa-file-word"></i> Formato de Traspaso (Oficio)
        </h2>
        <a href="consultar_inventario.php" class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-6 rounded-full shadow transition flex items-center">
            <i class="fas fa-arrow-left mr-2"></i> Regresar
        </a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        
        <div class="lg:col-span-4 bg-white p-6 rounded-xl shadow-lg border-t-4 border-primary-dark">
            <h3 class="text-lg font-bold text-gray-800 mb-4 border-b pb-2">Datos del Oficio</h3>
            
            <form id="formDocumento" class="space-y-4">
                
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">No. de Oficio</label>
                    <input type="text" id="num_oficio" class="w-full p-2 border border-gray-300 rounded focus:outline-none focus:border-primary-dark" placeholder="Ej. SATQ/0358/2026" required>
                </div>

                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">Fecha</label>
                    <input type="date" id="fecha" class="w-full p-2 border border-gray-300 rounded focus:outline-none focus:border-primary-dark" required value="<?php echo date('Y-m-d'); ?>">
                </div>

                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">Asunto / Tipo de Movimiento</label>
                    <input type="text" id="asunto" class="w-full p-2 border border-gray-300 rounded focus:outline-none focus:border-primary-dark" value="Traspaso" required>
                </div>

                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">Unidad / Área Destino</label>
                    <input type="text" id="area" class="w-full p-2 border border-gray-300 rounded focus:outline-none focus:border-primary-dark" placeholder="Ej. Unidad de Parque Vehicular" required>
                </div>

                <div class="relative">
                    <label class="block text-sm font-bold text-gray-700 mb-1">Recibe (Nombre - Cargo)</label>
                    <input type="text" id="recibe" class="w-full p-2 border border-gray-300 rounded focus:outline-none focus:border-primary-dark" placeholder="Ej. C. Miguel Ángel Espinosa Lozada - Jefe de Unidad..." autocomplete="off" required>
                    <div id="lista_sugerencias_recibe" class="absolute w-full bg-white border border-gray-300 rounded-b-md shadow-lg z-10 hidden max-h-48 overflow-y-auto"></div>
                </div>

                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">Entrega (Nombre - Puesto)</label>
                    <input type="text" id="entrega" class="w-full p-2 border border-gray-300 rounded focus:outline-none focus:border-primary-dark" value="<?php echo htmlspecialchars($entregaDefault); ?>" required>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">Realizó (Iniciales)</label>
                        <input type="text" id="realizo" class="w-full p-2 border border-gray-300 rounded focus:outline-none focus:border-primary-dark" placeholder="Ej. ACC">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">Revisó (Iniciales)</label>
                        <input type="text" id="reviso" class="w-full p-2 border border-gray-300 rounded focus:outline-none focus:border-primary-dark" placeholder="Ej. EFA">
                    </div>
                </div>

                <button type="button" onclick="iniciarGenerarTraspaso()" class="w-full bg-indigo-700 hover:bg-indigo-800 text-white font-bold py-3 px-4 rounded-lg shadow transition mt-6 flex justify-center items-center gap-2">
                    <i class="fas fa-file-pdf"></i> Generar Oficio PDF
                </button>
            </form>
        </div>

        <div class="lg:col-span-8 bg-white p-6 rounded-xl shadow-lg">
            <h3 class="text-lg font-bold text-gray-800 mb-4 border-b pb-2">Equipos a Traspasar (<?php echo count($equipos); ?>)</h3>
            
            <!-- BUSCADOR DE BIENES EXISTENTES -->
            <div class="mb-6 relative">
                <label class="block text-sm font-bold text-gray-700 mb-1"><i class="fas fa-search mr-1 text-primary-dark"></i> Buscar y agregar otros bienes al traspaso</label>
                <div class="flex gap-2">
                    <input type="text" id="buscar_bienes_input" class="w-full p-2.5 border border-gray-300 rounded focus:outline-none focus:border-primary-dark text-sm" placeholder="Buscar por No. Inventario, No. Mueble, Serie, Marca o Modelo..." autocomplete="off">
                </div>
                <div id="lista_sugerencias_bienes" class="absolute w-full bg-white border border-gray-300 rounded-b-md shadow-lg z-10 hidden max-h-60 overflow-y-auto mt-1"></div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 border">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-bold text-gray-600 uppercase">No. Bien Mueble</th>
                            <th class="px-4 py-3 text-left text-xs font-bold text-gray-600 uppercase">Descripción</th>
                            <th class="px-4 py-3 text-left text-xs font-bold text-gray-600 uppercase">Modelo</th>
                            <th class="px-4 py-3 text-left text-xs font-bold text-gray-600 uppercase">Marca</th>
                            <th class="px-4 py-3 text-left text-xs font-bold text-gray-600 uppercase">Serie</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach($equipos as $eq): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-sm font-bold text-primary-dark"><?php echo htmlspecialchars($eq['no_bien_mueble'] ?? 'S/N'); ?></td>
                            <td class="px-4 py-3 text-sm text-gray-600 uppercase"><?php echo htmlspecialchars($eq['nombre_tipo'] ?? 'EQUIPO DE CÓMPUTO'); ?></td>
                            <td class="px-4 py-3 text-sm text-gray-800"><?php echo htmlspecialchars($eq['modelo']); ?></td>
                            <td class="px-4 py-3 text-sm text-gray-800"><?php echo htmlspecialchars($eq['marca']); ?></td>
                            <td class="px-4 py-3 text-sm text-gray-500 font-mono"><?php echo htmlspecialchars($eq['num_serie']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<script>
    // Función para convertir fecha 'YYYY-MM-DD' a formato largo '16 de abril de 2026'
    function formatearFechaLarga(fechaIso) {
        const meses = ["enero", "febrero", "marzo", "abril", "mayo", "junio", "julio", "agosto", "septiembre", "octubre", "noviembre", "diciembre"];
        const partes = fechaIso.split('-');
        if(partes.length !== 3) return fechaIso;
        
        const dia = parseInt(partes[2], 10);
        const mes = meses[parseInt(partes[1], 10) - 1];
        const anio = partes[0];
        
        return `${dia} de ${mes} de ${anio}`;
    }

    function generarDocumento() {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF('portrait', 'mm', 'letter');
    
    // Dimensiones de hoja carta en mm
    const pageWidth = 215.9;
    const pageHeight = 279.4;
    const margenIzquierdo = 30; 
    const margenDerecho = 25;
    const anchoUtil = pageWidth - margenIzquierdo - margenDerecho;

    const imgFondo = new Image();
    imgFondo.src = 'img/logo_izquierdo.png';

    imgFondo.onload = function() {
        doc.addImage(imgFondo, 'PNG', 0, 0, pageWidth, pageHeight);

        // 2. CABECERA
        let y = 45; 
        doc.setFontSize(10);
        doc.setFont("Montserrat", "normal");
        
        const fechaTexto = `Quintana Roo, a ${formatearFechaLarga(document.getElementById('fecha').value)}`;
        const numOficio = `No. de Oficio: ${document.getElementById('num_oficio').value}`;
        const asunto = `Asunto: ${document.getElementById('asunto').value}.`;

        doc.text(fechaTexto, pageWidth - margenDerecho, y, { align: 'right' }); y += 5;
        doc.text(numOficio, pageWidth - margenDerecho, y, { align: 'right' }); y += 5;
        doc.setFont("Montserrat", "bold");
        doc.text(asunto, pageWidth - margenDerecho, y, { align: 'right' }); y += 15;

        // 3. DESTINATARIO
        doc.setFontSize(10);
        const destinatario = document.getElementById('recibe').value.split('-');
        doc.text(destinatario[0].trim().toUpperCase(), margenIzquierdo, y); y += 5;
        if(destinatario[1]) {
            const lineasPuesto = doc.splitTextToSize(destinatario[1].trim().toUpperCase(), anchoUtil);
            doc.text(lineasPuesto, margenIzquierdo, y);
            y += (lineasPuesto.length * 5);
        }
        doc.text("P R E S E N T E .", margenIzquierdo, y); y += 15;

        // 4. CUERPO DEL TEXTO (JUSTIFICADO)
        doc.setFont("Montserrat", "normal");
        const areaDestino = document.getElementById('area').value;
        const textoCuerpo = `A través de la presente me dirijo a Usted, para enviarle un cordial saludo, asimismo solicito gire sus respetables instrucciones a quien corresponda, para que se lleve a cabo EL TRASPASO DE EQUIPO DE CÓMPUTO, que se encuentra bajo el resguardo del SATQ y sea asignado a la ${areaDestino}, mismos que a continuación se describen:`;

        const lineasCuerpo = doc.splitTextToSize(textoCuerpo, anchoUtil);

        // IMPORTANTE: Para justificar, el primer parámetro debe ser el margen izquierdo
        // y el cuarto parámetro debe incluir maxWidth
        doc.text(lineasCuerpo, margenIzquierdo, y, { 
            align: 'justify', 
            maxWidth: anchoUtil 
        });

        y += (lineasCuerpo.length * 5) + 10;

        // 5. TABLA DE EQUIPOS
        doc.autoTable({
            startY: y,
            margin: { left: margenIzquierdo, right: margenDerecho },
            head: [['NO. BIEN MUEBLE', 'DESCRIPCIÓN', 'MODELO', 'MARCA', 'SERIE']],
            body: equiposSeleccionados.map(eq => [
                eq.no_bien_mueble || 'S/N',
                (eq.nombre_tipo || 'EQUIPO').toUpperCase(),
                eq.modelo,
                eq.marca,
                eq.num_serie
            ]),
            theme: 'grid',
            styles: { fontSize: 8, halign: 'center', font: 'Montserrat' },
            headStyles: { fillColor: [114, 21, 56] },
            didDrawPage: function (data) {
                // Si la tabla es muy larga y crea una página nueva, volvemos a pintar el fondo
                if (data.pageNumber > 1) {
                    doc.addImage(imgFondo, 'PNG', 0, 0, pageWidth, pageHeight);
                }
            }
        });
        
        // --- 6. DESPEDIDA Y FIRMAS ---
        let finalY = doc.lastAutoTable.finalY + 15;

        // Si no hay suficiente espacio para la despedida y las firmas (aprox 80mm), creamos nueva página
        if (finalY > pageHeight - 80) {
            doc.addPage();
            doc.addImage(imgFondo, 'PNG', 0, 0, pageWidth, pageHeight);
            finalY = 45; 
        }
        y = finalY;

        doc.setFont("Montserrat", "normal");
        doc.setFontSize(10);

        const textoDespedida = "Sin más por el momento, me despido agradeciendo su amable atención. Para cualquier duda o aclaración, por favor comunicarse a las extensiones 10017 o 10011.";

        // Dividimos el texto para que respete el ancho útil
        const lineasDespedida = doc.splitTextToSize(textoDespedida, anchoUtil);

        // JUSTIFICADO: Usamos margenIzquierdo y maxWidth
        doc.text(lineasDespedida, margenIzquierdo, y, { 
            align: 'justify', 
            maxWidth: anchoUtil 
        });

        // Calculamos el nuevo 'y' basado en las líneas escritas
        y += (lineasDespedida.length * 5) + 15;

        // --- SECCIÓN DE ATENTAMENTE ---
        doc.setFont("Montserrat", "bold");
        doc.text("A t e n t a m e n t e", pageWidth / 2, y, { align: "center" }); 

        y += 20; // Espacio para la firma física
        // Etiquetas de firmas
        doc.text("ENTREGA", (pageWidth / 4), y, { align: "center" });
        doc.text("RECIBE", (pageWidth / 4) * 3, y, { align: "center" });
        
        // NOMBRES DE FIRMAS
        y += 6;
        doc.setFont("Montserrat", "normal");
        doc.setFontSize(9);
        
        // Extraemos solo el nombre (antes del guion) de los inputs
        const nombreEntrega = document.getElementById('entrega').value.split('-')[0].trim();
        const nombreRecibe = document.getElementById('recibe').value.split('-')[0].trim();
        
        
        doc.text(nombreEntrega.toUpperCase(), (pageWidth / 4), y, { align: "center" });
        doc.text(nombreRecibe.toUpperCase(), (pageWidth / 4) * 3, y, { align: "center" });
        
        // 7. PIE DE PÁGINA
        doc.setFontSize(7);
        const pieY = pageHeight - 25;
        doc.text("C.c.p. Archivo", margenIzquierdo, pieY);
        doc.text(`Realizó: ${document.getElementById('realizo').value} / Revisó: ${document.getElementById('reviso').value}`, margenIzquierdo, pieY + 4);

        doc.save(`Traspaso_${document.getElementById('num_oficio').value.replace(/\//g, '_')}.pdf`);
    };

    imgFondo.onerror = function() {
        alert("No se pudo cargar la imagen de fondo.");
    };
}

    // --- RENDERIZAR TABLA DE EQUIPOS DINÁMICAMENTE ---
    function renderTablaEquipos() {
        const tbody = document.querySelector('tbody');
        tbody.innerHTML = '';
        
        if (equiposSeleccionados.length === 0) {
            tbody.innerHTML = `<tr><td colspan="5" class="p-8 text-center text-gray-400">No hay equipos seleccionados para traspaso.</td></tr>`;
            return;
        }
        
        equiposSeleccionados.forEach((eq, idx) => {
            const tr = document.createElement('tr');
            tr.className = 'hover:bg-gray-50';
            tr.innerHTML = `
                <td class="px-4 py-3 text-sm font-bold text-primary-dark">${escapeHtml(eq.no_bien_mueble || 'S/N')}</td>
                <td class="px-4 py-3 text-sm text-gray-600 uppercase">${escapeHtml(eq.nombre_tipo || 'EQUIPO DE CÓMPUTO')}</td>
                <td class="px-4 py-3 text-sm text-gray-800">${escapeHtml(eq.modelo || '')}</td>
                <td class="px-4 py-3 text-sm text-gray-800">${escapeHtml(eq.marca || '')}</td>
                <td class="px-4 py-3 text-sm text-gray-500 font-mono flex justify-between items-center">
                    <span>${escapeHtml(eq.num_serie || '')}</span>
                    <button type="button" onclick="quitarEquipo(${idx})" class="text-red-600 hover:text-red-800 ml-2" title="Quitar del traspaso">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                </td>
            `;
            tbody.appendChild(tr);
        });
        
        // Actualizar contador
        const counterHeader = document.querySelector('h3.text-lg');
        if (counterHeader) {
            counterHeader.innerHTML = `Equipos a Traspasar (${equiposSeleccionados.length})`;
        }
    }
    
    function escapeHtml(str) {
        if (!str) return '';
        return str.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
    }
    
    window.quitarEquipo = function(idx) {
        equiposSeleccionados.splice(idx, 1);
        renderTablaEquipos();
    };

    // --- AUTOCOMPLETADO INTELIGENTE PARA EL USUARIO QUE RECIBE ---
    let usuariosGlobal = [];
    const inputRecibe = document.getElementById('recibe');
    const cajaSugerencias = document.getElementById('lista_sugerencias_recibe');
    const inputArea = document.getElementById('area');

    document.addEventListener('DOMContentLoaded', () => {
        // Cargar usuarios al cargar la página
        fetch('consultar_usuarios.php?ajax_pdf=1')
            .then(res => res.json())
            .then(data => { 
                usuariosGlobal = data; 
            });
            
        // Renderizado inicial de la tabla
        renderTablaEquipos();
    });

    function mostrarSugerenciasRecibe(texto) {
        cajaSugerencias.innerHTML = '';
        const textoNorm = texto.toLowerCase().trim();
        
        const coincidencias = usuariosGlobal.filter(u => {
            if (textoNorm === '') return true;
            return (u.nombre_completo && u.nombre_completo.toLowerCase().includes(textoNorm)) ||
                   (u.cargo && u.cargo.toLowerCase().includes(textoNorm)) ||
                   (u.nombre_direccion && u.nombre_direccion.toLowerCase().includes(textoNorm)) ||
                   (u.nombre_natural && u.nombre_natural.toLowerCase().includes(textoNorm));
        });

        if (coincidencias.length > 0) {
            cajaSugerencias.classList.remove('hidden');
            coincidencias.slice(0, 30).forEach(c => {
                const div = document.createElement('div');
                div.className = 'p-3 border-b hover:bg-indigo-50 cursor-pointer text-sm transition-colors';
                div.innerHTML = `<div class="font-bold text-primary-dark">${c.nombre_completo}</div><div class="text-xs text-gray-600">${c.cargo || 'Sin cargo'} | ${c.nombre_direccion || 'Sin área'}</div>`;
                div.addEventListener('click', function() {
                    inputRecibe.value = `${c.nombre_completo} - ${c.cargo || 'Sin cargo'}`;
                    inputRecibe.dataset.nombreNatural = c.nombre_natural || c.nombre_completo;
                    if (c.nombre_direccion) inputArea.value = c.nombre_direccion;
                    cajaSugerencias.classList.add('hidden'); 
                });
                cajaSugerencias.appendChild(div);
            });
        } else { 
            cajaSugerencias.classList.add('hidden'); 
        }
    }

    inputRecibe.addEventListener('focus', function() {
        mostrarSugerenciasRecibe(this.value);
    });

    inputRecibe.addEventListener('input', function() {
        mostrarSugerenciasRecibe(this.value);
    });

    document.addEventListener('click', function(e) {
        if (e.target !== inputRecibe && !cajaSugerencias.contains(e.target)) {
            cajaSugerencias.classList.add('hidden');
        }
    });

    // --- BUSCADOR Y AUTOAGREGADO DE BIENES EXISTENTES ---
    const inputBienes = document.getElementById('buscar_bienes_input');
    const cajaBienes = document.getElementById('lista_sugerencias_bienes');
    
    inputBienes.addEventListener('input', function() {
        const texto = this.value.trim();
        cajaBienes.innerHTML = '';
        
        if (texto.length < 2) { cajaBienes.classList.add('hidden'); return; }
        
        fetch(`generar_traspaso.php?buscar_bienes=1&q=${encodeURIComponent(texto)}`)
            .then(res => res.json())
            .then(data => {
                if (data.length > 0) {
                    cajaBienes.classList.remove('hidden');
                    data.forEach(item => {
                        const div = document.createElement('div');
                        div.className = 'p-3 border-b hover:bg-indigo-50 cursor-pointer text-sm transition-colors';
                        div.innerHTML = `
                            <div class="font-bold text-primary-dark">${item.nombre_tipo || 'Equipo'} - ${item.marca || ''} ${item.modelo || ''}</div>
                            <div class="text-xs text-gray-600">No. Mueble: ${item.no_bien_mueble || 'S/N'} | Serie: ${item.num_serie || 'S/S'} | Actual: ${item.personal_asignado || 'STOCK'}</div>
                        `;
                        div.addEventListener('click', function() {
                            // Check if already selected
                            const existe = equiposSeleccionados.some(e => e.id === item.id);
                            if (existe) {
                                Swal.fire({
                                    icon: 'info',
                                    title: 'Equipo ya agregado',
                                    text: 'Este equipo ya está en la lista de traspaso.',
                                    confirmButtonColor: '#721538'
                                });
                            } else {
                                equiposSeleccionados.push(item);
                                renderTablaEquipos();
                            }
                            inputBienes.value = '';
                            cajaBienes.classList.add('hidden');
                        });
                        cajaBienes.appendChild(div);
                    });
                } else {
                    cajaBienes.classList.add('hidden');
                }
            });
    });
    
    document.addEventListener('click', function(e) {
        if (e.target !== inputBienes && !cajaBienes.contains(e.target)) {
            cajaBienes.classList.add('hidden');
        }
    });

    // --- ACCIÓN: INICIAR GENERACIÓN Y GUARDADO ---
    window.iniciarGenerarTraspaso = function() {
        const form = document.getElementById('formDocumento');
        if (!form.reportValidity()) {
            return;
        }

        const recibeVal = inputRecibe.value;
        const nombreRecibeNatural = inputRecibe.dataset.nombreNatural || recibeVal.split('-')[0].trim();
        
        if (!nombreRecibeNatural) {
            Swal.fire({
                icon: 'warning',
                title: 'Nombre Inválido',
                text: 'Por favor selecciona o ingresa el nombre de la persona que recibe.',
                confirmButtonColor: '#721538'
            });
            return;
        }

        if (equiposSeleccionados.length === 0) {
            Swal.fire({
                icon: 'warning',
                title: 'Sin Equipos',
                text: 'Debes tener al menos un equipo en la lista para traspasar.',
                confirmButtonColor: '#721538'
            });
            return;
        }

        const ids = equiposSeleccionados.map(eq => eq.id);

        Swal.fire({
            title: '¿Generar Traspaso?',
            text: `Se actualizará el propietario en la base de datos a "${nombreRecibeNatural}" para los ${equiposSeleccionados.length} equipos seleccionados y se generará el oficio PDF.`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#721538',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Sí, generar y guardar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                // Mostrar cargando
                Swal.fire({
                    title: 'Procesando Traspaso...',
                    text: 'Actualizando base de datos y generando PDF.',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                const formData = new FormData();
                formData.append('actualizar_propietario', '1');
                formData.append('nuevo_propietario', nombreRecibeNatural);
                ids.forEach(id => formData.append('ids[]', id));

                fetch('generar_traspaso.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        // Generar PDF
                        generarDocumento();
                        
                        Swal.fire({
                            icon: 'success',
                            title: '¡Traspaso Completado!',
                            text: 'El propietario se ha actualizado en la base de datos y se ha descargado el oficio PDF.',
                            confirmButtonColor: '#721538',
                            confirmButtonText: 'Aceptar'
                        }).then(() => {
                            window.location.href = 'consultar_inventario.php';
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error de Actualización',
                            text: data.message || 'Ocurrió un error al actualizar el propietario en la base de datos.',
                            confirmButtonColor: '#721538'
                        });
                    }
                })
                .catch(err => {
                    console.error(err);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error de Red',
                        text: 'No se pudo comunicar con el servidor para guardar el traspaso.',
                        confirmButtonColor: '#721538'
                    });
                });
            }
        });
    };
</script>

</body>
</html>