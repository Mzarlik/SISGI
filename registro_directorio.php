<?php require_once 'config.php'; session_start(); 
require_once 'session_check.php';
if (!isset($_SESSION['usuario'])) { header("Location: index.php"); exit(); } ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registro Extensión</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/style.css">
    <script src="js/sweetalert2.all.min.js"></script>
    
    <style>
        /* --- ESTILOS UNIFICADOS (600px) --- */
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
            max-width: 600px; /* Ancho compacto solicitado */
            margin: 40px auto !important;
            background: transparent;
            
            box-sizing: border-box;
            padding-bottom: 40px;
        }
        
        .card {
            background-color: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            padding: 40px;
            box-sizing: border-box;
        }

        h2 { color: #721538; text-align: center; margin-bottom: 30px; margin-top: 0; }

        /* Estilos de Formulario */
        label { display: block; margin-bottom: 5px; color: #555; font-weight: bold; font-size: 0.9em; }
        
        input[type="text"] {
            width: 100%;
            padding: 12px;
            margin-bottom: 15px;
            border: 1px solid #ccc;
            border-radius: 6px;
            box-sizing: border-box;
            font-size: 16px;
            background-color: #fcfcfc;
        }

        /* Sistema de Grilla (2 Columnas) */
        .form-row {
            display: flex;
            gap: 15px;
        }
        .form-col {
            flex: 1;
        }

        /* Botón Guardar (Principal) */
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

        /* Botones Secundarios (Grises) */
        .button-group { display: flex; gap: 10px; margin-top: 25px; }
        
        .btn-accion {
            flex: 1; 
            padding: 12px; 
            background-color: #555; /* Gris uniforme */
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
        
        /* Opción: Si quieres diferenciar el de Consultar, descomenta esto */
        /* .btn-consultar { background-color: #2c3e50; } */

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
        <h2>📞 Nueva Extensión</h2>
        
        <form id="formDir">
            
            <div class="form-row">
                <div class="form-col">
                    <label>Dirección General:</label>
                    <input type="text" name="direccion" placeholder="Ej: Tesorería" required>
                </div>
                <div class="form-col">
                    <label>Área / Depto:</label>
                    <input type="text" name="area" placeholder="Ej: Cajas">
                </div>
            </div>

            <label>Nombre Personal:</label>
            <input type="text" name="nombre" placeholder="Nombre del funcionario">

            <div class="form-row">
                <div class="form-col">
                    <label>Extensión:</label>
                    <input type="text" name="extension" placeholder="Ej: 1045" required>
                </div>
                <div class="form-col">
                    <label>Número Directo:</label>
                    <input type="text" name="directo" placeholder="Opcional">
                </div>
            </div>

            <button type="submit">Guardar Extensión</button>
        </form>

        <div class="button-group">
            <a href="consultar_directorio.php" class="btn-accion btn-consultar">📂 Consultar Lista</a>
            <a href="dashboard.php" class="btn-accion">🏠 Menú Principal</a>
        </div>

        <p class="logout">
            <a href="logout.php">Cerrar sesión</a>
        </p>
    </div>
</div>

<script>
document.getElementById('formDir').addEventListener('submit', function(e){
    e.preventDefault();
    fetch('guardar_directorio.php', { method: 'POST', body: new FormData(this) })
    .then(r => r.json())
    .then(d => {
        if(d.success) {
            Swal.fire({
                icon: 'success',
                title: '¡Guardado!',
                text: 'Extensión registrada correctamente',
                confirmButtonColor: '#721538'
            }).then(() => document.getElementById('formDir').reset());
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: d.message,
                confirmButtonColor: '#721538'
            });
        }
    });
});
</script>
</body>
</html>