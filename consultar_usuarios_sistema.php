<?php
// consultar_usuarios_sistema.php
require_once 'session_check.php';
require_once 'config.php';

// 1. SEGURIDAD: Solo Admin o Masterweb puede ver la lista de usuarios del sistema
if (!isset($_SESSION['usuario']) || ($_SESSION['rol'] !== 'admin' && $_SESSION['rol'] !== 'masterweb')) {
    header("Location: dashboard.php");
    exit();
}

$conn = get_db_connection();
if (!$conn) {
    die("Error de conexión a la base de datos.");
}

// 2. ENDPOINT AJAX PARA ACCIONES (EDITAR / ELIMINAR)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];
    $userId = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    
    if ($userId <= 0) {
        echo json_encode(['success' => false, 'error' => 'ID de usuario inválido.']);
        $conn->close();
        exit;
    }
    
    if ($action === 'delete') {
        // Impedir que un administrador se elimine a sí mismo
        $stmt_check = $conn->prepare("SELECT usuario FROM usuarios WHERE id = ?");
        $stmt_check->bind_param("i", $userId);
        $stmt_check->execute();
        $res_check = $stmt_check->get_result();
        if ($row_check = $res_check->fetch_assoc()) {
            if ($row_check['usuario'] === $_SESSION['usuario']) {
                echo json_encode(['success' => false, 'error' => 'No puedes eliminar tu propia cuenta en sesión activa.']);
                $stmt_check->close();
                $conn->close();
                exit;
            }
        }
        $stmt_check->close();
        
        $stmt = $conn->prepare("DELETE FROM usuarios WHERE id = ?");
        $stmt->bind_param("i", $userId);
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => $stmt->error]);
        }
        $stmt->close();
        $conn->close();
        exit;
    }
    
    if ($action === 'edit') {
        $new_username = trim($_POST['usuario'] ?? '');
        $new_role = $_POST['rol'] ?? '';
        $new_password = $_POST['password'] ?? '';
        
        if (empty($new_username) || empty($new_role)) {
            echo json_encode(['success' => false, 'error' => 'El nombre de usuario y el rol son obligatorios.']);
            $conn->close();
            exit;
        }
        
        // Verificar roles válidos según el ENUM de la BD
        $roles_validos = ['admin', 'tecnico', 'invitado', 'masterweb', 'redes'];
        if (!in_array($new_role, $roles_validos)) {
            echo json_encode(['success' => false, 'error' => 'Rol seleccionado no es válido en el sistema.']);
            $conn->close();
            exit;
        }
        
        // Verificar si el nombre de usuario ya existe para otro ID
        $stmt_dup = $conn->prepare("SELECT id FROM usuarios WHERE usuario = ? AND id != ?");
        $stmt_dup->bind_param("si", $new_username, $userId);
        $stmt_dup->execute();
        $stmt_dup->store_result();
        if ($stmt_dup->num_rows > 0) {
            echo json_encode(['success' => false, 'error' => "El nombre de usuario '$new_username' ya existe."]);
            $stmt_dup->close();
            $conn->close();
            exit;
        }
        $stmt_dup->close();
        
        if (!empty($new_password)) {
            // Actualizar incluyendo contraseña nueva
            $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE usuarios SET usuario = ?, password = ?, rol = ? WHERE id = ?");
            $stmt->bind_param("sssi", $new_username, $new_password_hash, $new_role, $userId);
        } else {
            // Actualizar sin tocar la contraseña actual
            $stmt = $conn->prepare("UPDATE usuarios SET usuario = ?, rol = ? WHERE id = ?");
            $stmt->bind_param("ssi", $new_username, $new_role, $userId);
        }
        
        if ($stmt->execute()) {
            // Si el admin editó su propio usuario actual, actualizar la sesión
            if ($userId === (int)$_SESSION['usuario_id']) {
                $_SESSION['usuario'] = $new_username;
                $_SESSION['rol'] = $new_role;
            }
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => $stmt->error]);
        }
        $stmt->close();
        $conn->close();
        exit;
    }
}

// 3. OBTENER LISTADO DE USUARIOS
$sql = "SELECT id, usuario, rol, created_at FROM usuarios ORDER BY id DESC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Accesos | SISGI</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="js/tailwindcss.js"></script>
    <link rel="stylesheet" href="css/all.min.css">
    <script src="js/sweetalert2.all.min.js"></script>
    
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'brand': '#721538',
                        'brand-dark': '#500e26',
                        'brand-light': '#9d2449',
                    }
                }
            }
        }
    </script>
    <style>
        body { background-color: #d6d1ca; font-family: 'Segoe UI', system-ui, sans-serif; }
        
        /* Estilos para SweetAlert */
        .swal-field-label { display: block; text-align: left; font-size: 0.75rem; font-weight: bold; color: #555; margin-bottom: 2px; text-transform: uppercase; }
        .swal-custom-input { width: 100% !important; margin: 0 0 12px 0 !important; font-size: 0.9rem !important; height: 40px !important; border: 1px solid #ccc; border-radius: 6px; padding: 0 10px; }
    </style>
</head>
<body class="bg-[#d6d1ca] min-h-screen p-4 sm:p-8">

    <div class="max-w-5xl mx-auto space-y-6">
        
        <!-- ENCABEZADO -->
        <div class="flex flex-col sm:flex-row justify-between items-center bg-white p-6 rounded-2xl shadow-md border-l-8 border-brand">
            <div>
                <h2 class="text-2xl sm:text-3xl font-extrabold text-brand flex items-center gap-3">
                    <i class="fas fa-users-cog"></i> Gestión de Accesos
                </h2>
                <p class="text-sm text-gray-500 mt-1">
                    Administra las cuentas de usuario con acceso al panel administrativo, sus roles y permisos en el sistema.
                </p>
            </div>
            <div class="mt-4 sm:mt-0 flex gap-2 w-full sm:w-auto">
                <a href="alta_usuarios.php" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-2.5 px-6 rounded-full shadow transition flex items-center justify-center gap-2 flex-grow sm:flex-grow-0">
                    <i class="fas fa-user-plus"></i> Registrar Nuevo
                </a>
                <a href="dashboard.php" class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-2.5 px-6 rounded-full shadow transition flex items-center justify-center gap-2">
                    <i class="fas fa-home"></i> Volver
                </a>
            </div>
        </div>

        <!-- TABLA DE USUARIOS -->
        <div class="bg-white rounded-2xl shadow-lg overflow-hidden border border-gray-200">
            <div class="p-6 border-b border-gray-100 flex justify-between items-center bg-gray-50">
                <h3 class="text-lg font-bold text-gray-800">Usuarios con Acceso al Dashboard</h3>
                <span class="bg-brand text-white px-3 py-1 rounded-full text-xs font-bold">
                    <?= $result ? $result->num_rows : 0 ?> Usuarios Registrados
                </span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead class="bg-brand text-white uppercase text-xs font-bold">
                        <tr>
                            <th class="px-6 py-4" width="10%">ID</th>
                            <th class="px-6 py-4" width="35%">Usuario</th>
                            <th class="px-6 py-4" width="25%">Rol / Permisos</th>
                            <th class="px-6 py-4 text-center" width="15%">Estado</th>
                            <th class="px-6 py-4 text-center" width="15%">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        <?php if ($result && $result->num_rows > 0): ?>
                            <?php while($row = $result->fetch_assoc()): ?>
                            <tr id="row-<?= $row['id'] ?>" class="hover:bg-indigo-50/40 transition">
                                <td class="px-6 py-4 font-mono text-gray-400">#<?= $row['id'] ?></td>
                                <td class="px-6 py-4 font-bold text-gray-800"><?= htmlspecialchars($row['usuario']) ?></td>
                                <td class="px-6 py-4">
                                    <?php 
                                    $badge_class = 'bg-gray-100 text-gray-700';
                                    if ($row['rol'] === 'admin') $badge_class = 'bg-purple-100 text-purple-700';
                                    elseif ($row['rol'] === 'masterweb') $badge_class = 'bg-cyan-100 text-cyan-700';
                                    elseif ($row['rol'] === 'tecnico') $badge_class = 'bg-emerald-100 text-emerald-700';
                                    elseif ($row['rol'] === 'redes') $badge_class = 'bg-orange-100 text-orange-700';
                                    ?>
                                    <span class="px-3 py-1 rounded-full text-xs font-bold <?= $badge_class ?>">
                                        <?= strtoupper($row['rol']) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <span class="inline-flex items-center">
                                        <span class="inline-block w-2.5 h-2.5 rounded-full bg-green-500 mr-2 animate-pulse"></span>
                                        <span class="text-sm font-semibold text-gray-600">Activo</span>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-center whitespace-nowrap space-x-1">
                                    <button onclick="editarUsuario(<?= $row['id'] ?>, '<?= htmlspecialchars(addslashes($row['usuario'])) ?>', '<?= $row['rol'] ?>')" class="w-8 h-8 rounded-md bg-blue-50 text-blue-600 hover:bg-blue-600 hover:text-white transition inline-flex items-center justify-center shadow-sm cursor-pointer" title="Editar Usuario">
                                        <i class="fas fa-pencil-alt"></i>
                                    </button>
                                    <button onclick="eliminarUsuario(<?= $row['id'] ?>, '<?= htmlspecialchars(addslashes($row['usuario'])) ?>')" class="w-8 h-8 rounded-md bg-red-50 text-red-600 hover:bg-red-600 hover:text-white transition inline-flex items-center justify-center shadow-sm cursor-pointer" title="Eliminar Usuario">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center py-10 text-gray-400">No hay usuarios del sistema registrados.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

<script>
function editarUsuario(id, usuario, rol) {
    const htmlContent = `
        <div class="text-left mt-4 text-sm">
            <label class="swal-field-label">Nombre de Usuario (Login)</label>
            <input id="edit-usuario" class="swal-custom-input" value="${usuario}" placeholder="Ej. admin_user" required>
            
            <label class="swal-field-label">Nueva Contraseña (Opcional)</label>
            <input type="password" id="edit-password" class="swal-custom-input" placeholder="Dejar en blanco para conservar la actual">
            
            <label class="swal-field-label">Rol del Sistema</label>
            <select id="edit-rol" class="swal-custom-input" required>
                <option value="tecnico" ${rol === 'tecnico' ? 'selected' : ''}>Técnico (tecnico)</option>
                <option value="admin" ${rol === 'admin' ? 'selected' : ''}>Administrador (admin)</option>
                <option value="masterweb" ${rol === 'masterweb' ? 'selected' : ''}>Master Web (masterweb)</option>
                <option value="redes" ${rol === 'redes' ? 'selected' : ''}>Redes (redes)</option>
                <option value="invitado" ${rol === 'invitado' ? 'selected' : ''}>Invitado (invitado)</option>
            </select>
        </div>
    `;

    Swal.fire({
        title: 'Editar Usuario del Sistema',
        html: htmlContent,
        showCancelButton: true,
        confirmButtonColor: '#721538',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Guardar Cambios',
        cancelButtonText: 'Cancelar',
        preConfirm: () => {
            const userVal = document.getElementById('edit-usuario').value.trim();
            const rolVal = document.getElementById('edit-rol').value;
            const passVal = document.getElementById('edit-password').value;
            
            if (!userVal) {
                Swal.showValidationMessage('El nombre de usuario es obligatorio.');
                return false;
            }
            return { usuario: userVal, rol: rolVal, password: passVal };
        }
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Guardando...',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading()
            });

            const formData = new FormData();
            formData.append('action', 'edit');
            formData.append('id', id);
            formData.append('usuario', result.value.usuario);
            formData.append('rol', result.value.rol);
            formData.append('password', result.value.password);

            fetch('consultar_usuarios_sistema.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                Swal.close();
                if (data.success) {
                    Swal.fire({
                        title: '¡Actualizado!',
                        text: 'El usuario se ha actualizado correctamente.',
                        icon: 'success',
                        timer: 1500,
                        showConfirmButton: false
                    }).then(() => {
                        location.reload(); // Recarga para actualizar lista y sesiones
                    });
                } else {
                    Swal.fire('Error', data.error || 'Ocurrió un error al actualizar.', 'error');
                }
            })
            .catch(err => {
                Swal.close();
                console.error(err);
                Swal.fire('Error', 'No se pudo conectar al servidor.', 'error');
            });
        }
    });
}

function eliminarUsuario(id, usuario) {
    Swal.fire({
        title: '¿Eliminar cuenta de acceso?',
        html: `¿Estás seguro de eliminar el acceso para <b>"${usuario}"</b>?<br><span class="text-xs text-red-500 font-semibold"><i class="fas fa-exclamation-triangle"></i> Esta acción retirará los permisos de inicio de sesión de esta cuenta de inmediato.</span>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Eliminando...',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading()
            });

            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('id', id);

            fetch('consultar_usuarios_sistema.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                Swal.close();
                if (data.success) {
                    Swal.fire({
                        title: '¡Eliminado!',
                        text: 'El usuario ha sido removido del sistema.',
                        icon: 'success',
                        timer: 1500,
                        showConfirmButton: false
                    });
                    
                    // Remover fila de la tabla
                    const row = document.getElementById(`row-${id}`);
                    if (row) {
                        row.classList.add('transition-all', 'duration-500', 'opacity-0', 'scale-95');
                        setTimeout(() => { row.remove(); }, 500);
                    }
                } else {
                    Swal.fire('Error', data.error || 'No se pudo eliminar al usuario.', 'error');
                }
            })
            .catch(err => {
                Swal.close();
                console.error(err);
                Swal.fire('Error', 'No se pudo conectar al servidor.', 'error');
            });
        }
    });
}
</script>

</body>
</html>
<?php
$conn->close();
?>