<?php
// Incluye la configuración de la base de datos y maneja la sesión
require_once 'session_check.php';
require_once 'config.php';

// Redirige si el usuario no está autenticado
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}

// Bloquear acceso a técnicos de redes (se maneja en el backend también)
if (isset($_SESSION['rol']) && $_SESSION['rol'] === 'redes') {
    header("Location: dashboard.php?error=acceso_denegado");
    exit();
}

// ===============================================
// 1. LÓGICA PHP PARA OBTENER DATOS DESDE LA BD
// ===============================================
$conn = get_db_connection();
$tipos_equipo = [];
$ubicaciones = [];

if ($conn) {
    // A. Obtener ubicaciones distintas existentes en la base de datos
    $sql_ubicaciones = "SELECT DISTINCT ubicacion FROM inventario_soporte WHERE ubicacion IS NOT NULL AND ubicacion <> '' ORDER BY ubicacion ASC";
    $result_ubicaciones = $conn->query($sql_ubicaciones);
    if ($result_ubicaciones && $result_ubicaciones->num_rows > 0) {
        while ($row = $result_ubicaciones->fetch_assoc()) {
            $ubicaciones[] = $row['ubicacion'];
        }
    }

    // B. Obtener tipos de equipo desde la nueva tabla relacional
    $sql_tipos = "SELECT id_tipo, nombre_tipo FROM tipo_bien_inventario ORDER BY nombre_tipo ASC";
    $result_tipos = $conn->query($sql_tipos);
    
    if ($result_tipos && $result_tipos->num_rows > 0) {
        while ($row = $result_tipos->fetch_assoc()) {
            $tipos_equipo[] = $row;
        }
    }

    // C. Obtener lista de personal asignado (de usuarios del sistema y existentes en inventario)
    $personal_asignado_sug = [];
    
    // Obtener de la tabla usuarios
    $sql_usuarios = "SELECT usuario FROM usuarios ORDER BY usuario ASC";
    $result_usuarios = $conn->query($sql_usuarios);
    if ($result_usuarios && $result_usuarios->num_rows > 0) {
        while ($row = $result_usuarios->fetch_assoc()) {
            $user_formatted = ucwords(strtolower($row['usuario']));
            if (!in_array($user_formatted, $personal_asignado_sug)) {
                $personal_asignado_sug[] = $user_formatted;
            }
        }
    }
    
    // Obtener de inventario_soporte
    $sql_personal = "SELECT DISTINCT personal_asignado FROM inventario_soporte WHERE personal_asignado IS NOT NULL AND personal_asignado <> '' AND personal_asignado <> 'STOCK' ORDER BY personal_asignado ASC";
    $result_personal = $conn->query($sql_personal);
    if ($result_personal && $result_personal->num_rows > 0) {
        while ($row = $result_personal->fetch_assoc()) {
            $pers = trim($row['personal_asignado']);
            if (!in_array($pers, $personal_asignado_sug)) {
                $personal_asignado_sug[] = $pers;
            }
        }
    }
    
    // Ordenar alfabéticamente y asegurar STOCK
    sort($personal_asignado_sug);
    if (!in_array('STOCK', $personal_asignado_sug)) {
        array_unshift($personal_asignado_sug, 'STOCK');
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Registro de Inventario | Soporte</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> 
    <link rel="stylesheet" href="css/fonts.css"> 
    <link rel="stylesheet" href="css/all.min.css"> 
    <link rel="stylesheet" href="css/style.css"> 
    <script src="js/sweetalert2.all.min.js"></script>
    <style>
        body { 
            background: radial-gradient(circle at 10% 20%, #f4f3f0 0%, #dbd7cf 90%);
            font-family: 'Inter', 'Segoe UI', sans-serif; 
            margin: 0; 
            padding: 30px 0; 
            min-height: 100vh; 
            display: flex !important; 
            flex-direction: column;
            align-items: center;
            justify-content: flex-start;
            box-sizing: border-box;
        }
        
        .center-container { 
            width: 95%; 
            max-width: 850px; 
            margin: 0 auto !important; 
            background: transparent; 
            box-sizing: border-box; 
            display: block !important;
            height: auto !important;
            min-height: auto !important;
        }
        
        .card { 
            background-color: #ffffff; 
            border-radius: 20px; 
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08), 0 1px 3px rgba(0, 0, 0, 0.02); 
            border: 1px solid rgba(255, 255, 255, 0.8);
            padding: 24px 30px; 
            box-sizing: border-box; 
            transition: transform 0.3s ease;
            width: 100% !important;
            max-width: 850px !important;
        }
        
        .header-container {
            text-align: center;
            margin-bottom: 20px;
        }
        
        .header-logo {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background-color: #f2ecef;
            color: #721538;
            width: 42px;
            height: 42px;
            border-radius: 10px;
            margin-bottom: 8px;
            font-size: 18px;
            box-shadow: inset 0 0 0 1px rgba(114, 21, 56, 0.1);
        }

        h2 { 
            color: #721538; 
            font-size: 22px;
            font-weight: 800;
            margin: 0 0 4px 0; 
            letter-spacing: -0.5px;
        }
        
        .subtitle {
            color: #718096;
            font-size: 13px;
            margin: 0;
            font-weight: 500;
        }
        
        /* Secciones del Formulario */
        .form-section {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 16px 20px;
            margin-bottom: 16px;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }
        
        .form-section:hover {
            border-color: #cbd5e0;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.02);
        }
        
        .section-title {
            font-size: 14px;
            font-weight: 700;
            color: #721538;
            margin-top: 0;
            margin-bottom: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            gap: 8px;
            border-bottom: 1px solid #edf2f7;
            padding-bottom: 8px;
        }
        
        .section-title i {
            font-size: 14px;
        }

        label { 
            display: block; 
            margin-bottom: 5px; 
            color: #4a5568; 
            font-weight: 600; 
            font-size: 12.5px; 
        }
        
        input[type="text"], 
        input[type="number"], 
        select, 
        textarea { 
            width: 100%; 
            height: 40px;
            padding: 8px 12px; 
            margin-bottom: 12px; 
            border: 1.5px solid #e2e8f0; 
            border-radius: 8px; 
            box-sizing: border-box; 
            font-size: 14px; 
            font-family: 'Inter', sans-serif;
            background-color: #f8fafc; 
            color: #2d3748;
            transition: all 0.2s ease-in-out;
        }
        
        textarea {
            height: auto;
            resize: vertical;
            min-height: 90px;
        }
        
        input[type="text"]::placeholder, 
        input[type="number"]::placeholder, 
        textarea::placeholder {
            color: #a0aec0;
            font-size: 13px;
        }

        input:focus, 
        select:focus, 
        textarea:focus { 
            outline: none;
            border-color: #721538;
            background-color: #ffffff;
            box-shadow: 0 0 0 3px rgba(114, 21, 56, 0.12);
        }
        
        /* Personalización de Select */
        select {
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            background-image: url("data:image/svg+xml;charset=utf-8,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%23721538' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            background-size: 12px;
            padding-right: 36px !important;
            cursor: pointer;
        }

        .form-row { 
            display: flex; 
            gap: 15px; 
        }
        
        .form-row-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
        }
        
        .form-col { 
            flex: 1; 
            min-width: 0;
        }
        
        /* Layout horizontal de secciones */
        .section-split {
            display: flex;
            gap: 20px;
        }
        
        .split-main {
            flex: 1.6;
            min-width: 0;
        }
        
        .split-side {
            flex: 1;
            display: flex;
            flex-direction: column;
            min-width: 0;
        }
        
        .split-side textarea {
            flex: 1;
            margin-bottom: 12px;
        }

        /* CPU Contenedor periféricos */
        #contenedor_perifericos {
            background-color: #faf5f6;
            border: 1px solid #f2e2e6;
            border-left: 4px solid #721538;
            padding: 10px 14px;
            border-radius: 8px;
            margin-bottom: 12px;
            display: none;
            animation: slideDown 0.25s ease-out;
        }
        
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-5px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .perifericos-title {
            display: block;
            margin-bottom: 8px;
            color: #721538;
            font-weight: 600;
            font-size: 12.5px;
        }
        
        .perifericos-title i {
            margin-right: 4px;
        }
        
        .perifericos-options {
            display: flex;
            gap: 12px;
        }
        
        .checkbox-card {
            display: flex;
            align-items: center;
            gap: 6px;
            background: #ffffff;
            padding: 6px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            cursor: pointer;
            user-select: none;
            transition: all 0.2s;
            font-size: 12.5px;
            color: #4a5568;
            font-weight: 500;
        }
        
        .checkbox-card:hover {
            border-color: #721538;
            background: #fdfafb;
        }
        
        .checkbox-card input[type="checkbox"] {
            margin: 0;
            width: 14px;
            height: 14px;
            accent-color: #721538;
            cursor: pointer;
        }

        /* Foto de evidencia dropzone */
        .file-upload-wrapper {
            position: relative;
            width: 100%;
            margin-bottom: 12px;
        }
        .file-upload-wrapper input[type="file"] {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
            z-index: 10;
        }
        .file-upload-label {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            border: 2px dashed #cbd5e0;
            border-radius: 8px;
            padding: 15px 10px;
            background-color: #fafbfc;
            cursor: pointer;
            transition: all 0.2s ease-in-out;
            text-align: center;
        }
        .file-upload-wrapper:hover .file-upload-label {
            border-color: #721538;
            background-color: #fdfafb;
        }
        .upload-icon {
            font-size: 20px;
            color: #a0aec0;
            margin-bottom: 6px;
            transition: color 0.2s;
        }
        .file-upload-wrapper:hover .upload-icon {
            color: #721538;
        }
        .upload-text {
            font-size: 12.5px;
            font-weight: 600;
            color: #4a5568;
            margin-bottom: 3px;
        }
        .file-name-preview {
            font-size: 11px;
            font-weight: 600;
            color: #718096;
            background-color: #edf2f7;
            padding: 2px 10px;
            border-radius: 20px;
            max-width: 90%;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            transition: all 0.2s;
        }
        .file-selected .file-name-preview {
            background-color: #e6f4ea;
            color: #137333;
        }
        .file-selected {
            border-color: #137333;
            background-color: #f4faf6;
        }
        .file-upload-wrapper:hover .file-selected {
            border-color: #137333;
            background-color: #eaf6ed;
        }

        /* Botones unificados */
        .action-footer {
            display: flex;
            gap: 12px;
            margin-top: 15px;
            align-items: center;
        }
        
        .btn-submit { 
            flex: 2; 
            height: 42px;
            padding: 0 16px; 
            background-color: #721538; 
            color: white; 
            border: none; 
            border-radius: 8px; 
            font-size: 14.5px; 
            font-weight: 700; 
            font-family: 'Inter', sans-serif;
            cursor: pointer; 
            transition: all 0.2s ease-in-out; 
            box-shadow: 0 4px 10px rgba(114, 21, 56, 0.15);
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        
        .btn-submit:hover { 
            background-color: #5a102c; 
            transform: translateY(-1px);
            box-shadow: 0 6px 14px rgba(114, 21, 56, 0.25);
        }
        
        .btn-submit:active {
            transform: translateY(0);
        }
        
        .btn-accion { 
            flex: 1; 
            height: 42px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 0 12px; 
            background-color: #ffffff; 
            color: #4a5568; 
            border: 1px solid #e2e8f0; 
            border-radius: 8px; 
            cursor: pointer; 
            text-align: center; 
            font-size: 13.5px; 
            font-weight: 600;
            font-family: 'Inter', sans-serif;
            transition: all 0.2s ease-in-out; 
            text-decoration: none; 
            box-sizing: border-box;
        }
        
        .btn-accion:hover { 
            background-color: #f7fafc; 
            color: #2d3748;
            border-color: #cbd5e0;
            transform: translateY(-1px);
        }
        
        .btn-accion:active {
            transform: translateY(0);
        }
        
        .logout { 
            text-align: center; 
            margin-top: 15px; 
            font-size: 13px;
        }
        
        .logout a { 
            color: #721538; 
            text-decoration: none; 
            font-weight: 600; 
            transition: color 0.2s;
        }
        
        .logout a:hover {
            color: #5a102c;
            text-decoration: underline;
        }
        
        @media (max-width: 768px) { 
            body {
                align-items: flex-start;
            }
            .center-container {
                margin: 15px auto !important;
            }
            .form-row, .form-row-grid, .section-split { 
                flex-direction: column; 
                display: flex;
                gap: 0; 
            } 
            .split-main, .split-side {
                flex: none;
                width: 100%;
            }
            .split-side textarea {
                min-height: 90px;
            }
            .split-side .file-upload-label {
                padding: 20px;
            }
            .card { 
                padding: 20px 15px; 
            } 
            .form-section {
                padding: 14px 12px;
            }
            .action-footer {
                flex-direction: column;
                gap: 8px;
            }
            .btn-submit, .btn-accion {
                width: 100%;
                flex: none;
            }
        }
    </style>
</head>
<body>

<div class="center-container">
    <div class="card">
        <div class="header-container">
            <div class="header-logo">
                <i class="fas fa-boxes"></i>
            </div>
            <h2>Registro de Inventario | Soporte</h2>
            <p class="subtitle">Sistema Integrado de Gestión de Inventario (SISGI)</p>
        </div>
        
        <form id="formInventario" enctype="multipart/form-data">
            <!-- SECCIÓN 1: IDENTIFICACIÓN Y ESTADO -->
            <div class="form-section">
                <h3 class="section-title"><i class="fas fa-tag"></i> Identificación y Estado</h3>
                <div class="form-row-grid">
                    <div class="form-col">
                        <label for="num_inventario">Número de Inventario:</label>
                        <input type="text" id="num_inventario" name="num_inventario" placeholder="Ej: AS-2024-0001" required>
                    </div>
                    <div class="form-col">
                        <label for="municipio">Municipio:</label>
                        <input type="text" id="municipio" name="municipio" value="Solidaridad">
                    </div>
                    <div class="form-col">
                        <label for="no_bien_mueble">No. de Bien Mueble (Opcional):</label>
                        <input type="text" id="no_bien_mueble" name="no_bien_mueble" placeholder="Ej: 5111000001">
                    </div>
                </div>
                <div class="form-row-grid">
                    <div class="form-col">
                        <label for="no_inv_anterior">No. de Inventario Anterior (Opcional):</label>
                        <input type="text" id="no_inv_anterior" name="no_inv_anterior" placeholder="Ej: INV-ANT-1234">
                    </div>
                    <div class="form-col">
                        <label for="estatus">Estatus:</label>
                        <select id="estatus" name="estatus" required>
                            <option value="En Stock" selected>En Stock</option>
                            <option value="Asignado">Asignado</option>
                            <option value="En Mantenimiento">En Mantenimiento</option>
                            <option value="Para Baja">Para Baja</option>
                        </select>
                    </div>
                    <div class="form-col">
                        <label for="color">Color:</label>
                        <input type="text" id="color" name="color" placeholder="Ej: Negro, Gris, Blanco">
                    </div>
                </div>
            </div>

            <!-- SECCIÓN 2: DETALLES DEL EQUIPO -->
            <div class="form-section">
                <h3 class="section-title"><i class="fas fa-laptop"></i> Detalles del Equipo</h3>
                <div class="section-split">
                    <div class="split-main">
                        <div class="form-row">
                            <div class="form-col">
                                <label for="id_tipo_bien">Tipo de Equipo:</label>
                                <select id="id_tipo_bien" name="id_tipo_bien" required>
                                    <option value="">-- Selecciona --</option>
                                    <?php 
                                    if (!empty($tipos_equipo)) {
                                        foreach ($tipos_equipo as $tipo) {
                                            echo "<option value=\"" . htmlspecialchars($tipo['id_tipo']) . "\">" . htmlspecialchars($tipo['nombre_tipo']) . "</option>";
                                        }
                                    } else {
                                        echo "<option value=\"\" disabled>-- Error al cargar tipos --</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="form-col">
                                <label for="marca">Marca:</label>
                                <input type="text" id="marca" name="marca" placeholder="Ej: Dell, HP, Samsung..." required>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-col">
                                <label for="modelo">Modelo:</label>
                                <input type="text" id="modelo" name="modelo" placeholder="Ej: Latitude 5420, P2422H" required>
                            </div>
                            <div class="form-col">
                                <label for="num_serie">Número de Serie:</label>
                                <input type="text" id="num_serie" name="num_serie" placeholder="S/N o Service Tag" required>
                            </div>
                        </div>
                        
                        <div id="contenedor_perifericos">
                            <span class="perifericos-title"><i class="fas fa-keyboard"></i> CPU con periféricos:</span>
                            <div class="perifericos-options">
                                <label class="checkbox-card">
                                    <input type="checkbox" id="incluye_teclado" name="incluye_teclado" value="Sí"> Teclado
                                </label>
                                <label class="checkbox-card">
                                    <input type="checkbox" id="incluye_mouse" name="incluye_mouse" value="Sí"> Mouse
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="split-side">
                        <label for="descripcion">Descripción / Especificaciones:</label>
                        <textarea id="descripcion" name="descripcion" placeholder="Detalles de hardware, sistema operativo, accesorios, etc."></textarea>
                    </div>
                </div>
            </div>

            <!-- SECCIÓN 3: ASIGNACIÓN Y EVIDENCIA -->
            <div class="form-section">
                <h3 class="section-title"><i class="fas fa-user-check"></i> Asignación y Evidencia</h3>
                <div class="section-split">
                    <div class="split-main">
                        <div>
                            <label for="personal_asignado">Personal Asignado:</label>
                            <input type="text" id="personal_asignado" name="personal_asignado" list="lista_personal" placeholder="Nombre de la persona o 'STOCK'">
                            <datalist id="lista_personal">
                                <?php 
                                if (!empty($personal_asignado_sug)) {
                                    foreach ($personal_asignado_sug as $pers) {
                                        echo "<option value=\"" . htmlspecialchars($pers) . "\">";
                                    }
                                }
                                ?>
                            </datalist>
                        </div>
                        <div>
                            <label for="ubicacion">Ubicación:</label>
                            <input type="text" id="ubicacion" name="ubicacion" list="lista_ubicaciones" placeholder="Selecciona o escribe ubicación" required>
                            <datalist id="lista_ubicaciones">
                                <?php 
                                if (!empty($ubicaciones)) {
                                    foreach ($ubicaciones as $ubi) {
                                        echo "<option value=\"" . htmlspecialchars($ubi) . "\">";
                                    }
                                }
                                ?>
                            </datalist>
                        </div>
                    </div>
                    
                    <div class="split-side">
                        <label for="foto_evidencia" style="margin-bottom: 8px;">Foto de Evidencia (Obligatorio):</label>
                        <div class="file-upload-wrapper">
                            <label for="foto_evidencia" class="file-upload-label">
                                <i class="fas fa-camera upload-icon"></i>
                                <span class="upload-text">Tomar o seleccionar foto</span>
                                <span id="file-name-preview" class="file-name-preview">Sin archivo</span>
                            </label>
                            <input type="file" id="foto_evidencia" name="foto_evidencia" accept="image/*" capture="environment" required>
                        </div>
                    </div>
                </div>
            </div>

            <div class="action-footer">
                <input type="submit" value="Registrar Equipo" class="btn-submit">
                <button type="button" onclick="window.location.href='consultar_inventario.php';" class="btn-accion"><i class="fas fa-search"></i> Consultar</button>
                <button type="button" onclick="window.location.href='dashboard.php';" class="btn-accion"><i class="fas fa-home"></i> Menú</button>
            </div>
        </form>
        <p class="logout"><a href="logout.php">Cerrar sesión</a></p>
    </div>
</div>

<script>
// Lógica dinámica según el Tipo de Bien seleccionado
    document.getElementById('id_tipo_bien').addEventListener('change', function() {
        let textoSeleccionado = this.options[this.selectedIndex].text.toUpperCase();
        
        // Elementos de la interfaz
        let contenedorPerifericos = document.getElementById('contenedor_perifericos');
        let checkboxTeclado = document.getElementById('incluye_teclado');
        let checkboxMouse = document.getElementById('incluye_mouse');
        
        let inputMarca = document.getElementById('marca');
        let inputModelo = document.getElementById('modelo');
        
        // ---------------------------------------------------------
        // 1. LÓGICA PARA PERIFÉRICOS (Solo para CPU o Computadoras)
        // ---------------------------------------------------------
        if(textoSeleccionado.includes('CPU') || textoSeleccionado.includes('COMPUTADORA') || textoSeleccionado.includes('ALL IN ONE') || textoSeleccionado.includes('PC ESCRITORIO') || textoSeleccionado === 'PC') {
            contenedorPerifericos.style.display = 'block';
        } else {
            contenedorPerifericos.style.display = 'none';
            checkboxTeclado.checked = false;
            checkboxMouse.checked = false;
        }

        // ---------------------------------------------------------
        // 2. LÓGICA PARA MOBILIARIO (Deshabilitar Marca y Modelo)
        // ---------------------------------------------------------
        if(textoSeleccionado.includes('SILLA') || (textoSeleccionado.includes('ESCRITORIO') && !textoSeleccionado.includes('PC')) || textoSeleccionado.includes('MUEBLE') || textoSeleccionado.includes('ARCHIVERO')) {
            // Hacemos que sean de solo lectura (para que sí se envíen por POST)
            inputMarca.readOnly = true;
            inputModelo.readOnly = true;
            
            // Quitamos la restricción de obligatorio
            inputMarca.required = false;
            inputModelo.required = false;
            
            // Rellenamos automáticamente con N/A
            inputMarca.value = 'N/A';
            inputModelo.value = 'N/A';
            
            // Cambiamos el fondo a un gris para indicar que están bloqueados
            inputMarca.style.backgroundColor = '#e2e8f0'; 
            inputModelo.style.backgroundColor = '#e2e8f0';
            inputMarca.style.cursor = 'not-allowed';
            inputModelo.style.cursor = 'not-allowed';

        } else {
            // Restauramos a la normalidad si es un equipo tecnológico (Ej: Monitor, CPU, Impresora)
            inputMarca.readOnly = false;
            inputModelo.readOnly = false;
            
            inputMarca.required = true;
            inputModelo.required = true;
            
            inputMarca.style.backgroundColor = '#f8fafc';
            inputModelo.style.backgroundColor = '#f8fafc';
            inputMarca.style.cursor = 'text';
            inputModelo.style.cursor = 'text';
            
            // Limpiamos los campos solo si tenían el 'N/A' automático
            if(inputMarca.value === 'N/A') inputMarca.value = '';
            if(inputModelo.value === 'N/A') inputModelo.value = '';
        }
    });

    // Actualizar nombre de archivo seleccionado en el dropzone
    document.getElementById('foto_evidencia').addEventListener('change', function() {
        let label = this.closest('.file-upload-wrapper').querySelector('.file-upload-label');
        let preview = document.getElementById('file-name-preview');
        if (this.files && this.files.length > 0) {
            preview.textContent = this.files[0].name;
            label.classList.add('file-selected');
        } else {
            preview.textContent = 'Sin archivo';
            label.classList.remove('file-selected');
        }
    });

    // Lógica de Envío del Formulario
    document.getElementById('formInventario').addEventListener('submit', function(e) {
        e.preventDefault();
        
        let datos = new FormData(this);
        
        Swal.fire({
            title: 'Registrando...', didOpen: () => Swal.showLoading()
        });

        fetch('guardar_inventario.php', { method: 'POST', body: datos })
        .then(response => response.json())
        .then(data => {
            if(data.success) {
                Swal.fire({ icon: 'success', title: '¡Registrado!', text: data.message, confirmButtonColor: '#721538' })
                .then(() => {
                    document.getElementById('formInventario').reset();
                    // Restablecer vista previa de archivo
                    let label = document.querySelector('.file-upload-label');
                    if (label) label.classList.remove('file-selected');
                    let preview = document.getElementById('file-name-preview');
                    if (preview) preview.textContent = 'Sin archivo';
                });
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: data.message, confirmButtonColor: '#721538' });
            }
        })
        .catch(error => {
            console.error(error);
            Swal.fire({ icon: 'error', title: 'Error', text: 'Error de comunicación con guardar_inventario.php', confirmButtonColor: '#721538' });
        });
    });
</script>
</body>
</html>