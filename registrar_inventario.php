<?php
// Incluye la configuración de la base de datos y maneja la sesión
require_once 'session_check.php';
require_once 'config.php';
session_start();

// Redirige si el usuario no está autenticado
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}



// ===============================================
// 1. LÓGICA PHP PARA OBTENER DATOS DESDE LA BD
// ===============================================
$conn = get_db_connection();
$tipos_equipo = [];
$ubicaciones = [];

if ($conn) {
    /**
     * Función auxiliar para obtener valores ENUM de una columna específica.
     */
    function get_enum_values($conn, $table, $column_name) {
        $values = [];
        $sql_enum = "SHOW COLUMNS FROM {$table} LIKE '{$column_name}'";
        $result = $conn->query($sql_enum);

        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $enum_definition = $row['Type'];
            
            if (preg_match("/^enum\('(.*)'\)$/", $enum_definition, $matches)) {
                $values = explode("','", $matches[1]);
            }
        }
        return $values;
    }

    // A. Obtener ubicaciones (Mantiene el sistema ENUM)
    $ubicaciones = get_enum_values($conn, 'inventario_soporte', 'ubicacion');

    // B. Obtener tipos de equipo desde la nueva tabla relacional
    $sql_tipos = "SELECT id_tipo, nombre_tipo FROM tipo_bien_inventario ORDER BY nombre_tipo ASC";
    $result_tipos = $conn->query($sql_tipos);
    
    if ($result_tipos && $result_tipos->num_rows > 0) {
        while ($row = $result_tipos->fetch_assoc()) {
            $tipos_equipo[] = $row;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Registro de Inventario | Soporte</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> 
    <link rel="stylesheet" href="css/style.css"> 
    <script src="js/sweetalert2.all.min.js"></script>
    <style>
        body { background-color: #d6d1ca; font-family: 'Segoe UI', sans-serif; margin: 0; padding: 0; min-height: 100vh; display: block !important; }
        .center-container { height: auto !important; min-height: auto !important; width: 95%; max-width: 900px; margin: 40px auto !important; background: transparent; box-sizing: border-box; }
        .card { background-color: #ffffff; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.15); padding: 40px; box-sizing: border-box; }
        h2 { color: #721538; text-align: center; margin-bottom: 30px; margin-top: 0; }
        label { display: block; margin-bottom: 5px; color: #555; font-weight: bold; font-size: 0.9em; }
        input[type="text"], input[type="number"], select, textarea, input[type="file"] { width: 100%; padding: 12px; margin-bottom: 15px; border: 1px solid #ccc; border-radius: 6px; box-sizing: border-box; font-size: 16px; background-color: #f7fcf7; }
        .form-row { display: flex; gap: 20px; }
        .form-col { flex: 1; }
        input[type="submit"] { width: 100%; padding: 15px; background-color: #721538; color: white; border: none; border-radius: 8px; font-size: 18px; font-weight: bold; cursor: pointer; transition: background 0.3s; margin-top: 15px; }
        input[type="submit"]:hover { background-color: #5d112d; }
        .button-group { display: flex; gap: 15px; margin-top: 25px; }
        .btn-accion { flex: 1; padding: 12px; background-color: #721538; color: white; border: none; border-radius: 6px; cursor: pointer; text-align: center; font-size: 15px; transition: 0.3s; text-decoration: none; font-weight: bold; }
        .btn-accion:hover { background-color: #5d112d; }
        .logout { text-align: center; margin-top: 20px; }
        .logout a { color: #721538; text-decoration: none; font-weight: bold; }
        @media (max-width: 768px) { .form-row { flex-direction: column; gap: 0; } .card { padding: 20px; } }
    </style>
</head>
<body>

<div class="center-container">
    <div class="card">
        <h2>Registro de Inventario | Soporte</h2>
        <form id="formInventario" enctype="multipart/form-data">
            <div class="form-row">
                <div class="form-col">
                    <label for="num_inventario">Número de Inventario:</label>
                    <input type="text" id="num_inventario" name="num_inventario" placeholder="Ej: AS-2024-0001" required>
                </div>
                <div class="form-col">
                    <label for="id_tipo_bien">Tipo de Equipo:</label>
                    <select id="id_tipo_bien" name="id_tipo_bien" required>
                        <option value="">-- Selecciona --</option>
                        <?php 
                        if (!empty($tipos_equipo)) {
                            foreach ($tipos_equipo as $tipo) {
                                echo "<option value=\"" . htmlspecialchars($tipo['id_tipo']) . "\">" . htmlspecialchars($tipo['nombre_tipo']) . "</option>";
                            }
                        } else {
                            echo "<option value=\"\" disabled>-- Error al cargar tipos --</option>";
                        }
                        ?>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-col">
                    <label for="marca">Marca:</label>
                    <input type="text" id="marca" name="marca" placeholder="Ej: Dell, HP, Samsung..." required>
                </div>
                <div class="form-col">
                    <label for="modelo">Modelo:</label>
                    <input type="text" id="modelo" name="modelo" placeholder="Ej: Latitude 5420, P2422H" required>
                </div>
            </div>

<label for="num_serie">Número de Serie:</label>
            <input type="text" id="num_serie" name="num_serie" placeholder="S/N o Service Tag" required>
            
            <div id="contenedor_perifericos" style="display: none; background-color: #e9ecef; padding: 15px; border-radius: 6px; margin-bottom: 15px; border: 1px dashed #721538;">
                <label style="margin-bottom: 10px; color: #721538;"><i class="fas fa-keyboard"></i> Este equipo es un CPU. ¿Incluye periféricos con el mismo número de inventario?</label>
                <div style="display: flex; gap: 20px;">
                    <label style="font-weight: normal; cursor: pointer;">
                        <input type="checkbox" id="incluye_teclado" name="incluye_teclado" value="Sí"> Incluye Teclado
                    </label>
                    <label style="font-weight: normal; cursor: pointer;">
                        <input type="checkbox" id="incluye_mouse" name="incluye_mouse" value="Sí"> Incluye Mouse
                    </label>
                </div>
            </div>
            <label for="descripcion">Descripción / Especificaciones:</label>
            <textarea id="descripcion" name="descripcion" rows="3" placeholder="Detalles de hardware, sistema operativo, accesorios, etc."></textarea>
            
            <div class="form-row">
                <div class="form-col">
                    <label for="personal_asignado">Personal Asignado:</label>
                    <input type="text" id="personal_asignado" name="personal_asignado" placeholder="Nombre de la persona o 'STOCK'">
                </div>
                <div class="form-col">
                    <label for="ubicacion">Ubicación:</label>
                    <select id="ubicacion" name="ubicacion" required>
                        <option value="">-- Selecciona --</option>
                        <?php 
                        if (!empty($ubicaciones)) {
                            foreach ($ubicaciones as $ubicacion) {
                                $safe_ubicacion = htmlspecialchars($ubicacion);
                                echo "<option value=\"{$safe_ubicacion}\">" . ucwords($safe_ubicacion) . "</option>";
                            }
                        } else {
                            echo "<option value=\"\" disabled>-- Error al cargar ubicaciones --</option>";
                        }
                        ?>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-col">
                    <label for="foto_evidencia">Foto de Evidencia (Obligatorio):</label>
                    <input type="file" id="foto_evidencia" name="foto_evidencia" accept="image/*" capture="environment" required>
                </div>
            </div>

            <input type="submit" value="Registrar Equipo">
        </form>

        <div class="button-group">
            <button onclick="window.location.href='consultar_inventario.php';" class="btn-accion">🔍 Consultar Inventario</button>
            <button onclick="window.location.href='dashboard.php';" class="btn-accion">🏠 Menú Principal</button>
        </div>
        <p class="logout"><a href="logout.php">Cerrar sesión</a></p>
    </div>
</div>

<script>
// Lógica dinámica según el Tipo de Bien seleccionado
    document.getElementById('id_tipo_bien').addEventListener('change', function() {
        let textoSeleccionado = this.options[this.selectedIndex].text.toUpperCase();
        
        // Elementos de la interfaz
        let contenedorPerifericos = document.getElementById('contenedor_perifericos');
        let checkboxTeclado = document.getElementById('incluye_teclado');
        let checkboxMouse = document.getElementById('incluye_mouse');
        
        let inputMarca = document.getElementById('marca');
        let inputModelo = document.getElementById('modelo');
        
        // ---------------------------------------------------------
        // 1. LÓGICA PARA PERIFÉRICOS (Solo para CPU o Computadoras)
        // ---------------------------------------------------------
        // Quitamos 'ESCRITORIO' de aquí para que no confunda el mueble con una PC
        if(textoSeleccionado.includes('CPU') || textoSeleccionado.includes('COMPUTADORA') || textoSeleccionado.includes('ALL IN ONE')) {
            contenedorPerifericos.style.display = 'block';
        } else {
            contenedorPerifericos.style.display = 'none';
            checkboxTeclado.checked = false;
            checkboxMouse.checked = false;
        }

        // ---------------------------------------------------------
        // 2. LÓGICA PARA MOBILIARIO (Deshabilitar Marca y Modelo)
        // ---------------------------------------------------------
        if(textoSeleccionado.includes('SILLA') || textoSeleccionado.includes('ESCRITORIO') || textoSeleccionado.includes('MUEBLE') || textoSeleccionado.includes('ARCHIVERO')) {
            // Hacemos que sean de solo lectura (para que sí se envíen por POST)
            inputMarca.readOnly = true;
            inputModelo.readOnly = true;
            
            // Quitamos la restricción de obligatorio
            inputMarca.required = false;
            inputModelo.required = false;
            
            // Rellenamos automáticamente con N/A
            inputMarca.value = 'N/A';
            inputModelo.value = 'N/A';
            
            // Cambiamos el fondo a un gris para indicar que están bloqueados
            inputMarca.style.backgroundColor = '#e2e8f0'; 
            inputModelo.style.backgroundColor = '#e2e8f0';
            inputMarca.style.cursor = 'not-allowed';
            inputModelo.style.cursor = 'not-allowed';

        } else {
            // Restauramos a la normalidad si es un equipo tecnológico (Ej: Monitor, CPU, Impresora)
            inputMarca.readOnly = false;
            inputModelo.readOnly = false;
            
            inputMarca.required = true;
            inputModelo.required = true;
            
            inputMarca.style.backgroundColor = '#f7fcf7';
            inputModelo.style.backgroundColor = '#f7fcf7';
            inputMarca.style.cursor = 'text';
            inputModelo.style.cursor = 'text';
            
            // Limpiamos los campos solo si tenían el 'N/A' automático
            if(inputMarca.value === 'N/A') inputMarca.value = '';
            if(inputModelo.value === 'N/A') inputModelo.value = '';
        }
    });

    // Lógica de Envío del Formulario
    document.getElementById('formInventario').addEventListener('submit', function(e) {
        e.preventDefault();
        
        let datos = new FormData(this);
        
        Swal.fire({
            title: 'Registrando...', didOpen: () => Swal.showLoading()
        });

        fetch('guardar_inventario.php', { method: 'POST', body: datos })
        .then(response => response.json())
        .then(data => {
            if(data.success) {
                Swal.fire({ icon: 'success', title: '¡Registrado!', text: data.message, confirmButtonColor: '#2e7d32' })
                .then(() => {
                    document.getElementById('formInventario').reset();
                });
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: data.message, confirmButtonColor: '#2e7d32' });
            }
        })
        .catch(error => {
            console.error(error);
            Swal.fire({ icon: 'error', title: 'Error', text: 'Error de comunicación con guardar_inventario.php' });
        });
    });
</script>
</body>
</html>