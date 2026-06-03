<?php
require_once 'config.php';
session_start();

if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}

if ($_SESSION['rol'] === 'redes') {
    // Si es redes, lo regresamos al dashboard con un mensaje de error opcional
    header("Location: dashboard.php?error=acceso_denegado");
    exit();
}

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Registro AD</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> 
    <link rel="stylesheet" href="css/style.css">
    <script src="js/sweetalert2.all.min.js"></script>
    <style>
        /* --- ESTILOS RESPONSIVE Y ANCHOS --- */
        body {
            background-color: #d6d1ca;
            font-family: 'Segoe UI', sans-serif;
            margin: 0;
            padding: 0;
            min-height: 100vh;
            /* CRÍTICO: Forzamos bloque para que el margin:auto funcione */
            display: block !important; 
        }
        
        .center-container {
                 /* IMPORTANTE: Estos estilos permiten el scroll vertical sin cortar el contenido */       
            height: auto !important;
            min-height: auto !important;
            width: 95%;
            max-width: 850px; /* Ancho cómodo para 2 columnas */
            /* El truco del centrado: 40px arriba/abajo, AUTO a los lados */
            margin: 40px auto !important; 
            background: transparent;    
            box-sizing: border-box;
        }
        
        .card {
            background-color: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            padding: 40px;
            box-sizing: border-box;
        }

        h2 { color: #721538; text-align: center; margin-bottom: 30px; margin-top: 0; }

        /* Estilos de Formulario */
        label { display: block; margin-bottom: 5px; color: #555; font-weight: bold; font-size: 0.9em; }
        
        input[type="text"], input[type="date"], select {
            width: 100%;
            padding: 12px;
            margin-bottom: 15px; /* Espacio entre filas */
            border: 1px solid #ccc;
            border-radius: 6px;
            box-sizing: border-box;
            font-size: 16px;
            background-color: #fcfcfc;
        }

        /* --- SISTEMA DE GRILLA (2 COLUMNAS) --- */
        .form-row {
            display: flex;
            gap: 20px; /* Espacio entre columna izq y der */
        }
        .form-col {
            flex: 1; /* Ocupa el 50% cada una */
        }

        /* Botón Guardar */
        input[type="submit"] {
            width: 100%;
            padding: 15px;
            background-color: #721538;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 18px;
            font-weight: bold;
            cursor: pointer;
            transition: background 0.3s;
            margin-top: 15px;
        }
        input[type="submit"]:hover { background-color: #5d112d; }

        /* Botones de Navegación */
        .button-group { display: flex; gap: 15px; margin-top: 25px; }
        .btn-accion {
            flex: 1; padding: 12px; background-color: #555; color: white;
            border: none; border-radius: 6px; cursor: pointer; text-align: center;
            font-size: 15px; transition: 0.3s; text-decoration: none; font-weight: bold;
        }
        .btn-accion:hover { background-color: #333; }

        .logout { text-align: center; margin-top: 20px; }
        .logout a { color: #721538; text-decoration: none; font-weight: bold; }

        /* MÓVIL: Se vuelve 1 sola columna */
        @media (max-width: 768px) {
            .form-row { flex-direction: column; gap: 0; }
            .card { padding: 20px; }
        }
    </style>
</head>
<body>

<div class="center-container">
    <div class="card">

        <h2>Registro de Usuarios AD</h2>

        <form id="formRegistro">
            
            <div class="form-row">
                <div class="form-col">
                    <label for="secretaria">Secretaría:</label>
                    <select id="secretaria" name="secretaria" required onchange="cargarDirecciones()">
                        <option value="">-- Selecciona --</option>
                    </select>
                </div>
                <div class="form-col">
                    <label for="direccion">Dirección / Área:</label>
                    <select id="direccion" name="direccion" required disabled>
                        <option value="">-- Espera Secretaría --</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-col">
                    <label>Número de Oficio:</label>
                    <input type="text" name="num_oficio" placeholder="Ej: MSOL/..." required>
                </div>
                <div class="form-col">
                    <label>Fecha de Alta:</label>
                    <input type="date" name="fecha_alta" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-col">
                    <label>No. de empleado:</label>
                    <input type="text" name="num_empleado" placeholder="Ej: 88410" required>
                </div>
                    <div class="form-col">
                        <label>Nombres:</label>
                        <input type="text" name="nombres" placeholder="Nombre del usuario" required>
                    </div>
            </div>

            <div class="form-row">
                <div class="form-col">
                    <label>Apellido paterno:</label>
                    <input type="text" name="apellido_paterno" placeholder="Ej: OLPER" required>
                </div>
                <div class="form-col">
                    <label>Apellido materno:</label>
                    <input type="text" name="apellido_materno" placeholder="Ej: MZARLIK" required>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-col">
                    <label>Cuenta de Usuario:</label>
                    <input type="text" name="usuario" placeholder="Ej: jlopez" required>
                </div>
                <div class="form-col">
                    <label>Contraseña:</label>
                    <input type="text" name="contrasena" placeholder="Contraseña temporal" required>
                </div>
            </div>

            <input type="submit" value="Guardar Usuario">
        </form>

        <div class="button-group">
            <button onclick="window.location.href='consultar_usuarios.php';" class="btn-accion">
                📋 Consultar
            </button>
            <button onclick="window.location.href='dashboard.php';" class="btn-accion">
                🏠 Menú Principal
            </button>

</div>

<script>
    // --- CATÁLOGO COMPLETO ---
    const catalogo = {
        "Presidencia Municipal": [
            "Secretaría Presidencial", "Secretaría Particular", "Secretaría de Relaciones Públicas",
            "Secretaría de Giras, Logística y Eventos especiales", "Secretaría de Evaluación del Desempeño y Calidad del Servicio",
            "Secretaría de Asesores Técnicos", "Unidad de Vinculación para la Transparencia y Acceso a la información Pública"
        ],
        "Secretaría General del Ayuntamiento": [
            "Secretaría General del Ayuntamiento", "Dirección de Gobierno Municipal", "Dirección de Transporte Público",
            "Dirección de Registro Civil", "Dirección del Centro de Retención Municipal", "Dirección General de Archivo Municipal",
            "Unidad de Asuntos Religiosos", "Unidad de Atención al Migrante", "Unidad Especializada de Derechos Humanos",
            "Dirección de Asuntos Nacionales e Internacionales", "Secretaría Ejecutiva del SIPINNA", "Unidad Técnica Jurídica",
            "Unidad de Estadística Poblacional"
        ],
        "Tesorería Municipal": [
            "Tesorería Municipal", "Dirección de Finanzas", "Dirección de Ingresos", "Dirección de Egresos",
            "Dirección de Cobranza y Fiscalización", "Dirección de Catastro", "Dirección de Contabilidad"
        ],
        "Órgano Interno de Control": [
            "Órgano Interno de Control", "Dirección de Normatividad, Control y Evaluación", "Dirección de Auditoría Financiera",
            "Dirección de Investigación Administrativa y Responsabilidades", "Dirección de Substanciadora, Consultiva y de Análisis Jurídico"
        ],
        "Secretaría de Seguridad Ciudadana Municipal": [
            "Subsecretaría de Seguridad Ciudadana Municipal", "Dirección de Policía Preventiva", "Dirección de Tránsito Municipal",
            "Dirección de Participación Ciudadana y Prevención del Delito", "Academia de Policía", "Dirección de Asuntos Internos",
            "Dirección de Policía Turística", "Dirección de Policía de Tránsito y Vialidad", "Dirección de Planeación y Administración",
            "Dirección de Jurídica", "Dirección de Psicología Policial", "Subdirección de Comunicación Social"
        ],
        "Oficialía Mayor": [
            "Oficialía Mayor", "Dirección de Recursos Humanos", "Dirección de Servicios Generales", "Dirección de Adquisiciones y Licitaciones",
            "Dirección de Medios de Comunicación Municipales y Difunsión", "Unidad de Parque Vehicular", "Dirección de Nuevas Tecnologías de la Información y Comunicaciones",
            "Dirección de Imagen Institucional", "Dirección de Capacitación", "Unidad de Inventario y Almacén"
        ],
        "Secretaría de Justicia Social y Participación Ciudadana": [
            "Secretaría de Justicia Social y Participación Ciudadana", "Dirección de Participación Ciudadana", "Dirección de Educación, Desarrollo Humano y Bibliotecas",
            "Unidad de Igualdad de Género", "Unidad de Asuntos Indígenas", "Unidad de Atención a Personas con Discapacidad", "Dirección de Diversidad Sexual"
        ],
        "Secretaría de Ordenamiento Territorial": [
            "Secretaría de Ordenamiento Territorial", "Dirección de Desarrollo Urbano y Fisonomía", "Dirección de Supervisión de Movilidad",
            "Dirección de Regularización de la Tenencia de la Tierra"
        ],
        "Secretaría de Servicios Públicos Municipales": [
            "Secretaría de Servicios Públicos Municipales", "Dirección de Normatividad y Saneamiento Ambiental", "Dirección de Alumbrado Público",
            "Dirección de Espacios Públicos", "Dirección de Mantenimiento e Higiene Urbana", "Coordinación de Panteones y Funerarias"
        ],
        "Secretaría de Protección Civil, Prevención de Riesgos y Bomberos": [
            "Secretaría de Protección Civil, Prevención de Riesgos y Bomberos", "Dirección Operativa de Protección Civil", "Dirección de Bomberos",
            "Dirección de Meteorología", "Dirección de Normatividad y Riesgos", "Dirección Administrativa"
        ],
        "Secretaría de Desarrollo Económico y de Atracción de Inversiones": [
            "Secretaría de Desarrollo Económico y de Atracción de Inversiones", "Dirección de Industria y Comercio", "Dirección de Desarrollo Agropecuario y Pesquero",
            "Coordinación de Trabajo y Promoción del Empleo", "Comisión de Mejora Regulatoria", "Dirección de Mercados Municipales"
        ],
        "Secretaría de Planeación y Evaluación": [
            "Secretaría de Planeación y Evaluación", "Dirección de Planeación de Proyectos de Inversión Pública", "Dirección de Evaluación y Seguimiento"
        ],
        "Secretaría Jurídica y Consultiva": [
            "Secretaría Jurídica y Consultiva", "Dirección de Asuntos Contenciosos"
        ],
        "Secretaría de Justicia Cívica y Convivencia Humana": [
            "Secretaría de Justicia Cívica y Convivencia Humana", "Dirección de Jueces Cívicos", "Dirección de Centro de Mediación Municipal"
        ],
        "Secretaría de Turismo": [
            "Secretaría de Turismo", "Dirección de Mercadotecnía Turística y Promoción", "Dirección de Operaciones y Capacitación Turística"
        ],
        "Secretaría de Infraestructura y Obras Públicas": [
            "Secretaría de Infraestructura y Obras Públicas", "Dirección de Proyectos de Obra", "Dirección de Construcción", "Dirección de Maquinaria"
        ],
        "Secretaría de Salud Municipal": [
            "Secretaría de Salud Municipal", "Dirección de Salud Física y Mental"
        ],
        "Secretaría de Medio Ambiente Sustentable y Cambio Climático": [
            "Secretaría de Medio Ambiente Sustentable y Cambio Climático", "Dirección de Normatividad y Evaluación Ambiental",
            "Dirección de ZOFEMAT (Zona Federal Marítimo Terrestre)", "Centro de Bienestar Animal (CENCAAZ)", "Dirección de Gestión Ambiental y Cambio Climático"
        ],
        "Organismos Descentralizados": [
            "Organismo de Agua Potable", "Organismo de Residuos Sólidos", "Organismo de Vivienda"
        ],
        "Instituto Municipal de la Cultura y Las Artes": [
            "Dirección General", "Coordinación de Eventos Culturales", "Centro Cultural Playa del Carmen"
        ]
    };

    // 1. Llenar el Select de Secretarías al iniciar
    document.addEventListener('DOMContentLoaded', function() {
        const selectSec = document.getElementById('secretaria');
        const ordenadas = Object.keys(catalogo).sort();
        ordenadas.forEach(sec => {
            const option = document.createElement('option');
            option.value = sec; option.textContent = sec;
            selectSec.appendChild(option);
        });
    });

    // 2. Cargar Direcciones dependientes
    function cargarDirecciones() {
        const sec = document.getElementById('secretaria').value;
        const dir = document.getElementById('direccion');
        
        dir.innerHTML = '<option value="">-- Selecciona una opción --</option>';
        
        if (sec && catalogo[sec]) {
            dir.disabled = false;
            catalogo[sec].forEach(d => {
                const option = document.createElement('option');
                option.value = d; option.textContent = d;
                dir.appendChild(option);
            });
        } else {
            dir.disabled = true;
            dir.innerHTML = '<option value="">-- Esperando Secretaría --</option>';
        }
    }

    // 3. Envío del Formulario
    document.getElementById('formRegistro').addEventListener('submit', function(e) {
        e.preventDefault();
        
        if(document.getElementById('direccion').value === "") {
            Swal.fire({icon: 'warning', text: 'Por favor selecciona una Dirección.'});
            return;
        }

        let datos = new FormData(this);
        fetch('guardar_registro.php', { method: 'POST', body: datos })
        .then(response => response.json())
        .then(data => {
            if(data.success) {
                Swal.fire({ icon: 'success', title: '¡Guardado!', text: data.message, confirmButtonColor: '#721538' })
                .then(() => {
                    document.getElementById('formRegistro').reset();
                    document.getElementById('direccion').innerHTML = '<option value="">-- Primero selecciona Secretaría --</option>';
                    document.getElementById('direccion').disabled = true;
                });
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: data.message, confirmButtonColor: '#721538' });
            }
        });
    });
</script>

</body>
</html>