<?php
require_once 'config.php';
session_start();

if (!isset($_SESSION['usuario'])) {
    header("Location: index.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Registrar Nueva Licencia</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/style.css">
    <script src="js/sweetalert2.all.min.js"></script>
    
    <style>
        /* Estilos específicos para el formulario simplificado */
        .card {
            width: 100%;
            max-width: 500px; /* Ancho máximo para que se vea bien en escritorio */
            padding: 30px;
        }
        
        h2 {
            text-align: center;
            color: #721538;
            margin-bottom: 25px;
            border-bottom: 2px solid #eee;
            padding-bottom: 10px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #555;
        }

        input[type="text"], input[type="password"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
            box-sizing: border-box; /* Asegura que el padding no afecte el ancho */
            font-size: 14px;
        }

        input:focus {
            border-color: #721538;
            outline: none;
            box-shadow: 0 0 5px rgba(114, 21, 56, 0.2);
        }

        .btn-submit {
            background-color: #721538;
            color: white;
            padding: 12px;
            border: none;
            border-radius: 5px;
            width: 100%;
            font-size: 16px;
            cursor: pointer;
            margin-top: 20px;
            transition: background 0.3s;
        }

        .btn-submit:hover {
            background-color: #5a102c;
        }

        .nav-buttons {
            margin-top: 20px;
            display: flex;
            justify-content: center;
            gap: 10px;
        }
    </style>
</head>
<body>

<div class="center-container">
    <div class="card"> 
        <h2>➕ Nueva Cuenta Office 365</h2>

        <form id="formNuevaCuenta">
            
            <div class="form-group">
                <label for="direccion">Dirección:</label>
                <input type="text" name="direccion" id="direccion" placeholder="Ej: Dirección de TI" required>
            </div>

            <div class="form-group">
                <label for="area">Área:</label>
                <input type="text" name="area" id="area" placeholder="Ej: Soporte Técnico" required>
            </div>

            <div class="form-group">
                <label for="correo">Correo Electrónico:</label>
                <input type="text" name="correo" id="correo" placeholder="Ej: usuario@playadelcarmen.gob.mx" required>
            </div>

            <div class="form-group">
                <label for="password">Contraseña:</label>
                <input type="text" name="password" id="password" placeholder="Contraseña de la cuenta" required>
            </div>

            <button type="submit" class="btn-submit">Guardar Cuenta</button>
        </form>

        <div class="nav-buttons">
            <button type="button" onclick="window.location.href='consultar_licencias.php';" class="btn-secondary" style="margin:0; width:auto;">
                📂 Ver Lista
            </button>

            <button type="button" onclick="window.location.href='dashboard.php';" class="btn-secondary" style="margin:0; width:auto;">
                📊 Dashboard
            </button>
        </div>
 
    </div>
</div>

<script>
document.getElementById('formNuevaCuenta').addEventListener('submit', function(e) {
    e.preventDefault();

    let datos = new FormData(this);
    // Agregamos una bandera para que el script PHP sepa que es SOLO registro de cuenta
    datos.append('accion', 'registrar_cuenta'); 

    fetch('guardar_licencia.php', {
        method: 'POST',
        body: datos
    })
    .then(response => response.json())
    .then(data => {
        if(data.success) {
            Swal.fire({
                icon: 'success',
                title: '¡Registrado!',
                text: 'La nueva cuenta se ha guardado correctamente.',
                confirmButtonColor: '#721538'
            }).then(() => {
                // Redirigir a la lista o limpiar el formulario
                window.location.href = 'consultar_licencias.php'; 
            });
        } else {
            Swal.fire({ icon: 'error', title: 'Error', text: data.message });
        }
    })
    .catch(error => {
        console.error(error);
        Swal.fire({ icon: 'error', text: 'Error de conexión con el servidor.' });
    });
});
</script>

</body>
</html>