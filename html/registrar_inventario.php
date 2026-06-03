<?php
// Incluye la configuración de la base de datos y maneja la sesión
require_once 'config.php';
session_start();

// Redirige si el usuario no está autenticado
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}

if ($_SESSION['rol'] === 'redes') {
    // Si es redes, lo regresamos al dashboard con un mensaje de error opcional
    header("Location: dashboard.php?error=acceso_denegado");
    exit();
}

// ===============================================
// 1. LÓGICA PHP PARA OBTENER ENUMS DESDE LA BASE DE DATOS
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

    // Obtener valores para 'tipo' y 'ubicacion'
    $tipos_equipo = get_enum_values($conn, 'inventario_soporte', 'tipo');
    $ubicaciones = get_enum_values($conn, 'inventario_soporte', 'ubicacion');
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
        input[type="text"], input[type="number"], select, textarea { width: 100%; padding: 12px; margin-bottom: 15px; border: 1px solid #ccc; border-radius: 6px; box-sizing: border-box; font-size: 16px; background-color: #f7fcf7; }
        .form-row { display: flex; gap: 20px; }
        .form-col { flex: 1; }
        input[type="submit"] { width: 100%; padding: 15px; background-color: #721538; color: white; border: none; border-radius: 8px; font-size: 18px; font-weight: bold; cursor: pointer; transition: background 0.3s; margin-top: 15px; }
        input[type="submit"]:hover { background-color: #5d112d; }
        .button-group { display: flex; gap: 15px; margin-top: 25px; }
        .btn-accion { flex: 1; padding: 12px; background-color: #721538 color: white; border: none; border-radius: 6px; cursor: pointer; text-align: center; font-size: 15px; transition: 0.3s; text-decoration: none; font-weight: bold; }
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
        <form id="formInventario">
            <div class="form-row">
                <div class="form-col">
                    <label for="num_inventario">Número de Inventario:</label>
                    <input type="text" id="num_inventario" name="num_inventario" placeholder="Ej: AS-2024-0001" required>
                </div>
                <div class="form-col">
                    <label for="tipo">Tipo de Equipo:</label>
                    <select id="tipo" name="tipo" required>
                        <option value="">-- Selecciona --</option>
                        <?php 
                        if (!empty($tipos_equipo)) {
                            foreach ($tipos_equipo as $tipo) {
                                $safe_tipo = htmlspecialchars($tipo);
                                echo "<option value=\"{$safe_tipo}\">" . ucfirst($safe_tipo) . "</option>";
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