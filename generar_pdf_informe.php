<?php
require_once 'session_check.php';
require_once 'config.php';
require_once 'fpdf/fpdf.php'; // Asegúrate de tener la librería FPDF aquí
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario'])) { die("Acceso denegado"); }

// --- RECIBIR PARÁMETROS ---
$tipoReporte = isset($_GET['tipo']) ? $_GET['tipo'] : 'criticos';
$filtroSecretaria = isset($_GET['secretaria']) ? $_GET['secretaria'] : '';

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

// --- CONSULTA DATOS (Misma lógica de filtrado) ---
$conn = get_db_connection();

$whereClauses = [];
$furniture_types = "'Silla', 'Escritorio', 'Mueble', 'Archivero', 'Silla de oficina', 'Escritorio de oficina'";
$whereClauses[] = "tbi.nombre_tipo NOT IN ($furniture_types)";
$whereClauses[] = "tbi.nombre_tipo NOT LIKE '%Impresora%'";

if (!empty($filtroSecretaria)) {
    $sec = $conn->real_escape_string($filtroSecretaria);
    $whereClauses[] = "(s.nombres = '$sec' OR (s.nombres IS NULL AND '$sec' = 'SATQ'))";
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
$datosReporte = [];

// --- REPLICAR LÓGICA DE CLASIFICACIÓN ---
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

        $row['ram'] = $ramRaw;
        $row['tipodisco_capa'] = $discoRaw;
        $row['procesador'] = $procRaw;
        $row['sistemaOperativo'] = $osRaw;

        // Definir si entra en el reporte solicitado
        if ($tipoReporte == 'criticos') {
            if ($esDanado || $esObsoleto) {
                $motivos = [];
                if ($esDanado) $motivos[] = "Dañado / Baja";
                if ($esObsoleto) $motivos[] = "CPU Limitado (" . $procRaw . ")";
                $row['motivo_corto'] = implode(', ', $motivos);
                $datosReporte[] = $row;
            }
        } elseif ($tipoReporte == 'candidatos') {
            $esOptimo = ($esSSD && $ramGB >= 8 && $tieneWin11);
            if (!$esOptimo && !$esDanado && !$esObsoleto) {
                // Si no es óptimo, ni obsoleto/dañado, es candidato
                $datosReporte[] = $row;
            }
        }
    }
}


// --- CLASE PDF ---
class PDF extends FPDF {
    public $tituloReporte;
    public $subtitulo;

    function Header() {
        // Logo (Opcional, si tienes uno descomenta)
        // $this->Image('logo.png',10,6,30);
        $this->SetFont('Arial','B',14);
        $this->SetTextColor(114, 21, 56); // Color #721538
        $this->Cell(0,10,utf8_decode('Hacienda del Estado de Quintana Roo'),0,1,'C');
        $this->SetFont('Arial','B',12);
        $this->Cell(0,10,utf8_decode($this->tituloReporte),0,1,'C');
        if($this->subtitulo) {
            $this->SetFont('Arial','I',10);
            $this->SetTextColor(100);
            $this->Cell(0,5,utf8_decode($this->subtitulo),0,1,'C');
        }
        $this->Ln(5);

        // Encabezados de Tabla
        $this->SetFillColor(114, 21, 56);
        $this->SetTextColor(255);
        $this->SetFont('Arial','B',9);
        
        // Anchos: Dir(60), User(50), Hardware(45), InfoExtra(35) -> Total 190
        $this->Cell(60,8,utf8_decode('Dirección / Ubicación'),1,0,'L',true);
        $this->Cell(50,8,'Usuario',1,0,'L',true);
        $this->Cell(45,8,'Hardware',1,0,'L',true);
        $this->Cell(35,8,utf8_decode('Detalle / Acción'),1,0,'L',true);
        $this->Ln();
    }

    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial','I',8);
        $this->SetTextColor(128);
        $this->Cell(0,10,utf8_decode('Página ').$this->PageNo().'/{nb} - Generado por Sistema SATQ',0,0,'C');
    }
}

// --- GENERAR PDF ---
$pdf = new PDF();
$pdf->AliasNbPages();
$pdf->AddPage(); // Vertical por defecto, si es muy ancha la tabla usa 'L' (Landscape)

// Configurar Títulos
if ($tipoReporte == 'criticos') {
    $pdf->tituloReporte = "REPORTE DE EQUIPOS CON BAJO RENDIMIENTO / NO APTOS";
} else {
    $pdf->tituloReporte = "CANDIDATOS PARA ACTUALIZACIÓN A WINDOWS 11 PRO";
}

$pdf->subtitulo = empty($filtroSecretaria) ? "Todos los departamentos" : "Secretaría: " . $filtroSecretaria;

$pdf->SetFont('Arial','',9);
$pdf->SetTextColor(0);

foreach ($datosReporte as $d) {
    // Preparar textos para que no rompan la celda (MultiCell logic simplificada)
    $direccion = substr($d['direccion'], 0, 35);
    $usuario = substr($d['usuariosEquipo'], 0, 25);
    $hardware = $d['procesador'] . "\n" . $d['ram'] . " | " . $d['tipodisco_capa'];
    
    if($tipoReporte == 'criticos') {
        $infoExtra = $d['motivo_corto'];
    } else {
        $infoExtra = "SO: " . $d['sistemaOperativo'];
    }

    // Usamos MultiCell simulado o Cell normal. 
    // Para simplificar y que quede alineado, usaremos celdas de altura fija.
    $h = 12; // Altura de fila
    
    $currentX = $pdf->GetX();
    $currentY = $pdf->GetY();
    
    // Dibujar celdas
    // Dirección (MultiCell para que baje si es larga)
    $pdf->Cell(60,$h, utf8_decode($d['direccion']), 1, 0, 'L');
    
    // Usuario
    $pdf->Cell(50,$h, utf8_decode($usuario), 1, 0, 'L');
    
    // Hardware (Texto pequeño)
    $pdf->SetFont('Arial','',7);
    $pdf->Cell(45,$h, utf8_decode($d['procesador'] . " / " . $d['ram'] . " / " . $d['tipodisco_capa']), 1, 0, 'L');
    $pdf->SetFont('Arial','',9);

    // Extra
    $pdf->Cell(35,$h, utf8_decode($infoExtra), 1, 1, 'C');
}

if (count($datosReporte) == 0) {
    $pdf->Cell(190, 10, utf8_decode('No se encontraron equipos en esta categoría para la selección actual.'), 1, 1, 'C');
}

$pdf->Output('I', 'reporte_equipos.pdf');
?>