<?php
require_once 'session_check.php';
require_once 'config.php';
session_start();

// 1. SEGURIDAD DE SESIÓN
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}

$conn = get_db_connection();

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
$sql = "SELECT inv.num_inventario, inv.no_bien_mueble, tbi.nombre_tipo, inv.marca, inv.modelo, inv.num_serie, inv.personal_asignado 
        FROM inventario_soporte inv 
        LEFT JOIN tipo_bien_inventario tbi ON inv.id_tipo_bien = tbi.id_tipo 
        WHERE inv.id IN ($in_clause)";

$result = $conn->query($sql);
$equipos = [];
while ($row = $result->fetch_assoc()) {
    $equipos[] = $row;
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
        
        const equiposSeleccionados = <?php echo json_encode($equipos); ?>;
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
                    <input type="text" id="entrega" class="w-full p-2 border border-gray-300 rounded focus:outline-none focus:border-primary-dark" value="Nombre del Responsable - Área de Sistemas SATQ" required>
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

                <button type="button" onclick="generarDocumento()" class="w-full bg-indigo-700 hover:bg-indigo-800 text-white font-bold py-3 px-4 rounded-lg shadow transition mt-6 flex justify-center items-center gap-2">
                    <i class="fas fa-file-pdf"></i> Generar Oficio PDF
                </button>
            </form>
        </div>

        <div class="lg:col-span-8 bg-white p-6 rounded-xl shadow-lg">
            <h3 class="text-lg font-bold text-gray-800 mb-4 border-b pb-2">Equipos a Traspasar (<?php echo count($equipos); ?>)</h3>
            
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

    // --- AUTOCOMPLETADO INTELIGENTE PARA EL USUARIO QUE RECIBE ---
    let usuariosGlobal = [];
    document.addEventListener('DOMContentLoaded', () => {
        // Reutilizamos el endpoint PDF que trae todos los usuarios de Active Directory
        fetch('consultar_usuarios.php?ajax_pdf=1')
            .then(res => res.json())
            .then(data => { usuariosGlobal = data; });
    });

    const inputRecibe = document.getElementById('recibe');
    const cajaSugerencias = document.getElementById('lista_sugerencias_recibe');
    const inputArea = document.getElementById('area');

    inputRecibe.addEventListener('input', function() {
        const texto = this.value.toLowerCase();
        cajaSugerencias.innerHTML = '';
        
        if (texto === '') { cajaSugerencias.classList.add('hidden'); return; }

        const coincidencias = usuariosGlobal.filter(u => 
            (u.nombre_completo && u.nombre_completo.toLowerCase().includes(texto)) ||
            (u.cargo && u.cargo.toLowerCase().includes(texto)) ||
            (u.nombre_direccion && u.nombre_direccion.toLowerCase().includes(texto))
        );

        if (coincidencias.length > 0) {
            cajaSugerencias.classList.remove('hidden');
            coincidencias.forEach(c => {
                const div = document.createElement('div');
                div.className = 'p-3 border-b hover:bg-indigo-50 cursor-pointer text-sm transition-colors';
                div.innerHTML = `<div class="font-bold text-primary-dark">${c.nombre_completo}</div><div class="text-xs text-gray-600">${c.cargo || 'Sin cargo'} | ${c.nombre_direccion || 'Sin área'}</div>`;
                div.addEventListener('click', function() {
                    inputRecibe.value = `${c.nombre_completo} - ${c.cargo || 'Sin cargo'}`; 
                    if (c.nombre_direccion && !inputArea.value) inputArea.value = c.nombre_direccion;
                    cajaSugerencias.classList.add('hidden'); 
                });
                cajaSugerencias.appendChild(div);
            });
        } else { cajaSugerencias.classList.add('hidden'); }
    });

    document.addEventListener('click', function(e) {
        if (e.target !== inputRecibe) cajaSugerencias.classList.add('hidden');
    });
</script>

</body>
</html>