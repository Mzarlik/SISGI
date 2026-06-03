<?php
require_once 'session_check.php';
require_once 'config.php';
session_start();

if (!isset($_SESSION['usuario'])) { die("Acceso denegado."); }

// ================= CONFIGURACIÓN =================
// Altura fija de cada fila (Aumentada a 60px para que quepa la dirección)
$alto_fila_px = 60; 
// Color Institucional (Arena)
$color_institucional = "#B89B72";
// =================================================

// 1. FECHAS
$fecha_ref = isset($_GET['fecha']) ? $_GET['fecha'] : date('Y-m-d');
$ts = strtotime($fecha_ref);
$dia_semana = date('N', $ts);
$start_week = strtotime('-' . ($dia_semana - 1) . ' days', $ts);
$end_week   = strtotime('+' . (7 - $dia_semana) . ' days', $ts);

$fecha_anterior = date('Y-m-d', strtotime('-7 days', $ts));
$fecha_siguiente = date('Y-m-d', strtotime('+7 days', $ts));
$fecha_hoy = date('Y-m-d');

$fecha_inicio_sql = date('Y-m-d 00:00:00', $start_week);
$fecha_fin_sql    = date('Y-m-d 23:59:59', $end_week);

$dias = [];
$dias_nombre = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];
for($i=0; $i<7; $i++){
    $dias[] = date('Y-m-d', strtotime("+$i days", $start_week));
}

// 2. CONSULTA
// Asegúrate de que tu tabla tenga la columna 'direccion' o ajusta el SELECT
$conn = get_db_connection();
$sql = "SELECT * FROM eventos_calendario 
        WHERE fecha_inicio BETWEEN '$fecha_inicio_sql' AND '$fecha_fin_sql'
        ORDER BY fecha_inicio ASC";
$res = $conn->query($sql);

$agenda = [];
$slots_ocupados = [];
$min_hora_print = 8; 
$max_hora_print = 18;

$eventos_temp = [];
if($res && $res->num_rows > 0) {
    while($row = $res->fetch_assoc()){
        $eventos_temp[] = $row;
    }
}

foreach($eventos_temp as $row) {
    $start_ts = strtotime($row['fecha_inicio']);
    $end_ts   = strtotime($row['fecha_fin']);
    $dia = date('Y-m-d', $start_ts);
    
    $hora_inicio = intval(date('H', $start_ts));
    $hora_fin    = intval(date('H', $end_ts));
    if (intval(date('i', $end_ts)) > 0) $hora_fin++; 
    
    $duracion = $hora_fin - $hora_inicio;
    if ($duracion < 1) $duracion = 1;

    $row['duracion_filas'] = $duracion;
    $row['rango_hora'] = date('H:i', $start_ts) . ' - ' . date('H:i', $end_ts);
    
    // Cálculo de altura (ajustado por los bordes)
    $row['altura_px'] = ($duracion * $alto_fila_px) - 6; 

    if (!isset($agenda[$dia][$hora_inicio])) {
        $agenda[$dia][$hora_inicio] = $row;
        for($i = 1; $i < $duracion; $i++) {
            $slots_ocupados[$dia][$hora_inicio + $i] = true;
        }
    }
}
include 'header.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Cronograma - Playa del Carmen</title>
    <script src="js/tailwindcss.js"></script>

    <style>
        @media print {
            @page { size: landscape; margin: 0.5cm; }
            body { 
                -webkit-print-color-adjust: exact; 
                print-color-adjust: exact; 
                background: white;
                zoom: 90%;
            }
            .no-print { display: none !important; }
            .hoja { box-shadow: none !important; margin: 0 !important; width: 100% !important; max-width: 100% !important; }
            tr { page-break-inside: avoid; }
        }
        body { font-family: 'Segoe UI', sans-serif; font-size: 11px; background-color: #f3f4f6; }
        .hoja { background: white; width: 100%; max-width: 29cm; margin: 20px auto; padding: 0.5cm; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        
        table { border-collapse: collapse; width: 100%; table-layout: fixed; }
        th, td { border: 1px solid #e5e7eb; padding: 0; vertical-align: top; }
        
        .fila-hora { height: <?= $alto_fila_px ?>px; }
    </style>
</head>
<body class="min-h-screen">

    <div class="no-print sticky top-0 z-50 bg-white border-b shadow-sm px-4 py-2 flex justify-between items-center">
        <div class="flex items-center gap-2">
            <a href="calendario.php" class="text-gray-500 hover:text-gray-700 mr-2"><i class="fas fa-arrow-left"></i></a>
            <div class="bg-gray-100 p-1 rounded flex gap-1">
                <a href="?fecha=<?= $fecha_anterior ?>" class="px-2 py-1 hover:bg-white rounded shadow-sm text-gray-600"><i class="fas fa-chevron-left"></i></a>
                <a href="?fecha=<?= $fecha_hoy ?>" class="px-2 py-1 hover:bg-white rounded shadow-sm text-gray-700 font-bold text-xs">HOY</a>
                <a href="?fecha=<?= $fecha_siguiente ?>" class="px-2 py-1 hover:bg-white rounded shadow-sm text-gray-600"><i class="fas fa-chevron-right"></i></a>
            </div>
        </div>
        <button onclick="window.print()" style="background-color: <?= $color_institucional ?>;" class="hover:bg-opacity-90 text-white px-4 py-1.5 rounded text-xs font-bold shadow flex items-center gap-2">
            <i class="fas fa-print"></i> IMPRIMIR PDF
        </button>
    </div>

    <div class="hoja">
        <div class="flex justify-between items-end border-b-2 mb-4 pb-2" style="border-color: <?= $color_institucional ?>;">
            <div>
                <h1 class="text-lg font-bold uppercase text-gray-800 leading-tight">H. AYUNTAMIENTO DE PLAYA DEL CARMEN</h1>
                <h2 class="text-xs text-gray-600 font-bold uppercase">Dirección de Nuevas Tecnologías (DNTICS)</h2>
            </div>
            <div class="text-right">
                <h3 class="text-sm font-bold uppercase" style="color: <?= $color_institucional ?>;">Cronograma de Actividades</h3>
                <p class="text-xs text-gray-600">Semana del <b><?= date('d/m', $start_week) ?></b> al <b><?= date('d/m', $end_week) ?></b></p>
            </div>
        </div>

        <table class="w-full text-[10px]">
            <thead>
                <tr>
                    <th class="w-10 bg-gray-100 text-gray-600 font-bold py-1 border-gray-300">H</th>
                    <?php foreach($dias as $k => $fecha): ?>
                        <th class="bg-gray-50 text-gray-700 py-1 border-gray-300">
                            <div class="uppercase font-bold text-[9px]"><?= $dias_nombre[$k] ?></div>
                            <div class="text-gray-400 font-normal text-[9px]"><?= date('d/m', strtotime($fecha)) ?></div>
                        </th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php 
                for($hora = $min_hora_print; $hora <= $max_hora_print; $hora++): 
                    $hora_f = sprintf("%02d:00", $hora);
                ?>
                <tr class="fila-hora">
                    <td class="text-center font-bold text-gray-400 bg-gray-50 align-middle text-[9px] border-r-2 border-r-gray-200">
                        <?= $hora_f ?>
                    </td>
                    
                    <?php foreach($dias as $dia_actual): ?>
                        <?php 
                        if(isset($slots_ocupados[$dia_actual][$hora])) { continue; }

                        if(isset($agenda[$dia_actual][$hora])): 
                            $evt = $agenda[$dia_actual][$hora];
                            $rowspan = $evt['duracion_filas'];
                            $altura_style = "height: " . $evt['altura_px'] . "px;";
                            
                            // Colores de borde según tipo
                            $bg_box = "bg-white";
                            $bd_color = "border-[3px] border-blue-600"; $tx_color = "text-blue-800";

                            if(stripos($evt['tipo'], 'mantenimiento') !== false) { 
                                $bd_color="border-[3px] border-amber-500"; $tx_color="text-amber-800"; 
                            } elseif(stripos($evt['tipo'], 'antivirus') !== false) { 
                                $bd_color="border-[3px] border-red-500"; $tx_color="text-red-800"; 
                            } elseif(stripos($evt['tipo'], 'revision') !== false) { 
                                $bd_color="border-[3px] border-emerald-500"; $tx_color="text-emerald-800"; 
                            }
                            
                            $titulo = htmlspecialchars($evt['titulo']);
                            
                            // ------------------------------------------------------------------
                            // AQUÍ CAPTURAMOS LA DIRECCIÓN
                            // IMPORTANTE: Cambia 'direccion' si tu columna se llama diferente
                            // ------------------------------------------------------------------
                            $direccion_txt = isset($evt['direccion_destino']) ? $evt['direccion_destino'] : ''; 
                            // ------------------------------------------------------------------

                            $descripcion = htmlspecialchars($evt['descripcion'] ?? $evt['detalles'] ?? '');
                            $asig = !empty($evt['asignado_a']) ? $evt['asignado_a'] : '-';
                        ?>
                            <td rowspan="<?= $rowspan ?>" class="p-0.5 align-top border-gray-200">
                                <div style="<?= $altura_style ?>" class="<?= $bg_box ?> <?= $bd_color ?> rounded-lg w-full p-1.5 shadow-sm flex flex-col justify-between overflow-hidden relative z-10">
                                    
                                    <div>
                                        <div class="flex justify-between items-start border-b border-gray-100 pb-1 mb-1">
                                            <span class="font-bold text-[9px] <?= $tx_color ?> uppercase leading-none"><?= $titulo ?></span>
                                        </div>
                                        
                                        <?php if(!empty($direccion_txt)): ?>
                                        <div class="flex items-start gap-1 mb-1 text-gray-700 bg-gray-100 px-1 py-0.5 rounded border border-gray-200">
                                            <i class="fas fa-map-marker-alt text-[8px] text-red-500 mt-0.5"></i>
                                            <span class="text-[8px] font-bold uppercase leading-none break-words">
                                                <?= htmlspecialchars($direccion_txt) ?>
                                            </span>
                                        </div>
                                        <?php endif; ?>

                                        <div class="text-[9px] text-gray-600 leading-tight italic">
                                            <?= $descripcion ?>
                                        </div>
                                    </div>

                                    <div class="mt-1 pt-1 flex justify-between items-end border-t border-gray-50">
                                        <span class="text-[8px] text-gray-400 font-mono"><?= $evt['rango_hora'] ?></span>
                                        <span class="text-[8px] font-bold text-gray-500 truncate max-w-[60px]" title="<?= $asig ?>">
                                            <i class="fas fa-user text-[7px] mr-0.5"></i><?= substr($asig, 0, 10) ?>
                                        </span>
                                    </div>
                                </div>
                            </td>
                        <?php else: ?>
                            <td class="hover:bg-gray-50 transition-colors border-gray-200"></td>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </tr>
                <?php endfor; ?>
            </tbody>
        </table>
        
        <div class="mt-8 grid grid-cols-3 gap-8 page-break-inside-avoid">
             <div class="text-center">
                <div class="border-t border-gray-400 mx-8 mb-1"></div>
                <p class="text-[8px] font-bold uppercase text-gray-600">Solicitó</p>
            </div>
            <div class="text-center">
                <div class="border-t border-gray-400 mx-8 mb-1"></div>
                <p class="text-[8px] font-bold uppercase text-gray-600">Autorizó</p>
            </div>
            <div class="text-center">
                <div class="border-t border-gray-400 mx-8 mb-1"></div>
                <p class="text-[8px] font-bold uppercase text-gray-600">Realizó</p>
            </div>
        </div>
    </div>
</body>
</html>