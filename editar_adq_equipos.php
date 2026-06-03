<?php
require_once 'session_check.php';
require_once 'config.php';
session_start();

if (!isset($_SESSION['usuario']) || ($_SESSION['rol'] ?? '') !== 'admin') {
    header("Location: login.php");
    exit();
}

$id_adquisicion = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id_adquisicion === 0) {
    header("Location: consultar_adq_equipos.php");
    exit();
}

$conn = get_db_connection();
$tipos_desde_db = [];
$capitulos_desde_db = [];
$equipo = null;

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

    // Obtener datos del equipo a editar
    $stmt = $conn->prepare("SELECT * FROM Adq_equipos WHERE id_adquisicion = ?");
    $stmt->bind_param("i", $id_adquisicion);
    $stmt->execute();
    $resultado_equipo = $stmt->get_result();
    if ($resultado_equipo->num_rows > 0) {
        $equipo = $resultado_equipo->fetch_assoc();
    } else {
        header("Location: consultar_adq_equipos.php");
        exit();
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Editar Equipo | SISGI</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> 
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { background-color: #d6d1ca; font-family: 'Segoe UI', sans-serif; margin: 0; padding: 20px; min-height: 100vh; }
        .center-container { width: 95%; max-width: 850px; margin: 20px auto; }
        .card { background-color: #ffffff; border-radius: 12px; box-shadow: 0 8px 24px rgba(0,0,0,0.15); padding: 35px; }
        h2 { color: #721538; text-align: center; margin-bottom: 25px; font-size: 1.8rem; border-bottom: 2px solid #f0f0f0; padding-bottom: 15px; }
        
        label { display: block; margin-bottom: 8px; color: #444; font-weight: 600; font-size: 0.95em; }
        input[type="text"], input[type="number"], input[type="file"], select, textarea { 
            width: 100%; padding: 12px; margin-bottom: 20px; border: 1.5px solid #ddd; 
            border-radius: 8px; box-sizing: border-box; font-size: 15px; transition: border 0.3s;
        }
        input:focus, select:focus, textarea:focus { border-color: #721538; outline: none; box-shadow: 0 0 0 3px rgba(114, 21, 56, 0.1); }
        
        input[type="file"] { background-color: #f9fafb; padding: 9px 12px; cursor: pointer; }
        input[type="file"]::file-selector-button {
            background-color: #721538; color: white; border: none; border-radius: 6px;
            padding: 8px 12px; margin-right: 10px; cursor: pointer; transition: background-color 0.3s;
        }
        input[type="file"]::file-selector-button:hover { background-color: #5d112d; }

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

        .img-preview { max-width: 150px; border-radius: 8px; border: 1px solid #ccc; margin-bottom: 15px; display: block; }
        @media (max-width: 600px) { .form-row { flex-direction: column; gap: 0; } }
    </style>
</head>
<body>

<div class="center-container">
    <div class="card">
        <h2>Editar Equipo y Suministros</h2>
        <form id="formEdicion">
            <input type="hidden" name="id_adquisicion" value="<?= $equipo['id_adquisicion'] ?>">
            
            <div class="form-row">
                <div class="form-col">
                    <label for="tipo">Tipo de Equipo:</label>
                    <select id="tipo" name="tipo" required>
                        <option value="">-- Selecciona --</option>
                        <?php foreach ($tipos_desde_db as $t): ?>
                            <option value="<?= htmlspecialchars($t) ?>" <?= ($equipo['tipo'] === $t) ? 'selected' : '' ?>><?= htmlspecialchars($t) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-col">
                    <label for="marca">Marca:</label>
                    <input type="text" id="marca" name="marca" value="<?= htmlspecialchars($equipo['marca']) ?>" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-col">
                    <label for="modelo">Modelo:</label>
                    <input type="text" id="modelo" name="modelo" value="<?= htmlspecialchars($equipo['modelo']) ?>" required>
                </div>
                <div class="form-col">
                    <label for="imagen_equipo">Fotografía (Sube una nueva para reemplazar):</label>
                    <?php if (!empty($equipo['ruta_imagen'])): ?>
                        <img src="<?= htmlspecialchars($equipo['ruta_imagen']) ?>" alt="Imagen actual" class="img-preview" onerror="this.style.display='none'">
                    <?php else: ?>
                        <p style="font-size: 13px; color: #666; margin-bottom: 10px;"><i>Sin imagen registrada actualmente</i></p>
                    <?php endif; ?>
                    <input type="file" id="imagen_equipo" name="imagen_equipo" accept="image/jpeg, image/png, image/webp">
                </div>
            </div>

            <div class="form-row">
                <div class="form-col">
                    <label for="capacidad_almacenamiento">Capacidad / Almacenamiento:</label>
                    <input type="text" id="capacidad_almacenamiento" name="capacidad_almacenamiento" value="<?= htmlspecialchars($equipo['capacidad_almacenamiento'] ?? '') ?>">
                </div>
                <div class="form-col">
                    <label for="memoria_ram">Memoria RAM:</label>
                    <input type="text" id="memoria_ram" name="memoria_ram" value="<?= htmlspecialchars($equipo['memoria_ram'] ?? '') ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-col">
                    <label for="capitulos">Capítulo:</label>
                    <select id="capitulos" name="capitulos" required>
                        <option value="">-- Selecciona Capítulo --</option>
                        <?php foreach ($capitulos_desde_db as $cap): ?>
                            <option value="<?= htmlspecialchars($cap) ?>" <?= ($equipo['capitulos'] === $cap) ? 'selected' : '' ?>><?= htmlspecialchars($cap) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-col">
                    <label for="precio">Precio de Adquisición:</label>
                    <input type="number" id="precio" name="precio" step="0.01" min="0" value="<?= htmlspecialchars($equipo['precio']) ?>" required>
                </div>
            </div>

            <label for="detalles">Detalles Adicionales:</label>
            <textarea id="detalles" name="detalles" rows="3"><?= htmlspecialchars($equipo['detalles'] ?? '') ?></textarea>

            <input type="submit" value="Guardar Cambios">
        </form>

        <div class="button-group">
            <a href="consultar_adq_equipos.php" class="btn-accion">Cancelar y Volver</a>
        </div>
    </div>
</div>

<script>
    document.getElementById('formEdicion').addEventListener('submit', function(e) {
        e.preventDefault();
        let datos = new FormData(this);
        Swal.fire({ title: 'Actualizando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

        fetch('actualizar_adq_equipos.php', { method: 'POST', body: datos })
        .then(r => r.json())
        .then(data => {
            if(data.success) {
                Swal.fire({ icon: 'success', title: '¡Actualizado!', text: data.message, confirmButtonColor: '#721538' })
                .then(() => {
                    window.location.href = 'consultar_adq_equipos.php';
                });
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: data.message });
            }
        })
        .catch(error => {
            Swal.fire({ icon: 'error', title: 'Error', text: 'Error de conexión con el servidor.' });
        });
    });

    // Control de habilitación de campos técnicos (Igual que en el registro nuevo)
    function gestionarCamposTecnicos() {
        const tipo = document.getElementById('tipo').value.toLowerCase();
        const alm = document.getElementById('capacidad_almacenamiento');
        const ram = document.getElementById('memoria_ram');

        const completo = ['laptop', 'all in one', 'cpu', 'servidor', 'tablet', 'nas'];
        const soloAlm = ['disco duro', 'usb'];
        const soloRam = ['memoria ram'];

        // Solo deshabilitar si el usuario cambia el tipo manualmente a algo que no requiere campo
        if (completo.includes(tipo)) {
            alm.disabled = ram.disabled = false;
            alm.style.backgroundColor = ram.style.backgroundColor = "#fff";
        } else if (soloAlm.includes(tipo)) {
            alm.disabled = false; alm.style.backgroundColor = "#fff";
            ram.disabled = true; ram.style.backgroundColor = "#e9e9e9";
        } else if (soloRam.includes(tipo)) {
            ram.disabled = false; ram.style.backgroundColor = "#fff";
            alm.disabled = true; alm.style.backgroundColor = "#e9e9e9";
        } else {
             alm.disabled = ram.disabled = true;
             alm.style.backgroundColor = ram.style.backgroundColor = "#e9e9e9";
        }
    }

    document.getElementById('tipo').addEventListener('change', gestionarCamposTecnicos);
    // No ejecutamos al cargar para preservar los datos que ya vienen de la BD
    // document.addEventListener('DOMContentLoaded', gestionarCamposTecnicos);
</script>
</body>
</html>
<?php $conn->close(); ?>