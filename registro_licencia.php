<?php
require_once 'session_check.php';
require_once 'config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. SEGURIDAD: Verifica si el usuario está logueado y si su rol está permitido
$roles_permitidos = ['admin', 'tecnico'];

if (!isset($_SESSION['usuario']) || !in_array($_SESSION['rol'] ?? '', $roles_permitidos)) {
    header("Location: index.php"); // Redirige si no está logueado o su rol no está en la lista
    exit();
}

// --- PASO 1: Cargar solo las SECRETARÍAS al iniciar ---
$conn = get_db_connection();
$opciones_secretaria = "";

if ($conn) {
    // Asegúrate de que tu tabla se llame 'Secretarias' y la columna de nombre sea correcta
    // Ajusté 'nombres' según tu otro archivo, si es 'nombres_secretaria' cámbialo aquí.
    $query = "SELECT id_secretaria, names_secretaria FROM Secretarias ORDER BY names_secretaria ASC"; 
    // NOTA: En tu archivo acciones vi 'nombres' y 'nombres_secretaria', verifica cuál es el real. 
    // Aquí puse 'names_secretaria' como ejemplo genérico, o usa 'nombres' si así está en tu BD.
    
    // Si tu tabla usa 'nombres' (según vi en acciones_direcciones.php):
    $query = "SELECT id_secretaria, nombres FROM Secretarias ORDER BY nombres ASC";

    $result = $conn->query($query);

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            // Ajusta aquí también si la columna se llama diferente
            $nombre_sec = $row['nombres'] ?? $row['names_secretaria']; 
            $opciones_secretaria .= '<option value="' . $row['id_secretaria'] . '">' . $nombre_sec . '</option>';
        }
    }
    $conn->close();
}
// ----------------------------------------------------
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Registrar Nueva Licencia</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/style.css">
    <script src="js/sweetalert2.all.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <style>
        .card { width: 100%; max-width: 500px; padding: 30px; }
        h2 { text-align: center; color: #721538; margin-bottom: 25px; border-bottom: 2px solid #eee; padding-bottom: 10px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; color: #555; }
        input[type="text"], input[type="password"], select {
            width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 5px;
            box-sizing: border-box; font-size: 14px; background-color: white; height: 42px;
        }
        input:focus, select:focus { border-color: #721538; outline: none; box-shadow: 0 0 5px rgba(114, 21, 56, 0.2); }
        .btn-submit {
            background-color: #721538; color: white; padding: 12px; border: none; border-radius: 5px;
            width: 100%; font-size: 16px; cursor: pointer; margin-top: 20px; transition: background 0.3s;
        }
        .btn-submit:hover { background-color: #5a102c; }
        .nav-buttons { margin-top: 20px; display: flex; justify-content: center; gap: 10px; }
        
        select:disabled { background-color: #e9ecef; cursor: not-allowed; opacity: 0.7; }
    </style>
</head>
<body>

<div class="center-container">
    <div class="card"> 
        <h2>➕ Nueva Cuenta Office 365</h2>

        <form id="formNuevaCuenta">
            
            <div class="form-group">
                <label for="id_secretaria">Secretaría:</label>
                <select name="id_secretaria" id="id_secretaria" required>
                    <option value="">-- Selecciona una Secretaría --</option>
                    <?php echo $opciones_secretaria; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="id_direccion">Dirección:</label>
                <select name="id_direccion" id="id_direccion" required disabled>
                    <option value="">-- Primero elige Secretaría --</option>
                </select>
            </div>

            <div class="form-group">
                <label for="area">Área / Departamento:</label>
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
$(document).ready(function() {
    
    // --- LÓGICA DE CARGA DINÁMICA ---
    $('#id_secretaria').change(function() {
        var secretariaID = $(this).val();
        var $selectDir = $('#id_direccion');

        // Limpiamos el select de direcciones
        $selectDir.empty().append('<option value="">-- Cargando... --</option>');
        $selectDir.prop('disabled', true);

        if(secretariaID) {
            // Llamada AJAX a tu archivo centralizado
            $.ajax({
                type: 'GET',
                url: 'acciones_direcciones.php',
                data: { 
                    action: 'get_direcciones_por_secretaria', // <--- LA ACCIÓN NUEVA
                    id_secretaria: secretariaID 
                },
                dataType: 'json',
                success: function(response) {
                    $selectDir.empty(); // Limpiamos "Cargando..."
                    
                    if (response.length > 0) {
                        $selectDir.append('<option value="">-- Selecciona una Dirección --</option>');
                        $.each(response, function(i, item) {
                            // Ajusta aquí 'nombres_direcciones' si tu columna se llama distinto en el JSON
                            $selectDir.append(new Option(item.nombres_direcciones, item.id_direcciones));
                        });
                        $selectDir.prop('disabled', false); // Habilitamos
                    } else {
                        $selectDir.append('<option value="">No hay direcciones en esta secretaría</option>');
                    }
                },
                error: function() {
                    $selectDir.empty().append('<option value="">Error al cargar direcciones</option>');
                }
            }); 
        } else {
            // Si des-selecciona
            $selectDir.empty().append('<option value="">-- Primero elige Secretaría --</option>');
            $selectDir.prop('disabled', true);
        }
    });

    // --- GUARDADO DEL FORMULARIO ---
    document.getElementById('formNuevaCuenta').addEventListener('submit', function(e) {
        e.preventDefault();

        let datos = new FormData(this);
        // Enviamos 'id_direccion' automáticamente porque está en el select con name="id_direccion"
        
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
                    text: 'Cuenta guardada correctamente.',
                    confirmButtonColor: '#721538'
                }).then(() => {
                    window.location.href = 'consultar_licencias.php'; 
                });
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: data.message });
            }
        })
        .catch(error => {
            console.error(error);
            Swal.fire({ icon: 'error', text: 'Error de conexión.' });
        });
    });
});
</script>

</body>
</html>