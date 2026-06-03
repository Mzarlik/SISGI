<?php
require_once 'config.php';

// Configuración de sesión segura (10 minutos)
ini_set('session.gc_maxlifetime', 600);
session_set_cookie_params(600);

session_start();

// 1. VERIFICACIÓN DE SEGURIDAD
if (!isset($_SESSION['usuario'])) {
    header("Location: index.php");
    exit();
}

$usuario_actual = htmlspecialchars($_SESSION['usuario']);
$rol_usuario = $_SESSION['rol'] ?? 'tecnico';
$esAdmin = ($rol_usuario === 'admin' || $rol_usuario === 'masterweb');
$esTecnico = ($rol_usuario === 'tecnico');

// Definimos quién puede ver todo el contenido
$puedeVerTodo = ($esAdmin || $esTecnico);

// 2. OBTENER ESTADÍSTICAS (KPIs)
$conn = get_db_connection();

// Inicializamos contadores
$total_directorio = 0;
$total_licencias = 0;
$total_nas = 0;
$total_ad = 0;
$total_equipos = 0;
$total_mater = 0;

if ($conn) {
    // 2. Licencias Office (Visible para todos, incluido redes)
    $res = $conn->query("SELECT COUNT(*) as total FROM cuentas_office");
    if ($res) $total_licencias = $res->fetch_assoc()['total'];

    // Directorio
    $res = $conn->query("SELECT COUNT(*) as total FROM directorio_ext");
    if ($res) $total_directorio = $res->fetch_assoc()['total'];

    // Consultas solo para Admin y Técnico
    if ($puedeVerTodo) {
        // NAS
        $res = $conn->query("SELECT COUNT(*) as total FROM carpetas_nas");
        if ($res) $total_nas = $res->fetch_assoc()['total'];

        // Usuarios AD
        $res = $conn->query("SELECT COUNT(*) as total FROM registros_ad");
        if ($res) $total_ad = $res->fetch_assoc()['total'];

        // Equipos
        $res = $conn->query("SELECT COUNT(*) as total FROM equiposbd");
        if ($res) $total_equipos = $res->fetch_assoc()['total'];

        // Stock Material
        $res = $conn->query("SELECT COUNT(*) as total FROM stock_material");
        if ($res) $total_mater = $res->fetch_assoc()['total'];
    }
    
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | Sistema de Gestión</title>
    
    <link rel="stylesheet" href="css/fonts.css">   <link rel="stylesheet" href="css/estilos.css"> <link rel="stylesheet" href="css/all.min.css"> <script src="js/sweetalert2.all.min.js"></script></head>
<body class="bg-brand-bg min-h-screen text-gray-800">

    <nav class="bg-brand-dark shadow-sm sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center gap-3">
                    <div class="bg-brand-bg text-black p-2 rounded-lg shadow-md">
                        <i class="fas fa-layer-group"></i>
                    </div>
                    <span class="font-bold text-xl text-white tracking-tight">Sistema de Gestión</span>
                </div>

                <div class="flex items-center gap-4">
                    <div class="hidden md:flex flex-col items-end">
                        <span class="text-sm font-bold text-white">Hola, <?= ucfirst($usuario_actual) ?></span>
                        <span class="text-xs text-white uppercase"><?= $rol_usuario ?></span>
                    </div>
                    <div class="h-8 w-px bg-white-200 mx-2 hidden md:block"></div>
                    <a href="logout.php" class="text-white hover:text-red-700 text-sm font-medium transition flex items-center gap-2 px-3 py-2 rounded-lg hover:bg-red-50">
                        <i class="fas fa-sign-out-alt"></i> <span class="hidden sm:inline">Salir</span>                                      
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

        <div class="mb-10 animate-fade-in-up">
            <h1 class="text-3xl font-extrabold text-gray-800">Panel de Control</h1>
            <p class="text-gray-600 mt-2 text-lg">Resumen general del estado del sistema.</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-12">
            <a href="consultar_directorio.php" 
                class="group bg-white rounded-2xl p-6 shadow-md hover:shadow-xl transition-all duration-300 transform hover:-translate-y-1 border border-transparent hover:border-brand-dark/10 animate-fade-in-up"
                style="animation-delay: 0.1s">
                <div class="flex items-start justify-between mb-4">
                    <div>
                        <p class="text-gray-500 text-xs font-bold uppercase tracking-wider mb-1">Extensiones</p>
                        <h3 class="text-xl font-bold text-gray-800 group-hover:text-brand-dark transition-colors">Directorio</h3>
                    </div>
                    <div class="w-12 h-12 bg-brand-dark/10 text-brand-dark rounded-xl flex items-center justify-center text-xl group-hover:bg-brand-dark group-hover:text-white transition-colors duration-300">
                        <i class="fas fa-address-book"></i>
                    </div>
                </div>
                <div class="flex items-end justify-between mt-4">
                    <span class="text-4xl font-extrabold text-gray-900 counter" data-target="<?= $total_directorio ?>">0</span>
                    <span class="text-sm text-brand-light font-medium flex items-center gap-1 group-hover:translate-x-1 transition-transform">
                        Ver todo <i class="fas fa-arrow-right text-xs"></i>
                    </span>
                </div>
            </a>

            <a href="consultar_licencias.php" 
               class="group bg-white rounded-2xl p-6 shadow-md hover:shadow-xl transition-all duration-300 transform hover:-translate-y-1 border border-transparent hover:border-blue-200 animate-fade-in-up"
               style="animation-delay: 0.2s">
                <div class="flex items-start justify-between mb-4">
                    <div>
                        <p class="text-gray-500 text-xs font-bold uppercase tracking-wider mb-1">Licencias</p>
                        <h3 class="text-xl font-bold text-gray-800 group-hover:text-blue-700 transition-colors">Apps 365</h3>
                    </div>
                    <div class="w-12 h-12 bg-blue-50 text-blue-600 rounded-xl flex items-center justify-center text-xl group-hover:bg-blue-600 group-hover:text-white transition-colors duration-300">
                        <i class="fab fa-windows"></i>
                    </div>
                </div>
                <div class="flex items-end justify-between mt-4">
                    <span class="text-4xl font-extrabold text-gray-900 counter" data-target="<?= $total_licencias ?>">0</span>
                    <span class="text-sm text-blue-600 font-medium flex items-center gap-1 group-hover:translate-x-1 transition-transform">
                        Administrar <i class="fas fa-arrow-right text-xs"></i>
                    </span>
                </div>
            </a>

            <?php if ($puedeVerTodo): ?>
            <a href="consultar_nas.php" 
               class="group bg-white rounded-2xl p-6 shadow-md hover:shadow-xl transition-all duration-300 transform hover:-translate-y-1 border border-transparent hover:border-gray-300 animate-fade-in-up"
               style="animation-delay: 0.3s">
                <div class="flex items-start justify-between mb-4">
                    <div>
                        <p class="text-gray-500 text-xs font-bold uppercase tracking-wider mb-1">NAS</p>
                        <h3 class="text-xl font-bold text-gray-800 group-hover:text-gray-900 transition-colors">Carpetas</h3>
                    </div>
                    <div class="w-12 h-12 bg-gray-100 text-gray-600 rounded-xl flex items-center justify-center text-xl group-hover:bg-gray-700 group-hover:text-white transition-colors duration-300">
                        <i class="fas fa-network-wired"></i>
                    </div>
                </div>
                <div class="flex items-end justify-between mt-4">
                    <span class="text-4xl font-extrabold text-gray-900 counter" data-target="<?= $total_nas ?>">0</span>
                    <span class="text-sm text-gray-600 font-medium flex items-center gap-1 group-hover:translate-x-1 transition-transform">
                        Gestionar <i class="fas fa-arrow-right text-xs"></i>
                    </span>
                </div>
            </a>

            <a href="consultar_usuarios.php" 
               class="group bg-white rounded-2xl p-6 shadow-md hover:shadow-xl transition-all duration-300 transform hover:-translate-y-1 border border-transparent hover:border-violet-300 animate-fade-in-up"
               style="animation-delay: 0.4s">
                <div class="flex items-start justify-between mb-4">
                    <div>
                        <p class="text-gray-500 text-xs font-bold uppercase tracking-wider mb-1">Active Directory</p>
                        <h3 class="text-xl font-bold text-gray-800 group-hover:text-violet-700 transition-colors">Usuarios AD</h3>
                    </div>
                    <div class="w-12 h-12 bg-violet-50 text-violet-600 rounded-xl flex items-center justify-center text-xl group-hover:bg-violet-600 group-hover:text-white transition-colors duration-300">
                        <i class="fas fa-users-cog"></i>
                    </div>
                </div>
                <div class="flex items-end justify-between mt-4">
                    <span class="text-4xl font-extrabold text-gray-900 counter" data-target="<?= $total_ad ?>">0</span>
                    <span class="text-sm text-violet-600 font-medium flex items-center gap-1 group-hover:translate-x-1 transition-transform">
                        Gestionar <i class="fas fa-arrow-right text-xs"></i>
                    </span>
                </div>
            </a>

            <a href="consultar_equipos.php" 
               class="group bg-white rounded-2xl p-6 shadow-md hover:shadow-xl transition-all duration-300 transform hover:-translate-y-1 border border-transparent hover:border-cyan-300 animate-fade-in-up"
               style="animation-delay: 0.5s">
                <div class="flex items-start justify-between mb-4">
                    <div>
                        <p class="text-gray-500 text-xs font-bold uppercase tracking-wider mb-1">Levantamientos</p>
                        <h3 class="text-xl font-bold text-gray-800 group-hover:text-cyan-700 transition-colors">Equipos</h3>
                    </div>
                    <div class="w-12 h-12 bg-cyan-50 text-cyan-600 rounded-xl flex items-center justify-center text-xl group-hover:bg-cyan-600 group-hover:text-white transition-colors duration-300">
                        <i class="fas fa-laptop"></i>
                    </div>
                </div>
                <div class="flex items-end justify-between mt-4">
                    <span class="text-4xl font-extrabold text-gray-900 counter" data-target="<?= $total_equipos ?>">0</span>
                    <span class="text-sm text-cyan-600 font-medium flex items-center gap-1 group-hover:translate-x-1 transition-transform">
                        Inventario <i class="fas fa-arrow-right text-xs"></i>
                    </span>
                </div>
            </a>

            <a href="consultar_stock.php" 
               class="group bg-white rounded-2xl p-6 shadow-md hover:shadow-xl transition-all duration-300 transform hover:-translate-y-1 border border-transparent hover:border-emerald-300 animate-fade-in-up"
               style="animation-delay: 0.6s">
                <div class="flex items-start justify-between mb-4">
                    <div>
                        <p class="text-gray-500 text-xs font-bold uppercase tracking-wider mb-1">Stock de Material</p>
                        <h3 class="text-xl font-bold text-gray-800 group-hover:text-emerald-700 transition-colors">Total de registros</h3>
                    </div>
                    <div class="w-12 h-12 bg-emerald-50 text-emerald-600 rounded-xl flex items-center justify-center text-xl group-hover:bg-emerald-600 group-hover:text-white transition-colors duration-300">
                        <i class="fas fa-boxes"></i>
                    </div>
                </div>
                <div class="flex items-end justify-between mt-4">
                    <span class="text-4xl font-extrabold text-gray-900 counter" data-target="<?= $total_mater ?>">0</span>
                    <span class="text-sm text-emerald-600 font-medium flex items-center gap-1 group-hover:translate-x-1 transition-transform">
                        Ver inventario <i class="fas fa-arrow-right text-xs"></i>
                    </span>
                </div>
            </a>
            <?php endif; ?>

        </div>
        
        <?php if ($puedeVerTodo): ?>
        <div class="animate-fade-in-up" style="animation-delay: 0.7s">
            <h2 class="text-lg font-bold text-gray-700 mb-4 flex items-center gap-2">
                <i class="fas fa-bolt text-yellow-500"></i> Acciones Rápidas
            </h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4">
                
                <a href="perfil_360.php" class="flex items-center gap-3 p-4 bg-white border border-indigo-200 rounded-xl hover:border-indigo-500 hover:shadow-md transition-all group ring-1 ring-indigo-50">
                    <div class="bg-indigo-100 text-indigo-600 w-10 h-10 rounded-full flex items-center justify-center group-hover:bg-indigo-600 group-hover:text-white transition">
                        <i class="fas fa-search-location"></i>
                    </div>
                    <span class="font-medium text-gray-700 group-hover:text-gray-900 text-sm">Búsqueda 360°</span>
                </a>

                <a href="registro_directorio.php" class="flex items-center gap-3 p-4 bg-white border border-gray-200 rounded-xl hover:border-brand-dark hover:shadow-md transition-all group">
                    <div class="bg-green-100 text-green-600 w-10 h-10 rounded-full flex items-center justify-center group-hover:bg-green-600 group-hover:text-white transition">
                        <i class="fas fa-user-plus"></i>
                    </div>
                    <span class="font-medium text-gray-700 group-hover:text-gray-900 text-sm">Nueva extensión</span>
                </a>

                <a href="registro_nas.php" class="flex items-center gap-3 p-4 bg-white border border-gray-200 rounded-xl hover:border-gray-500 hover:shadow-md transition-all group">
                    <div class="bg-gray-100 text-gray-600 w-10 h-10 rounded-full flex items-center justify-center group-hover:bg-gray-600 group-hover:text-white transition">
                        <i class="fas fa-folder-plus"></i>
                    </div>
                    <span class="font-medium text-gray-700 group-hover:text-gray-900 text-sm">Nueva Carpeta NAS</span>
                </a>

                <a href="registro.php" class="flex items-center gap-3 p-4 bg-white border border-gray-200 rounded-xl hover:border-violet-500 hover:shadow-md transition-all group">
                    <div class="bg-violet-100 text-violet-600 w-10 h-10 rounded-full flex items-center justify-center group-hover:bg-violet-600 group-hover:text-white transition">
                        <i class="fas fa-user-shield"></i>
                    </div>
                    <span class="font-medium text-gray-700 group-hover:text-gray-900 text-sm">Nuevo Usuario AD</span>
                </a>

                <a href="registro_equipos.php" class="flex items-center gap-3 p-4 bg-white border border-gray-200 rounded-xl hover:border-cyan-500 hover:shadow-md transition-all group">
                    <div class="bg-cyan-100 text-cyan-600 w-10 h-10 rounded-full flex items-center justify-center group-hover:bg-cyan-600 group-hover:text-white transition">
                        <i class="fas fa-desktop"></i>
                    </div>
                    <span class="font-medium text-gray-700 group-hover:text-gray-900 text-sm">Nuevo Equipo</span>
                </a>

                <a href="registrar_material.php" class="flex items-center gap-3 p-4 bg-white border border-gray-200 rounded-xl hover:border-emerald-500 hover:shadow-md transition-all group">
                    <div class="bg-emerald-100 text-emerald-600 w-10 h-10 rounded-full flex items-center justify-center group-hover:bg-emerald-600 group-hover:text-white transition">
                        <i class="fas fa-box-open"></i>
                    </div>
                    <span class="font-medium text-gray-700 group-hover:text-gray-900 text-sm">Nuevo Material</span>
                </a>

                <a href="consultar_inventario.php" 
                    class="flex items-center gap-4 p-4 bg-white border border-gray-100 rounded-xl 
                    shadow-lg hover:shadow-xl hover:border-emerald-600 transition-all duration-300 group">
                    <div class="bg-emerald-50 text-emerald-700 w-12 h-10 rounded-full flex items-center justify-center 
                    text-xl group-hover:bg-emerald-600 group-hover:text-white transition-colors duration-300">
                        <i class="fas fa-desktop"></i>
                    </div>
                    <div>
                        <span class="text-xs font-medium text-gray-500 block">Módulo</span>
                        <span class="font-extrabold text-lg text-gray-800 group-hover:text-emerald-700 transition-colors">
                            Inventario Soporte
                        </span>
                    </div>
                </a>

                <?php if ($esAdmin): ?>
                <a href="registro_licencia.php" class="flex items-center gap-3 p-4 bg-white border border-gray-200 rounded-xl hover:border-blue-500 hover:shadow-md transition-all group">
                    <div class="bg-blue-100 text-blue-600 w-10 h-10 rounded-full flex items-center justify-center group-hover:bg-blue-600 group-hover:text-white transition">
                        <i class="fas fa-key"></i>
                    </div>
                    <span class="font-medium text-gray-700 group-hover:text-gray-900 text-sm">Nueva Licencia 365</span>
                </a>
                
                <a href="alta_usuarios.php" class="flex items-center gap-3 p-4 bg-white border border-amber-200 rounded-xl hover:border-amber-500 hover:shadow-md transition-all group ring-1 ring-amber-100">
                    <div class="bg-amber-100 text-amber-600 w-10 h-10 rounded-full flex items-center justify-center group-hover:bg-amber-600 group-hover:text-white transition">
                        <i class="fas fa-user-shield"></i>
                    </div>
                    <div class="flex flex-col">
                        <span class="font-bold text-gray-800 text-sm group-hover:text-amber-700">Crear Usuario Sistema</span>
                        <span class="text-[10px] text-gray-400 font-medium">Acceso Dashboard</span>
                    </div>
                </a>

                <a href="consultar_usuarios_sistema.php" class="flex items-center gap-3 p-4 bg-white border border-blue-200 rounded-xl hover:border-blue-500 hover:shadow-md transition-all group ring-1 ring-blue-100">
                    <div class="bg-blue-100 text-blue-600 w-10 h-10 rounded-full flex items-center justify-center group-hover:bg-blue-600 group-hover:text-white transition">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="flex flex-col">
                        <span class="font-bold text-gray-800 text-sm group-hover:text-blue-700">Ver Usuarios Activos</span>
                        <span class="text-[10px] text-gray-400 font-medium">Lista de accesos</span>
                    </div>
                </a>

                <a href="cambiar_password.php" class="flex items-center gap-3 p-4 bg-white border border-purple-200 rounded-xl hover:border-purple-500 hover:shadow-md transition-all group ring-1 ring-purple-100">
                    <div class="bg-purple-100 text-purple-600 w-10 h-10 rounded-full flex items-center justify-center group-hover:bg-purple-600 group-hover:text-white transition">
                        <i class="fas fa-unlock-alt"></i>
                    </div>
                    <div class="flex flex-col">
                        <span class="font-bold text-gray-800 text-sm group-hover:text-purple-700">Cambiar Contraseñas</span>
                        <span class="text-[10px] text-gray-400 font-medium">Resetear Acceso</span>
                    </div>
                </a>

                <a href="consultar_adq_equipos.php" class="flex items-center gap-3 p-4 bg-white border border-gray-200 rounded-xl hover:border-cyan-500 hover:shadow-md transition-all group">
                    <div class="bg-cyan-100 text-cyan-600 w-10 h-10 rounded-full flex items-center justify-center group-hover:bg-cyan-600 group-hover:text-white transition">
                        <i class="fas fa-cart-plus"></i>
                    </div>
                    <div class="flex flex-col">
                        <span class="font-medium text-gray-700 group-hover:text-gray-900 text-sm">Adquisiciones</span>
                        <span class="text-[10px] text-gray-400 font-medium">Suministros</span>
                    </div>
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

    </main>

    <footer class="mt-auto py-6 text-center text-gray-500 text-sm">
        <p>&copy; <?= date("Y") ?> Sistema de Soporte de Gestión Interna. <br> Todos los derechos reservados. <br> (SISGI) M.O.A </p>
    </footer>

    <script>
        document.addEventListener("DOMContentLoaded", () => {
            const counters = document.querySelectorAll('.counter');
            const speed = 200;

            counters.forEach(counter => {
                const updateCount = () => {
                    const target = +counter.getAttribute('data-target');
                    const count = +counter.innerText;
                    const inc = target / speed;

                    if (count < target) {
                        counter.innerText = Math.ceil(count + inc);
                        setTimeout(updateCount, 20);
                    } else {
                        counter.innerText = target;
                    }
                };
                updateCount();
            });
        });
    </script>

</body>
</html>