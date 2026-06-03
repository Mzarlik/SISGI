<?php require_once 'config.php'; session_start(); require_once 'session_check.php';
if (!isset($_SESSION['usuario'])) { header("Location: index.php"); exit(); }
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Registro NAS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/style.css">
    <script src="js/sweetalert2.all.min.js"></script>
    
    <style>
        /* --- ESTILOS GENERALES --- */
        body {
            background-color: #d6d1ca;
            font-family: 'Segoe UI', sans-serif;
            margin: 0;
            padding: 0;
            min-height: 100vh;
            display: block !important;
        }
        
        .center-container {
            width: 95%;
            /* AJUSTE SOLICITADO: Ancho de 600px */
            max-width: 600px; 
            margin: 40px auto !important;
            background: transparent;
            
            box-sizing: border-box;
            padding-bottom: 40px;
        }
        
        .card {
            background-color: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            padding: 40px; /* Un poco más de aire interno */
            box-sizing: border-box;
        }

        h2 { color: #721538; text-align: center; margin-bottom: 30px; margin-top: 0; }

        /* Estilos de Formulario */
        label { display: block; margin-bottom: 5px; color: #555; font-weight: bold; font-size: 0.9em; }
        
        input[type="text"], textarea {
            width: 100%;
            padding: 12px;
            margin-bottom: 15px;
            border: 1px solid #ccc;
            border-radius: 6px;
            box-sizing: border-box;
            font-size: 16px;
            background-color: #fcfcfc;
            font-family: inherit;
        }

        /* Sistema de Grilla (2 Columnas) */
        .form-row {
            display: flex;
            gap: 15px; /* Espacio entre columnas */
        }
        .form-col {
            flex: 1;
        }

        /* Botón Guardar (Principal - Vino) */
        button[type="submit"] {
            width: 100%;
            padding: 15px;
            background-color: #721538;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 18px;
            font-weight: bold;
            cursor: pointer;
            transition: background 0.3s;
            margin-top: 15px;
        }
        button[type="submit"]:hover { background-color: #5d112d; }

        /* Botones de Navegación */
        .button-group { display: flex; gap: 10px; margin-top: 25px; }
        
        /* AJUSTE SOLICITADO: Botones Grises (o Vino si cambias el color aquí) */
        .btn-accion {
            flex: 1; 
            padding: 12px; 
            background-color: #555; /* Gris para acciones secundarias */
            color: white;
            border: none; 
            border-radius: 6px; 
            cursor: pointer; 
            text-align: center;
            font-size: 15px; 
            transition: 0.3s; 
            text-decoration: none; 
            font-weight: bold;
        }
        .btn-accion:hover { background-color: #333; }
        
        /* Si prefieres que el de Consultar sea Vino, descomenta esto: */
        /* .btn-consultar { background-color: #721538; } */

        .logout { text-align: center; margin-top: 20px; }
        .logout a { color: #721538; text-decoration: none; font-weight: bold; }

        /* Móvil */
        @media (max-width: 768px) {
            .form-row { flex-direction: column; gap: 0; }
            .card { padding: 20px; }
            .button-group { flex-direction: column; }
        }
    </style>
</head>
<body>

<div class="center-container">
    <div class="card">
        <h2>📂 Nueva Carpeta NAS</h2>
        
        <form id="formNas">
            
            <div class="form-row">
                <div class="form-col">
                    <label>Ubicación / Depto:</label>
                    <input type="text" name="ubicacion" placeholder="Ej: Ingresos" required>
                </div>
                <div class="form-col">
                    <label>Nombre Carpeta:</label>
                    <input type="text" name="nombre_carpeta" placeholder="Ej: Respaldos" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-col">
                    <label>IP Servidor:</label>
                    <input type="text" name="ip_servidor" placeholder="Ej: 172.16.10.85">
                </div>
                <div class="form-col">
                    <label>Usuario:</label>
                    <input type="text" name="usuario" placeholder="Usuario red">
                </div>
            </div>

            <label>Contraseña:</label>
            <input type="text" name="password" placeholder="Contraseña de acceso">
            
            <label>Observaciones:</label>
            <textarea name="observaciones" placeholder="Detalles adicionales..." rows="3"></textarea>
            
            <button type="submit">Guardar Carpeta</button>
        </form>

        <div class="button-group">
            <a href="consultar_nas.php" class="btn-accion btn-consultar">📂 Consultar Lista</a>
            <a href="dashboard.php" class="btn-accion">🏠 Menú Principal</a>
        </div>

        <p class="logout">
            <a href="logout.php">Cerrar sesión</a>
        </p>
    </div>
</div>

<script>
document.getElementById('formNas').addEventListener('submit', function(e){
    e.preventDefault();
    const formData = new FormData(this);
    
    fetch('guardar_nas.php', { 
        method: 'POST', 
        body: formData 
    })
    .then(response => {
        if (!response.ok) { throw new Error("Error en la red o servidor"); }
        return response.text(); 
    })
    .then(text => {
        try {
            return JSON.parse(text); 
        } catch (error) {
            console.error("Respuesta inválida:", text);
            throw new Error("El servidor devolvió una respuesta inválida.");
        }
    })
    .then(data => {
        if(data.success) {
            Swal.fire({
                icon: 'success',
                title: '¡Guardado!',
                text: 'Carpeta registrada correctamente',
                confirmButtonColor: '#721538'
            }).then(() => document.getElementById('formNas').reset());
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: data.message,
                confirmButtonColor: '#721538'
            });
        }
    })
    .catch(error => {
        console.error(error);
        Swal.fire('Error', 'No se pudo guardar: ' + error.message, 'error');
    });
});
</script>
</body>
</html>