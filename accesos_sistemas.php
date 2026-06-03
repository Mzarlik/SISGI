<?php
// accesos_sistemas.php
require_once 'session_check.php';
require_once 'config.php';
session_start();

if (!isset($_SESSION['usuario'])) {
    header("Location: index.php");
    exit();
}

$conn = get_db_connection();
$conn->set_charset("utf8mb4");

// Traemos los sistemas activos ordenados por categoría y luego por orden personalizado
$sql = "SELECT * FROM sistemas_links WHERE activo = 1 ORDER BY categoria DESC, orden ASC";
$result = $conn->query($sql);

// Agrupamos los resultados en un array asociativo por categoría
$sistemas = [];
while ($row = $result->fetch_assoc()) {
    $sistemas[$row['categoria']][] = $row;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Accesos a Sistemas</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --bg-body: #f3f4f6;
            --text-main: #374151;
            --brand: #721538;
        }
        
        body { font-family: 'Segoe UI', sans-serif; background-color: var(--bg-body); margin: 0; padding: 20px; color: var(--text-main); }
        
        .container { max-width: 1100px; margin: 0 auto; }
        
        /* Header */
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .header h1 { margin: 0; color: var(--brand); font-size: 1.8rem; display: flex; align-items: center; gap: 10px; }
        .btn-back { text-decoration: none; color: #555; background: white; padding: 10px 15px; border-radius: 8px; border: 1px solid #ddd; font-weight: bold; transition: 0.2s; }
        .btn-back:hover { background: #eee; }

        /* Categorías */
        .category-section { margin-bottom: 40px; }
        .category-title { 
            font-size: 1.2rem; color: #555; border-bottom: 2px solid #e5e7eb; 
            padding-bottom: 10px; margin-bottom: 20px; font-weight: 600; text-transform: uppercase; letter-spacing: 1px;
        }

        /* GRID DE TARJETAS */
        .grid-links { 
            display: grid; 
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); 
            gap: 20px; 
        }

        /* DISEÑO DE LA TARJETA */
        .card-link {
            background: white;
            border-radius: 12px;
            text-decoration: none;
            color: #333;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            transition: transform 0.2s, box-shadow 0.2s;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            border: 1px solid rgba(0,0,0,0.05);
        }

        .card-link:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px rgba(0,0,0,0.1);
        }

        .card-icon {
            height: 100px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            color: white;
        }

        .card-body {
            padding: 15px;
            text-align: center;
        }

        .card-title { font-weight: bold; font-size: 1.1em; margin-bottom: 5px; }
        .card-url { font-size: 0.8em; color: #888; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

        /* Icono de enlace externo */
        .external-icon { font-size: 0.7em; margin-left: 5px; opacity: 0.5; }

    </style>
</head>
<body>

<div class="container">
    
    <div class="header">
        <h1><i class="fas fa-th"></i> Hub de Aplicaciones</h1>
        <a href="dashboard.php" class="btn-back"><i class="fas fa-arrow-left"></i> Volver al Inicio</a>
    </div>

    <?php if (empty($sistemas)): ?>
        <div style="text-align:center; padding:50px; color:#777;">
            <h2>No hay sistemas configurados</h2>
            <p>Agrega registros en la tabla 'sistemas_links' de tu base de datos.</p>
        </div>
    <?php endif; ?>

    <?php if (isset($sistemas['Turnos'])): ?>
    <div class="category-section">
        <div class="category-title"><i class="fas fa-users-cog"></i> Sistemas de Turnos</div>
        <div class="grid-links">
            <?php foreach ($sistemas['Turnos'] as $sys): ?>
                <a href="<?php echo $sys['url']; ?>" target="_blank" class="card-link">
                    <div class="card-icon" style="background-color: <?php echo $sys['color_fondo']; ?>;">
                        <i class="<?php echo $sys['icono']; ?>"></i>
                    </div>
                    <div class="card-body">
                        <div class="card-title"><?php echo $sys['nombre']; ?></div>
                        <div class="card-url"><?php echo $sys['url']; ?></div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if (isset($sistemas['Externos'])): ?>
    <div class="category-section">
        <div class="category-title"><i class="fas fa-globe"></i> Enlaces Externos</div>
        <div class="grid-links">
            <?php foreach ($sistemas['Externos'] as $sys): ?>
                <a href="<?php echo $sys['url']; ?>" target="_blank" class="card-link">
                    <div class="card-icon" style="background-color: <?php echo $sys['color_fondo']; ?>;">
                        <i class="<?php echo $sys['icono']; ?>"></i>
                    </div>
                    <div class="card-body">
                        <div class="card-title">
                            <?php echo $sys['nombre']; ?> <i class="fas fa-external-link-alt external-icon"></i>
                        </div>
                        <div class="card-url">Abrir sistema</div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

</div>

</body>
</html>