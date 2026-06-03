<?php
require_once 'config.php';
session_start();

if (!isset($_SESSION['usuario'])) { header("Location: index.php"); exit(); }

// --- PERMISOS ---
// Ajusta esto según prefieras: $esAdmin controla si se ve el botón "Nuevo", "Exportar" y "Acciones"
$rol_usuario = $_SESSION['rol'] ?? 'tecnico';
$esAdmin = true; // Forzado a true como en el primer código, o usa: ($rol_usuario === 'admin')
$puedeEditar = true; 

if ($_SESSION['rol'] === 'redes') {
    // Si es redes, lo regresamos al dashboard con un mensaje de error opcional
    header("Location: dashboard.php?error=acceso_denegado");
    exit();
}

$conn = get_db_connection();

// Paginación Lógica
$registros_por_pagina = 10;
$pagina_actual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
if ($pagina_actual < 1) $pagina_actual = 1;
$offset = ($pagina_actual - 1) * $registros_por_pagina;

// Búsqueda
$terminoBusqueda = isset($_GET['q']) ? trim($_GET['q']) : '';
$where_clause = "";
if (!empty($terminoBusqueda)) {
    $bs = $conn->real_escape_string($terminoBusqueda);
    $where_clause = "WHERE secretaria LIKE '%$bs%' OR direccion LIKE '%$bs%' OR num_oficio LIKE '%$bs%' OR nombres LIKE '%$bs%' OR usuario LIKE '%$bs%'";
}

// Contar total para paginación
$sql_total = "SELECT COUNT(*) as total FROM registros_ad $where_clause";
$total_registros = $conn->query($sql_total)->fetch_assoc()['total'];
$total_paginas = ceil($total_registros / $registros_por_pagina);

// Consulta de datos
$sql = "SELECT * FROM registros_ad $where_clause ORDER BY id DESC LIMIT $offset, $registros_por_pagina";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Usuarios AD | Gestión de Dominio</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <script src="js/tailwindcss.js"></script>
    <script src="js/sweetalert2.all.min.js"></script>
    <link rel="stylesheet" href="css/all.min.css">

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
        
        /* Estilos para edición */
        .input-edit { 
            width: 100%; padding: 4px 8px; border: 1px solid #721538; 
            border-radius: 4px; font-size: 0.85rem; outline: none;
        }

        /* --- VISTA MÓVIL (ADAPTATIVO) --- */
        @media (max-width: 1024px) {
            thead { display: none; }
            table, tbody, tr, td { display: block; width: 100%; }
            tr {
                background: white; margin-bottom: 1.5rem; border-radius: 0.75rem;
                box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
                border-left: 6px solid #721538; padding: 0.5rem 0; overflow: hidden;
            }
            td {
                display: flex; justify-content: space-between; align-items: center;
                text-align: right; padding: 10px 15px; border-bottom: 1px solid #f3f4f6; min-height: 45px;
            }
            td:last-child { border-bottom: none; background-color: #f9fafb; justify-content: center; }
            
            /* Genera los nombres de las columnas en móvil usando data-label */
            td::before {
                content: attr(data-label); font-weight: 700; color: #721538;
                text-transform: uppercase; font-size: 0.7rem; text-align: left; margin-right: 15px;
            }
        }
    </style>
</head>
<body class="bg-background min-h-screen p-4 sm:p-8">

    <div class="max-w-[1600px] mx-auto">
        
        <div class="flex flex-col sm:flex-row justify-between items-center mb-6">
            <h1 class="text-3xl font-bold text-primary-dark flex items-center gap-2 mb-4 sm:mb-0">
                <i class="fas fa-users"></i> Usuarios Dominio AD
                <span class="text-xs bg-gray-200 text-gray-600 px-3 py-1 rounded-full"><?= $total_registros ?> Registros</span>
            </h1>
        </div>

        <div class="bg-white p-4 rounded-xl shadow-md mb-6 flex flex-col lg:flex-row gap-4 items-center justify-between">
            <form action="" method="GET" class="relative w-full lg:max-w-lg">
                <button type="submit" class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-primary-dark">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </button>
                <input type="text" name="q" placeholder="Buscar por Nombre, Usuario, Oficio..." 
                       value="<?= htmlspecialchars($terminoBusqueda) ?>"
                       class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-full focus:ring-2 focus:ring-primary-dark outline-none transition-shadow">
            </form>

            <div class="flex gap-2 w-full lg:w-auto justify-center sm:justify-end">
                <button onclick="window.location.reload()" class="bg-gray-100 text-gray-600 p-3 rounded-full hover:bg-gray-200 transition shadow-sm" title="Recargar">
                    <i class="fas fa-sync-alt"></i>
                </button>

                <?php if($esAdmin): ?>
                    <a href="exportar_reporte.php?tipo=ad" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-2 px-6 rounded-full shadow flex items-center transition-transform hover:scale-105">
                        <i class="fas fa-file-excel mr-2"></i> Exportar
                    </a>
                    <a href="registro.php" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-6 rounded-full shadow flex items-center transition-transform hover:scale-105">
                        <i class="fas fa-plus mr-2"></i> Nuevo
                    </a>
                <?php endif; ?>

                <a href="dashboard.php" class="bg-gray-800 hover:bg-gray-900 text-white font-bold py-2 px-6 rounded-full shadow flex items-center">
                    <i class="fas fa-th-large mr-2"></i> Menú
                </a>
            </div>
        </div>

        <div class="overflow-hidden shadow-lg rounded-xl border border-gray-100 bg-white">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-primary-dark">
                    <tr class="text-white text-xs uppercase tracking-wider font-bold">
                        <th class="px-4 py-4 text-left">Secretaría / Dirección</th>
                        <th class="px-4 py-4 text-left">Oficio</th>
                        <th class="px-4 py-4 text-left">Fecha Alta</th>
                        <th class="px-4 py-4 text-left">No. Emp</th>
                        <th class="px-4 py-4 text-left">Nombre Completo</th>
                        <th class="px-4 py-4 text-left">Usuario</th>
                        <th class="px-4 py-4 text-left">Password</th>
                        <?php if($puedeEditar): ?>
                            <th class="px-4 py-4 text-center">Acciones</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-100 text-sm">
                    <?php if($result->num_rows > 0): while($row = $result->fetch_assoc()): $id = $row['id']; ?>
                    <tr id="fila_<?= $id ?>" class="table-row-hover transition-colors">
                        <td data-label="Secretaría" class="px-4 py-4 celda-editable" data-campo="secretaria">
                            <span class="editable"><?= htmlspecialchars($row['secretaria']) ?></span>
                            <div class="text-[10px] text-gray-400 uppercase mt-1 hidden sm:block"><?= htmlspecialchars($row['direccion']) ?></div>
                        </td>
                        <td data-label="Dirección" class="px-4 py-4 lg:hidden celda-editable" data-campo="direccion">
                            <span class="editable"><?= htmlspecialchars($row['direccion']) ?></span>
                        </td>
                        <td data-label="Oficio" class="px-4 py-4 font-mono text-xs celda-editable" data-campo="num_oficio">
                            <span class="editable"><?= htmlspecialchars($row['num_oficio']) ?></span>
                        </td>
                        <td data-label="Alta" class="px-4 py-4 text-xs celda-editable" data-campo="fecha_alta">
                            <span class="editable"><?= htmlspecialchars($row['fecha_alta']) ?></span>
                        </td>
                        <td data-label="No. Empleado" class="px-4 py-4 celda-editable" data-campo="num_empleado">
                            <span class="editable"><?= htmlspecialchars($row['num_empleado']) ?></span>
                        </td>
                        <td data-label="Nombre" class="px-4 py-4 font-semibold celda-editable" data-campo="nombres">
                            <span class="editable"><?= htmlspecialchars($row['nombres'] . ' ' . $row['apellido_paterno'] . ' ' . $row['apellido_materno']) ?></span>
                            <input type="hidden" name="apellido_paterno" value="<?= htmlspecialchars($row['apellido_paterno']) ?>">
                            <input type="hidden" name="apellido_materno" value="<?= htmlspecialchars($row['apellido_materno']) ?>">
                        </td>
                        <td data-label="Usuario" class="px-4 py-4 font-bold text-primary-dark celda-editable" data-campo="usuario">
                            <span class="editable"><?= htmlspecialchars($row['usuario']) ?></span>
                        </td>
                        <td data-label="Password" class="px-4 py-4 celda-editable" data-campo="contrasena" data-real-value="<?= htmlspecialchars($row['contrasena']) ?>">
                            <div class="flex items-center gap-2 justify-end lg:justify-start">
                                <span class="editable font-mono text-gray-400 tracking-widest" id="pass_txt_<?= $id ?>">••••••••</span>
                                <button onclick="togglePass(<?= $id ?>)" class="text-gray-400 hover:text-primary-dark transition-colors p-1">
                                    <i class="fas fa-eye" id="pass_icon_<?= $id ?>"></i>
                                </button>
                            </div>
                        </td>
                        <?php if($puedeEditar): ?>
                        <td data-label="Acciones" class="px-4 py-4 text-center whitespace-nowrap space-x-2">
                            <button class="btn-edit text-blue-600 hover:bg-blue-50 p-2 rounded-full transition" onclick="habilitarEdicion(<?= $id ?>)">
                                <i class="fas fa-pencil-alt"></i>
                            </button>
                            <button class="btn-save hidden text-green-600 hover:bg-green-50 p-2 rounded-full transition" onclick="guardarCambios(<?= $id ?>)">
                                <i class="fas fa-check"></i>
                            </button>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr>
                        <td colspan="8" class="px-4 py-10 text-center text-gray-500 italic">No se encontraron registros.</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if($total_paginas > 1): ?>
        <div class="mt-8 flex justify-center gap-2 flex-wrap">
            <?php 
            $delta = 1;
            $q_param = !empty($terminoBusqueda) ? "&q=".urlencode($terminoBusqueda) : "";

            // Botón Anterior
            if ($pagina_actual > 1): ?>
                <a href="?pagina=<?= $pagina_actual - 1 . $q_param ?>" class="w-8 h-8 flex items-center justify-center bg-white text-gray-600 border border-gray-200 rounded-md hover:text-primary-dark transition"><i class="fas fa-chevron-left text-xs"></i></a>
            <?php endif;

            for ($i = 1; $i <= $total_paginas; $i++): 
                if ($i == 1 || $i == $total_paginas || ($i >= $pagina_actual - $delta && $i <= $pagina_actual + $delta)):
                    $activo = ($i == $pagina_actual) ? 'bg-primary-dark text-white shadow-md transform scale-105' : 'bg-white text-gray-600 hover:bg-gray-50';
                ?>
                    <a href="?pagina=<?= $i . $q_param ?>" class="w-8 h-8 flex items-center justify-center text-sm font-medium rounded-md transition border border-gray-200 <?= $activo ?>">
                        <?= $i ?>
                    </a>
                <?php elseif ($i == $pagina_actual - $delta - 1 || $i == $pagina_actual + $delta + 1): ?>
                    <span class="w-8 h-8 flex items-center justify-center text-gray-400 text-sm">...</span>
                <?php endif; 
            endfor;

            // Botón Siguiente
            if ($pagina_actual < $total_paginas): ?>
                <a href="?pagina=<?= $pagina_actual + 1 . $q_param ?>" class="w-8 h-8 flex items-center justify-center bg-white text-gray-600 border border-gray-200 rounded-md hover:text-primary-dark transition"><i class="fas fa-chevron-right text-xs"></i></a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

    </div>

    <script>
    function togglePass(id) {
        const fila = document.getElementById('fila_' + id);
        const celdaPass = fila.querySelector('[data-campo="contrasena"]');
        const spanTexto = document.getElementById('pass_txt_' + id);
        const icono = document.getElementById('pass_icon_' + id);
        const passReal = celdaPass.getAttribute('data-real-value');

        if (spanTexto.textContent.includes('•')) {
            spanTexto.textContent = passReal;
            spanTexto.classList.remove('text-gray-400', 'tracking-widest');
            spanTexto.classList.add('text-primary-dark', 'font-bold');
            icono.classList.replace('fa-eye', 'fa-eye-slash');
        } else {
            spanTexto.textContent = '••••••••';
            spanTexto.classList.add('text-gray-400', 'tracking-widest');
            spanTexto.classList.remove('text-primary-dark', 'font-bold');
            icono.classList.replace('fa-eye-slash', 'fa-eye');
        }
    }

    function habilitarEdicion(id) {
        const fila = document.getElementById('fila_' + id);
        fila.querySelector('.btn-edit').classList.add('hidden');
        fila.querySelector('.btn-save').classList.remove('hidden');

        fila.querySelectorAll('.celda-editable').forEach(td => {
            const campo = td.dataset.campo;
            let val = td.hasAttribute('data-real-value') ? td.getAttribute('data-real-value') : td.querySelector('.editable').textContent.trim();
            
            // Si es la celda de nombre completo en tu DB original, quizás quieras editar solo 'nombres'
            td.innerHTML = `<input type="text" class="input-edit" name="${campo}" value="${val}">`;
        });
    }

    function guardarCambios(id) {
        const fila = document.getElementById('fila_' + id);
        const datos = new FormData();
        datos.append('id', id);

        fila.querySelectorAll('.input-edit').forEach(input => {
            datos.append(input.name, input.value);
        });

        Swal.fire({ title: 'Guardando...', didOpen: () => Swal.showLoading() });

        fetch('guardar_registro.php', { method: 'POST', body: datos })
        .then(r => r.json())
        .then(d => {
            if(d.success) {
                Swal.fire({icon: 'success', title: '¡Actualizado!', toast: true, position: 'top-end', showConfirmButton: false, timer: 1500});
                setTimeout(() => location.reload(), 800);
            } else {
                Swal.fire('Error', d.message, 'error');
            }
        })
        .catch(() => Swal.fire('Error', 'Fallo de conexión', 'error'));
    }
    </script>

</body>
</html>
<?php $conn->close(); ?>