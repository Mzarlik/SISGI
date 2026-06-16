<?php
// alta_usuarios.php
require_once 'session_check.php';
require_once 'config.php';

// 1. SEGURIDAD: Verifica si el usuario está logueado y si tiene rol de 'admin'
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
    
    $usuario = trim($_POST['usuario'] ?? '');
    $password_plain = $_POST['password'] ?? '';
    $rol = $_POST['rol'] ?? 'tecnico'; // Rol por defecto

    // Validación básica de datos
    if (empty($usuario) || empty($password_plain) || empty($rol)) {
        echo json_encode(['success' => false, 'message' => 'Todos los campos son obligatorios.']);
        $conn->close();
        exit();
    }

    // 3. Verificar si el usuario ya existe
    $stmt_check = $conn->prepare("SELECT id FROM usuarios WHERE usuario = ?");
    $stmt_check->bind_param("s", $usuario);
    $stmt_check->execute();
    $stmt_check->store_result();

    if ($stmt_check->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => "El usuario '{$usuario}' ya existe."]);
        $stmt_check->close();
        $conn->close();
        exit();
    }
    $stmt_check->close();

    // 4. Crear hash de la contraseña y registrar
    $password_hash = password_hash($password_plain, PASSWORD_DEFAULT);

    // NOTA IMPORTANTE: Asegúrate de que tu tabla 'usuarios' tenga las columnas 'usuario', 'password' y 'rol'.
    $sql_insert = "INSERT INTO usuarios (usuario, password, rol) VALUES (?, ?, ?)";
    $stmt_insert = $conn->prepare($sql_insert);
    
    if ($stmt_insert) {
        $stmt_insert->bind_param("sss", $usuario, $password_hash, $rol);
        
        if ($stmt_insert->execute()) {
            echo json_encode(['success' => true, 'message' => "Usuario '{$usuario}' registrado con éxito."]);
        } else {
            echo json_encode(['success' => false, 'message' => "Error al registrar: " . $stmt_insert->error]);
        }
        $stmt_insert->close();
    } else {
        echo json_encode(['success' => false, 'message' => "Error al preparar la consulta: " . $conn->error]);
    }

    $conn->close();
    exit();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Alta de Usuarios</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/style.css">
    <script src="js/sweetalert2.all.min.js"></script>
    <style>
        /* Estilos generales */
        body { background-color: #f1f1f1; }
        .center-container { display: flex; justify-content: center; align-items: center; min-height: 80vh; }
        .card { 
            background: white; padding: 40px; border-radius: 10px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); 
            max-width: 400px; width: 90%; 
        }
        h2 { text-align: center; color: #4a0c2c; margin-bottom: 30px; }
        input[type="text"], input[type="password"], select {
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
        <h2>👥 Alta de Nuevo Usuario</h2>
        
        <form id="formAltaUsuario" method="POST">
            
            <input type="text" name="usuario" placeholder="Nombre de Usuario (Login)" required>
            <input type="password" name="password" placeholder="Contraseña Inicial" required>

            <select name="rol" required>
                <option value="" disabled selected>Selecciona Rol</option>
                <option value="tecnico">Técnico (tecnico)</option>
                <option value="admin">Administrador (admin)</option>
                <option value="masterweb">Master Web (masterweb)</option>
                <option value="redes">Redes (redes)</option>
                <option value="invitado">Invitado (invitado)</option>
            </select>

            <button type="submit">Registrar Usuario</button>
        </form>

        <div style="margin-top:20px; text-align:center;">
            <a href="consultar_usuarios_sistema.php" class="btn-accion" style="margin-right:10px;">Consultar Usuarios</a>
            <a href="dashboard.php" class="btn-accion">Menú Principal</a>
        </div>

    </div>
</div>

<script>
document.getElementById('formAltaUsuario').addEventListener('submit', function(e) {
    e.preventDefault();

    let datos = new FormData(this);
    const btnSubmit = this.querySelector('button[type="submit"]');
    btnSubmit.disabled = true; // Deshabilita el botón

    fetch('alta_usuarios.php', {
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
                // Limpiar formulario al tener éxito
                document.getElementById('formAltaUsuario').reset();
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