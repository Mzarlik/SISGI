<?php
require_once 'session_check.php';
require_once 'config.php';
session_start();

// 1. SEGURIDAD: Verifica si el usuario está logueado y si su rol está permitido
$roles_permitidos = ['admin', 'tecnico'];

if (!isset($_SESSION['usuario']) || !in_array($_SESSION['rol'] ?? '', $roles_permitidos)) {
    header("Location: index.php"); // Redirige si no está logueado o su rol no está en la lista
    exit();
}
include 'header.php';

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Registro AD</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> 
    <link rel="stylesheet" href="css/style.css">
    <script src="js/sweetalert2.all.min.js"></script>
    

    <style>
        /* TUS ESTILOS ORIGINALES */
        body { background-color: #d6d1ca; font-family: 'Segoe UI', sans-serif; margin: 0; padding: 0; min-height: 100vh; display: block !important; }
        .center-container { height: auto !important; min-height: auto !important; width: 95%; max-width: 850px; margin: 40px auto !important; background: transparent; box-sizing: border-box; }
        .card { background-color: #ffffff; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); padding: 40px; box-sizing: border-box; }
        h2 { color: #721538; text-align: center; margin-bottom: 30px; margin-top: 0; }
        label { display: block; margin-bottom: 5px; color: #555; font-weight: bold; font-size: 0.9em; }
        input[type="text"], input[type="date"], select { width: 100%; padding: 12px; margin-bottom: 15px; border: 1px solid #ccc; border-radius: 6px; box-sizing: border-box; font-size: 16px; background-color: #fcfcfc; }
        .form-row { display: flex; gap: 20px; }
        .form-col { flex: 1; }
        input[type="submit"] { width: 100%; padding: 15px; background-color: #721538; color: white; border: none; border-radius: 8px; font-size: 18px; font-weight: bold; cursor: pointer; transition: background 0.3s; margin-top: 15px; }
        input[type="submit"]:hover { background-color: #5d112d; }
        .button-group { display: flex; gap: 15px; margin-top: 25px; }
        .btn-accion { flex: 1; padding: 12px; background-color: #555; color: white; border: none; border-radius: 6px; cursor: pointer; text-align: center; font-size: 15px; transition: 0.3s; text-decoration: none; font-weight: bold; }
        .btn-accion:hover { background-color: #333; }
        @media (max-width: 768px) { .form-row { flex-direction: column; gap: 0; } .card { padding: 20px; } }
    </style>
</head>
<body>

<div class="center-container">
    <div class="card">

        <h2>Registro de Usuarios AD</h2>

        <form id="formRegistro">
            
            <div class="form-row">
                <div class="form-col">
                    <label for="secretaria">Secretaría:</label>
                    <select id="secretaria" name="secretaria" required onchange="filtrarDirecciones()">
                        <option value="">-- Cargando... --</option>
                    </select>
                </div>
                <div class="form-col">
                    <label for="direccion">Dirección / Área:</label>
                    <select id="direccion" name="id_direccion" required disabled>
                        <option value="">-- Espera Secretaría --</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-col">
                    <label>Número de Oficio:</label>
                    <input type="text" name="num_oficio" placeholder="Ej: MSOL/..." required>
                </div>
                <div class="form-col">
                    <label>Fecha de Alta:</label>
                    <input type="date" name="fecha_alta" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-col">
                    <label>No. de empleado:</label>
                    <input type="text" name="num_empleado" placeholder="Ej: 18111" required>
                </div>
                <div class="form-col">
                    <label>Nombres:</label>
                    <input type="text" name="nombres" placeholder="Ej: Ema David" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-col">
                    <label>Apellido paterno:</label>
                    <input type="text" name="apellido_paterno" placeholder="Ej: OLPER" required>
                </div>
                <div class="form-col">
                    <label>Apellido materno:</label>
                    <input type="text" name="apellido_materno" placeholder="Ej: MZARLIK" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-col">
                    <label>Cargo / Puesto:</label>
                    <input type="text" name="cargo" placeholder="Ej: Jefe de Departamento">
                </div>
                <div class="form-col">
                    <label>Contacto (Correo / Teléfono):</label>
                    <div style="display:flex; gap:10px;">
                        <input type="text" name="correo_electronico" placeholder="Correo" style="flex:1;">
                        <input type="text" name="telefono" placeholder="Teléfono" style="flex:1;">
                    </div>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-col">
                    <label>Cuenta de Usuario:</label>
                    <input type="text" name="usuario" placeholder="Ej: jlopez" required>
                </div>
                <div class="form-col">
                    <label>Contraseña:</label>
                    <input type="text" name="contrasena" placeholder="Contraseña temporal" required>
                </div>
            </div>

            <input type="submit" value="Guardar Usuario">
        </form>

        <div class="button-group">
            <button onclick="window.location.href='consultar_usuarios.php';" class="btn-accion">📋 Consultar</button>
            <button onclick="window.location.href='dashboard.php';" class="btn-accion">🏠 Menú Principal</button>
        </div>
    </div>
</div>

<script>
    // URL del archivo que ya usamos en otros módulos
    const API_URL = 'acciones_direcciones.php';

    document.addEventListener('DOMContentLoaded', function() {
        cargarSecretarias();
    });

    // 1. Cargar Secretarías al iniciar
    function cargarSecretarias() {
        const selectSec = document.getElementById('secretaria');
        
        // Llamamos a la acción que ya existe en tu archivo reutilizado
        fetch(`${API_URL}?action=get_secretarias`)
            .then(response => {
                if(!response.ok) throw new Error("Error en red");
                return response.json();
            })
            .then(data => {
                selectSec.innerHTML = '<option value="">-- Selecciona Secretaría --</option>';
                data.forEach(sec => {
                    const option = document.createElement('option');
                    // Usamos el ID de la secretaría para filtrar
                    option.value = sec.id_secretaria; 
                    option.textContent = sec.nombres;
                    selectSec.appendChild(option);
                });
            })
            .catch(err => {
                console.error(err);
                Swal.fire('Error', 'No se pudieron cargar las secretarías. Revisa la consola.', 'error');
            });
    }

    // 2. Cargar Direcciones cuando cambia la Secretaría
    function filtrarDirecciones() {
        const idSecretaria = document.getElementById('secretaria').value;
        const selectDir = document.getElementById('direccion');
        
        // Limpiar select anterior
        selectDir.innerHTML = '<option value="">-- Cargando... --</option>';
        selectDir.disabled = true;

        if (!idSecretaria) {
            selectDir.innerHTML = '<option value="">-- Espera Secretaría --</option>';
            return;
        }

        // Llamamos a la API filtrando por ID de secretaría
        fetch(`${API_URL}?action=get_direcciones_por_secretaria&id_secretaria=${idSecretaria}`)
            .then(response => response.json())
            .then(data => {
                selectDir.innerHTML = '<option value="">-- Selecciona Dirección --</option>';
                
                if(data.length > 0) {
                    selectDir.disabled = false;
                    data.forEach(dir => {
                        const option = document.createElement('option');
                        // El value será el ID (esto es lo que se guarda en la BD)
                        option.value = dir.id_direcciones; 
                        option.textContent = dir.nombres_direcciones;
                        selectDir.appendChild(option);
                    });
                } else {
                    selectDir.innerHTML = '<option value="">(Sin direcciones registradas)</option>';
                }
            })
            .catch(err => {
                console.error(err);
                selectDir.innerHTML = '<option value="">Error al cargar</option>';
            });
    }

    // 3. Envío del Formulario (Guardado)
    document.getElementById('formRegistro').addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Validación extra: Verificar que se haya seleccionado una dirección (ID)
        if(document.getElementById('direccion').value === "") {
            Swal.fire({icon: 'warning', text: 'Por favor selecciona una Dirección.'});
            return;
        }

        let datos = new FormData(this);
        
        fetch('guardar_registro.php', { method: 'POST', body: datos })
        .then(response => response.json())
        .then(data => {
            if(data.success) {
                Swal.fire({ icon: 'success', title: '¡Guardado!', text: data.message, confirmButtonColor: '#721538' })
                .then(() => {
                    document.getElementById('formRegistro').reset();
                    // Reiniciar selects visualmente
                    document.getElementById('direccion').innerHTML = '<option value="">-- Espera Secretaría --</option>';
                    document.getElementById('direccion').disabled = true;
                });
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: data.message, confirmButtonColor: '#721538' });
            }
        })
        .catch(err => Swal.fire({ icon: 'error', title: 'Error de red', text: err }));
    });
</script>

</body>
</html>