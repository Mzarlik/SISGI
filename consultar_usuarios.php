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

        $sql = "SELECT r.num_empleado, r.correo_electronico as correo, r.telefono, r.cargo, d.nombre_direccion as area 
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
    <title>Usuarios AD</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="js/sweetalert2.all.min.js"></script>   
    <script src="js/jspdf.umd.min.js"></script>
    <script src="js/jspdf.plugin.autotable.min.js"></script>
    
    <style>
        :root {
            --brand-color: #721538;
            --brand-light: #942f54;
            --bg-color: #f3f4f6;
            --text-color: #374151;
        }

        body { font-family: 'Segoe UI', system-ui, sans-serif; background-color: var(--bg-color); margin: 0; padding: 20px; color: var(--text-color); }
        .container { max-width: 1200px; margin: 0 auto; }
        
        .search-input { width: 100%; padding: 12px 15px 12px 40px; border: 1px solid #d1d5db; border-radius: 8px; outline: none; transition: 0.3s; box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
        .search-input:focus { border-color: var(--brand-color); box-shadow: 0 0 0 3px rgba(114, 21, 56, 0.1); }
        .search-icon-svg { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); width: 20px; height: 20px; color: #9ca3af; z-index: 10; }
        
        .card { background: white; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); overflow: hidden; }
        .table-responsive { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 900px; }
        thead { background-color: var(--brand-color); color: white; }
        th { padding: 16px; text-align: left; font-weight: 600; font-size: 0.85em; text-transform: uppercase; letter-spacing: 0.05em; }
        td { padding: 16px; border-bottom: 1px solid #e5e7eb; font-size: 0.95em; vertical-align: top; }
        tr:hover { background-color: #fdf2f5; }

        .edit-input { width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 6px; margin-bottom: 4px; display: block; }
        .hidden { display: none !important; }
        .col-nombre { font-weight: 600; color: #111827; }
        .col-sec { font-size: 0.85em; color: #6b7280; display: block; }
        .col-dir { color: #374151; font-weight: 500; }
        .col-user { font-family: monospace; background: #eef2ff; color: #4338ca; padding: 4px 8px; border-radius: 4px; font-size: 0.9em; display: inline-block; margin-bottom: 5px;}
        .action-btn { width: 32px; height: 32px; border-radius: 6px; border: none; background: transparent; cursor: pointer; display: inline-flex; justify-content: center; align-items: center; }
        .btn-edit:hover { background: #f3f4f6; color: var(--brand-color); }
        .btn-save { background: #dcfce7; color: #15803d; margin-right: 5px; } 
        .btn-cancel { background: #fee2e2; color: #b91c1c; }

        .pagination { display: flex; justify-content: center; padding: 20px; gap: 5px; }
        .page-link { padding: 8px 12px; border-radius: 6px; text-decoration: none; font-size: 0.9em; transition: 0.2s; }
        .page-link.active { background-color: var(--brand-color); color: white; }
        .page-link.inactive { background-color: white; border: 1px solid #e5e7eb; color: var(--text-color); }
    </style>
</head>
<body>
    
    <div class="bg-white p-4 rounded-xl shadow-md mb-6 flex flex-col lg:flex-row gap-4 items-center justify-between">
        
        <form method="GET" action="consultar_usuarios.php" class="relative w-full lg:max-w-md">
            <svg class="search-icon-svg" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
            <input type="text" name="q" id="searchInput" value="<?php echo htmlspecialchars($busqueda); ?>" placeholder="Buscar por nombre, usuario, oficio..." class="search-input w-full p-3 border border-gray-300 rounded-full outline-none transition shadow-sm">
        </form>

        <div class="relative w-full lg:w-auto flex justify-end">
            <button type="button" id="btnOpciones" class="bg-[#721538] hover:bg-[#942f54] text-white font-bold py-3 px-6 rounded-full shadow transition flex items-center gap-2 w-full lg:w-auto justify-center cursor-pointer">
                <i class="fas fa-bars"></i> Opciones
            </button>

            <div id="dropdownOpciones" class="hidden absolute top-full mt-2 right-0 w-56 bg-white rounded-xl shadow-xl border border-gray-200 py-2 z-50">
                <a href="registro.php" class="block w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 hover:text-green-600 transition-colors font-medium">
                    <i class="fas fa-user-plus w-6 text-center text-green-500 mr-2"></i> Registrar Nuevo
                </a>
                <button type="button" onclick="generarReportePDF()" class="block w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 hover:text-red-700 transition-colors font-medium cursor-pointer">
                    <i class="fas fa-file-pdf w-6 text-center text-red-600 mr-2"></i> Exportar a PDF
                </button>
                <div class="h-px bg-gray-200 my-1"></div>
                <a href="dashboard.php" class="block w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 hover:text-gray-900 transition-colors font-medium">
                    <i class="fas fa-home w-6 text-center text-gray-500 mr-2"></i> Menú Principal
                </a>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th width="5%">ID</th>
                        <th width="25%">Nombre y Cargo</th>
                        <th width="25%">Ubicación</th>
                        <th width="20%">Contacto</th>
                        <th width="15%">Cuenta / Oficio</th>
                        <th width="10%" style="text-align:center;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php if(!$res || $res->num_rows == 0): ?>
                        <tr><td colspan="6" style="text-align:center; padding: 40px; color:#9ca3af;">No se encontraron resultados</td></tr>
                    <?php endif; ?>

                <?php if($res): while($row = $res->fetch_assoc()): 
                        $nombreCompleto = trim(preg_replace('/\s+/', ' ', $row['nombres'] . ' ' . $row['apellido_paterno'] . ' ' . $row['apellido_materno']));
                        $passVal = $row['contrasena'] ?? $row['password'] ?? '';
                    ?>
                    <tr id="fila_<?php echo $row['id']; ?>">
                        <td style="color:#9ca3af; font-size:0.8em; padding-top:20px;"><?php echo $row['id']; ?></td>

                        <td>
                            <div class="view-mode col-nombre">
                                <?php echo htmlspecialchars($nombreCompleto); ?>
                                <div style="font-size:0.75em; color:#6b7280; margin-top:2px;">Cargo: <?php echo htmlspecialchars($row['cargo'] ?? '---'); ?></div>
                                <div style="font-size:0.75em; color:#9ca3af; margin-top:2px;">Empleado: <?php echo htmlspecialchars($row['num_empleado']); ?></div>
                            </div>
                            <div class="edit-mode hidden">
                                <input type="text" class="edit-input" id="edit_nom_<?php echo $row['id']; ?>" value="<?php echo htmlspecialchars($row['nombres']); ?>" placeholder="Nombres">
                                <div style="display:flex; gap:5px;">
                                    <input type="text" class="edit-input" id="edit_pat_<?php echo $row['id']; ?>" value="<?php echo htmlspecialchars($row['apellido_paterno']); ?>" placeholder="A. Pat">
                                    <input type="text" class="edit-input" id="edit_mat_<?php echo $row['id']; ?>" value="<?php echo htmlspecialchars($row['apellido_materno']); ?>" placeholder="A. Mat">
                                </div>
                                <input type="text" class="edit-input" id="edit_cargo_<?php echo $row['id']; ?>" value="<?php echo htmlspecialchars($row['cargo'] ?? ''); ?>" placeholder="Cargo">
                            </div>
                        </td>

                        <td>
                            <div class="view-mode">
                                <span class="col-sec"><?php echo htmlspecialchars($row['nombre_secretaria'] ?? 'Sin asignar'); ?></span>
                                <span class="col-dir"><?php echo htmlspecialchars($row['nombre_direccion'] ?? '---'); ?></span>
                            </div>
                            <div class="edit-mode hidden">
                                <select class="edit-input" id="edit_dir_<?php echo $row['id']; ?>"></select>
                            </div>
                        </td>

                        <td>
                            <div class="view-mode">
                                <div style="font-size:0.85em; color:#374151;"><i class="fas fa-envelope text-gray-400"></i> <?php echo htmlspecialchars($row['correo_electronico'] ?? '---'); ?></div>
                                <div style="font-size:0.85em; color:#374151; margin-top:4px;"><i class="fas fa-phone text-gray-400"></i> <?php echo htmlspecialchars($row['telefono'] ?? '---'); ?></div>
                            </div>
                            <div class="edit-mode hidden">
                                <input type="text" class="edit-input" id="edit_correo_<?php echo $row['id']; ?>" value="<?php echo htmlspecialchars($row['correo_electronico'] ?? ''); ?>" placeholder="Correo">
                                <input type="text" class="edit-input" id="edit_tel_<?php echo $row['id']; ?>" value="<?php echo htmlspecialchars($row['telefono'] ?? ''); ?>" placeholder="Teléfono">
                            </div>
                        </td>

                        <td>
                            <div class="view-mode">
                                <span class="col-user" style="display:block; margin-bottom:4px; width: fit-content;"><i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($row['usuario']); ?></span>
                                <div style="font-size:0.75em; color:#6b7280; font-weight: 500;">Oficio: <?php echo htmlspecialchars($row['num_oficio'] ?? '---'); ?></div>
                                <div style="display:flex; align-items:center; gap:5px; color:#6b7280; font-size:0.9em; margin-top:4px;">
                                    <i class="fas fa-key" style="font-size:0.8em;"></i>
                                    <input type="password" id="ver_pass_<?php echo $row['id']; ?>" value="<?php echo htmlspecialchars($passVal); ?>" readonly style="border:none; background:transparent; width:80px; font-family:monospace; color:#374151;">
                                    <i class="fas fa-eye" onclick="togglePassword(<?php echo $row['id']; ?>)" style="cursor:pointer; color:var(--brand-color);" title="Ver"></i>
                                </div>
                            </div>
                            <div class="edit-mode hidden">
                                <input type="text" class="edit-input" id="edit_oficio_<?php echo $row['id']; ?>" value="<?php echo htmlspecialchars($row['num_oficio']); ?>" placeholder="No. Oficio">
                                <input type="text" class="edit-input" id="edit_user_<?php echo $row['id']; ?>" value="<?php echo htmlspecialchars($row['usuario']); ?>">
                                <input type="text" class="edit-input" id="edit_pass_<?php echo $row['id']; ?>" value="<?php echo htmlspecialchars($passVal); ?>">
                            </div>
                        </td>

                        <td style="text-align:center;">
                            <div class="view-mode">
                                <button class="action-btn btn-edit" onclick="activarEdicion(<?php echo $row['id']; ?>, <?php echo $row['id_direccion'] ?? 0; ?>)"><i class="fas fa-pencil-alt"></i></button>
                            </div>
                            <div class="edit-mode hidden">
                                <button class="action-btn btn-save" onclick="guardarEdicion(<?php echo $row['id']; ?>)"><i class="fas fa-check"></i></button>
                                <button class="action-btn btn-cancel" onclick="cancelarEdicion(<?php echo $row['id']; ?>)"><i class="fas fa-times"></i></button>
                            </div>
                        </td>
                    </tr>
                <?php endwhile; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <div class="pagination">
        <?php for($i=1; $i<=$paginas; $i++): ?>
            <a href="?p=<?php echo $i; ?>&q=<?php echo $busqueda; ?>" class="page-link <?php echo ($i==$pagina) ? 'active' : 'inactive'; ?>"><?php echo $i; ?></a>
        <?php endfor; ?>
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

    // --- EXPORTACIÓN A PDF (Tu función original optimizada) ---
    async function generarReportePDF() {
        // Cierra el menú desplegable visualmente
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

            doc.setFontSize(18);
            doc.setTextColor(114, 21, 56); 
            doc.text("Reporte de Usuarios AD", 14, 20);
            
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
                styles: { fontSize: 8 },
                headStyles: { fillColor: [114, 21, 56] }, 
                alternateRowStyles: { fillColor: [245, 245, 245] }
            });

            doc.save(`Reporte_Usuarios_AD_${new Date().getTime()}.pdf`);
            Swal.close();
        } catch (error) {
            console.error(error);
            Swal.fire('Error', 'No se pudo generar el PDF. Verifica tu conexión.', 'error');
        }
    }

    // --- FUNCIONES DE EDICIÓN EN LÍNEA ---
    function togglePassword(id) {
        const input = document.getElementById(`ver_pass_${id}`);
        input.type = (input.type === "password") ? "text" : "password";
    }

    function activarEdicion(id, idDireccionActual) {
        const fila = document.getElementById(`fila_${id}`);
        fila.querySelectorAll('.view-mode').forEach(el => el.classList.add('hidden'));
        fila.querySelectorAll('.edit-mode').forEach(el => el.classList.remove('hidden'));
        fila.style.backgroundColor = '#fff1f2'; 
        
        const select = document.getElementById(`edit_dir_${id}`);
        select.innerHTML = ''; 
        let currentSec = '';
        let group = null;
        catalogo.forEach(item => {
            if(item.nombre_secretaria !== currentSec) {
                currentSec = item.nombre_secretaria;
                group = document.createElement('optgroup');
                group.label = currentSec;
                select.appendChild(group);
            }
            let option = document.createElement('option');
            option.value = item.id_direccion;
            option.text = item.nombre_direccion;
            if(item.id_direccion == idDireccionActual) option.selected = true;
            group.appendChild(option);
        });
    }

    function cancelarEdicion(id) { location.reload(); }

    function guardarEdicion(id) {
        const data = new FormData();
        data.append('id', id);
        data.append('id_direccion', document.getElementById(`edit_dir_${id}`).value);
        data.append('num_oficio', document.getElementById(`edit_oficio_${id}`).value);
        data.append('nombres', document.getElementById(`edit_nom_${id}`).value);
        data.append('apellido_paterno', document.getElementById(`edit_pat_${id}`).value);
        data.append('apellido_materno', document.getElementById(`edit_mat_${id}`).value);
        data.append('usuario', document.getElementById(`edit_user_${id}`).value);
        data.append('contrasena', document.getElementById(`edit_pass_${id}`).value);
        data.append('cargo', document.getElementById(`edit_cargo_${id}`).value);
        data.append('correo_electronico', document.getElementById(`edit_correo_${id}`).value);
        data.append('telefono', document.getElementById(`edit_tel_${id}`).value);

        Swal.fire({ title: 'Guardando...', didOpen: () => Swal.showLoading() });
        fetch('actualizar_usuario.php', { method: 'POST', body: data })
        .then(r => r.json())
        .then(d => {
            if(d.success) {
                Swal.fire({ icon: 'success', title: '¡Actualizado!', timer: 1000, showConfirmButton: false }).then(() => location.reload());
            } else {
                Swal.fire('Error', d.message, 'error');
            }
        });
    }
</script>
</body>
</html>