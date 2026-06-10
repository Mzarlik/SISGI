<?php
// Evitar el acceso directo si no hay sesión (Seguridad básica)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Variables de identidad
$nombre_municipio = "Hacienda del Estado de Quintana Roo";
$sistema_nombre = "Sistema de Inventario SATQ";
$usuario_logueado = $_SESSION['usuario'] ?? 'Invitado';
$rol_usuario = $_SESSION['rol'] ?? 'usuario';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $sistema_nombre; ?></title>

    <link rel="stylesheet" href="css/all.min.css">

    <script src="js/tailwindcss.js"></script>

    <script src="js/sweetalert2.all.min.js"></script>
    <script src="js/session_timer.js"></script>

    <script src="js/jspdf.umd.min.js"></script>
    <script src="js/jspdf.plugin.autotable.min.js"></script>

    <script>
        // Configuración de colores institucionales para Tailwind
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'primary-dark': '#721538',
                        'primary-light': '#961e4b',
                        'accent': '#d6d1ca'
                    }
                }
            }
        }
    </script>

    <style>
        body { background-color: #d6d1ca; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .nav-shadow { box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        /* Ocultar scrollbar en vistas limpias */
        .scrollbar-hide::-webkit-scrollbar { display: none; }
    </style>
</head>
<body class="min-h-screen">

<nav class="bg-primary-dark text-white nav-shadow sticky top-0 z-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">
            <a href="dashboard.php" class="flex items-center gap-3 hover:opacity-90 transition-opacity text-white no-underline">
                <div class="bg-white p-1 rounded-lg">
                    <i class="fas fa-laptop-code text-primary-dark text-xl"></i>
                </div>
                <div>
                    <h1 class="text-sm font-bold leading-tight uppercase tracking-tighter">
                        <?php echo $nombre_municipio; ?>
                    </h1>
                    <p class="text-[10px] text-accent/80 font-medium">
                        <?php echo $sistema_nombre; ?>
                    </p>
                </div>
            </a>

            <div class="flex items-center gap-4">
                <div class="text-right hidden sm:block">
                    <p class="text-xs font-bold leading-none"><?php echo htmlspecialchars($usuario_logueado); ?></p>
                    <p class="text-[10px] text-accent uppercase tracking-widest"><?php echo $rol_usuario; ?></p>
                </div>
                <div class="h-8 w-8 rounded-full bg-primary-light flex items-center justify-center border border-white/20">
                    <i class="fas fa-user text-xs"></i>
                </div>
                <a href="logout.php" class="text-white/70 hover:text-white transition-colors" title="Cerrar Sesión">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </div>
    </div>
</nav>

<main class="py-6"></main>