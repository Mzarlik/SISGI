<?php
require_once 'session_check.php';
require_once 'config.php';

// 1. SEGURIDAD
if (!isset($_SESSION['usuario'])) {
    header("Location: index.php");
    exit();
}

$usuario_actual = htmlspecialchars($_SESSION['usuario']);
$rol_usuario = $_SESSION['rol'] ?? 'tecnico';
$esAdmin = ($rol_usuario === 'admin' || $rol_usuario === 'masterweb');
$esTecnico = ($rol_usuario === 'tecnico');
$puedeVerTodo = ($esAdmin || $esTecnico);

// 2. DATOS KPI Y LÓGICA
$conn = get_db_connection();

// Inicializar variables
$total_inventario = 0;
$total_ad = 0;
$total_equipos = 0;
$total_mater = 0;
$lista_anuncios = [];

if ($conn) {
    
    // --- LÓGICA DE ANUNCIOS (POST: Insertar y Borrar) ---
    if ($esAdmin && $_SERVER['REQUEST_METHOD'] === 'POST') {
        // 1. Crear Aviso
        if (isset($_POST['crear_aviso'])) {
            $titulo = $conn->real_escape_string($_POST['titulo']);
            $mensaje = $conn->real_escape_string($_POST['mensaje']);
            $tipo = $conn->real_escape_string($_POST['tipo']); // urgente, info, general
            
            $sql = "INSERT INTO anuncios (titulo, mensaje, tipo) VALUES ('$titulo', '$mensaje', '$tipo')";
            $conn->query($sql);
            header("Location: dashboard.php"); 
            exit();
        }
        
        // 2. Borrar Aviso
        if (isset($_POST['borrar_aviso'])) {
            $id_aviso = (int)$_POST['id_aviso'];
            $conn->query("DELETE FROM anuncios WHERE id = $id_aviso");
            header("Location: dashboard.php");
            exit();
        }
    }

    // --- CONSULTAS ---

    // A. Inventario General (DNTICS)
    $res = $conn->query("SELECT COUNT(*) as total FROM inventario_soporte");
    if ($res) $total_inventario = $res->fetch_assoc()['total'] ?? 0;

    // B. Obtener Lista de Anuncios (Últimos 10)
    $res_anuncios = $conn->query("SELECT * FROM anuncios ORDER BY fecha DESC LIMIT 10");
    if ($res_anuncios) {
        while ($row = $res_anuncios->fetch_assoc()) {
            $lista_anuncios[] = $row;
        }
    }

    // C. Consultas restringidas (KPIs)
    if ($puedeVerTodo) {
        // Usuarios AD
        $res = $conn->query("SELECT COUNT(*) as total FROM registros_ad");
        if ($res) $total_ad = $res->fetch_assoc()['total'] ?? 0;

        // Equipos (Solo Tecnología, sin muebles)
        $furniture_types = "'Silla', 'Escritorio', 'Mueble', 'Archivero', 'Silla de oficina', 'Escritorio de oficina'";
        $res = $conn->query("SELECT COUNT(inv.id) as total FROM inventario_soporte inv LEFT JOIN tipo_bien_inventario tbi ON inv.id_tipo_bien = tbi.id_tipo WHERE tbi.nombre_tipo NOT IN ($furniture_types)");
        if ($res) $total_equipos = $res->fetch_assoc()['total'] ?? 0;

        // Stock Material
        $res = $conn->query("SELECT COUNT(*) as total FROM stock_material");
        if ($res) $total_mater = $res->fetch_assoc()['total'] ?? 0;
    }
    
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Pro | Sistema de Gestión</title>
    
    <script src="js/tailwindcss.js"></script>
    <link rel="stylesheet" href="css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'brand': '#721538',       // Vino Institucional
                        'brand-dark': '#500e26',
                        'brand-light': '#9d2449',
                    }
                }
            }
        }
    </script>
    <style>
        .scrollbar-hide::-webkit-scrollbar { display: none; }
        .scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }
        /* Scrollbar personalizada para el tablón */
        .custom-scrollbar::-webkit-scrollbar { width: 4px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: #f1f1f1; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 4px; }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #9ca3af; }
    </style>
</head>
<body class="bg-gray-100 font-sans text-gray-800 overflow-hidden">

<div class="flex h-screen">

    <aside id="sidebar" class="w-64 bg-brand text-white flex flex-col transition-all duration-300 fixed md:relative z-30 h-full hidden md:flex shadow-2xl">
        
        <div class="h-16 flex items-center px-6 bg-brand-dark border-b border-white/10 shadow-sm">
            <i class="fas fa-layer-group text-xl mr-3 text-white/90"></i>
            <span class="text-lg font-bold tracking-wide">SISGI </span>
        </div>

        <?php
        $rolMenu = $_SESSION['rol'] ?? '';
        
        // Definición de permisos por bloque
        $esAdminTec    = in_array($rolMenu, ['admin', 'tecnico']);
        $esRedes       = ($rolMenu === 'redes');
        $esRecMaster   = in_array($rolMenu, ['recepcion', 'masterweb']);
        
        // Lógica de visibilidad
        $verCalendario = $esAdminTec;
        $verNas        = $esAdminTec;
        $verInventario = ($esAdminTec || $esRecMaster || $esRedes ); // Redes NO lo ve
        $verDirectorio = ($esAdminTec || $esRedes || $esRecMaster);
        $verLicencias  = ($esAdminTec || $esRedes);
        $verRegistros  = $esAdminTec; // Usuarios AD, Equipos, SISGI, etc.
        ?>

        <div class="flex-1 overflow-y-auto py-4 px-3 space-y-1 scrollbar-hide">

            <p class="px-3 text-xs font-bold text-white/40 uppercase tracking-wider mb-2 mt-2">Principal</p>
            
            <a href="dashboard.php" class="flex items-center gap-3 px-3 py-2.5 bg-white/10 rounded-lg text-white font-medium border-l-4 border-white shadow-sm">
                <i class="fas fa-home w-5 text-center"></i> Dashboard
            </a>
            
            <?php if ($verCalendario): ?>
            <a href="calendario.php" class="flex items-center gap-3 px-3 py-2.5 text-white/80 hover:bg-white/10 hover:text-white rounded-lg transition-colors">
                 <i class="far fa-calendar-alt w-5 text-center"></i> Calendario TI
            </a>
            <?php endif; ?>

            <p class="px-3 text-xs font-bold text-white/40 uppercase tracking-wider mb-2 mt-6">Gestión TI</p>

            <?php if ($verNas): ?>
                <a href="consultar_nas.php" class="flex items-center gap-3 px-3 py-2 text-white/80 hover:bg-white/10 hover:text-white rounded-lg transition-colors">
                    <i class="fas fa-hdd w-5 text-center"></i> Carpetas NAS
                </a>
            <?php endif; ?>
            
            <?php if ($verDirectorio): ?>
                <a href="consultar_directorio.php" class="flex items-center gap-3 px-3 py-2 text-white/80 hover:bg-white/10 hover:text-white rounded-lg transition-colors">
                    <i class="fas fa-address-book w-5 text-center"></i> Directorio
                </a>
            <?php endif; ?>

            <?php if ($verInventario): ?>
                <a href="consultar_inventario.php" class="flex items-center gap-3 px-3 py-2 text-white/80 hover:bg-white/10 hover:text-white rounded-lg transition-colors">
                    <i class="fas fa-desktop w-5 text-center"></i> Inventario DNTICS
                </a>
            <?php endif; ?>


            <?php if ($verRegistros || $verLicencias): ?>
                <p class="px-3 text-xs font-bold text-white/40 uppercase tracking-wider mb-2 mt-6">Registros Rápidos</p>
                <div class="space-y-1">
                    
                    <?php if ($verRegistros): ?>
                        <a href="registro.php" class="flex items-center gap-3 px-3 py-2 text-sm text-white/70 hover:text-white hover:bg-white/5 rounded-md">
                            <i class="fas fa-plus text-xs"></i> Usuario AD
                        </a>
                        <a href="registrar_inventario.php" class="flex items-center gap-3 px-3 py-2 text-sm text-white/70 hover:text-white hover:bg-white/5 rounded-md">
                            <i class="fas fa-plus text-xs"></i> Equipo (Inventario)
                        </a>
                    <?php endif; ?>

                    <?php if ($verLicencias): ?>
                        <a href="registro_licencia.php" class="flex items-center gap-3 px-3 py-2 text-sm text-white/70 hover:text-white hover:bg-white/5 rounded-md">
                            <i class="fas fa-plus text-xs"></i> Licencia 365
                        </a>
                    <?php endif; ?>

                    <?php if ($verRegistros): ?>
                        <a href="alta_usuarios.php" class="flex items-center gap-3 px-3 py-2 text-sm text-white/70 hover:text-white hover:bg-white/5 rounded-md">
                            <i class="fas fa-plus text-xs"></i> Alta de Nuevo Usuario (SISGI)
                        </a>
                        <a href="consultar_adq_equipos.php" class="flex items-center gap-3 px-3 py-2 text-sm text-white/70 hover:text-white hover:bg-white/5 rounded-md">
                            <i class="fas fa-plus text-xs"></i> Adquisiciones De Equipos
                        </a>
                        <a href="usuariosAD.php" class="flex items-center gap-3 px-3 py-2.5 text-white/80 hover:bg-white/10 hover:text-white rounded-lg transition-colors">
                            <i class="fas fa-search w-5 text-center"></i> Usuarios SISGI
                        </a>
                    <?php endif; ?>

                </div>
            <?php endif; ?>

        </div>

        <div class="p-4 bg-brand-dark border-t border-white/10">
            <a href="logout.php" class="flex items-center gap-3 text-white/80 hover:text-red-300 transition-colors">
                <i class="fas fa-sign-out-alt"></i> Cerrar Sesión
            </a>
        </div>
    </aside>

    <div class="flex-1 flex flex-col overflow-hidden">
        
        <header class="h-16 bg-white shadow-sm flex items-center justify-between px-6 z-20">
            <button onclick="toggleSidebar()" class="md:hidden text-gray-500 hover:text-brand focus:outline-none">
                <i class="fas fa-bars text-xl"></i>
            </button>
            
            <h2 class="text-lg font-bold text-gray-700 hidden md:block">
                Panel de Control
            </h2>

            <div class="flex items-center gap-4">
                
                <div class="relative mr-2">
                    <button onclick="toggleNotif()" id="btnNotif" class="relative text-gray-400 hover:text-brand transition focus:outline-none p-1">
                        <i class="fas fa-bell text-xl"></i>
                        <span id="badgeNotif" class="hidden absolute -top-1 -right-1 bg-red-500 text-white text-[10px] font-bold px-1.5 py-0.5 rounded-full border-2 border-white">0</span>
                    </button>
                    <div id="listNotif" class="hidden absolute right-0 mt-3 w-80 bg-white rounded-xl shadow-xl border border-gray-100 z-50 overflow-hidden">
                        <div class="bg-gray-50 px-4 py-3 border-b border-gray-100 flex justify-between items-center">
                            <h3 class="text-xs font-bold text-gray-500 uppercase tracking-wider">Actividad Reciente</h3>
                            <span class="text-[10px] text-gray-400">Últimas 24h</span>
                        </div>
                        <div id="contentNotif" class="max-h-64 overflow-y-auto">
                            </div>
                    </div>
                </div>
                <div class="text-right hidden sm:block border-l pl-4 ml-2">
                    <p class="text-sm font-bold text-gray-700">Hola, <?= ucfirst($usuario_actual) ?></p>
                    <p class="text-xs text-gray-400 uppercase tracking-wide"><?= $rol_usuario ?></p>
                </div>
                <div class="w-10 h-10 rounded-full bg-brand-light/10 text-brand flex items-center justify-center text-lg border border-brand-light/20">
                    <i class="fas fa-user"></i>
                </div>
            </div>
        </header>

        <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-50 p-6 md:p-8">
            <div class="max-w-7xl mx-auto">
                
                <div class="mb-8">
                    <h1 class="text-2xl font-bold text-gray-800">Resumen General</h1>
                    <p class="text-gray-500 text-sm">Estado del sistema en tiempo real.</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-10">
                    
                    <a href="consultar_inventario.php" class="block group">
                        <div class="bg-white p-5 rounded-2xl shadow-sm border border-gray-100 hover:shadow-lg transition-all duration-300 relative border-l-4 border-l-blue-500">
                            <div class="flex justify-between items-start mb-3">
                                <div>
                                    <p class="text-[10px] font-bold tracking-wider text-gray-400 uppercase">General</p>
                                    <h3 class="text-base font-bold text-gray-800">Inventario DNTICS</h3>
                                </div>
                                <div class="w-10 h-10 bg-blue-50 text-blue-600 rounded-lg flex items-center justify-center group-hover:bg-blue-600 group-hover:text-white transition">
                                    <i class="fas fa-boxes text-lg"></i>
                                </div>
                            </div>
                            <div class="flex items-center gap-2 mt-2">
                                <span class="text-3xl font-bold text-gray-800 counter" data-target="<?= $total_inventario ?>">0</span>
                                <span class="text-xs text-gray-400 font-medium">bienes</span>
                            </div>
                            <div class="mt-3 pt-3 border-t border-gray-50 flex justify-between items-center text-xs">
                                <span class="text-blue-600 font-medium">Gestionar</span>
                                <i class="fas fa-arrow-right text-blue-200"></i>
                            </div>
                        </div>
                    </a>

                    <?php if ($puedeVerTodo): ?>
                    <a href="consultar_usuarios.php" class="block group">
                        <div class="bg-white p-5 rounded-2xl shadow-sm border border-gray-100 hover:shadow-lg transition-all duration-300 border-l-4 border-l-purple-500">
                            <div class="flex justify-between items-start mb-3">
                                <div>
                                    <p class="text-[10px] font-bold tracking-wider text-gray-400 uppercase">Directorio Activo</p>
                                    <h3 class="text-base font-bold text-gray-800">Usuarios AD</h3>
                                </div>
                                <div class="w-10 h-10 bg-purple-50 text-purple-600 rounded-lg flex items-center justify-center group-hover:bg-purple-600 group-hover:text-white transition">
                                    <i class="fas fa-users text-lg"></i>
                                </div>
                            </div>
                            <div class="flex items-center gap-2 mt-2">
                                <span class="text-3xl font-bold text-gray-800 counter" data-target="<?= $total_ad ?>">0</span>
                                <span class="text-xs text-gray-400 font-medium">cuentas</span>
                            </div>
                            <div class="mt-3 pt-3 border-t border-gray-50 flex justify-between items-center text-xs">
                                <span class="text-purple-600 font-medium">Ver listado</span>
                                <i class="fas fa-arrow-right text-purple-200"></i>
                            </div>
                        </div>
                    </a>

                    <a href="consultar_equipos.php" class="block group">
                        <div class="bg-white p-5 rounded-2xl shadow-sm border border-gray-100 hover:shadow-lg transition-all duration-300 border-l-4 border-l-cyan-500">
                            <div class="flex justify-between items-start mb-3">
                                <div>
                                    <p class="text-[10px] font-bold tracking-wider text-gray-400 uppercase">Inventario</p>
                                    <h3 class="text-base font-bold text-gray-800">Equipos</h3>
                                </div>
                                <div class="w-10 h-10 bg-cyan-50 text-cyan-600 rounded-lg flex items-center justify-center group-hover:bg-cyan-600 group-hover:text-white transition">
                                    <i class="fas fa-desktop text-lg"></i>
                                </div>
                            </div>
                            <div class="flex items-center gap-2 mt-2">
                                <span class="text-3xl font-bold text-gray-800 counter" data-target="<?= $total_equipos ?>">0</span>
                                <span class="text-xs text-gray-400 font-medium">registrados</span>
                            </div>
                             <div class="mt-3 pt-3 border-t border-gray-50 flex justify-between items-center text-xs">
                                <span class="text-cyan-600 font-medium">Gestionar</span>
                                <i class="fas fa-arrow-right text-cyan-200"></i>
                            </div>
                        </div>
                    </a>

                    <a href="consultar_stock.php" class="block group">
                        <div class="bg-white p-5 rounded-2xl shadow-sm border border-gray-100 hover:shadow-lg transition-all duration-300 border-l-4 border-l-emerald-500">
                            <div class="flex justify-between items-start mb-3">
                                <div>
                                    <p class="text-[10px] font-bold tracking-wider text-gray-400 uppercase">Almacén</p>
                                    <h3 class="text-base font-bold text-gray-800">Stock Material</h3>
                                </div>
                                <div class="w-10 h-10 bg-emerald-50 text-emerald-600 rounded-lg flex items-center justify-center group-hover:bg-emerald-600 group-hover:text-white transition">
                                    <i class="fas fa-boxes text-lg"></i>
                                </div>
                            </div>
                            <div class="flex items-center gap-2 mt-2">
                                <span class="text-3xl font-bold text-gray-800 counter" data-target="<?= $total_mater ?>">0</span>
                                <span class="text-xs text-gray-400 font-medium">artículos</span>
                            </div>
                             <div class="mt-3 pt-3 border-t border-gray-50 flex justify-between items-center text-xs">
                                <span class="text-emerald-600 font-medium">Inventario</span>
                                <i class="fas fa-arrow-right text-emerald-200"></i>
                            </div>
                        </div>
                    </a>
                    <?php endif; ?>

                </div>
                
                
                <a href="accesos_sistemas.php" class="group relative block h-full min-h-[180px] overflow-hidden rounded-2xl shadow-lg hover:shadow-2xl transition-all duration-300 hover:-translate-y-1">
    
    <div class="absolute inset-0 w-full h-full">
        <img src="img/FPLAYA.png" 
             alt="Fondo Sistemas" 
             class="h-full w-full object-cover transition-transform duration-700 group-hover:scale-110 opacity-90">
    </div>

    <div class="absolute inset-0 bg-gradient-to-t from-slate-900 via-slate-900/60 to-slate-900/40 group-hover:via-slate-900/50 transition-colors duration-300"></div>

    <div class="relative z-10 p-6 h-full flex flex-col justify-between">
        
        <div class="flex justify-between items-start">
            <div>
                <h3 class="text-2xl font-bold text-white mb-1 drop-shadow-md">Sistemas</h3>
                <div class="flex items-center gap-2">
                    <span class="inline-block w-2 h-2 rounded-full bg-green-400 animate-pulse"></span>
                    <p class="text-slate-200 text-sm font-medium drop-shadow-sm">Portal de Aplicaciones</p>
                </div>
            </div>
            
            <div class="h-10 w-10 rounded-full bg-white/20 backdrop-blur-md border border-white/10 flex items-center justify-center text-white shadow-sm group-hover:bg-brand group-hover:border-brand transition-colors">
                <i class="fas fa-network-wired"></i>
            </div>
        </div>

        <div class="mt-6">
            <div class="flex flex-wrap gap-2 mb-3">
                <span class="text-[10px] uppercase font-bold tracking-wider bg-black/30 backdrop-blur-sm text-white px-2 py-1 rounded border border-white/10">Turnos</span>
                <span class="text-[10px] uppercase font-bold tracking-wider bg-black/30 backdrop-blur-sm text-white px-2 py-1 rounded border border-white/10">Enlaces</span>
            </div>

            <span class="inline-flex items-center gap-2 text-sm font-bold text-white group-hover:text-green-300 transition-colors">
                Acceder ahora <i class="fas fa-arrow-right transition-transform group-hover:translate-x-1"></i>
            </span>
        </div>
    </div>
</a>
               
<script>
    // Toggle Mobile Sidebar
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        sidebar.classList.toggle('hidden');
        sidebar.classList.toggle('absolute');
        sidebar.classList.toggle('h-full');
    }

    // Toggle Modal Avisos
    function toggleModalAviso() {
        const modal = document.getElementById('modal-aviso');
        const content = document.getElementById('modal-content');
        
        if (modal.classList.contains('hidden')) {
            // Abrir
            modal.classList.remove('hidden');
            setTimeout(() => {
                content.classList.remove('scale-95', 'opacity-0');
                content.classList.add('scale-100', 'opacity-100');
            }, 10);
        } else {
            // Cerrar
            content.classList.remove('scale-100', 'opacity-100');
            content.classList.add('scale-95', 'opacity-0');
            setTimeout(() => {
                modal.classList.add('hidden');
            }, 300);
        }
    }

    // === LÓGICA DE NOTIFICACIONES ===
    function verificarNotificaciones() {
        fetch('api_eventos.php?accion=notificaciones')
        .then(r => r.json())
        .then(data => {
            const badge = document.getElementById('badgeNotif');
            const content = document.getElementById('contentNotif');
            if (data.total > 0) {
                badge.innerText = data.total; 
                badge.classList.remove('hidden');
                
                let html = '';
                data.items.forEach(item => {
                    // Colores según tipo
                    let icon = 'fa-calendar-day';
                    let color = 'text-blue-600 bg-blue-50';
                    if(item.tipo === 'mantenimiento') { icon = 'fa-tools'; color = 'text-orange-600 bg-orange-50'; }
                    if(item.tipo === 'antivirus') { icon = 'fa-shield-alt'; color = 'text-red-600 bg-red-50'; }
                    if(item.tipo === 'revision') { icon = 'fa-clipboard-check'; color = 'text-emerald-600 bg-emerald-50'; }

                    html += `
                        <a href="calendario.php" class="block p-3 hover:bg-gray-50 transition border-b border-gray-50">
                            <div class="flex items-start gap-3">
                                <div class="w-8 h-8 rounded-full ${color} flex items-center justify-center shrink-0">
                                    <i class="fas ${icon} text-xs"></i>
                                </div>
                                <div>
                                    <p class="text-xs font-bold text-gray-700 leading-tight">${item.titulo}</p>
                                    <p class="text-[10px] text-gray-400 mt-1">${item.tiempo}</p>
                                </div>
                            </div>
                        </a>
                    `;
                });
                content.innerHTML = html;
            } else {
                badge.classList.add('hidden');
                content.innerHTML = '<div class="p-4 text-center text-gray-400 text-xs"><i class="far fa-bell-slash mb-1 block text-lg"></i>Sin actividad reciente</div>';
            }
        })
        .catch(err => console.error('Error notificaciones:', err));
    }

    function toggleNotif() {
        document.getElementById('listNotif').classList.toggle('hidden');
    }

    // Cerrar al hacer clic fuera (Notificaciones)
    document.addEventListener('click', function(event) {
        const btn = document.getElementById('btnNotif');
        const list = document.getElementById('listNotif');
        if (!btn.contains(event.target) && !list.contains(event.target)) {
            list.classList.add('hidden');
        }
    });

    // Iniciar Funciones
    document.addEventListener("DOMContentLoaded", () => {
        
        // Notificaciones (Pooling cada 30s)
        verificarNotificaciones();
        setInterval(verificarNotificaciones, 30000);

        // Contadores Animados
        const counters = document.querySelectorAll('.counter');
        const speed = 200;
        counters.forEach(counter => {
            const updateCount = () => {
                const target = +counter.getAttribute('data-target');
                const count = +counter.innerText;
                const inc = target / speed;
                if (count < target) {
                    counter.innerText = Math.ceil(count + inc);
                    setTimeout(updateCount, 15);
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