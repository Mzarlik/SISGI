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

if (!$esAdmin && !$esTecnico) {
    header("Location: dashboard.php");
    exit();
}

// 2. OBTENER CATÁLOGO DINÁMICO DE LA BASE DE DATOS
$conn = get_db_connection();
$catalogo_db = [];
if ($conn) {
    $sqlCat = "SELECT d.nombre_direccion, s.nombres as nombre_secretaria 
               FROM cat_direcciones d 
               JOIN Secretarias s ON d.id_secretaria = s.id_secretaria 
               ORDER BY s.nombres ASC, d.nombre_direccion ASC";
    $resCat = $conn->query($sqlCat);
    if ($resCat) {
        while ($row = $resCat->fetch_assoc()) {
            $sec = $row['nombre_secretaria'];
            $dir = $row['nombre_direccion'];
            if (!isset($catalogo_db[$sec])) {
                $catalogo_db[$sec] = [];
            }
            $catalogo_db[$sec][] = $dir;
        }
    }
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Calendario SATQ | SISGI</title>
    <script src="js/sweetalert2.all.min.js"></script>
    <script src="js/session_timer.js"></script>
    <script src="js/tailwindcss.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js'></script>
    
    <script>
        tailwind.config = {
            theme: { extend: { colors: { 'brand': '#721538', 'brand-dark': '#500e26' } } }
        }
    </script>
    <style>
        /* Estilos Generales */
        .fc-toolbar-title { font-size: 1.25rem !important; font-weight: 700; color: #374151; text-transform: capitalize; }
        .fc-button-primary { background-color: #721538 !important; border-color: #721538 !important; }
        .fc-event { cursor: pointer; transition: opacity 0.2s; border: none; }
        .fc-event:hover { opacity: 0.9; }
        .fc-timegrid-event .fc-event-main { padding: 2px 4px; }
        
        /* Ocultar elementos si alguien presiona Ctrl+P por error, 
           para forzarlos a usar el botón dedicado que genera el reporte limpio */
        @media print {
            aside, header, #btn-imprimir, .no-print, .fc-button-group, .fc-today-button { 
                display: none !important; 
            }
            body { 
                background: white !important; 
                color: black !important;
                height: auto !important;
                overflow: visible !important;
            }
            .flex-1, main, #calendar { 
                height: auto !important; 
                overflow: visible !important; 
                width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            .fc-scroller { 
                height: auto !important; 
                overflow: visible !important; 
            }
            .fc-header-toolbar { 
                margin-top: 10px !important;
                margin-bottom: 20px !important; 
            }
        }
    </style>
</head>
<body class="bg-gray-100 font-sans h-screen flex overflow-hidden">

    <aside class="w-64 bg-brand text-white flex flex-col hidden md:flex">
        <div class="h-16 flex items-center px-6 bg-brand-dark shadow-sm">
            <span class="text-lg font-bold">SISGI</span>
        </div>
        <div class="flex-1 p-4 space-y-2">
            <a href="dashboard.php" class="flex items-center gap-3 px-3 py-2 text-white/70 hover:bg-white/10 rounded-lg">
                <i class="fas fa-home w-5"></i> Dashboard
            </a>
            <a href="calendario.php" class="flex items-center gap-3 px-3 py-2 bg-white/10 text-white rounded-lg border-l-4 border-white">
                <i class="far fa-calendar-alt w-5"></i> Calendario SATQ
            </a>
        </div>
    </aside>

    <div class="flex-1 flex flex-col h-full">
        <header class="h-16 bg-white shadow-sm flex items-center justify-between px-6 z-20">
            <h2 class="text-lg font-bold text-gray-700 flex items-center gap-2">
                <i class="far fa-calendar-check text-brand"></i> Gestión de Mantenimientos
            </h2>
            
            <div class="flex items-center gap-4">
                
                <div class="relative mr-2">
                    <button id="btnNotif" onclick="toggleNotificaciones()" class="relative text-gray-500 hover:text-brand transition focus:outline-none p-1">
                        <i class="fas fa-bell text-xl"></i>
                        <span id="badgeNotif" class="hidden absolute -top-1 -right-1 bg-red-500 text-white text-[10px] font-bold px-1.5 py-0.5 rounded-full border-2 border-white">0</span>
                    </button>
                    <div id="listNotif" class="hidden absolute right-0 mt-3 w-80 bg-white rounded-xl shadow-xl border border-gray-100 z-50 overflow-hidden">
                        <div class="bg-gray-50 px-4 py-3 border-b border-gray-100">
                            <h3 class="text-xs font-bold text-gray-500 uppercase">Actividad Reciente</h3>
                        </div>
                        <div id="contentNotif" class="max-h-64 overflow-y-auto">
                            <p class="text-xs text-gray-400 p-4 text-center">Cargando...</p>
                        </div>
                    </div>
                </div>

                <button id="btn-imprimir" onclick="abrirReporte()" class="text-gray-500 hover:bg-gray-100 p-2 rounded-lg transition" title="Generar Reporte Formal">
                    <i class="fas fa-print"></i>
                </button>

                <div class="text-right border-l pl-4 ml-2">
                    <p class="text-sm font-bold text-gray-700"><?= ucfirst($usuario_actual) ?></p>
                    <p class="text-[10px] text-gray-400 uppercase"><?= $rol_usuario ?></p>
                </div>
            </div>
        </header>

        <main class="flex-1 p-6 overflow-hidden flex flex-col relative">
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 flex-1 overflow-y-auto">
                <div id='calendar' class="h-full"></div>
            </div>
        </main>
    </div>

    <?php if ($esAdmin): ?>
    <div id="modal-evento" class="fixed inset-0 bg-black/50 z-50 hidden flex items-center justify-center p-4 backdrop-blur-sm">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden transform transition-all scale-95 opacity-0" id="modal-content">
            <div class="bg-gray-50 px-6 py-4 border-b flex justify-between items-center">
                <h3 class="font-bold text-gray-700" id="modal-titulo">Agendar Actividad</h3>
                <button type="button" onclick="closeModal()" class="text-gray-400 hover:text-red-500"><i class="fas fa-times"></i></button>
            </div>
            
            <form id="formEvento" class="p-6 space-y-3">
                <input type="hidden" name="id" id="eventoId">
                <input type="hidden" name="accion" id="eventoAccion" value="guardar">

                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Título</label>
                    <input type="text" name="titulo" id="inputTitulo" required class="w-full px-3 py-2 border rounded-lg text-sm outline-none focus:border-brand">
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Inicio</label>
                        <input type="datetime-local" name="start" id="inputInicio" required class="w-full px-3 py-2 border rounded-lg text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Fin</label>
                        <input type="datetime-local" name="end" id="inputFin" required class="w-full px-3 py-2 border rounded-lg text-sm">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Tipo de Servicio</label>
                    <select name="tipo" id="inputTipo" class="w-full px-3 py-2 border rounded-lg text-sm bg-white">
                        <option value="mantenimiento">Mantenimiento Prev.</option>
                        <option value="antivirus">Instalación Antivirus</option>
                        <option value="office">Licencia Office 365</option>
                        <option value="revision">Revisión Gral.</option>
                    </select>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Secretaría</label>
                        <select id="selectSecretaria" name="secretaria" class="w-full px-3 py-2 border rounded-lg text-sm bg-white" required>
                            <option value="">Seleccione...</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Dirección</label>
                        <select id="selectDireccion" name="direccion" class="w-full px-3 py-2 border rounded-lg text-sm bg-white" disabled required>
                            <option value="">Seleccione Secretaría primero</option>
                        </select>
                    </div>
                </div>

                <div class="p-3 bg-gray-50 rounded-lg border border-gray-100">
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Asignar Responsable</label>
                    <div class="flex gap-2">
                        <div class="w-10 h-10 rounded-full bg-white border border-gray-200 flex items-center justify-center text-gray-500 shadow-sm">
                            <i class="fas fa-user"></i>
                        </div>
                        <select name="asignado_a" id="inputAsignado" class="flex-1 px-3 py-2 border rounded-lg text-sm bg-white focus:border-brand outline-none">
                            <option value="">-- Sin asignar (Pendiente) --</option>
                            <option value="TODOS" class="font-bold text-brand">★ Todo el Equipo TI</option>
                            </select>
                    </div>
                </div>

                <div class="flex justify-between items-center pt-4 border-t">
                    <button type="button" id="btnEliminar" class="hidden px-4 py-2 text-sm text-red-600 hover:bg-red-50 rounded-lg font-bold transition">
                        <i class="fas fa-trash-alt mr-2"></i> Eliminar
                    </button>
                    <div class="flex gap-2 ml-auto">
                        <button type="button" onclick="closeModal()" class="px-4 py-2 text-sm text-gray-500 hover:bg-gray-100 rounded-lg transition">Cancelar</button>
                        <button type="submit" class="px-4 py-2 bg-brand text-white text-sm rounded-lg hover:bg-brand-dark shadow-md transition">Guardar</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <script>
        // --- 1. DECLARAMOS LA VARIABLE GLOBALMENTE ---
        let calendar; 

        // --- CONFIGURACIÓN CATÁLOGO ---
        const catalogo = <?php echo json_encode($catalogo_db, JSON_UNESCAPED_UNICODE); ?>;

        const toLocalISO = (date) => {
            const tzOffset = date.getTimezoneOffset() * 60000;
            return (new Date(date - tzOffset)).toISOString().slice(0, 16);
        };

        document.addEventListener('DOMContentLoaded', function() {
            cargarUsuarios();

            // Configurar Selects Anidados
            const selSec = document.getElementById('selectSecretaria');
            const selDir = document.getElementById('selectDireccion');
            if(selSec) {
                for(const k in catalogo) {
                    let opt = document.createElement('option');
                    opt.value = k; opt.textContent = k;
                    selSec.appendChild(opt);
                }
                selSec.addEventListener('change', function() {
                    selDir.innerHTML = '<option value="">Seleccione Dirección...</option>';
                    if(this.value && catalogo[this.value]) {
                        selDir.disabled = false;
                        catalogo[this.value].forEach(d => {
                            let opt = document.createElement('option');
                            opt.value = d; opt.textContent = d;
                            selDir.appendChild(opt);
                        });
                    } else { selDir.disabled = true; }
                });
            }

            // --- INICIAR FULLCALENDAR ---
            var calendarEl = document.getElementById('calendar');
            
            // 2. ASIGNAMOS A LA VARIABLE GLOBAL (SIN 'var' NI 'let' AQUI)
            calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                slotMinTime: '07:00:00',
                slotMaxTime: '20:00:00',
                locale: 'es',
                nowIndicator: true,
                allDaySlot: true,
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'timeGridWeek,timeGridDay,dayGridMonth'
                },
                events: 'api_eventos.php?accion=listar',
                editable: <?= $esAdmin ? 'true' : 'false' ?>,
                selectable: <?= $esAdmin ? 'true' : 'false' ?>,

                // VISUALIZACIÓN PERSONALIZADA
                eventContent: function(arg) {
                    let asignado = arg.event.extendedProps.asignado_a;
                    let icono = '';
                    let textoAsignado = '';

                    if (asignado === 'TODOS') {
                        icono = '<i class="fas fa-users text-[10px] mr-1"></i>';
                        textoAsignado = '<div class="text-[9px] opacity-90 italic">Equipo TI</div>';
                    } else if (asignado) {
                        icono = '<i class="fas fa-user-check text-[10px] mr-1"></i>';
                        textoAsignado = `<div class="text-[9px] opacity-90 italic truncate">${asignado}</div>`;
                    } else {
                        textoAsignado = '<div class="text-[9px] opacity-60 italic">-- Pendiente --</div>';
                    }

                    return {
                        html: `
                        <div class="p-0.5 overflow-hidden h-full flex flex-col justify-center">
                            <div class="font-bold text-xs flex items-center whitespace-nowrap overflow-hidden text-ellipsis">${icono} ${arg.event.title}</div>
                            ${textoAsignado}
                            <div class="text-[9px] opacity-80 hidden md:block">${arg.timeText}</div>
                        </div>`
                    }
                },

                // CREAR
                select: function(info) {
                    <?php if ($esAdmin): ?>
                    resetModal();
                    document.getElementById('modal-titulo').innerText = 'Nuevo Evento';
                    document.getElementById('eventoAccion').value = 'guardar';
                    document.getElementById('inputInicio').value = toLocalISO(info.start);
                    document.getElementById('inputFin').value = toLocalISO(info.end);
                    openModal();
                    <?php endif; ?>
                },

                // EDITAR / BORRAR
                eventClick: function(info) {
                    <?php if ($esAdmin): ?>
                    resetModal();
                    document.getElementById('modal-titulo').innerText = 'Editar Evento';
                    document.getElementById('eventoAccion').value = 'editar';
                    document.getElementById('eventoId').value = info.event.id;
                    document.getElementById('inputTitulo').value = info.event.title;
                    document.getElementById('inputInicio').value = toLocalISO(info.event.start);
                    document.getElementById('inputFin').value = toLocalISO(info.event.end || info.event.start);
                    
                    if(info.event.extendedProps.tipo) document.getElementById('inputTipo').value = info.event.extendedProps.tipo;
                    
                    if(info.event.extendedProps.asignado_a) {
                        document.getElementById('inputAsignado').value = info.event.extendedProps.asignado_a;
                    } else {
                        document.getElementById('inputAsignado').value = "";
                    }

                    const btnDel = document.getElementById('btnEliminar');
                    btnDel.classList.remove('hidden');
                    btnDel.onclick = () => { if(confirm('¿Seguro que deseas eliminar este evento?')) eliminarEvento(info.event.id); };
                    openModal();
                    <?php endif; ?>
                }
            });
            calendar.render();

            // --- ENVÍO DE FORMULARIO ---
            const form = document.getElementById('formEvento');
            if(form) {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    const fd = new FormData(this);
                    fetch('api_eventos.php', { method: 'POST', body: fd })
                    .then(r => r.json())
                    .then(data => {
                        if(data.status === 'success') {
                            calendar.refetchEvents();
                            closeModal();
                            verificarNotificaciones(); 
                        } else {
                            alert('Error: ' + data.message);
                        }
                    });
                });
            }

            // Iniciar notificaciones
            verificarNotificaciones();
            setInterval(verificarNotificaciones, 30000);
        });

        // --- 3. FUNCIÓN DE IMPRESIÓN CORREGIDA ---
        function abrirReporte() {
            // Verificar si el calendario ya cargó
            if (!calendar) {
                console.error("El calendario aún no está listo.");
                return;
            }
            
            // Accedemos directo a la variable global 'calendar'
            var fechaActual = calendar.getDate();
            
            // Convertimos a formato ISO (YYYY-MM-DD)
            var fechaISO = fechaActual.toISOString().split('T')[0];
            
            // Obtenemos la vista actual para conservarla en el reporte
            var vistaActual = calendar.view ? calendar.view.type : 'timeGridWeek';
            
            // Abrimos el reporte limpio
            window.open('imprimir_reporte.php?fecha=' + fechaISO + '&vista=' + vistaActual, '_blank');
        }

        // --- FUNCIONES AUXILIARES ---
        function cargarUsuarios() {
            const select = document.getElementById('inputAsignado');
            if(!select) return;
            fetch('api_eventos.php?accion=usuarios')
            .then(r => r.json())
            .then(users => {
                users.forEach(u => {
                    let opt = document.createElement('option');
                    opt.value = u; opt.textContent = u; 
                    select.appendChild(opt);
                });
            });
        }

        function eliminarEvento(id) {
            const fd = new FormData();
            fd.append('accion', 'eliminar');
            fd.append('id', id);
            fetch('api_eventos.php', { method: 'POST', body: fd })
            .then(r => r.json()).then(data => { if(data.status === 'success') { 
                // Usamos la variable global también aquí para refrescar
                if(calendar) calendar.refetchEvents(); else location.reload();
                closeModal();
            }});
        }

        function resetModal() {
            document.getElementById('formEvento').reset();
            document.getElementById('eventoId').value = '';
            document.getElementById('eventoAccion').value = 'guardar';
            document.getElementById('btnEliminar').classList.add('hidden');
            document.getElementById('selectDireccion').disabled = true;
        }

        function openModal() {
            document.getElementById('modal-evento').classList.remove('hidden');
            setTimeout(() => { document.getElementById('modal-content').classList.remove('scale-95', 'opacity-0'); }, 10);
        }

        function closeModal() {
            document.getElementById('modal-content').classList.add('scale-95', 'opacity-0');
            setTimeout(() => { document.getElementById('modal-evento').classList.add('hidden'); }, 300);
        }

        function verificarNotificaciones() {
            fetch('api_eventos.php?accion=notificaciones').then(r => r.json()).then(data => {
                const badge = document.getElementById('badgeNotif');
                const content = document.getElementById('contentNotif');
                
                if (data.total > 0) {
                    badge.innerText = data.total; 
                    badge.classList.remove('hidden');
                    let html = '';
                    data.items.forEach(item => {
                        let colorBg = "bg-blue-50"; let colorTxt = "text-blue-500"; let icono = "fa-info";
                        if(item.tipo === 'mantenimiento') { colorBg = "bg-orange-50"; colorTxt = "text-orange-500"; icono = "fa-tools"; }
                        if(item.tipo === 'antivirus') { colorBg = "bg-red-50"; colorTxt = "text-red-500"; icono = "fa-shield-virus"; }
                        
                        let badgeAsig = '';
                        if(item.asignacion === 'Equipo' || item.asignacion === 'TODOS') {
                            badgeAsig = '<span class="text-[9px] bg-purple-100 text-purple-700 px-1.5 py-0.5 rounded ml-2 border border-purple-200">EQUIPO</span>';
                        } else {
                            badgeAsig = `<span class="text-[9px] bg-green-100 text-green-700 px-1.5 py-0.5 rounded ml-2 border border-green-200">${item.asignacion}</span>`;
                        }

                        html += `
                        <div class="p-3 border-b hover:bg-gray-50 flex gap-3 items-start transition-colors cursor-default">
                            <div class="w-8 h-8 rounded-full ${colorBg} ${colorTxt} flex items-center justify-center shrink-0 mt-1 shadow-sm">
                                <i class="fas ${icono} text-xs"></i>
                            </div>
                            <div class="flex-1">
                                <h4 class="text-xs font-bold text-gray-700 leading-tight">${item.titulo}</h4>
                                <div class="flex items-center mt-1">
                                    <p class="text-[10px] text-gray-400 font-medium bg-gray-100 px-1.5 rounded"><i class="far fa-clock mr-1"></i>${item.tiempo}</p>
                                    ${badgeAsig}
                                </div>
                            </div>
                        </div>`;
                    });
                    content.innerHTML = html;
                } else {
                    badge.classList.add('hidden');
                    content.innerHTML = '<div class="flex flex-col items-center justify-center p-6 text-gray-300"><i class="far fa-bell-slash text-2xl mb-2"></i><p class="text-xs">Sin pendientes asignados</p></div>';
                }
            });
        }

        function toggleNotificaciones() { document.getElementById('listNotif').classList.toggle('hidden'); }
    </script>
</body>
</html>