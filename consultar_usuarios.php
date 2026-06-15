<?php
// consultar_usuarios.php
require_once 'session_check.php';
require_once 'config.php';

// 1. SEGURIDAD Y CONEXIÓN
$roles_permitidos = ['admin', 'tecnico'];
if (!isset($_SESSION['usuario']) || !in_array($_SESSION['rol'] ?? '', $roles_permitidos)) {
    header("Location: dashboard.php");
    exit();
}

$conn = get_db_connection();
$conn->set_charset("utf8mb4");

// ==========================================
// LÓGICA PARA OBTENER DETALLES DE UN USUARIO (AJAX para Resguardos)
// ==========================================
if (isset($_GET['ajax_details'])) {
    ob_clean();
    header('Content-Type: application/json');
    $nombreCompleto = isset($_GET['nombre']) ? $conn->real_escape_string($_GET['nombre']) : '';
    // Limpiamos los espacios múltiples para asegurar el "Match"
    $nombreCompleto = trim(preg_replace('/\s+/', ' ', $nombreCompleto));
    $data = ['found' => false];

    if (!empty($nombreCompleto)) {
        // Separamos el nombre en palabras para buscar sin importar el orden (Ej. Apellidos primero)
        $palabras = explode(' ', $nombreCompleto);
        $condiciones = [];
        $campo_completo = "CONCAT_WS(' ', NULLIF(TRIM(r.nombres), ''), NULLIF(TRIM(r.apellido_paterno), ''), NULLIF(TRIM(r.apellido_materno), ''))";
        
        foreach ($palabras as $palabra) {
            if (!empty(trim($palabra))) {
                $safe_palabra = $conn->real_escape_string(trim($palabra));
                $condiciones[] = "$campo_completo LIKE '%$safe_palabra%'";
            }
        }
        $where_match = implode(' AND ', $condiciones);

        $sql = "SELECT r.id, r.nombres, r.apellido_paterno, r.apellido_materno, r.usuario, r.contrasena, r.num_oficio, r.num_empleado, r.correo_electronico as correo, r.telefono, r.cargo, r.id_direccion, d.nombre_direccion as area 
                FROM registros_ad r
                LEFT JOIN cat_direcciones d ON r.id_direccion = d.id_direccion
                WHERE $where_match LIMIT 1";
        
        $res = $conn->query($sql);
        if ($res && $row = $res->fetch_assoc()) {
            $data = ['found' => true, 'details' => $row];
        }
    }
    echo json_encode($data);
    $conn->close();
    exit;
}

// ==========================================
// LÓGICA PARA EXPORTAR DATOS (JSON PARA PDF)
// ==========================================
if (isset($_GET['ajax_pdf'])) {
    ob_clean();
    header('Content-Type: application/json');

    $busqueda = isset($_GET['q']) ? $conn->real_escape_string($_GET['q']) : '';
    $where = "WHERE 1=1";
    if($busqueda) {
        $where .= " AND (r.nombres LIKE '%$busqueda%' OR r.usuario LIKE '%$busqueda%' OR r.num_empleado LIKE '%$busqueda%' OR r.num_oficio LIKE '%$busqueda%' OR r.cargo LIKE '%$busqueda%' OR r.correo_electronico LIKE '%$busqueda%' OR s.nombres LIKE '%$busqueda%' OR d.nombre_direccion LIKE '%$busqueda%')";
    }

    $sql = "SELECT r.num_oficio, 
                   TRIM(REPLACE(CONCAT(r.nombres, ' ', COALESCE(r.apellido_paterno,''), ' ', COALESCE(r.apellido_materno,'')), '  ', ' ')) as nombre_completo,
                   TRIM(REPLACE(CONCAT(r.apellido_materno, ' ', r.nombres, ' ', COALESCE(r.apellido_paterno,'')), '  ', ' ')) as nombre_natural,
                   r.usuario, r.cargo, r.correo_electronico, r.telefono,
                   d.nombre_direccion, 
                   s.nombres as nombre_secretaria 
            FROM registros_ad r
            LEFT JOIN cat_direcciones d ON r.id_direccion = d.id_direccion
            LEFT JOIN Secretarias s ON d.id_secretaria = s.id_secretaria
            $where
            ORDER BY s.nombres ASC, d.nombre_direccion ASC, r.nombres ASC";

    $res = $conn->query($sql);
    $data = [];
    if ($res) {
        while($row = $res->fetch_assoc()) {
            $data[] = $row;
        }
    }
    echo json_encode($data);
    $conn->close();
    exit;
}

// ==========================================
// LÓGICA DE LA VISTA (HTML NORMAL)
// ==========================================

$sqlCat = "SELECT d.id_direccion, d.nombre_direccion, s.nombres as nombre_secretaria 
           FROM cat_direcciones d 
           JOIN Secretarias s ON d.id_secretaria = s.id_secretaria 
           ORDER BY s.nombres, d.nombre_direccion";
$resCat = $conn->query($sqlCat);
$catalogo = [];
if ($resCat) {
    while($row = $resCat->fetch_assoc()) { $catalogo[] = $row; }
}

$busqueda = isset($_GET['q']) ? $conn->real_escape_string($_GET['q']) : '';
$where = "WHERE 1=1";
if($busqueda) {
    $where .= " AND (r.nombres LIKE '%$busqueda%' 
                OR r.usuario LIKE '%$busqueda%' 
                OR r.num_empleado LIKE '%$busqueda%' 
                OR r.num_oficio LIKE '%$busqueda%' 
                OR r.cargo LIKE '%$busqueda%'
                OR r.correo_electronico LIKE '%$busqueda%'
                OR s.nombres LIKE '%$busqueda%' 
                OR d.nombre_direccion LIKE '%$busqueda%')"; 
}

$limite = 10;
$pagina = isset($_GET['p']) ? (int)$_GET['p'] : 1;
$offset = ($pagina - 1) * $limite;

$sqlTotal = "SELECT COUNT(*) as total 
             FROM registros_ad r 
             LEFT JOIN cat_direcciones d ON r.id_direccion = d.id_direccion
             LEFT JOIN Secretarias s ON d.id_secretaria = s.id_secretaria
             $where";
$resTotal = $conn->query($sqlTotal);
$total = $resTotal ? $resTotal->fetch_assoc()['total'] : 0;
$paginas = $limite > 0 ? ceil($total / $limite) : 1;

$sql = "SELECT r.*, 
               d.nombre_direccion, 
               s.nombres as nombre_secretaria 
        FROM registros_ad r
        LEFT JOIN cat_direcciones d ON r.id_direccion = d.id_direccion
        LEFT JOIN Secretarias s ON d.id_secretaria = s.id_secretaria
        $where
        ORDER BY r.id DESC LIMIT $offset, $limite";

$res = $conn->query($sql);
include 'header.php';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Usuarios SATQ</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="js/sweetalert2.all.min.js"></script>   
    <script src="js/jspdf.umd.min.js"></script>
    <script src="js/jspdf.plugin.autotable.min.js"></script>
    <script src="js/xlsx.full.min.js"></script>
    <script src="js/exceljs.min.js"></script>
    <!-- Fuentes Montserrat para jsPDF -->
    <script src="js/Montserrat-normal.js"></script>
    <script src="js/Montserrat-bold.js"></script>
    
    <style>
        :root {
            --brand-color: #721538;
            --brand-light: #942f54;
            --bg-color: #f3f4f6;
            --text-color: #374151;
        }

        body { font-family: 'Segoe UI', system-ui, sans-serif; background-color: #d6d1ca; margin: 0; padding: 20px; color: var(--text-color); }
        .container { max-width: 1200px; margin: 0 auto; }
        
        .search-input { width: 100%; padding: 12px 15px 12px 40px; border: 1px solid #d1d5db; border-radius: 8px; outline: none; transition: 0.3s; box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
        .search-input:focus { border-color: var(--brand-color); box-shadow: 0 0 0 3px rgba(114, 21, 56, 0.1); }
        .search-icon-svg { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); width: 20px; height: 20px; color: #9ca3af; z-index: 10; }
        
        table { width: 100%; border-collapse: collapse; min-width: 900px; }
        td { padding: 16px; border-bottom: 1px solid #e5e7eb; font-size: 0.95em; vertical-align: top; }
        tbody tr:hover { background-color: #fdf2f5; }

        .hidden { display: none !important; }
        .col-nombre { font-weight: 600; color: #111827; }
        .col-sec { font-size: 0.85em; color: #6b7280; display: block; }
        .col-dir { color: #374151; font-weight: 500; }
        
        .pagination { display: flex; justify-content: center; padding: 20px; gap: 5px; }
        .page-link { padding: 8px 12px; border-radius: 6px; text-decoration: none; font-size: 0.9em; transition: 0.2s; }
        .page-link.active { background-color: var(--brand-color); color: white; }
        .page-link.inactive { background-color: white; border: 1px solid #e5e7eb; color: var(--text-color); }

        /* Estilos para inputs dentro de SweetAlert al editar */
        .swal-field-label { display: block; text-align: left; font-size: 0.75rem; font-weight: bold; color: #555; margin-bottom: 2px; text-transform: uppercase; }
        .swal-custom-input { width: 100% !important; margin: 0 0 12px 0 !important; font-size: 0.9rem !important; height: 40px !important; border: 1px solid #ccc; border-radius: 6px; padding: 0 10px; }
    </style>
</head>
<body class="p-4 sm:p-8 bg-[#d6d1ca] min-h-screen">
    <div class="max-w-7xl mx-auto space-y-6">
        
        <div class="flex flex-col sm:flex-row justify-between items-center mb-2">
            <h2 class="text-3xl font-bold text-primary-dark flex items-center gap-2">
                <i class="fas fa-users-cog"></i> Usuarios SATQ
                <span class="text-xs bg-white/60 text-primary-dark/80 px-3 py-1 rounded-full italic font-semibold"><?php echo $total; ?> registrados</span>
            </h2>
        </div>
        
        <div class="bg-white p-4 rounded-xl shadow-md flex flex-col lg:flex-row gap-4 items-center justify-between">
            <form method="GET" action="consultar_usuarios.php" class="relative w-full lg:max-w-md">
                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400">
                    <i class="fas fa-search"></i>
                </span>
                <input type="text" name="q" id="searchInput" value="<?php echo htmlspecialchars($busqueda); ?>" placeholder="Buscar por nombre, usuario, oficio..." class="w-full pl-11 p-3 border border-gray-300 rounded-full focus:ring-2 focus:ring-[#721538] outline-none transition shadow-sm">
            </form>

            <div class="relative w-full lg:w-auto flex justify-end">
                <button type="button" id="btnOpciones" class="bg-[#721538] hover:bg-[#942f54] text-white font-bold py-3 px-6 rounded-full shadow transition flex items-center gap-2 w-full lg:w-auto justify-center cursor-pointer">
                    <i class="fas fa-bars"></i> Opciones
                </button>

                <div id="dropdownOpciones" class="hidden absolute top-full mt-2 right-0 w-60 bg-white rounded-xl shadow-xl border border-gray-200 overflow-hidden z-50">
                    <a href="registro.php" class="block px-4 py-3 text-gray-700 hover:bg-indigo-50 hover:text-indigo-700 transition border-b border-gray-100 font-medium">
                        <i class="fas fa-user-plus w-6 text-center text-green-600 text-base"></i> Registrar Nuevo
                    </a>
                    <button type="button" onclick="generarReportePDF()" class="w-full text-left px-4 py-3 text-gray-700 hover:bg-indigo-50 hover:text-indigo-700 transition border-b border-gray-100 font-medium cursor-pointer">
                        <i class="fas fa-file-pdf w-6 text-center text-red-600 text-base"></i> Exportar a PDF
                    </button>
                    <button type="button" onclick="exportarExcel()" class="w-full text-left px-4 py-3 text-gray-700 hover:bg-indigo-50 hover:text-indigo-700 transition border-b border-gray-100 font-medium cursor-pointer">
                        <i class="fas fa-file-excel w-6 text-center text-green-600 text-base"></i> Exportar a Excel
                    </button>
                    <a href="dashboard.php" class="block px-4 py-3 text-gray-700 hover:bg-gray-100 transition font-medium">
                        <i class="fas fa-home w-6 text-center text-gray-600 text-base"></i> Menú Principal
                    </a>
                </div>
            </div>
        </div>

    <div class="bg-white shadow-lg rounded-xl overflow-hidden border border-gray-200 relative z-0">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-primary-dark text-white text-xs font-bold uppercase tracking-wider">
                    <tr>
                        <th width="5%" class="px-6 py-4">ID</th>
                        <th width="25%" class="px-6 py-4">Nombre y Cargo</th>
                        <th width="25%" class="px-6 py-4">Ubicación</th>
                        <th width="20%" class="px-6 py-4">Contacto</th>
                        <th width="15%" class="px-6 py-4">Cuenta / Oficio</th>
                        <th width="10%" class="px-6 py-4 text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-sm text-gray-700 bg-white">
                <?php if(!$res || $res->num_rows == 0): ?>
                        <tr><td colspan="6" style="text-align:center; padding: 40px; color:#9ca3af;">No se encontraron resultados</td></tr>
                    <?php endif; ?>

                <?php if($res): while($row = $res->fetch_assoc()): 
                        $nombreCompleto = trim(preg_replace('/\s+/', ' ', $row['nombres'] . ' ' . $row['apellido_paterno'] . ' ' . $row['apellido_materno']));
                        $passVal = $row['contrasena'] ?? $row['password'] ?? '';
                    ?>
                    <tr id="fila_<?php echo $row['id']; ?>" class="table-row-hover transition-colors duration-150">
                        <td data-label="ID" class="px-6 py-4 text-gray-400 font-mono text-xs">#<?php echo $row['id']; ?></td>

                        <td data-label="Personal" class="px-6 py-4">
                            <span class="font-bold text-gray-800 block"><?php echo htmlspecialchars($nombreCompleto); ?></span>
                            <span class="text-xs text-gray-500 block mt-0.5">Cargo: <?php echo htmlspecialchars($row['cargo'] ?? '---'); ?></span>
                            <span class="text-xs text-gray-400 block mt-0.5">Num. Empleado: <?php echo htmlspecialchars($row['num_empleado']); ?></span>
                        </td>

                        <td data-label="Ubicación" class="px-6 py-4">
                            <span class="text-xs text-gray-500 uppercase tracking-wide block mb-1"><?php echo htmlspecialchars($row['nombre_secretaria'] ?? 'Sin asignar'); ?></span>
                            <span class="text-sm font-medium text-primary-dark"><?php echo htmlspecialchars($row['nombre_direccion'] ?? '---'); ?></span>
                        </td>

                        <td data-label="Contacto" class="px-6 py-4">
                            <div class="text-sm text-gray-700 mb-1"><i class="far fa-envelope text-gray-400 mr-1"></i> <?php echo htmlspecialchars($row['correo_electronico'] ?? '---'); ?></div>
                            <div class="text-sm text-gray-700"><i class="fas fa-phone-alt text-gray-400 mr-1"></i> <?php echo htmlspecialchars($row['telefono'] ?? '---'); ?></div>
                        </td>

                        <td data-label="Cuenta" class="px-6 py-4">
                            <span class="bg-indigo-50 text-indigo-700 px-2.5 py-1 rounded-md font-mono text-sm font-bold border border-indigo-100 inline-block mb-2 shadow-sm">
                                <i class="fas fa-user-circle mr-1"></i> <?php echo htmlspecialchars($row['usuario']); ?>
                            </span>
                            <div class="text-xs text-gray-500 font-medium mb-1">Oficio: <span class="text-gray-700"><?php echo htmlspecialchars($row['num_oficio'] ?? '---'); ?></span></div>
                            
                            <div class="flex items-center gap-2 text-gray-500 mt-1">
                                <i class="fas fa-key text-xs"></i>
                                <input type="password" id="ver_pass_<?php echo $row['id']; ?>" value="<?php echo htmlspecialchars($passVal); ?>" readonly class="bg-transparent border-none w-20 outline-none text-xs font-mono tracking-widest text-gray-700">
                                <button onclick="togglePassword(<?php echo $row['id']; ?>)" class="text-gray-400 hover:text-primary-dark transition focus:outline-none p-1">
                                    <i class="fas fa-eye" id="icon_pass_<?php echo $row['id']; ?>"></i>
                                </button>
                            </div>
                        </td>

                        <td data-label="Acciones" class="px-6 py-4 text-center whitespace-nowrap">
                            <button class="w-8 h-8 rounded border border-gray-300 text-gray-500 hover:text-[#721538] hover:border-[#721538] transition flex items-center justify-center mx-auto" onclick="abrirModalEdicion(<?php echo htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8'); ?>)" title="Editar Información">
                                <i class="fas fa-pencil-alt"></i>
                            </button>
                        </td>
                    </tr>
                <?php endwhile; endif; ?>
                </tbody>
            </table>
        </div>
        
        <?php if($paginas > 1): ?>
        <div class="p-4 bg-gray-50 border-t border-gray-200 flex justify-center flex-wrap gap-2">
            <?php 
            $rango = 2;
            $inicio = max(1, $pagina - $rango);
            $fin = min($paginas, $pagina + $rango);
            
            if ($pagina > 1) {
                echo '<a href="?p='.($pagina-1).'&q='.urlencode($busqueda).'" class="w-8 h-8 flex items-center justify-center rounded-md border border-gray-200 bg-white text-gray-600 hover:bg-gray-50 hover:text-primary-dark transition-all"><i class="fas fa-chevron-left text-xs"></i></a>';
            }

            for($i = $inicio; $i <= $fin; $i++) {
                $activeClass = ($i == $pagina) ? 'bg-primary-dark text-white shadow-md border-primary-dark scale-105' : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-50 hover:border-gray-300 hover:text-primary-dark';
                echo '<a href="?p='.$i.'&q='.urlencode($busqueda).'" class="w-8 h-8 flex items-center justify-center rounded-md border transition-all text-sm font-medium '.$activeClass.'">'.$i.'</a>';
            }

            if ($pagina < $paginas) {
                echo '<a href="?p='.($pagina+1).'&q='.urlencode($busqueda).'" class="w-8 h-8 flex items-center justify-center rounded-md border border-gray-200 bg-white text-gray-600 hover:bg-gray-50 hover:text-primary-dark transition-all"><i class="fas fa-chevron-right text-xs"></i></a>';
            }
            ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
    const catalogo = <?php echo json_encode($catalogo); ?>;

    // --- MANEJO DEL MENÚ DESPLEGABLE ---
    document.addEventListener('DOMContentLoaded', () => {
        const btnOpciones = document.getElementById('btnOpciones');
        const dropdownOpciones = document.getElementById('dropdownOpciones');

        if (btnOpciones && dropdownOpciones) {
            btnOpciones.addEventListener('click', (e) => {
                e.stopPropagation();
                dropdownOpciones.classList.toggle('hidden');
            });
        }
    });

    window.addEventListener('click', function(event) {
        const dropdownOpciones = document.getElementById('dropdownOpciones');
        const btnOpciones = document.getElementById('btnOpciones');
        if (dropdownOpciones && !dropdownOpciones.classList.contains('hidden')) {
            if (!dropdownOpciones.contains(event.target) && !btnOpciones.contains(event.target)) {
                dropdownOpciones.classList.add('hidden');
            }
        }
    });

    // --- EXPORTACIÓN A PDF ---
    async function generarReportePDF() {
        const dropdown = document.getElementById('dropdownOpciones');
        if (dropdown) dropdown.classList.add('hidden');

        const busqueda = document.getElementById('searchInput').value;
        
        Swal.fire({ 
            title: 'Generando Reporte...', 
            text: 'Por favor espere mientras se crea el PDF.',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading() 
        });

        try {
            const response = await fetch(`consultar_usuarios.php?ajax_pdf=1&q=${encodeURIComponent(busqueda)}`);
            const datos = await response.json();

            if(!datos || datos.length === 0) {
                Swal.fire('Atención', 'No hay datos para exportar con los filtros actuales.', 'info');
                return;
            }

            const { jsPDF } = window.jspdf;
            const doc = new jsPDF();
            doc.setFont("Montserrat", "normal");

            doc.setFontSize(18);
            doc.setTextColor(114, 21, 56); 
            doc.text("Reporte de Usuarios SATQ", 14, 20);
            
            doc.setFontSize(10);
            doc.setTextColor(100);
            doc.text(`Generado el: ${new Date().toLocaleDateString()} ${new Date().toLocaleTimeString()}`, 14, 26);
            if(busqueda) doc.text(`Filtro aplicado: "${busqueda}"`, 14, 31);

            const columnas = ["Secretaría", "Dirección / Área", "Oficio", "Nombre y Cargo", "Contacto", "Usuario"];
            const filas = datos.map(row => [
                row.nombre_secretaria || 'Sin asignar',
                row.nombre_direccion || '---',
                row.num_oficio || '---',
                `${row.nombre_completo}\n${row.cargo || 'Sin cargo'}`,
                `${row.correo_electronico || 'Sin correo'}\n${row.telefono || 'Sin tel'}`,
                row.usuario
            ]);

            doc.autoTable({
                head: [columnas],
                body: filas,
                startY: 35,
                styles: { font: 'Montserrat', fontSize: 8 },
                headStyles: { fillColor: [114, 21, 56] }, 
                alternateRowStyles: { fillColor: [245, 245, 245] }
            });

            doc.save(`Reporte_Usuarios_SATQ_${new Date().getTime()}.pdf`);
            Swal.close();
        } catch (error) {
            console.error(error);
            Swal.fire('Error', 'No se pudo generar el PDF. Verifica tu conexión.', 'error');
        }
    }

    // --- EXPORTACIÓN A EXCEL ---
    async function exportarExcel() {
        const dropdown = document.getElementById('dropdownOpciones');
        if (dropdown) dropdown.classList.add('hidden');

        const busqueda = document.getElementById('searchInput').value;
        
        Swal.fire({ 
            title: 'Generando Excel...', 
            text: 'Por favor espere mientras se crea el archivo.',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading() 
        });

        try {
            const response = await fetch(`consultar_usuarios.php?ajax_pdf=1&q=${encodeURIComponent(busqueda)}`);
            const datos = await response.json();

            if(!datos || datos.length === 0) {
                Swal.fire('Atención', 'No hay datos para exportar con los filtros actuales.', 'info');
                return;
            }

            const workbook = new ExcelJS.Workbook();
            const worksheet = workbook.addWorksheet('Usuarios SATQ');
            worksheet.views = [
                { state: 'frozen', xSplit: 0, ySplit: 5, activeCell: 'A6', showGridLines: true }
            ];

            // 1. Títulos y Metadatos
            worksheet.mergeCells('A1:I1');
            const titleRow = worksheet.getRow(1);
            titleRow.getCell(1).value = "REPORTE DE USUARIOS SATQ";
            titleRow.height = 35;
            titleRow.getCell(1).font = { name: 'Segoe UI', size: 16, bold: true, color: { argb: 'FF721538' } };
            titleRow.getCell(1).alignment = { vertical: 'middle', horizontal: 'left' };

            worksheet.mergeCells('A2:I2');
            const filterRow = worksheet.getRow(2);
            filterRow.getCell(1).value = busqueda ? `Filtro aplicado: "${busqueda}"` : "Todos los usuarios";
            filterRow.height = 20;
            filterRow.getCell(1).font = { name: 'Segoe UI', size: 11, italic: true, color: { argb: 'FF555555' } };
            filterRow.getCell(1).alignment = { vertical: 'middle', horizontal: 'left' };

            worksheet.mergeCells('A3:I3');
            const dateRow = worksheet.getRow(3);
            const now = new Date();
            dateRow.getCell(1).value = `Fecha de Generación: ${now.toLocaleDateString()} ${now.toLocaleTimeString()}`;
            dateRow.height = 20;
            dateRow.getCell(1).font = { name: 'Segoe UI', size: 10, italic: true, color: { argb: 'FF777777' } };
            dateRow.getCell(1).alignment = { vertical: 'middle', horizontal: 'left' };

            // Fila 4 vacía
            worksheet.getRow(4).height = 10;

            // Fila 5: Encabezados
            const headers = ["Secretaría", "Dirección / Área", "No. Oficio", "No. Empleado", "Nombre Completo", "Cargo", "Usuario / Cuenta", "Correo Electrónico", "Teléfono"];
            const headerRow = worksheet.getRow(5);
            headerRow.values = headers;
            headerRow.height = 28;

            headerRow.eachCell((cell) => {
                cell.font = { name: 'Segoe UI', size: 11, bold: true, color: { argb: 'FFFFFFFF' } };
                cell.fill = {
                    type: 'pattern',
                    pattern: 'solid',
                    fgColor: { argb: 'FF721538' } // Guinda institucional
                };
                cell.alignment = { vertical: 'middle', horizontal: 'center', wrapText: true };
                cell.border = {
                    top: { style: 'medium', color: { argb: 'FF721538' } },
                    bottom: { style: 'medium', color: { argb: 'FF5A102C' } },
                    left: { style: 'thin', color: { argb: 'FF8A244E' } },
                    right: { style: 'thin', color: { argb: 'FF8A244E' } }
                };
            });

            // 2. Filas de Datos
            let startRow = 6;
            datos.forEach((row, index) => {
                const rowData = [
                    row.nombre_secretaria || 'Sin asignar',
                    row.nombre_direccion || '---',
                    row.num_oficio || '---',
                    row.num_empleado || '---',
                    row.nombre_completo || '',
                    row.cargo || 'Sin cargo',
                    row.usuario || '',
                    row.correo_electronico || 'Sin correo',
                    row.telefono || 'Sin tel'
                ];

                const dataRow = worksheet.getRow(startRow);
                dataRow.values = rowData;
                dataRow.height = 22;

                const rowBgColor = (index % 2 === 0) ? 'FFFFFFFF' : 'FFF9FAFB'; // Gris alterno / blanco

                dataRow.eachCell((cell, colNumber) => {
                    cell.font = { name: 'Segoe UI', size: 10, color: { argb: 'FF333333' } };
                    cell.fill = {
                        type: 'pattern',
                        pattern: 'solid',
                        fgColor: { argb: rowBgColor }
                    };
                    cell.border = {
                        top: { style: 'thin', color: { argb: 'FFE5E7EB' } },
                        bottom: { style: 'thin', color: { argb: 'FFE5E7EB' } },
                        left: { style: 'thin', color: { argb: 'FFE5E7EB' } },
                        right: { style: 'thin', color: { argb: 'FFE5E7EB' } }
                    };

                    // Alineaciones
                    let horizontalAlign = 'left';
                    if ([3, 4, 7, 9].includes(colNumber)) {
                        horizontalAlign = 'center';
                    }
                    cell.alignment = { vertical: 'middle', horizontal: horizontalAlign, wrapText: true };
                });

                startRow++;
            });

            // 3. Ajuste de Ancho de Columnas
            worksheet.columns.forEach((col, colIdx) => {
                let maxLen = 10;
                if (headers[colIdx]) {
                    maxLen = Math.max(maxLen, headers[colIdx].toString().length);
                }
                for (let r = 6; r < startRow; r++) {
                    const cellVal = worksheet.getCell(r, colIdx + 1).value;
                    if (cellVal) {
                        maxLen = Math.max(maxLen, cellVal.toString().length);
                    }
                }
                col.width = Math.max(12, Math.min(maxLen + 4, 45));
            });

            // Filtros automáticos
            worksheet.autoFilter = `A5:I${startRow - 1}`;

            const buffer = await workbook.xlsx.writeBuffer();
            const blob = new Blob([buffer], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = `Reporte_Usuarios_SATQ_${new Date().toISOString().slice(0,10)}.xlsx`;
            link.click();
            URL.revokeObjectURL(link.href);
            Swal.close();
        } catch (error) {
            console.error(error);
            Swal.fire('Error', 'No se pudo generar el archivo Excel.', 'error');
        }
    }

    // --- MOSTRAR / OCULTAR CONTRASEÑA EN TABLA ---
    function togglePassword(id) {
        const input = document.getElementById(`ver_pass_${id}`);
        const icon = document.getElementById(`icon_pass_${id}`);
        
        if(input.type === "password") {
            input.type = "text";
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            input.type = "password";
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    }

    // --- MODAL DE EDICIÓN ---
    function abrirModalEdicion(user) {
        const idDireccionActual = user.id_direccion || 0;
        
        let optionsHtml = '<option value="">-- Seleccionar Dirección --</option>';
        let currentSec = '';
        catalogo.forEach(item => {
            if(item.nombre_secretaria !== currentSec) {
                if (currentSec) {
                    optionsHtml += `</optgroup>`;
                }
                currentSec = item.nombre_secretaria;
                optionsHtml += `<optgroup label="${currentSec}">`;
            }
            const selected = item.id_direccion == idDireccionActual ? 'selected' : '';
            optionsHtml += `<option value="${item.id_direccion}" ${selected}>${item.nombre_direccion}</option>`;
        });
        if (currentSec) {
            optionsHtml += `</optgroup>`;
        }

        let htmlContent = `
            <div class="text-left mt-4 text-sm max-h-[70vh] overflow-y-auto px-1">
                <input type="hidden" id="edit-id" value="${user.id || 0}">

                <div class="mb-3">
                    <label class="swal-field-label">Nombres</label>
                    <input id="edit-nombres" class="swal-custom-input" value="${user.nombres || ''}">
                </div>
                
                <div class="grid grid-cols-2 gap-3 mb-3">
                    <div>
                        <label class="swal-field-label">Primer Apellido</label>
                        <input id="edit-pat" class="swal-custom-input" value="${user.apellido_paterno || ''}">
                    </div>
                    <div>
                        <label class="swal-field-label">Segundo Apellido</label>
                        <input id="edit-mat" class="swal-custom-input" value="${user.apellido_materno || ''}">
                    </div>
                </div>
                
                <div class="grid grid-cols-2 gap-3 mb-3">
                    <div>
                        <label class="swal-field-label">Número de Empleado</label>
                        <input id="edit-num-emp" class="swal-custom-input" value="${user.num_empleado || ''}">
                    </div>
                    <div>
                        <label class="swal-field-label">Cargo</label>
                        <input id="edit-cargo" class="swal-custom-input" value="${user.cargo || ''}">
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="swal-field-label">Dirección / Área</label>
                    <select id="edit-dir" class="swal-custom-input bg-white">${optionsHtml}</select>
                </div>
                
                <div class="grid grid-cols-2 gap-3 mb-3">
                    <div>
                        <label class="swal-field-label">Correo Electrónico</label>
                        <input id="edit-correo" class="swal-custom-input" value="${user.correo_electronico || ''}">
                    </div>
                    <div>
                        <label class="swal-field-label">Teléfono</label>
                        <input id="edit-tel" class="swal-custom-input" value="${user.telefono || ''}">
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="swal-field-label">Número de Oficio</label>
                    <input id="edit-oficio" class="swal-custom-input" value="${user.num_oficio || ''}">
                </div>
                
                <div class="grid grid-cols-2 gap-3 mb-1">
                    <div>
                        <label class="swal-field-label">Usuario / Cuenta</label>
                        <input id="edit-user" class="swal-custom-input" value="${user.usuario || ''}">
                    </div>
                    <div>
                        <label class="swal-field-label">Contraseña</label>
                        <input id="edit-pass" class="swal-custom-input" value="${user.contrasena || ''}">
                    </div>
                </div>
            </div>
        `;

        Swal.fire({
            title: `<div class="text-xl font-bold border-b pb-2"><i class="fas fa-user-edit text-[#721538] mr-1"></i> Editar Usuario</div>`,
            html: htmlContent,
            width: '600px',
            showCancelButton: true,
            cancelButtonText: 'Cancelar',
            confirmButtonText: '<i class="fas fa-save mr-1"></i> Guardar Cambios',
            confirmButtonColor: '#721538',
            preConfirm: () => {
                const nombres = document.getElementById('edit-nombres').value.trim();
                const id_direccion = document.getElementById('edit-dir').value;
                const usuario = document.getElementById('edit-user').value.trim();

                if (!nombres) {
                    Swal.showValidationMessage('El campo Nombres es obligatorio.');
                    return false;
                }
                if (!id_direccion) {
                    Swal.showValidationMessage('Debe seleccionar una Dirección / Área.');
                    return false;
                }
                if (!usuario) {
                    Swal.showValidationMessage('El campo Usuario / Cuenta es obligatorio.');
                    return false;
                }

                return {
                    id: document.getElementById('edit-id').value,
                    id_direccion: id_direccion,
                    num_oficio: document.getElementById('edit-oficio').value.trim(),
                    nombres: nombres,
                    apellido_paterno: document.getElementById('edit-pat').value.trim(),
                    apellido_materno: document.getElementById('edit-mat').value.trim(),
                    usuario: usuario,
                    contrasena: document.getElementById('edit-pass').value.trim(),
                    cargo: document.getElementById('edit-cargo').value.trim(),
                    correo_electronico: document.getElementById('edit-correo').value.trim(),
                    telefono: document.getElementById('edit-tel').value.trim(),
                    num_empleado: document.getElementById('edit-num-emp').value.trim()
                }
            }
        }).then((result) => {
            if (result.isConfirmed && result.value) {
                const data = new FormData();
                for (let key in result.value) {
                    data.append(key, result.value[key]);
                }
                
                Swal.fire({ title: 'Guardando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
                fetch('actualizar_usuario.php', { method: 'POST', body: data })
                    .then(r => r.json())
                    .then(d => {
                        if(d.success) {
                            Swal.fire({ icon: 'success', title: '¡Actualizado!', timer: 1200, showConfirmButton: false }).then(() => location.reload());
                        } else {
                            Swal.fire('Error', d.message, 'error');
                        }
                    })
                    .catch(() => Swal.fire('Error', 'No se pudo guardar la información. Verifica tu conexión.', 'error'));
            }
        });
    }
</script>
</body>
</html>