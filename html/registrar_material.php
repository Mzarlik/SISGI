<?php
require_once 'config.php';
session_start();

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
// 1. LÓGICA PHP PARA OBTENER TIPOS DESDE LA BASE DE DATOS
// ===============================================
$conn = get_db_connection();
$todos_los_tipos = [];

if ($conn) {
    // Consulta SQL especial para obtener la definición del tipo ENUM
    $sql_enum = "SHOW COLUMNS FROM stock_material LIKE 'tipo'";
    $result = $conn->query($sql_enum);

    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $enum_definition = $row['Type']; // Ejemplo: "enum('Disco duro','USB',...)"
        
        // --- MÉTODO ROBUSTO DE EXTRACCIÓN CON EXPRESIÓN REGULAR ---
        // 1. Buscar la cadena entre los paréntesis del ENUM
        preg_match("/^enum\('(.*)'\)$/", $enum_definition, $matches);
        
        if (isset($matches[1])) {
            // 2. Dividir la cadena resultante por "','". Esto separa los valores.
            $todos_los_tipos = explode("','", $matches[1]);
        }
    }
}

// ===============================================
// 2. DEFINICIÓN DE TIPOS QUE REQUIEREN DETALLE (Necesaria para la validación JS)
// ===============================================
$tipos_con_detalle = [
    "Disco duro",
    "USB",
    "Memoria RAM",
    "Tarjeta microSD",
    "Unidad de estado solido M.2",
    "Unidad de estado solido SATA 2.5\"",
    "Tarjeta de red inalámbrica"
];

// Codificamos la lista a minúsculas y JSON para la lógica de JavaScript
$tipos_con_detalle_json = json_encode(array_map('strtolower', $tipos_con_detalle));
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Registro de Material | Stock</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> 
    <link rel="stylesheet" href="css/style.css">
    <script src="js/sweetalert2.all.min.js"></script>
    <style>
        body { background-color: #d6d1ca; font-family: 'Segoe UI', sans-serif; margin: 0; padding: 0; min-height: 100vh; display: block !important; }
        .center-container { height: auto !important; min-height: auto !important; width: 95%; max-width: 850px; margin: 40px auto !important; background: transparent; box-sizing: border-box; }
        .card { background-color: #ffffff; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); padding: 40px; box-sizing: border-box; }
        h2 { color: #721538; text-align: center; margin-bottom: 30px; margin-top: 0; }
        label { display: block; margin-bottom: 5px; color: #555; font-weight: bold; font-size: 0.9em; }
        input[type="text"], input[type="number"], input[type="date"], select, textarea { width: 100%; padding: 12px; margin-bottom: 15px; border: 1px solid #ccc; border-radius: 6px; box-sizing: border-box; font-size: 16px; background-color: #fcfcfc; }
        input:disabled, select:disabled { background-color: #eee; cursor: not-allowed; }
        .form-row { display: flex; gap: 20px; }
        .form-col { flex: 1; }
        input[type="submit"] { width: 100%; padding: 15px; background-color: #721538; color: white; border: none; border-radius: 8px; font-size: 18px; font-weight: bold; cursor: pointer; transition: background 0.3s; margin-top: 15px; }
        input[type="submit"]:hover { background-color: #5d112d; }
        .button-group { display: flex; gap: 15px; margin-top: 25px; }
        .btn-accion { flex: 1; padding: 12px; background-color: #555; color: white; border: none; border-radius: 6px; cursor: pointer; text-align: center; font-size: 15px; transition: 0.3s; text-decoration: none; font-weight: bold; }
        .btn-accion:hover { background-color: #333; }
        .logout { text-align: center; margin-top: 20px; }
        .logout a { color: #721538; text-decoration: none; font-weight: bold; }
        @media (max-width: 768px) { .form-row { flex-direction: column; gap: 0; } .card { padding: 20px; } }
    </style>
</head>
<body>

<div class="center-container">
    <div class="card">
        <h2>Registro de Material | Stock</h2>
        <form id="formStock">
            <div class="form-row">
                <div class="form-col">
                    <label for="tipo">Tipo de Material:</label>
                    <select id="tipo" name="tipo" required onchange="manejarMarcaModelo()">
                        <option value="">-- Selecciona --</option>
                        
                        <?php 
                        // ===============================================
                        // AQUI SE INYECTAN LOS TIPOS DE MATERIAL DESDE $todos_los_tipos
                        // ===============================================
                        if (!empty($todos_los_tipos)) {
                            foreach ($todos_los_tipos as $tipo) {
                                // Sanitizar la salida es una buena práctica
                                $safe_tipo = htmlspecialchars($tipo);
                                echo "<option value=\"{$safe_tipo}\">" . $safe_tipo . "</option>";
                            }
                        } else {
                            // Mensaje si no se pudieron cargar los tipos
                            echo "<option value=\"\" disabled>-- Error al cargar tipos --</option>";
                        }
                        ?>
                        
                    </select>
                </div>
                <div class="form-col">
                    <label for="unidades">Unidades:</label>
                    <input type="number" id="unidades" name="unidades" min="0" placeholder="Cantidad en stock" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-col">
                    <label for="marca">Marca:</label>
                    <input type="text" id="marca" name="marca" placeholder="Ej: Kingston, HP..." disabled>
                </div>
                <div class="form-col">
                    <label for="modelo">Modelo:</label>
                    <input type="text" id="modelo" name="modelo" placeholder="Ej: DataTraveler, A400" disabled>
                </div>
            </div>
            <label for="descripcion">Descripción / Observaciones:</label>
            <textarea id="descripcion" name="descripcion" rows="3" placeholder="Detalles, capacidad, color, etc."></textarea>
            <label for="fecha_alta">Fecha de Alta:</label>
            <input type="date" id="fecha_alta" name="fecha_alta" value="<?php echo date('Y-m-d'); ?>" required>
            <input type="submit" value="Registrar Material">
        </form>
        <div class="button-group">
            <button onclick="window.location.href='consultar_stock.php';" class="btn-accion">📋 Consultar Stock</button>
            <button onclick="window.location.href='dashboard.php';" class="btn-accion">🏠 Menú Principal</button>
        </div>
        <p class="logout"><a href="logout.php">Cerrar sesión</a></p>
    </div>
</div>

<script>
    // =================================================================
    // LÓGICA JAVASCRIPT: Habilitación de Marca y Modelo
    // =================================================================
    // Los tipos que requieren detalle, inyectados desde PHP (en minúsculas)
    const TIPOS_CON_DETALLE = <?php echo $tipos_con_detalle_json; ?>; 
    
    function manejarMarcaModelo() {
        const tipo = document.getElementById('tipo').value.toLowerCase();
        const marca = document.getElementById('marca');
        const modelo = document.getElementById('modelo');
        
        // Determina si el tipo requiere marca/modelo
        const requiere = TIPOS_CON_DETALLE.includes(tipo);
        
        marca.disabled = !requiere;
        modelo.disabled = !requiere;
        
        if (!requiere) {
            marca.value = ''; 
            modelo.value = '';
            // Si no se requiere, se remueve el 'required'
            marca.removeAttribute('required'); 
            modelo.removeAttribute('required');
        } else {
            // Si se requiere, se añade el 'required'
            marca.setAttribute('required', 'required'); 
            modelo.setAttribute('required', 'required');
        }
    }
    
    // Lógica de Envío del Formulario (se mantiene)
    document.getElementById('formStock').addEventListener('submit', function(e) {
        e.preventDefault();

        const tipo = document.getElementById('tipo').value.toLowerCase();
        const marca = document.getElementById('marca').value.trim();
        const modelo = document.getElementById('modelo').value.trim();

        // Validación de Marca/Modelo (si se requiere y está vacío)
        if (TIPOS_CON_DETALLE.includes(tipo) && (marca === '' || modelo === '')) {
            Swal.fire({
                icon: 'warning', 
                title: 'Campos Faltantes', 
                text: 'Para este tipo de material, la Marca y el Modelo son obligatorios.'
            });
            return;
        }
        
        let datos = new FormData(this);
        
        Swal.fire({
            title: 'Registrando...', didOpen: () => Swal.showLoading()
        });

        fetch('guardar_stock.php', { method: 'POST', body: datos })
        .then(response => response.json())
        .then(data => {
            if(data.success) {
                Swal.fire({ icon: 'success', title: '¡Registrado!', text: data.message, confirmButtonColor: '#721538' })
                .then(() => {
                    document.getElementById('formStock').reset();
                    document.getElementById('fecha_alta').value = '<?php echo date('Y-m-d'); ?>';
                    manejarMarcaModelo();
                });
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: data.message, confirmButtonColor: '#721538' });
            }
        })
        .catch(error => {
            console.error(error);
            Swal.fire({ icon: 'error', title: 'Error', text: 'Error de comunicación con guardar_stock.php' });
        });
    });

    // Ejecutar al cargar para establecer el estado inicial de los campos
    document.addEventListener('DOMContentLoaded', manejarMarcaModelo);
</script>
</body>
</html>