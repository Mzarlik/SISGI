<?php
// imprimir_informe.php
require_once 'session_check.php';
require_once 'config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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

// --- AUXILIAR: PARSEAR HARDWARE ---
if (!function_exists('analizarDescripcionHardware')) {
    function analizarDescripcionHardware($descripcion, $marca = '', $modelo = '') {
        $desc = mb_strtoupper($descripcion);
        $full = $desc . " | " . mb_strtoupper($marca) . " | " . mb_strtoupper($modelo);
        
        // 1. Procesador
        $procesador = "Intel Core i5"; // Default razonable
        if (stripos($full, 'Celeron') !== false) {
            $procesador = "Intel Celeron";
        } elseif (stripos($full, 'Pentium') !== false) {
            $procesador = "Intel Pentium";
        } elseif (stripos($full, 'Atom') !== false) {
            $procesador = "Intel Atom";
        } elseif (stripos($full, 'Dual Core') !== false || stripos($full, 'Core 2') !== false || stripos($full, 'Core2') !== false) {
            $procesador = "Intel Dual Core";
        } elseif (stripos($full, 'i3') !== false) {
            $procesador = "Intel Core i3";
        } elseif (stripos($full, 'i7') !== false) {
            $procesador = "Intel Core i7";
        } elseif (stripos($full, 'Ryzen') !== false) {
            $procesador = "AMD Ryzen";
        }
        
        // 2. RAM
        $ram = "8 GB"; // Default
        if (preg_match('/(\d+)\s*(GB|MB|GM|G)/i', $desc, $matches)) {
            $val = intval($matches[1]);
            $unit = strtoupper($matches[2]);
            if ($val > 0) {
                if ($unit === 'MB') {
                    $ram = $val . " MB";
                } else {
                    $ram = $val . " GB";
                }
            }
        }
        
        // 3. Disco
        $disco = "HDD 500GB"; // Default
        if (stripos($full, 'SSD') !== false || stripos($full, 'M.2') !== false || stripos($full, 'SOLIDO') !== false || stripos($full, 'SÓLIDO') !== false) {
            $disco = "SSD 240GB";
        } elseif (preg_match('/(\d+)\s*(GB|TB)\s*(DISCO|HDD|MECANICO)/i', $desc, $matches)) {
            $disco = "HDD " . $matches[1] . $matches[2];
        }
        
        // 4. Sistema Operativo
        $so = "Windows 10 Pro"; // Default
        if (stripos($full, 'Windows 11') !== false || stripos($full, 'Win 11') !== false || stripos($full, 'Win11') !== false) {
            $so = "Windows 11 Pro";
        } elseif (stripos($full, 'Windows 7') !== false || stripos($full, 'Win 7') !== false || stripos($full, 'Win7') !== false || stripos($full, 'WUINDOS 7') !== false) {
            $so = "Windows 7 Pro";
        } elseif (stripos($full, 'Windows CE') !== false) {
            $so = "Windows CE";
        }
        
        return [
            'procesador' => $procesador,
            'ram' => $ram,
            'tipodisco_capa' => $disco,
            'sistemaOperativo' => $so
        ];
    }
}

// --- 3. OBTENCIÓN Y PROCESAMIENTO DE DATOS ---
$conn = get_db_connection();

$whereClauses = [];
$furniture_types = "'Silla', 'Escritorio', 'Mueble', 'Archivero', 'Silla de oficina', 'Escritorio de oficina'";
$whereClauses[] = "tbi.nombre_tipo NOT IN ($furniture_types)";
$whereClauses[] = "tbi.nombre_tipo NOT LIKE '%Impresora%'";

if (!empty($filtroSecretaria)) {
    $sec = $conn->real_escape_string($filtroSecretaria);
    $whereClauses[] = "(s.nombres = '$sec' OR (s.nombres IS NULL AND '$sec' = 'SATQ'))";
}

if (!empty($filtroDireccion)) {
    $dir = $conn->real_escape_string($filtroDireccion);
    $whereClauses[] = "(d.nombre_direccion = '$dir' OR (d.nombre_direccion IS NULL AND inv.nombre_ubicacion = '$dir'))";
}

$whereSQL = "WHERE " . implode(' AND ', $whereClauses);

$sql = "SELECT inv.id, inv.num_inventario as numInventario, tbi.nombre_tipo as tipoequipo, 
               CONCAT(COALESCE(inv.marca,''), ' ', COALESCE(inv.modelo,'')) as marca_modelo,
               inv.personal_asignado as usuariosEquipo, 
               COALESCE(d.nombre_direccion, inv.nombre_ubicacion) as direccion, 
               inv.estatus as estatus_equipo, inv.descripcion as observaciones,
               COALESCE(s.nombres, 'SATQ') as secretaria,
               inv.descripcion, inv.marca, inv.modelo
        FROM inventario_soporte inv
        LEFT JOIN tipo_bien_inventario tbi ON inv.id_tipo_bien = tbi.id_tipo
        LEFT JOIN registros_ad r ON TRIM(REPLACE(CONCAT(r.nombres, ' ', COALESCE(r.apellido_paterno,''), ' ', COALESCE(r.apellido_materno,'')), '  ', ' ')) = inv.personal_asignado
        LEFT JOIN cat_direcciones d ON r.id_direccion = d.id_direccion
        LEFT JOIN Secretarias s ON d.id_secretaria = s.id_secretaria
        $whereSQL 
        ORDER BY direccion ASC";

$result = $conn->query($sql);
$datos = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $hardware = analizarDescripcionHardware($row['descripcion'], $row['marca'] ?? '', $row['modelo'] ?? '');
        
        $ramRaw = $hardware['ram']; 
        $discoRaw = $hardware['tipodisco_capa'];
        $procRaw = $hardware['procesador'];
        $osRaw = $hardware['sistemaOperativo'];
        $estatusRaw = $row['estatus_equipo'] ?? '';

        $ramGB = intval($ramRaw); 
        $esSSD = (stripos($discoRaw, 'SSD') !== false || stripos($discoRaw, 'M.2') !== false || stripos($discoRaw, 'Solid') !== false);
        $esHDD = !$esSSD;
        $tieneWin11 = (stripos($osRaw, '11') !== false);

        $esObsoleto = (stripos($procRaw, 'Celeron') !== false || 
                       stripos($procRaw, 'Pentium') !== false || 
                       stripos($procRaw, 'Atom') !== false || 
                       stripos($procRaw, 'Duo') !== false);

        $esDanado = (stripos($estatusRaw, 'Dañado') !== false || stripos($estatusRaw, 'Baja') !== false || stripos($estatusRaw, 'Para Baja') !== false);

        // Mapear los datos de hardware al array de la fila para que no se rompan las referencias
        $row['ram'] = $ramRaw;
        $row['tipodisco_capa'] = $discoRaw;
        $row['procesador'] = $procRaw;
        $row['sistemaOperativo'] = $osRaw;

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
            // 1. Es Obsoleto o Dañado (CRÍTICO)
            if ($esDanado) {
                if ($tipoReporte == 'criticos' || $tipoReporte == 'unificado') {
                    $row['etiqueta_tipo'] = 'BAJA';
                    $row['clase_badge'] = 'badge-baja';
                    $row['diagnostico_print'] = "Equipo Dañado / Baja";
                    $agregar = true;
                }
            } elseif ($esObsoleto) {
                if ($tipoReporte == 'criticos' || $tipoReporte == 'unificado') {
                    $row['etiqueta_tipo'] = 'BAJA';
                    $row['clase_badge'] = 'badge-baja';
                    $row['diagnostico_print'] = "Procesador Limitado ($procRaw)";
                    $agregar = true;
                }
            } 
            // 2. Es Candidato (MEJORA)
            else {
                // Si es SSD, >=8GB y Win11, es ÓPTIMO, así que no se agrega a Bajas o Mejoras.
                $esOptimo = ($esSSD && $ramGB >= 8 && $tieneWin11);
                if (!$esOptimo) {
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
    <script src="js/jspdf.umd.min.js"></script>
    <script src="js/jspdf.plugin.autotable.min.js"></script>
    <script src="js/Montserrat-normal.js"></script>
    <script src="js/Montserrat-bold.js"></script>
    <style>
        body { 
            margin: 0; 
            padding: 0; 
            background-color: #525659; 
            height: 100vh; 
            display: flex; 
            flex-direction: column; 
            justify-content: center; 
            align-items: center; 
            color: white; 
            font-family: sans-serif; 
        }
        #pdf-viewer { 
            width: 100%; 
            height: 100%; 
            border: none; 
            display: none; 
        }
        .loader { 
            border: 4px solid #f3f3f3; 
            border-top: 4px solid <?= $colorHeader ?>; 
            border-radius: 50%; 
            width: 40px; 
            height: 40px; 
            animation: spin 1s linear infinite; 
            margin-bottom: 10px; 
        }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    </style>
    <script>
        function togglePanel() {
            var panel = document.getElementById('panelOpciones');
            var btn = document.getElementById('btnMostrarPanel');
            
            if (panel.style.display === 'none' || panel.style.display === '') {
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
        
        <button onclick="descargarPDF()" style="margin-top: 10px; width: 100%; padding: 8px; cursor: pointer; font-weight: bold; background: #333; color: white; border: none; border-radius: 4px;">📥 DESCARGAR PDF</button>
    </div>

    <script>
        const datos = <?php echo json_encode($datos); ?>;
        const tituloReporte = <?php echo json_encode($titulo); ?>;
        const subTituloReporte = <?php echo json_encode($subtitulo); ?>;
        const descReporte = <?php echo json_encode(strip_tags($desc)); ?>;

        window.globalDoc = null;

        window.onload = function() {
            generarPDF();
        };

        function generarPDF() {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF('l', 'pt', 'letter');
            window.globalDoc = doc;
            
            const pageWidth = doc.internal.pageSize.getWidth();
            const pageHeight = doc.internal.pageSize.getHeight();
            const marginLeft = 40;
            const marginRight = 40;
            
            const logoGob = new Image();
            logoGob.src = 'img/logo_gobierno.png';
            
            const logoHacienda = new Image();
            logoHacienda.src = 'img/logo_hacienda.png';
            
            Promise.all([
                new Promise((resolve) => { logoGob.onload = resolve; logoGob.onerror = resolve; }),
                new Promise((resolve) => { logoHacienda.onload = resolve; logoHacienda.onerror = resolve; })
            ]).then(() => {
                let startY = 40;
                
                if(logoGob.width > 0) {
                    doc.addImage(logoGob, 'PNG', marginLeft, startY, 130, 45);
                }
                
                doc.setFont("Montserrat", "normal");
                doc.setFontSize(9);
                doc.setTextColor(100);
                const fechaActual = new Date().toLocaleDateString('es-MX', { day: '2-digit', month: '2-digit', year: 'numeric' });
                const horaActual = new Date().toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' });
                doc.text(`Fecha de Emisión: ${fechaActual}`, pageWidth - marginRight, startY + 10, { align: 'right' });
                doc.text(`Hora: ${horaActual}`, pageWidth - marginRight, startY + 22, { align: 'right' });

                startY += 55;

                doc.setFont("Montserrat", "bold");
                doc.setFontSize(14);
                doc.setTextColor(114, 21, 56);
                doc.text(tituloReporte, pageWidth / 2, startY, { align: "center" });
                startY += 15;

                doc.setFontSize(10);
                doc.setTextColor(50);
                doc.text(`Filtro Aplicado: ${subTituloReporte}`, pageWidth / 2, startY, { align: "center" });
                startY += 12;
                
                doc.setFont("Montserrat", "normal");
                doc.setFontSize(9);
                doc.text(`${descReporte} (Total: ${datos.length})`, pageWidth / 2, startY, { align: "center" });
                startY += 20;

                const filas = datos.map(d => [
                    d.numInventario || 'S/N',
                    d.etiqueta_tipo || 'N/A',
                    `${d.tipoequipo || ''}\n${d.marca_modelo || ''}`,
                    `${d.direccion || ''}\n${d.secretaria || ''}`,
                    d.usuariosEquipo || 'STOCK',
                    `CPU: ${d.procesador || ''}\nRAM: ${d.ram || ''}\nDISCO: ${d.tipodisco_capa || ''}`,
                    d.diagnostico_print || ''
                ]);

                doc.autoTable({
                    startY: startY,
                    head: [['Inventario', 'Estatus', 'Equipo', 'Ubicación / Dirección', 'Usuario', 'Hardware', 'Diagnóstico / Acción']],
                    body: filas.length > 0 ? filas : [['', '', '', '-- No se encontraron registros --', '', '', '']],
                    theme: 'grid',
                    headStyles: { fillColor: [240, 240, 240], textColor: [0, 0, 0], fontStyle: 'bold', halign: 'center' },
                    styles: { font: 'Montserrat', fontSize: 8, cellPadding: 4, textColor: [0,0,0], lineColor: [0,0,0], lineWidth: 0.5, valign: 'middle' },
                    columnStyles: { 
                        0: { halign: 'center', fontStyle: 'bold' },
                        1: { halign: 'center', fontStyle: 'bold', textColor: [114, 21, 56] }
                    },
                    didDrawPage: function(data) {
                        if(logoHacienda.width > 0) {
                            doc.addImage(logoHacienda, 'PNG', pageWidth - marginRight - 160, pageHeight - 65, 160, 40);
                        }
                        
                        doc.setFontSize(8);
                        doc.setFont("Montserrat", "normal");
                        doc.setTextColor(100);
                        const footerText = "Hacienda del Estado de Quintana Roo\nSATQ\nwww.satq.qroo.gob.mx";
                        doc.text(footerText, marginLeft, pageHeight - 40, { align: 'left' });
                    }
                });

                let finalY = doc.lastAutoTable.finalY + 40;

                if (finalY > pageHeight - 80) {
                    doc.addPage();
                    finalY = 60;
                }

                doc.setFont("Montserrat", "bold");
                doc.setFontSize(10);
                doc.setTextColor(0);

                doc.line(marginLeft + 40, finalY, marginLeft + 240, finalY);
                doc.text("Manuel Alejandro Lozano Reyes", marginLeft + 140, finalY + 12, { align: 'center' });
                doc.setFont("Montserrat", "normal");
                doc.setFontSize(9);
                doc.text("Soporte Técnico - SATQ", marginLeft + 140, finalY + 22, { align: 'center' });

                doc.setFont("Montserrat", "bold");
                doc.setFontSize(10);
                doc.line(pageWidth - marginRight - 240, finalY, pageWidth - marginRight - 40, finalY);
                doc.text("Recibió / Enterado", pageWidth - marginRight - 140, finalY + 12, { align: 'center' });
                doc.setFont("Montserrat", "normal");
                doc.setFontSize(9);
                doc.text("Titular del Área / Enlace Administrativo", pageWidth - marginRight - 140, finalY + 22, { align: 'center' });

                const pdfBlobUrl = doc.output('bloburl');
                document.getElementById('loading').style.display = 'none';
                const viewer = document.getElementById('pdf-viewer');
                viewer.style.display = 'block';
                viewer.src = pdfBlobUrl;
            });
        }
    </script>

</body>
</html>
<?php $conn->close(); ?>