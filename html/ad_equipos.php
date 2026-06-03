<?php
require_once 'config.php';
session_start();

if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}

$conn = get_db_connection();
$tipos_desde_db = [];
$capitulos_desde_db = [];

if ($conn) {
    // Obtener Tipos
    $sql_enum = "SHOW COLUMNS FROM Adq_equipos LIKE 'tipo'";
    $result = $conn->query($sql_enum);
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        preg_match("/^enum\('(.*)'\)$/", $row['Type'], $matches);
        if (isset($matches[1])) { $tipos_desde_db = explode("','", $matches[1]); }
    }

    // Obtener Capítulos
    $sql_enum_cap = "SHOW COLUMNS FROM Adq_equipos LIKE 'capitulos'";
    $result_cap = $conn->query($sql_enum_cap);
    if ($result_cap && $result_cap->num_rows > 0) {
        $row_cap = $result_cap->fetch_assoc();
        preg_match("/^enum\('(.*)'\)$/", $row_cap['Type'], $matches_cap);
        if (isset($matches_cap[1])) { $capitulos_desde_db = explode("','", $matches_cap[1]); }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Adquisición de Equipos | SISGI</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> 
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { background-color: #d6d1ca; font-family: 'Segoe UI', sans-serif; margin: 0; padding: 20px; min-height: 100vh; }
        .center-container { width: 95%; max-width: 850px; margin: 20px auto; }
        .card { background-color: #ffffff; border-radius: 12px; box-shadow: 0 8px 24px rgba(0,0,0,0.15); padding: 35px; }
        h2 { color: #721538; text-align: center; margin-bottom: 25px; font-size: 1.8rem; border-bottom: 2px solid #f0f0f0; padding-bottom: 15px; }
        
        label { display: block; margin-bottom: 8px; color: #444; font-weight: 600; font-size: 0.95em; }
        input[type="text"], input[type="number"], select, textarea { 
            width: 100%; padding: 12px; margin-bottom: 20px; border: 1.5px solid #ddd; 
            border-radius: 8px; box-sizing: border-box; font-size: 15px; transition: border 0.3s;
        }
        input:focus, select:focus, textarea:focus { border-color: #721538; outline: none; box-shadow: 0 0 0 3px rgba(114, 21, 56, 0.1); }
        
        .form-row { display: flex; gap: 20px; }
        .form-col { flex: 1; }
        
        input[type="submit"] { 
            width: 100%; padding: 16px; background-color: #721538; color: white; 
            border: none; border-radius: 8px; font-size: 17px; font-weight: bold; 
            cursor: pointer; transition: all 0.3s; margin-top: 10px;
        }
        input[type="submit"]:hover { background-color: #5d112d; transform: translateY(-1px); }
        
        .button-group { display: flex; gap: 15px; margin-top: 25px; border-top: 1px solid #eee; padding-top: 20px; }
        .btn-accion { 
            flex: 1; padding: 12px; background-color: #6b7280; color: white; border: none; 
            border-radius: 8px; cursor: pointer; text-align: center; font-size: 14px; 
            transition: 0.3s; text-decoration: none; font-weight: 600;
        }
        .btn-accion:hover { background-color: #4b5563; }

        @media (max-width: 600px) { .form-row { flex-direction: column; gap: 0; } }
    </style>
</head>
<body>

<div class="center-container">
    <div class="card">
        <h2>Registro de equipamiento y suministros informáticos</h2>
        <form id="formAdquisicion">
            
            <div class="form-row">
                <div class="form-col">
                    <label for="tipo">Tipo de Equipo:</label>
                    <select id="tipo" name="tipo" required>
                        <option value="">-- Selecciona --</option>
                        <?php foreach ($tipos_desde_db as $t): ?>
                            <option value="<?= htmlspecialchars($t) ?>"><?= htmlspecialchars($t) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-col">
                    <label for="marca">Marca:</label>
                    <input type="text" id="marca" name="marca" placeholder="Ej: Dell, HP, Epson" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-col">
                    <label for="modelo">Modelo:</label>
                    <input type="text" id="modelo" name="modelo" placeholder="Ej: Latitude 5420" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-col">
                    <label for="capacidad_almacenamiento">Capacidad / Almacenamiento:</label>
                    <input type="text" id="capacidad_almacenamiento" name="capacidad_almacenamiento">
                </div>
                <div class="form-col">
                    <label for="memoria_ram">Memoria RAM:</label>
                    <input type="text" id="memoria_ram" name="memoria_ram">
                </div>
            </div>

            <div class="form-row">
                <div class="form-col">
                    <label for="capitulos">Capítulo:</label>
                    <select id="capitulos" name="capitulos" required>
                        <option value="">-- Selecciona Capítulo --</option>
                        <?php foreach ($capitulos_desde_db as $cap): ?>
                            <option value="<?= htmlspecialchars($cap) ?>"><?= htmlspecialchars($cap) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-col">
                    <label for="precio">Precio de Adquisición:</label>
                    <input type="number" id="precio" name="precio" step="0.01" min="0" placeholder="0.00" required>
                </div>
            </div>

            <label for="detalles">Detalles Adicionales:</label>
            <textarea id="detalles" name="detalles" rows="3" placeholder="Observaciones adicionales..."></textarea>

            <input type="submit" value="Registrar Adquisición">
        </form>

        <div class="button-group">
            <a href="consultar_adq_equipos.php" class="btn-accion">📋 Ver Adquisiciones</a>
            <a href="dashboard.php" class="btn-accion">🏠 Menú Principal</a>
        </div>
    </div>
</div>

<script>
    // Lógica AJAX para enviar el formulario
    document.getElementById('formAdquisicion').addEventListener('submit', function(e) {
        e.preventDefault();
        let datos = new FormData(this);
        Swal.fire({ title: 'Procesando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

        fetch('guardar_ad_equipos.php', { method: 'POST', body: datos })
        .then(r => r.json())
        .then(data => {
            if(data.success) {
                Swal.fire({ icon: 'success', title: '¡Éxito!', text: data.message, confirmButtonColor: '#721538' })
                .then(() => {
                    this.reset();
                    gestionarCamposTecnicos(); // Resetear estados de inputs
                });
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: data.message });
            }
        });
    });

    // Control de habilitación de campos técnicos
    function gestionarCamposTecnicos() {
        const tipo = document.getElementById('tipo').value.toLowerCase();
        const alm = document.getElementById('capacidad_almacenamiento');
        const ram = document.getElementById('memoria_ram');

        const completo = ['laptop', 'all in one', 'cpu', 'servidor', 'tablet', 'nas'];
        const soloAlm = ['disco duro', 'usb'];
        const soloRam = ['memoria ram'];

        // Reset inicial
        [alm, ram].forEach(i => {
            i.disabled = true;
            i.value = "";
            i.style.backgroundColor = "#e9e9e9";
            i.placeholder = "N/A para este tipo";
        });

        if (completo.includes(tipo)) {
            alm.disabled = ram.disabled = false;
            alm.style.backgroundColor = ram.style.backgroundColor = "#fff";
            alm.placeholder = "Ej: 512GB SSD";
            ram.placeholder = "Ej: 16GB DDR4";
        } else if (soloAlm.includes(tipo)) {
            alm.disabled = false;
            alm.style.backgroundColor = "#fff";
            alm.placeholder = "Ej: 1TB / 2TB";
        } else if (soloRam.includes(tipo)) {
            ram.disabled = false;
            ram.style.backgroundColor = "#fff";
            ram.placeholder = "Ej: 8GB DDR4";
        }
    }

    document.getElementById('tipo').addEventListener('change', gestionarCamposTecnicos);
    document.addEventListener('DOMContentLoaded', gestionarCamposTecnicos);
</script>
</body>
</html>