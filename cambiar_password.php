<?php
// cambiar_password.php
require_once 'session_check.php';
require_once 'config.php';
session_start();

// 1. SEGURIDAD: Verifica si el usuario está logueado y si tiene rol de 'admin'
// SOLO UN ADMINISTRADOR DEBERÍA PODER CAMBIAR LA CONTRASEÑA DE CUALQUIER USUARIO
if (!isset($_SESSION['usuario']) || ($_SESSION['rol'] ?? '') !== 'admin') {
    header("Location: index.php"); // Redirige si no es admin o no está logueado
    exit();
}

$conn = get_db_connection();
if (!$conn) {
    die("Error de conexión a la base de datos.");
}

// 2. LÓGICA DE PROCESAMIENTO (Si se envía el formulario)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $id_usuario = trim($_POST['id_usuario'] ?? ''); // El ID del usuario a modificar
    $password_plain = $_POST['password'] ?? '';
    
    // Validación básica de datos
    if (empty($id_usuario) || empty($password_plain)) {
        echo json_encode(['success' => false, 'message' => 'Debe seleccionar un usuario e ingresar una nueva contraseña.']);
        $conn->close();
        exit();
    }

    // 3. Crear hash de la nueva contraseña
    $password_hash = password_hash($password_plain, PASSWORD_DEFAULT);

    // 4. Actualizar la contraseña en la base de datos
    $sql_update = "UPDATE usuarios SET password = ? WHERE id = ?";
    $stmt_update = $conn->prepare($sql_update);
    
    if ($stmt_update) {
        // La 's' es para el password_hash (string), la 'i' es para el id (integer)
        $stmt_update->bind_param("si", $password_hash, $id_usuario);
        
        if ($stmt_update->execute()) {
            // Obtener el nombre de usuario para el mensaje de éxito (opcional, pero más informativo)
            $stmt_name = $conn->prepare("SELECT usuario FROM usuarios WHERE id = ?");
            $stmt_name->bind_param("i", $id_usuario);
            $stmt_name->execute();
            $result_name = $stmt_name->get_result();
            $user_row = $result_name->fetch_assoc();
            $username = $user_row ? $user_row['usuario'] : 'ID: ' . $id_usuario;
            $stmt_name->close();

            echo json_encode(['success' => true, 'message' => "Contraseña del usuario '{$username}' actualizada con éxito."]);
        } else {
            echo json_encode(['success' => false, 'message' => "Error al actualizar la contraseña: " . $stmt_update->error]);
        }
        $stmt_update->close();
    } else {
        echo json_encode(['success' => false, 'message' => "Error al preparar la consulta: " . $conn->error]);
    }

    $conn->close();
    exit();
}

// 3. OBTENER LISTA DE USUARIOS PARA EL SELECT
$sql_select_users = "SELECT id, usuario, rol FROM usuarios ORDER BY usuario ASC";
$result_users = $conn->query($sql_select_users);
$usuarios = [];
if ($result_users) {
    while ($row = $result_users->fetch_assoc()) {
        $usuarios[] = $row;
    }
} else {
    // Manejar error de consulta si es necesario
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Cambiar Contraseña</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/style.css">
    <script src="js/sweetalert2.all.min.js"></script>
    <style>
        /* Estilos similares a alta_usuarios.php */
        body { background-color: #f1f1f1; }
        .center-container { display: flex; justify-content: center; align-items: center; min-height: 80vh; }
        .card { 
            background: white; padding: 40px; border-radius: 10px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); 
            max-width: 400px; width: 90%; 
        }
        h2 { text-align: center; color: #4a0c2c; margin-bottom: 30px; }
        input[type="password"], select {
            width: 100%; padding: 12px; margin-bottom: 20px; border: 1px solid #ccc; border-radius: 6px; box-sizing: border-box;
        }
        button[type="submit"] {
            background-color: #4a0c2c; color: white; padding: 12px 20px; border: none; border-radius: 6px; 
            width: 100%; cursor: pointer; font-size: 16px; transition: background-color 0.3s;
        }
        button[type="submit"]:hover { background-color: #842149; }
        .btn-accion { display: inline-block; padding: 10px 15px; background-color: #ccc; color: #333; text-decoration: none; border-radius: 6px; transition: background-color 0.3s; }
        .btn-accion:hover { background-color: #bbb; }
    </style>
</head>
<body>

<div class="center-container">
    <div class="card"> 
        <h2>🔑 Cambiar Contraseña de Usuario</h2>
        
        <form id="formCambiarPassword" method="POST">
            
            <select name="id_usuario" required>
                <option value="" disabled selected>Selecciona el Usuario a Modificar</option>
                <?php foreach ($usuarios as $user): ?>
                    <option value="<?= htmlspecialchars($user['id']) ?>">
                        <?= htmlspecialchars($user['usuario']) ?> (Rol: <?= htmlspecialchars($user['rol']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
            
            <input type="password" name="password" placeholder="Nueva Contraseña" required>

            <button type="submit">Actualizar Contraseña</button>
        </form>

        <div style="margin-top:20px; text-align:center;">
            <a href="dashboard.php" class="btn-accion">Menú Principal</a>
        </div>

    </div>
</div>

<script>
document.getElementById('formCambiarPassword').addEventListener('submit', function(e) {
    e.preventDefault();

    let datos = new FormData(this);
    const btnSubmit = this.querySelector('button[type="submit"]');
    btnSubmit.disabled = true; // Deshabilita el botón

    fetch('cambiar_password.php', {
        method: 'POST',
        body: datos
    })
    .then(response => response.json())
    .then(data => {
        if(data.success) {
            Swal.fire({
                icon: 'success',
                title: '¡Éxito!',
                text: data.message,
                confirmButtonColor: '#4a0c2c'
            }).then(() => {
                // Limpiar solo el campo de contraseña, mantener el usuario seleccionado si es necesario
                document.querySelector('input[name="password"]').value = '';
            });
        } else {
            Swal.fire({ 
                icon: 'error', 
                title: 'Error', 
                text: data.message 
            });
        }
        btnSubmit.disabled = false; // Habilita el botón
    })
    .catch(error => {
        console.error(error);
        Swal.fire({ 
            icon: 'error', 
            title: 'Error de Conexión', 
            text: 'No se pudo contactar al servidor.' 
        });
        btnSubmit.disabled = false; // Habilita el botón
    });
});
</script>

</body>
</html>