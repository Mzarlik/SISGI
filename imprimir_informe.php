<?php
// imprimir_informe.php
require_once 'session_check.php';
require_once 'config.php';
session_start();

if (!isset($_SESSION['usuario'])) { die("Acceso denegado"); }

// --- 1. RECIBIR PARÁMETROS ---
$tipoReporte = isset($_GET['tipo']) ? $_GET['tipo'] : 'unificado'; 
$filtroSecretaria = isset($_GET['secretaria']) ? $_GET['secretaria'] : '';
$filtroDireccion = isset($_GET['direccion']) ? $_GET['direccion'] : ''; 
$vista = isset($_GET['vista']) ? $_GET['vista'] : 'completa';

// Parámetros para la excepción manual (Forzar a Baja/Mejora)
$modInv = isset($_GET['mod_inv']) ? trim($_GET['mod_inv']) : '';
$modEstatus = isset($_GET['mod_estatus']) ? $_GET['mod_estatus'] : 'baja';
$modMotivo = isset($_GET['mod_motivo']) ? trim($_GET['mod_motivo']) : '';

// --- 2. CONFIGURACIÓN DE TÍTULOS ---
$colorHeader = "#721538"; // Guinda Institucional

if ($tipoReporte == 'criticos') {
    $titulo = "REPORTE DE EQUIPOS OBSOLETOS / NO APTOS";
    $desc = "Listado exclusivo de equipos que requieren reemplazo.";
} elseif ($tipoReporte == 'candidatos') {
    $titulo = "CANDIDATOS PARA ACTUALIZACIÓN";
    $desc = "Listado exclusivo de equipos que requieren mejoras.";
} else {
    // CASO UNIFICADO
    $titulo = "DIAGNÓSTICO INTEGRAL DE INFRAESTRUCTURA";
    $desc = "Reporte general de inventario: Equipos para baja y candidatos a actualización.";
}

if ($vista == 'filtrada') {
    $desc .= " <b>(Omitiendo equipos que únicamente requieren instalación de Windows 11)</b>.";
}

// Subtítulo dinámico
$subtitulo = "Reporte General - Todas las Áreas";
if (!empty($filtroSecretaria) && !empty($filtroDireccion)) {
    $subtitulo = "Secretaría: " . $filtroSecretaria . " | Dirección: " . $filtroDireccion;
} elseif (!empty($filtroSecretaria)) {
    $subtitulo = "Secretaría: " . $filtroSecretaria . " (Todas las Direcciones)";
} elseif (!empty($filtroDireccion)) {
    $subtitulo = "Dirección: " . $filtroDireccion;
}

// --- 3. OBTENCIÓN Y PROCESAMIENTO DE DATOS ---
$conn = get_db_connection();
$whereSQL = "WHERE tipoequipo NOT LIKE '%Impresora%'";

if (!empty($filtroSecretaria)) {
    $sec = $conn->real_escape_string($filtroSecretaria);
    $whereSQL .= " AND secretaria = '$sec'";
}
if (!empty($filtroDireccion)) {
    $dir = $conn->real_escape_string($filtroDireccion);
    $whereSQL .= " AND direccion = '$dir'";
}

$sql = "SELECT * FROM equiposbd $whereSQL ORDER BY direccion ASC";
$result = $conn->query($sql);
$datos = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $ramGB = intval($row['ram']);
        $discoRaw = $row['tipodisco_capa'];
        $procRaw = $row['procesador'];
        $osRaw = $row['sistemaOperativo'];

        $esSSD = (stripos($discoRaw, 'SSD') !== false || stripos($discoRaw, 'M.2') !== false || stripos($discoRaw, 'Solid') !== false);
        $esHDD = !$esSSD;
        $tieneWin11 = (stripos($osRaw, '11') !== false);

        $esObsoleto = (stripos($procRaw, 'Celeron') !== false || 
                       stripos($procRaw, 'Pentium') !== false || 
                       stripos($procRaw, 'Atom') !== false || 
                       stripos($procRaw, 'Duo') !== false);

        $agregar = false;
        $esForzado = false;

        // --- INICIO: LÓGICA DE OVERRIDE MANUAL ---
        if ($modInv !== '' && $row['numInventario'] == $modInv) {
            $esForzado = true;
            if ($modEstatus == 'baja') {
                if ($tipoReporte == 'criticos' || $tipoReporte == 'unificado') {
                    $row['etiqueta_tipo'] = 'BAJA';
                    $row['clase_badge'] = 'badge-baja';
                    $row['diagnostico_print'] = !empty($modMotivo) ? "Falla de Hardware ($modMotivo)" : "Falla de Hardware (Requiere Reemplazo)";
                    $agregar = true;
                }
            } elseif ($modEstatus == 'mejora') {
                if ($tipoReporte == 'candidatos' || $tipoReporte == 'unificado') {
                    $row['etiqueta_tipo'] = 'MEJORA';
                    $row['clase_badge'] = 'badge-mejora';
                    $row['diagnostico_print'] = !empty($modMotivo) ? $modMotivo : "Requiere Actualización (Forzado)";
                    $agregar = true;
                }
            }
        }
        // --- FIN: LÓGICA DE OVERRIDE MANUAL ---

        // SI NO FUE FORZADO, SE APLICA LA LÓGICA NORMAL
        if (!$esForzado) {
            // 1. Es Obsoleto (CRÍTICO)
            if ($esObsoleto) {
                if ($tipoReporte == 'criticos' || $tipoReporte == 'unificado') {
                    $row['etiqueta_tipo'] = 'BAJA';
                    $row['clase_badge'] = 'badge-baja';
                    $row['diagnostico_print'] = "Procesador Limitado ($procRaw)";
                    $agregar = true;
                }
            } 
            // 2. Es Candidato (MEJORA)
            else {
                $acciones = [];
                $esCandidato = false;
                $soloRequiereWin11 = false;

                if ($esHDD) { $acciones[] = "Cambio de unidad a SSD"; $esCandidato = true; }
                if ($ramGB < 8) { $acciones[] = "Aumento de memoria RAM (Actual: $ramGB GB)"; $esCandidato = true; }
                if (!$tieneWin11) { $acciones[] = "Actualizar Sistema Operativo a Win 11"; $esCandidato = true; }

                if (!$esHDD && $ramGB >= 8 && !$tieneWin11) { $soloRequiereWin11 = true; }
                if ($vista == 'filtrada' && $soloRequiereWin11) { $esCandidato = false; }

                if ($esCandidato) {
                    if ($tipoReporte == 'candidatos' || $tipoReporte == 'unificado') {
                        $row['etiqueta_tipo'] = 'MEJORA';
                        $row['clase_badge'] = 'badge-mejora';
                        $row['diagnostico_print'] = implode(', ', $acciones);
                        $agregar = true;
                    }
                }
            }
        }

        if ($agregar) {
            $datos[] = $row;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte Técnico - SATQ</title>
    <style>
        body { font-family: 'Arial', sans-serif; font-size: 11px; color: #333; margin: 0; padding: 20px; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        
        th { 
            background-color: <?= $colorHeader ?>; 
            color: white; 
            font-weight: bold; 
            font-size: 10px; 
            text-transform: uppercase; 
            padding: 8px 10px;
            text-align: left;
            border: 1px solid #721538;
        }

        td { 
            border-bottom: 1px solid #ddd; 
            padding: 8px 10px; 
            vertical-align: middle;
            background-color: #fff; 
        }
        
        tr:hover td { background-color: #f9f9f9; }

        .badge { font-size: 9px; font-weight: bold; padding: 3px 6px; border-radius: 4px; display: inline-block; min-width: 55px; text-align: center; }
        .badge-baja { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; } 
        .badge-mejora { background: #dbeafe; color: #1e40af; border: 1px solid #bfdbfe; } 

        .header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; border-bottom: 2px solid <?= $colorHeader ?>; padding-bottom: 15px; }
        .header-content { text-align: left; }
        .header h1 { margin: 0; font-size: 18px; color: <?= $colorHeader ?>; text-transform: uppercase; font-weight: bold; }
        .header h2 { margin: 5px 0; font-size: 12px; color: #444; }
        .fecha-box { text-align: right; font-size: 11px; color: #666; }

        .firmas { margin-top: 50px; display: flex; justify-content: space-between; page-break-inside: avoid; }
        .firma-box { width: 40%; text-align: center; border-top: 1px solid #000; padding-top: 5px; }

        @media print {
            .no-print { display: none !important; }
            body { padding: 0; }
            th { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .badge { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }

        #panelOpciones { transition: opacity 0.3s ease; }
    </style>
    <script>
        function togglePanel() {
            var panel = document.getElementById('panelOpciones');
            var btn = document.getElementById('btnMostrarPanel');
            
            if (panel.style.display === 'none') {
                panel.style.display = 'block';
                btn.style.display = 'none';
            } else {
                panel.style.display = 'none';
                btn.style.display = 'block';
            }
        }
    </script>
</head>
<body onload="window.print()">

    <button id="btnMostrarPanel" onclick="togglePanel()" class="no-print" style="display:none; position: fixed; top: 15px; right: 15px; background: <?= $colorHeader ?>; color: white; border: none; padding: 10px 15px; border-radius: 50px; box-shadow: 0 4px 10px rgba(0,0,0,0.3); cursor: pointer; z-index: 1001; font-weight: bold; font-size: 14px;">
        ⚙️ Opciones
    </button>

    <div id="panelOpciones" class="no-print" style="position: fixed; top: 15px; right: 15px; background: #fff; padding: 15px; border: 1px solid #ccc; box-shadow: 0 4px 15px rgba(0,0,0,0.15); border-radius: 8px; z-index: 1000; width: 240px;">
        <div style="margin-bottom: 10px; font-weight: bold; color: <?= $colorHeader ?>; display: flex; justify-content: space-between; align-items: center; font-size: 12px;">
            <span>OPCIONES DE REPORTE</span>
            <span onclick="togglePanel()" style="cursor: pointer; font-size: 16px; padding: 0 5px;" title="Ocultar Panel">🔽</span>
        </div>
        
        <a href="?tipo=unificado&secretaria=<?= urlencode($filtroSecretaria) ?>&direccion=<?= urlencode($filtroDireccion) ?>&vista=<?= $vista ?>" 
           style="display: block; margin-bottom: 5px; padding: 6px; text-decoration: none; font-size: 11px; border-radius: 4px; border: 1px solid #ddd;
           background: <?= $tipoReporte == 'unificado' ? '#721538' : '#f9f9f9' ?>; color: <?= $tipoReporte == 'unificado' ? '#fff' : '#333' ?>;">
           📄 Unificado
        </a>
        <a href="?tipo=criticos&secretaria=<?= urlencode($filtroSecretaria) ?>&direccion=<?= urlencode($filtroDireccion) ?>&vista=<?= $vista ?>" 
           style="display: block; margin-bottom: 5px; padding: 6px; text-decoration: none; font-size: 11px; border-radius: 4px; border: 1px solid #ddd;
           background: <?= $tipoReporte == 'criticos' ? '#721538' : '#f9f9f9' ?>; color: <?= $tipoReporte == 'criticos' ? '#fff' : '#333' ?>;">
           ⛔ Solo Bajas
        </a>
        <a href="?tipo=candidatos&secretaria=<?= urlencode($filtroSecretaria) ?>&direccion=<?= urlencode($filtroDireccion) ?>&vista=<?= $vista ?>" 
           style="display: block; margin-bottom: 10px; padding: 6px; text-decoration: none; font-size: 11px; border-radius: 4px; border: 1px solid #ddd;
           background: <?= $tipoReporte == 'candidatos' ? '#721538' : '#f9f9f9' ?>; color: <?= $tipoReporte == 'candidatos' ? '#fff' : '#333' ?>;">
           🛠️ Solo Mejoras
        </a>

        <div style="border-top: 1px solid #eee; padding-top: 5px; margin-top: 5px;">
            <a href="?tipo=<?= $tipoReporte ?>&secretaria=<?= urlencode($filtroSecretaria) ?>&direccion=<?= urlencode($filtroDireccion) ?>&vista=filtrada" 
               style="display: block; padding: 6px; text-decoration: none; font-size: 10px; color: #333; background: <?= $vista == 'filtrada' ? '#dcfce7' : '#fff' ?>; border: 1px solid #ddd; text-align: center; border-radius: 4px;">
               <?= $vista == 'filtrada' ? '✅ Filtro Activo' : '🌿 Omitir "Solo Win11"' ?>
            </a>
        </div>

        <div style="border-top: 1px solid #eee; padding-top: 10px; margin-top: 10px;">
            <span style="font-size: 11px; font-weight: bold; color: <?= $colorHeader ?>;">🛠️ Excepción Manual</span>
            <form id="formExcepcion" method="GET" action="" style="margin-top: 5px; font-size: 10px;">
                <input type="hidden" name="tipo" id="hidden_tipo" value="<?= htmlspecialchars($tipoReporte) ?>">
                <input type="hidden" name="secretaria" value="<?= htmlspecialchars($filtroSecretaria) ?>">
                <input type="hidden" name="direccion" value="<?= htmlspecialchars($filtroDireccion) ?>">
                <input type="hidden" name="vista" value="<?= htmlspecialchars($vista) ?>">
                
                <input type="text" name="mod_inv" placeholder="Inv. (Ej. 515103089)" value="<?= htmlspecialchars($modInv) ?>" style="width: 100%; margin-bottom: 5px; padding: 4px; border: 1px solid #ccc; box-sizing: border-box; font-size: 10px;" required>
                
                <select name="mod_estatus" id="mod_estatus" style="width: 100%; margin-bottom: 5px; padding: 4px; border: 1px solid #ccc; box-sizing: border-box; font-size: 10px;">
                    <option value="baja" <?= $modEstatus == 'baja' ? 'selected' : '' ?>>Forzar a BAJA</option>
                    <option value="mejora" <?= $modEstatus == 'mejora' ? 'selected' : '' ?>>Forzar a MEJORA</option>
                </select>
                
                <input type="text" name="mod_motivo" placeholder="Motivo (Ej. Falla en fuente)" value="<?= htmlspecialchars($modMotivo) ?>" style="width: 100%; margin-bottom: 5px; padding: 4px; border: 1px solid #ccc; box-sizing: border-box; font-size: 10px;" required>
                
                <button type="submit" onclick="document.getElementById('hidden_tipo').value = document.getElementById('mod_estatus').value === 'baja' ? 'criticos' : 'candidatos';" style="width: 100%; padding: 5px; background: #6b7280; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: bold;">Aplicar Excepción</button>
                
                <?php if(!empty($modInv)): ?>
                    <a href="?tipo=<?= $tipoReporte ?>&secretaria=<?= urlencode($filtroSecretaria) ?>&direccion=<?= urlencode($filtroDireccion) ?>&vista=<?= $vista ?>" style="display: block; text-align: center; margin-top: 5px; color: #b91c1c; text-decoration: none; font-weight: bold;">❌ Quitar excepción</a>
                <?php endif; ?>
            </form>
        </div>
        
        <button onclick="window.print()" style="margin-top: 10px; width: 100%; padding: 8px; cursor: pointer; font-weight: bold; background: #333; color: white; border: none; border-radius: 4px;">🖨️ IMPRIMIR</button>
    </div>

    <div class="header">
        <div class="header-content">
            <h1>Hacienda del Estado de Quintana Roo</h1>
            <h2>SATQ</h2>
            <div style="font-size: 12px; margin-top: 5px; color: #721538; font-weight: bold;"><?= $titulo ?></div>
        </div>
        <div class="fecha-box">
            <strong>Fecha de Emisión:</strong><br>
            <?= date('d/m/Y') ?><br>
            <?= date('H:i A') ?>
        </div>
    </div>

    <div style="margin-bottom: 10px; font-size: 11px; padding: 8px; background: #f9f9f9; border-left: 4px solid <?= $colorHeader ?>;">
        <strong>Filtro Aplicado:</strong> <?= htmlspecialchars($subtitulo) ?> <br>
        <span style="color: #666; font-style: italic;"><?= $desc ?> (Total: <?= count($datos) ?>)</span>
    </div>

    <table>
        <thead>
            <tr>
                <th width="8%" style="text-align: center;">Inventario</th>
                <th width="8%" style="text-align: center;">Estatus</th>
                <th width="15%">Equipo</th> 
                <th width="20%">Ubicación / Dirección</th>
                <th width="15%">Usuario</th>
                <th width="14%">Hardware</th>
                <th width="20%">Acción Requerida / Diagnóstico</th>
            </tr>
        </thead>
        <tbody>
            <?php if(count($datos) > 0): foreach($datos as $d): ?>
            <tr>
                <td style="font-weight: bold; text-align: center; font-family: monospace; font-size: 12px;"><?= htmlspecialchars($d['numInventario']) ?></td>
                
                <td style="text-align: center;">
                    <span class="badge <?= $d['clase_badge'] ?>">
                        <?= $d['etiqueta_tipo'] ?>
                    </span>
                </td>

                <td>
                    <strong style="color: #333;"><?= htmlspecialchars($d['tipoequipo']) ?></strong><br>
                    <span style="font-size: 10px; color: #777;"><?= htmlspecialchars($d['marca_modelo']) ?></span>
                </td>

                <td>
                    <strong style="color: #333;"><?= htmlspecialchars($d['direccion']) ?></strong><br>
                    <span style="color:#777; font-size:9px;"><?= htmlspecialchars($d['secretaria']) ?></span>
                </td>
                
                <td style="color: #444;"><?= htmlspecialchars($d['usuariosEquipo']) ?></td>
                
                <td>
                    <span style="font-size: 10px; color: #555;">
                    <i style="color: #888;">CPU:</i> <?= htmlspecialchars($d['procesador']) ?><br>
                    <i style="color: #888;">RAM:</i> <?= htmlspecialchars($d['ram']) ?>
                    <i style="color: #888;">DISCO:</i> <?= htmlspecialchars($d['tipodisco_capa']) ?>
                    </span>
                </td>
                
                <td>
                    <span style="font-weight: bold; color: #333; font-size: 11px;">
                        <?= $d['diagnostico_print'] ?>
                    </span>
                </td>
            </tr>
            <?php endforeach; else: ?>
            <tr>
                <td colspan="7" style="text-align: center; padding: 30px; color: #777;">
                    -- No se encontraron registros con los filtros seleccionados --
                </td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="firmas">
        <div class="firma-box">
            <br><br>
            <strong>Manuel Alejandro Lozano Reyes</strong><br>
            <span style="font-size: 10px; color: #555;">Soporte Técnico - SATQ</span>
        </div>
        <div class="firma-box">
            <br><br>
            <strong>Recibió / Enterado</strong><br>
            <span style="font-size: 10px; color: #555;">Titular del Área / Enlace Administrativo</span>
        </div>
    </div>

</body>
</html>
<?php $conn->close(); ?>