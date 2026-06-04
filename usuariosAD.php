<?php
// usuarios.php
require_once 'config.php';

session_start();

// --- CONTROL DE ACCESO ESTRICTO ---
// Solo permitimos la entrada a usuarios logueados que sean 'masterweb' o 'admin'
if (!isset($_SESSION['usuario']) || !in_array($_SESSION['rol'], ['masterweb', 'admin'])) {
    header("Location: index.php");
    exit();
}

$conn = get_db_connection();
if ($conn === null) {
    die("Error de conexión a la base de datos.");
}

$mensaje = "";
$tipo_mensaje = "";

// --- PROCESAR ACTUALIZACIÓN DE ROL (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_role') {
    $user_id = intval($_POST['user_id'] ?? 0);
    $nuevo_rol = trim($_POST['rol'] ?? '');

    // Roles permitidos según tu estructura institucional
    $roles_permitidos = ['admin', 'tecnico', 'recepcion', 'masterweb', 'redes', 'usuario'];

    if (in_array($nuevo_rol, $roles_permitidos) && $user_id > 0) {
        
        // Evitar que un 'admin' rebaje o altere a un 'masterweb' por seguridad
        if ($_SESSION['rol'] === 'admin' && $nuevo_rol === 'masterweb') {
            $mensaje = "No tienes privilegios para asignar el rol de Masterweb.";
            $tipo_mensaje = "error";
        } else {
            $stmt = $conn->prepare("UPDATE usuarios SET rol = ? WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param("si", $nuevo_rol, $user_id);
                if ($stmt->execute()) {
                    $mensaje = "Rol actualizado correctamente.";
                    $tipo_mensaje = "success";
                } else {
                    $mensaje = "Error al actualizar el rol en la base de datos.";
                    $tipo_mensaje = "error";
                }
                $stmt->close();
            }
        }
    } else {
        $mensaje = "Rol no válido o datos incorrectos.";
        $tipo_mensaje = "error";
    }
}

// --- OBTENER LISTA DE USUARIOS ---
$usuarios = [];
$resultado = $conn->query("SELECT id, usuario, rol FROM usuarios ORDER BY usuario ASC");
if ($resultado) {
    while ($fila = $resultado->fetch_assoc()) {
        $usuarios[] = $fila;
    }
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Usuarios y Roles | SATQ</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/estilos.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="bg-light">

    <nav class="navbar navbar-dark bg-dark shadow-sm mb-4">
        <div class="container">
            <span class="navbar-brand mb-0 h1">
                <i class="fas fa-users-cog me-2"></i> Panel de Administración SATQ
            </span>
            <span class="text-light text-sm">
                Conectado como: <strong><?= htmlspecialchars($_SESSION['nombre_real'] ?? $_SESSION['usuario']) ?></strong> (<?= htmlspecialchars($_SESSION['rol']) ?>)
            </span>
        </div>
    </nav>

    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-10">
                
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="text-secondary h4">Control de Roles (Directorio Activo)</h2>
                    <a href="/dashboard.php" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-arrow-left me-1"></i> Volver al Dashboard
                    </a>
                </div>

                <?php if ($mensaje): ?>
                    <div class="alert <?= $tipo_mensaje === 'success' ? 'alert-success' : 'alert-danger' ?> alert-dismissible fade show" role="alert">
                        <i class="fas <?= $tipo_mensaje === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?> me-2"></i>
                        <?= htmlspecialchars($mensaje) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <div class="card shadow-sm border-0 rounded-3">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-dark">
                                    <tr>
                                        <th class="ps-4" style="width: 10%">ID</th>
                                        <th style="width: 45%">Usuario de Dominio</th>
                                        <th style="width: 25%">Rol Asignado</th>
                                        <th class="text-center pe-4" style="width: 20%">Acción</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($usuarios) === 0): ?>
                                        <tr>
                                            <td colspan="4" class="text-center text-muted py-4">No hay usuarios auto-registrados todavía. Sin accesos previos.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($usuarios as $user): ?>
                                            <tr>
                                                <td class="ps-4 text-monospace text-muted">#<?= $user['id'] ?></td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="bg-secondary text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 35px; height: 35px;">
                                                            <i class="fas fa-user text-xs"></i>
                                                        </div>
                                                        <div>
                                                            <span class="fw-bold text-dark"><?= htmlspecialchars($user['usuario']) ?></span>
                                                    <span class="text-muted d-block text-xs">@qroo.gob.mx</span>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <?php
                                                    $badge_class = 'bg-secondary';
                                                    if ($user['rol'] === 'masterweb') $badge_class = 'bg-danger';
                                                    elseif ($user['rol'] === 'admin') $badge_class = 'bg-primary';
                                                    elseif ($user['rol'] === 'tecnico') $badge_class = 'bg-success';
                                                    elseif ($user['rol'] === 'redes') $badge_class = 'bg-warning text-dark';
                                                    elseif ($user['rol'] === 'recepcion') $badge_class = 'bg-info text-dark';
                                                    ?>
                                                    <span class="badge <?= $badge_class ?> px-2 py-1.5 text-uppercase">
                                                        <?= htmlspecialchars($user['rol'] ?: 'usuario') ?>
                                                    </span>
                                                </td>
                                                <td class="text-center pe-4">
                                                    <form method="POST" action="" class="d-flex gap-2 justify-content-center">
                                                        <input type="hidden" name="action" value="update_role">
                                                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                        
                                                        <select name="rol" class="form-select form-select-sm" style="max-width: 130px;" aria-label="Seleccionar Rol">
                                                            <option value="usuario" <?= $user['rol'] === 'usuario' ? 'selected' : '' ?>>Usuario</option>
                                                            <option value="admin" <?= $user['rol'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                                                            <option value="tecnico" <?= $user['rol'] === 'tecnico' ? 'selected' : '' ?>>Técnico</option>
                                                            <option value="recepcion" <?= $user['rol'] === 'recepcion' ? 'selected' : '' ?>>Recepción</option>
                                                            <option value="masterweb" <?= $user['rol'] === 'masterweb' ? 'selected' : '' ?>>Masterweb</option>
                                                            <option value="redes" <?= $user['rol'] === 'redes' ? 'selected' : '' ?>>Redes</option>
                                                        </select>
                                                        
                                                        <button type="submit" class="btn btn-dark btn-sm" title="Guardar cambios">
                                                            <i class="fas fa-save"></i>
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>