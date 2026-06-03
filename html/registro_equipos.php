<?php
require_once 'config.php';
session_start();

if (!isset($_SESSION['usuario'])) {
    header("Location: index.php");
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
    <title>Registro de Equipos</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/style.css">
    <script src="js/sweetalert2.all.min.js"></script>
    
    <style>
        /* --- ESTILOS CORREGIDOS (SCROLL SEGURO & RESPONSIVE) --- */
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background-color: #d6d1ca; 
            margin: 0; 
            padding: 0; 
            min-height: 100vh; 
        }
        
        .center-container { 
            /* IMPORTANTE: Estos estilos permiten el scroll vertical sin cortar el contenido */
            display: block !important;
            height: auto !important;
            min-height: auto !important;
            
            width: 95%; 
            max-width: 800px; 
            margin: 40px auto; 
            padding-bottom: 40px; 
            box-sizing: border-box;
        }
        
        .card { 
            background: #fff; 
            padding: 30px; 
            border-radius: 12px; 
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1); 
            width: 100%;
            box-sizing: border-box;
        }
        
        h2 { color: #721538; text-align: center; margin-bottom: 25px; margin-top: 0; }
        h4 { margin-top: 0; color: #721538; margin-bottom: 15px; border-bottom: 1px solid #ddd; padding-bottom: 10px; }
        
        /* --- FORMULARIO --- */
        .section-box {
            background: #f9f9f9; 
            padding: 20px; 
            border-radius: 8px; 
            margin-bottom: 20px; 
            border: 1px solid #eee;
        }

        label { display: block; margin-bottom: 6px; color: #555; font-weight: bold; font-size: 0.9em; }
        
        input[type="text"], select { 
            width: 100%; 
            padding: 12px; 
            margin-bottom: 15px; 
            border: 1px solid #ccc; 
            border-radius: 6px; 
            box-sizing: border-box; 
            font-size: 16px; 
            background-color: white;
        }

        /* GRID RESPONSIVE */
        .form-row {
            display: flex;
            gap: 15px;
            margin-bottom: 5px;
        }
        
        .form-col {
            flex: 1; 
        }

        /* --- BOTONES --- */
        button[type="submit"] { 
            width: 100%; 
            padding: 15px; 
            background-color: #721538; 
            color: white; 
            border: none; 
            border-radius: 8px; 
            cursor: pointer; 
            font-size: 18px; 
            font-weight: bold; 
            transition: background-color 0.3s; 
            margin-top: 10px; 
        }
        button[type="submit"]:hover { background-color: #5d112d; }

        .button-group { display: flex; gap: 10px; margin-top: 25px; }
        .btn-accion { 
            padding: 12px; 
            background-color: #555; 
            color: white; 
            border: none; 
            border-radius: 6px; 
            cursor: pointer; 
            flex-grow: 1; 
            text-align: center; 
            font-size: 15px; 
            transition: 0.3s; 
        }
        .btn-accion:hover { background-color: #333; }
        .logout { text-align: center; margin-top: 20px; }
        .logout a { color: #721538; text-decoration: none; font-weight: bold; }

        /* MÓVIL */
        @media (max-width: 768px) {
            .card { padding: 20px; }
            .form-row { flex-direction: column; gap: 0; }
            input[type="text"], select { margin-bottom: 12px; }
            .button-group { flex-direction: column; }
        }
    </style>
</head>
<body>

<div class="center-container">
    <div class="card">
        <h2>🖥️ Registro de Nuevo Equipo</h2>

        <form id="formRegistroEquipo">
            
            <!-- SECCIÓN 1: UBICACIÓN Y ASIGNACIÓN -->
            <div class="section-box">
                <h4>1. Ubicación y Asignación</h4>
                
                <!-- CAMPOS DE SECRETARÍA Y DIRECCIÓN (LISTAS DEPENDIENTES) -->
                <div class="form-row">
                    <div class="form-col">
                        <label for="secretaria">Secretaría / Dependencia:</label>
                        <select id="secretaria" name="secretaria" required onchange="cargarDirecciones()">
                            <option value="">-- Selecciona una opción --</option>
                            <!-- Se llena automáticamente con JS -->
                        </select>
                    </div>
                    <div class="form-col">
                        <label for="direccion">Dirección / Área:</label>
                        <select id="direccion" name="direccion" required disabled>
                            <option value="">-- Esperando Secretaría --</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-col">
                        <label>Usuario de Dominio:</label>
                        <input type="text" name="usuarioDominio" placeholder="ej: juan.perez" required>
                    </div>
                    <div class="form-col">
                        <label>Usuario del Equipo (Real):</label>
                        <input type="text" name="usuariosEquipo" placeholder="Nombre completo" required>
                    </div>
                </div>
            </div>

            <!-- SECCIÓN 2: DETALLES TÉCNICOS -->
            <div class="section-box">
                <h4>2. Detalles Técnicos</h4>
                
                <div class="form-row">
                    <div class="form-col">
                        <label>Tipo:</label>
                        <select name="tipoequipo" required>
                            <option value="">-- Selecciona --</option>
                            <option value="PC">PC Escritorio</option>
                            <option value="Laptop">Laptop</option>
                            <option value="AiO">All in One</option>
                            <option value="Servidor">Servidor</option>
                            <option value="Impresora">Impresora</option>
                        </select>
                    </div>
                    <div class="form-col">
                        <label>Marca y Modelo:</label>
                        <input type="text" name="marca_modelo" placeholder="Ej: Dell Optiplex 3080" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-col">
                        <label>Procesador:</label>
                        <input type="text" name="procesador" placeholder="Ej: Intel i5 10ma Gen" required>
                    </div>
                    <div class="form-col">
                        <label>RAM:</label>
                        <input type="text" name="ram" placeholder="Ej: 8GB DDR4" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-col">
                        <label>Disco Duro:</label>
                        <input type="text" name="tipodisco_capa" placeholder="Ej: SSD 256GB" required>
                    </div>
                    <div class="form-col">
                        <label>Sistema Operativo:</label>
                        <input type="text" name="sistemaOperativo" placeholder="Ej: Windows 10 Pro" required>
                    </div>
                </div>

                <div class="form-col">
                    <label>Nivel Acceso:</label>
                    <select name="nivelAccesoEquipo" required>
                        <option value="Usuario">Usuario Estándar</option>
                        <option value="Administrador">Administrador</option>
                    </select>
                </div>
            </div>

            <!-- SECCIÓN 3: RED E INVENTARIO -->
            <div class="section-box">
                <h4>3. Identificación</h4>
                <div class="form-row">
                    <div class="form-col">
                        <label>No. Inventario:</label>
                        <input type="text" name="numInventario" placeholder="Etiqueta Oficial" required>
                    </div>
                    <div class="form-col">
                        <label>Dirección IP:</label>
                        <input type="text" name="direccionIP" placeholder="Ej: 192.168.1.50" required>
                    </div>
                </div>
            </div>

            <button type="submit">Guardar Equipo</button>
        </form>

        <div class="button-group">
            <button onclick="window.location.href='consultar_equipos.php';" class="btn-accion">Consultar Equipos</button>
            <button onclick="window.location.href='dashboard.php';" class="btn-accion">Menú Principal</button>
        </div>

        <div class="logout"><a href="logout.php">Cerrar sesión</a></div>
    </div>
</div>

<!-- SCRIPT: Lógica de Listas Dependientes y Envío -->
<script>
    // --- CATÁLOGO OFICIAL DEL AYUNTAMIENTO ---
    const catalogo = {
        "Presidencia Municipal": [
            "Secretaría Presidencial",
            "Secretaría Particular",
            "Secretaría de Relaciones Públicas",
            "Secretaría de Giras, Logística y Eventos especiales",
            "Secretaría de Evaluación del Desempeño y Calidad del Servicio",
            "Secretaría de Asesores Técnicos",
            "Unidad de Vinculación para la Transparencia y Acceso a la información Pública"
        ],
        "Secretaría General del Ayuntamiento": [
            "Secretaría General del Ayuntamiento",
            "Dirección de Gobierno Municipal",
            "Dirección de Transporte Público",
            "Dirección de Registro Civil",
            "Dirección del Centro de Retención Municipal",
            "Dirección General de Archivo Municipal",
            "Unidad de Asuntos Religiosos",
            "Unidad de Atención al Migrante",
            "Unidad Especializada de Derechos Humanos",
            "Dirección de Asuntos Nacionales e Internacionales",
            "Secretaría Ejecutiva del SIPINNA",
            "Unidad Técnica Jurídica",
            "Unidad de Estadística Poblacional"
        ],
        "Tesorería Municipal": [
            "Tesorería Municipal",
            "Dirección de Finanzas",
            "Dirección de Ingresos",
            "Dirección de Egresos",
            "Dirección de Cobranza y Fiscalización",
            "Dirección de Catastro"
        ],
        "Órgano Interno de Control": [
            "Órgano Interno de Control",
            "Dirección de Normatividad, Control y Evaluación",
            "Dirección de Auditoría Financiera",
            "Dirección de Investigación Administrativa y Responsabilidades",
            "Dirección de Substanciadora, Consultiva y de Análisis Jurídico"
        ],
        "Secretaría de Seguridad Ciudadana Municipal": [
            "Subsecretaría de Seguridad Ciudadana Municipal",
            "Dirección de Policía Preventiva",
            "Dirección de Tránsito Municipal",
            "Dirección de Participación Ciudadana y Prevención del Delito",
            "Academia de Policía",
            "Dirección de Asuntos Internos",
            "Dirección de Policía Turística",
            "Dirección de Policía de Tránsito y Vialidad",
            "Dirección de Planeación y Administración",
            "Dirección de Jurídica",
            "Dirección de Psicología Policial",
            "Subdirección de Comunicación Social"
        ],
        "Oficialía Mayor": [
            "Oficialía Mayor",
            "Dirección de Recursos Humanos",
            "Dirección de Servicios Generales",
            "Dirección de Adquisiciones y Licitaciones",
            "Dirección de Medios de Comunicación Municipales y Difunsión",
            "Unidad de Parque Vehicular",
            "Dirección de Nuevas Tecnologías de la Información y Comunicaciones",
            "Dirección de Imagen Institucional",
            "Dirección de Capacitación",
            "Unidad de Inventario y Almacén"
        ],
        "Secretaría de Justicia Social y Participación Ciudadana": [
            "Secretaría de Justicia Social y Participación Ciudadana",
            "Dirección de Participación Ciudadana",
            "Dirección de Educación, Desarrollo Humano y Bibliotecas",
            "Unidad de Igualdad de Género",
            "Unidad de Asuntos Indígenas",
            "Unidad de Atención a Personas con Discapacidad",
            "Dirección de Diversidad Sexual"
        ],
        "Secretaría de Ordenamiento Territorial": [
            "Secretaría de Ordenamiento Territorial",
            "Dirección de Desarrollo Urbano y Fisonomía",
            "Dirección de Supervisión de Movilidad",
            "Dirección de Regularización de la Tenencia de la Tierra"
        ],
        "Secretaría de Servicios Públicos Municipales": [
            "Secretaría de Servicios Públicos Municipales",
            "Dirección de Normatividad y Saneamiento Ambiental",
            "Dirección de Alumbrado Público",
            "Dirección de Espacios Públicos",
            "Dirección de Mantenimiento e Higiene Urbana",
            "Coordinación de Panteones y Funerarias"
        ],
        "Secretaría de Protección Civil, Prevención de Riesgos y Bomberos": [
            "Secretaría de Protección Civil, Prevención de Riesgos y Bomberos",
            "Dirección Operativa de Protección Civil",
            "Dirección de Bomberos",
            "Dirección de Meteorología",
            "Dirección de Normatividad y Riesgos"
        ],
        "Secretaría de Desarrollo Económico y de Atracción de Inversiones": [
            "Secretaría de Desarrollo Económico y de Atracción de Inversiones",
            "Dirección de Industria y Comercio",
            "Dirección de Desarrollo Agropecuario y Pesquero",
            "Coordinación de Trabajo y Promoción del Empleo",
            "Comisión de Mejora Regulatoria",
            "Dirección de Mercados Municipales"
        ],
        "Secretaría de Planeación y Evaluación": [
            "Secretaría de Planeación y Evaluación",
            "Dirección de Planeación de Proyectos de Inversión Pública",
            "Dirección de Evaluación y Seguimiento"
        ],
        "Secretaría Jurídica y Consultiva": [
            "Secretaría Jurídica y Consultiva",
            "Dirección de Asuntos Contenciosos"
        ],
        "Secretaría de Justicia Cívica y Convivencia Humana": [
            "Secretaría de Justicia Cívica y Convivencia Humana",
            "Dirección de Jueces Cívicos",
            "Dirección de Centro de Mediación Municipal"
        ],
        "Secretaría de Turismo": [
            "Secretaría de Turismo",
            "Dirección de Mercadotecnía Turística y Promoción",
            "Dirección de Operaciones y Capacitación Turística"
        ],
        "Secretaría de Infraestructura y Obras Públicas": [
            "Secretaría de Infraestructura y Obras Públicas",
            "Dirección de Proyectos de Obra",
            "Dirección de Construcción",
            "Dirección de Maquinaria"
        ],
        "Secretaría de Salud Municipal": [
            "Secretaría de Salud Municipal",
            "Dirección de Salud Física y Mental"
        ],
        "Secretaría de Medio Ambiente Sustentable y Cambio Climático": [
            "Secretaría de Medio Ambiente Sustentable y Cambio Climático",
            "Dirección de Normatividad y Evaluación Ambiental",
            "Dirección de ZOFEMAT (Zona Federal Marítimo Terrestre)",
            "Centro de Bienestar Animal (CENCAAZ)",
            "Dirección de Gestión Ambiental y Cambio Climático"
        ],
        "Organismos Descentralizados": [
            "Organismo de Agua Potable",
            "Organismo de Residuos Sólidos",
            "Organismo de Vivienda"
        ],
        "Instituto Municipal de la Cultura y Las Artes": [
            "Dirección General",
            "Coordinación de Eventos Culturales",
            "Centro Cultural Playa del Carmen"
        ]
    };

    // 1. Inicializar el select de Secretarías
    document.addEventListener('DOMContentLoaded', function() {
        const selectSec = document.getElementById('secretaria');
        // Ordenar alfabéticamente las llaves
        const ordenadas = Object.keys(catalogo).sort();
        
        ordenadas.forEach(sec => {
            const option = document.createElement('option');
            option.value = sec;
            option.textContent = sec;
            selectSec.appendChild(option);
        });
    });

    // 2. Función para cargar direcciones dependientes
    function cargarDirecciones() {
        const sec = document.getElementById('secretaria').value;
        const dir = document.getElementById('direccion');
        
        // Limpiar opciones previas
        dir.innerHTML = '<option value="">-- Selecciona una opción --</option>';
        
        if (sec && catalogo[sec]) {
            dir.disabled = false;
            catalogo[sec].forEach(d => {
                const option = document.createElement('option');
                option.value = d;
                option.textContent = d;
                dir.appendChild(option);
            });
        } else {
            dir.disabled = true;
            dir.innerHTML = '<option value="">-- Esperando Secretaría --</option>';
        }
    }

    // 3. Manejo del Envío (AJAX)
    document.getElementById('formRegistroEquipo').addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Validación visual
        if(document.getElementById('direccion').value === "") {
            Swal.fire({icon: 'warning', text: 'Por favor selecciona una Dirección / Área.'});
            return;
        }

        const datos = new FormData(this);
        const submitBtn = this.querySelector('button[type="submit"]');
        submitBtn.disabled = true; // Evitar doble click

        fetch('guardar_equipo.php', { method: 'POST', body: datos })
        .then(r => r.json())
        .then(data => {
            submitBtn.disabled = false;
            if(data.success) {
                Swal.fire({ 
                    icon: 'success', 
                    title: '¡Guardado!', 
                    text: data.message, 
                    confirmButtonColor: '#721538' 
                }).then(() => {
                    // Resetear formulario y selects
                    document.getElementById('formRegistroEquipo').reset();
                    document.getElementById('direccion').innerHTML = '<option value="">-- Esperando Secretaría --</option>';
                    document.getElementById('direccion').disabled = true;
                });
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: data.message });
            }
        })
        .catch(error => {
            submitBtn.disabled = false;
            console.error(error);
            Swal.fire({ icon: 'error', title: 'Error de red', text: 'No se pudo conectar con el servidor.' });
        });
    });
</script>

</body>
</html>