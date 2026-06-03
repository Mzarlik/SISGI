<?php
require_once 'session_check.php';
require_once 'config.php';
require_once 'fpdf/fpdf.php'; // Asegúrate de tener la librería FPDF aquí
session_start();

if (!isset($_SESSION['usuario'])) { die("Acceso denegado"); }

// --- RECIBIR PARÁMETROS ---
$tipoReporte = isset($_GET['tipo']) ? $_GET['tipo'] : 'criticos';
$filtroSecretaria = isset($_GET['secretaria']) ? $_GET['secretaria'] : '';

// --- CONSULTA DATOS (Misma lógica de filtrado) ---
$conn = get_db_connection();
$whereSQL = "WHERE tipoequipo NOT LIKE '%Impresora%'";
if (!empty($filtroSecretaria)) {
    $sec = $conn->real_escape_string($filtroSecretaria);
    $whereSQL .= " AND secretaria = '$sec'";
}

$sql = "SELECT * FROM equiposbd $whereSQL ORDER BY direccion ASC";
$result = $conn->query($sql);

$datosReporte = [];

// --- REPLICAR LÓGICA DE CLASIFICACIÓN ---
while ($row = $result->fetch_assoc()) {
    $ramRaw = $row['ram']; 
    $discoRaw = $row['tipodisco_capa'];
    $procRaw = $row['procesador'];
    $osRaw = $row['sistemaOperativo'];

    $ramGB = (int) filter_var($ramRaw, FILTER_SANITIZE_NUMBER_INT);
    $esSSD = (stripos($discoRaw, 'SSD') !== false || stripos($discoRaw, 'M.2') !== false || stripos($discoRaw, 'Solid') !== false);
    $esHDD = !$esSSD;
    $tieneWin11 = (stripos($osRaw, '11') !== false);
    $esI3 = (stripos($procRaw, 'i3') !== false);
    $esMuyLento = (stripos($procRaw, 'Celeron') !== false || stripos($procRaw, 'Pentium') !== false || stripos($procRaw, 'Atom') !== false || stripos($procRaw, 'Duo') !== false);
    $procLimitado = ($esMuyLento || $esI3);

    // Definir si entra en el reporte solicitado
    if ($tipoReporte == 'criticos') {
        if ($ramGB < 4 || $esHDD || $procLimitado) {
            $motivos = [];
            if ($procLimitado) $motivos[] = ($esI3) ? "CPU i3" : "CPU Lento";
            if ($esHDD) $motivos[] = "HDD Lento";
            if ($ramGB < 4) $motivos[] = "RAM Baja";
            $row['motivo_corto'] = implode(', ', $motivos);
            $datosReporte[] = $row;
        }
    } elseif ($tipoReporte == 'candidatos') {
        if ($esSSD && $ramGB >= 8 && !$tieneWin11) {
            $datosReporte[] = $row;
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
        $this->Cell(0,10,utf8_decode('H. Ayuntamiento de Playa del Carmen'),0,1,'C');
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
        $this->Cell(0,10,utf8_decode('Página ').$this->PageNo().'/{nb} - Generado por Sistema DNTICS',0,0,'C');
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