<?php 
require_once 'config.php'; 
session_start();

// 1. SEGURIDAD
if (!isset($_SESSION['usuario'])) { 
    header("Location: login.php"); 
    exit(); 
}

if ($_SESSION['rol'] === 'redes') {
    // Si es redes, lo regresamos al dashboard con un mensaje de error opcional
    header("Location: dashboard.php?error=acceso_denegado");
    exit();
}

$conn = get_db_connection();

// 2. OBTENER MATERIALES CON STOCK DISPONIBLE
// MODIFICADO: Se agregó 'descripcion' a la lista de columnas SELECT.
$sql = "SELECT id, tipo, marca, modelo, descripcion, unidades FROM stock_material WHERE unidades > 0 ORDER BY tipo ASC, marca ASC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Salida de Material</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <link rel="stylesheet" href="css/style.css"> 
    <script src="js/sweetalert2.all.min.js"></script>
    <script src="js/tailwindcss.js"></script>
    <link rel="stylesheet" href="css/all.min.css">
    <style>
        /* ESTILOS DE TU PROYECTO */
        body { background-color: #d6d1ca; font-family: 'Segoe UI', sans-serif; }
        .card { background-color: #ffffff; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .btn-primary { background-color: #721538; color: white; transition: 0.3s; }
        .btn-primary:hover { background-color: #5d112d; }
        
        /* Estilos para inputs */
        label { font-weight: bold; color: #555; display: block; margin-bottom: 5px; }
        .input-custom {
            width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 6px; 
            background: #f9f9f9; outline: none; transition: border 0.3s;
        }
        .input-custom:focus { border-color: #721538; background: #fff; }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4">

<div class="w-full max-w-lg">
    
    <div class="mb-4 flex justify-between items-center">
        <h2 class="text-2xl font-bold text-[#721538] flex items-center gap-2">
            <i class="fas fa-dolly-flatbed"></i> Salida de Material
        </h2>
        <a href="consultar_stock.php" class="text-gray-600 hover:text-[#721538] font-medium text-sm">
            <i class="fas fa-arrow-left"></i> Volver al Stock
        </a>
    </div>

    <div class="card p-8">
        <form id="formSalida">
            
            <div class="mb-6">
                <label for="material_id">Seleccionar Material:</label>
                <select id="material_id" name="id" class="input-custom cursor-pointer" required onchange="actualizarMaximo()">
                    <option value="" data-stock="0">-- Elige un producto --</option>
                    <?php if($result->num_rows > 0): while($row = $result->fetch_assoc()): ?>
                        <option value="<?= $row['id'] ?>" data-stock="<?= $row['unidades'] ?>">
                         <?= htmlspecialchars($row['tipo']) ?> - <?= htmlspecialchars($row['marca']) ?> 
                         <?= htmlspecialchars($row['descripcion']) ?> (Stock: <?= $row['unidades'] ?>) 
                        </option>
                    <?php endwhile; endif; ?>
                </select>
                <p class="text-xs text-gray-500 mt-1" id="infoStock">Selecciona un producto para ver disponibilidad.</p>
            </div>

            <div class="mb-6">
                <label for="cantidad">Cantidad a ocupar:</label>
                <div class="flex items-center gap-2">
                    <input type="number" id="cantidad" name="cantidad" class="input-custom text-center font-bold text-lg" 
                           min="1" value="1" required>
                </div>
            </div>

            <div class="mb-6">
                <label for="motivo">Motivo / Destino (Opcional):</label>
                <textarea id="motivo" name="descripcion_salida" rows="2" class="input-custom" placeholder="Ej: Para el equipo de Finanzas..."></textarea>
            </div>

            <button type="submit" class="w-full btn-primary py-3 rounded-lg font-bold text-lg shadow-md flex justify-center items-center gap-2">
                <i class="fas fa-check-circle"></i> Confirmar Salida
            </button>

        </form>
    </div>
</div>

<script>
    // Validar que no se pidan más de los que hay
    function actualizarMaximo() {
        const select = document.getElementById('material_id');
        const inputCant = document.getElementById('cantidad');
        const info = document.getElementById('infoStock');
        
        // Obtener stock del atributo data-stock
        const stockDisponible = parseInt(select.options[select.selectedIndex].getAttribute('data-stock')) || 0;
        
        inputCant.max = stockDisponible;
        
        if(stockDisponible > 0) {
            info.innerHTML = `Disponibles: <span class="font-bold text-[#721538]">${stockDisponible}</span> unidades.`;
            info.classList.remove('text-red-500');
        } else {
            info.innerHTML = "Selecciona un producto.";
        }
    }

    // Enviar formulario
    document.getElementById('formSalida').addEventListener('submit', function(e) {
        e.preventDefault();

        const select = document.getElementById('material_id');
        const stockActual = parseInt(select.options[select.selectedIndex].getAttribute('data-stock'));
        const cantidadSolicitada = parseInt(document.getElementById('cantidad').value);

        // Validación frontend extra
        if (cantidadSolicitada > stockActual) {
            Swal.fire('Error', `Solo hay ${stockActual} unidades disponibles.`, 'warning');
            return;
        }

        const datos = new FormData(this);

        Swal.fire({
            title: 'Procesando...',
            text: 'Actualizando inventario',
            didOpen: () => Swal.showLoading()
        });

        fetch('procesar_salida.php', { method: 'POST', body: datos })
        .then(response => response.json())
        .then(data => {
            if(data.success) {
                Swal.fire({
                    icon: 'success',
                    title: '¡Salida registrada!',
                    text: `Stock restante: ${data.nuevo_stock}`,
                    confirmButtonColor: '#721538'
                }).then(() => {
                    // Recargar para actualizar el select
                    location.reload(); 
                    // Opcional: window.location.href = 'consultar_stock.php';
                });
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: data.message, confirmButtonColor: '#721538' });
            }
        })
        .catch(error => {
            Swal.fire({ icon: 'error', title: 'Error', text: 'Error de conexión con el servidor.' });
        });
    });
</script>

</body>
</html>
<?php $conn->close(); ?>