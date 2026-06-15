<?php
require_once 'session_check.php';
require_once 'config.php';

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
        /* Estilos para el autocompletar */
        .sugerencias-box {
            position: absolute;
            background: white;
            border: 1px solid #ccc;
            border-top: none;
            z-index: 1000;
            width: 100%; /* Del ancho del input */
            max-height: 200px;
            overflow-y: auto;
            display: none; /* Oculto por defecto */
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            border-radius: 0 0 6px 6px;
        }

        .sugerencia-item {
            padding: 10px;
            cursor: pointer;
            border-bottom: 1px solid #eee;
            font-size: 14px;
        }

        .sugerencia-item:hover {
            background-color: #f1f1f1;
            color: #721538;
            font-weight: bold;
        }

        /* --- ESTILOS GENERALES --- */
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background-color: #d6d1ca; 
            margin: 0; 
            padding: 0; 
            min-height: 100vh; 
        }
        
        .center-container { 
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
        
        input[type="text"], select, textarea { 
            width: 100%; 
            padding: 12px; 
            margin-bottom: 15px; 
            border: 1px solid #ccc; 
            border-radius: 6px; 
            box-sizing: border-box; 
            font-size: 16px; 
            background-color: white;
            font-family: inherit;
        }
        
        textarea {
            resize: vertical; /* Permite estirar hacia abajo */
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
            input[type="text"], select, textarea { margin-bottom: 12px; }
            .button-group { flex-direction: column; }
        }
    </style>
</head>
<body>

<div class="center-container">
    <div class="card">
        <h2>🖥️ Registro de Nuevo Equipo</h2>

        <form id="formRegistroEquipo">
            
            <div class="section-box">
                <h4>1. Ubicación y Asignación</h4>
                
                <div class="form-row">
                    <div class="form-col">
                        <label for="secretaria">Secretaría / Dependencia:</label>
                        <select id="secretaria" name="secretaria" required onchange="cargarDirecciones()">
                            <option value="">-- Selecciona una opción --</option>
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
                        <input type="text" name="usuarioDominio" placeholder="Ej: david.olan" required>
                    </div>
                    <div class="form-col">
                        <label>Usuario del Equipo (Real):</label>
                        <input type="text" name="usuariosEquipo" placeholder="Ej: Jose David Olan Peraza" required>
                    </div>
                </div>
            </div>

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
                        <select name="sistemaOperativo" required>
                            <option value="" disabled selected>Selecciona una opción</option>
                            <option value="No enciende">Para Baja</option>
                            <option value="Windows 10 Home">Windows 10 Home</option>
                            <option value="Windows 10 Pro">Windows 10 Pro</option>
                            <option value="Windows 11 Home">Windows 11 Home</option>
                            <option value="Windows 11 Pro">Windows 11 Pro</option>
                            <option value="macOS">macOS</option>
                            <option value="Windows MiniOS">Windows MiniOS</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-col" style="position: relative;"> 
                        <label for="busqueda_office">Cuenta de Office Asignada:</label>
                        <input type="text" id="busqueda_office" placeholder="Escribe para buscar correo..." autocomplete="off">
                        <input type="hidden" name="id_cuenta_office" id="id_cuenta_office">
                        <div id="lista_sugerencias" class="sugerencias-box"></div>
                    </div>
                    <div class="form-col">
                        <label>Nivel Acceso:</label>
                        <select name="nivelAccesoEquipo" required>
                            <option value="Usuario">Usuario Estándar</option>
                            <option value="Administrador">Administrador</option>
                        </select>
                    </div>
                </div>
            </div>

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

            <div class="section-box">
                <h4>4. Estado y Observaciones</h4>
                
                <div class="form-row">
                    <div class="form-col">
                        <label>Antivirus ESET:</label>
                        <select name="antivirus_eset">
                            <option value="No">No Instalado</option>
                            <option value="Si">Instalado</option>
                            <option value="N/A">N/A (Mac/Otro)</option>
                        </select>
                    </div>
                    <div class="form-col">
                        <label>Estatus del Equipo:</label>
                        <select name="estatus_equipo" required>
                            <option value="Operativo" selected>Operativo</option>
                            <option value="En Revisión">Para revisión</option>
                            <option value="Dañado">Dañado / Para Baja</option>
                            <option value="Baja">Baja Definitiva</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-col">
                        <label>Observaciones Adicionales:</label>
                        <textarea name="observaciones" rows="3" placeholder="Detalles extra, fallas reportadas, software especial instalado, etc."></textarea>
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

<script>
    // Variables globales
    let listaAreas = []; 
    let cuentasGlobales = [];

    // --- 1. INICIALIZACIÓN ---
    document.addEventListener('DOMContentLoaded', function() {
        cargarDatosDesdeBD();    // Cargar Secretarías/Direcciones
        cargarCuentasOffice();   // Cargar Correos Office
    });

    // --- 2. CARGA DE CATÁLOGOS ---
    function cargarDatosDesdeBD() {
        fetch('obtener_areas.php')
        .then(response => response.json())
        .then(json => {
            if (json.success) {
                listaAreas = json.data;
                llenarSelectSecretarias();
            } else {
                Swal.fire('Error', 'No se pudieron cargar las áreas: ' + json.message, 'error');
            }
        })
        .catch(err => {
            console.error(err);
            Swal.fire('Error', 'Error de conexión al cargar el catálogo.', 'error');
        });
    }

    function cargarCuentasOffice() {
        fetch('obtener_cuentas_office.php')
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                cuentasGlobales = data.data; 
            }
        })
        .catch(err => console.error("Error cargando cuentas office:", err));
    }

    // --- 3. LÓGICA DE ÁREAS (SECRETARÍA -> DIRECCIÓN) ---
    function llenarSelectSecretarias() {
        const selectSec = document.getElementById('secretaria');
        selectSec.innerHTML = '<option value="">-- Selecciona una opción --</option>';

        listaAreas.forEach(item => {
            const option = document.createElement('option');
            option.value = item.nombre_secretaria; // Usamos NOMBRE
            option.textContent = item.nombre_secretaria;
            selectSec.appendChild(option);
        });
    }

    // Se llama desde el HTML onchange="cargarDirecciones()" (necesitamos hacerla global)
    window.cargarDirecciones = function() {
        const nombreSecretariaSeleccionada = document.getElementById('secretaria').value;
        const selectDir = document.getElementById('direccion');
        
        selectDir.innerHTML = '<option value="">-- Selecciona una opción --</option>';
        
        const secretariaEncontrada = listaAreas.find(area => area.nombre_secretaria === nombreSecretariaSeleccionada);

        if (secretariaEncontrada && secretariaEncontrada.direcciones.length > 0) {
            selectDir.disabled = false;
            secretariaEncontrada.direcciones.forEach(dir => {
                const option = document.createElement('option');
                option.value = dir.nombre_direcciones; // Usamos NOMBRE
                option.textContent = dir.nombre_direcciones;
                selectDir.appendChild(option);
            });
        } else {
            selectDir.disabled = true;
            if (nombreSecretariaSeleccionada) {
                selectDir.innerHTML = '<option value="">-- Sin direcciones registradas --</option>';
            } else {
                selectDir.innerHTML = '<option value="">-- Esperando Secretaría --</option>';
            }
        }
    }

    // --- 4. LÓGICA DE BUSCADOR OFFICE (AUTOCOMPLETAR) ---
    const inputBusqueda = document.getElementById('busqueda_office');
    const inputHiddenID = document.getElementById('id_cuenta_office');
    const cajaSugerencias = document.getElementById('lista_sugerencias');

    inputBusqueda.addEventListener('input', function() {
        const texto = this.value.toLowerCase();
        cajaSugerencias.innerHTML = '';
        
        if (texto === '') {
            cajaSugerencias.style.display = 'none';
            inputHiddenID.value = ''; 
            return;
        }

        const coincidencias = cuentasGlobales.filter(cuenta => 
            cuenta.Correo.toLowerCase().includes(texto)
        );

        if (coincidencias.length > 0) {
            cajaSugerencias.style.display = 'block';
            
            coincidencias.forEach(c => {
                const div = document.createElement('div');
                div.classList.add('sugerencia-item');
                div.textContent = `${c.Correo} [${c.Conectados || 0}]`;
                
                div.addEventListener('click', function() {
                    inputBusqueda.value = c.Correo; 
                    inputHiddenID.value = c.id;     
                    cajaSugerencias.style.display = 'none'; 
                });
                
                cajaSugerencias.appendChild(div);
            });
        } else {
            cajaSugerencias.style.display = 'none';
        }
    });

    document.addEventListener('click', function(e) {
        if (e.target !== inputBusqueda) {
            cajaSugerencias.style.display = 'none';
        }
    });

    // --- 5. ENVÍO DEL FORMULARIO ---
    document.getElementById('formRegistroEquipo').addEventListener('submit', function(e) {
        e.preventDefault();
        
        if(document.getElementById('direccion').value === "") {
            Swal.fire({icon: 'warning', text: 'Por favor selecciona una Dirección / Área.'});
            return;
        }

        const datos = new FormData(this);
        const submitBtn = this.querySelector('button[type="submit"]');
        submitBtn.disabled = true; 

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
                    document.getElementById('formRegistroEquipo').reset();
                    document.getElementById('direccion').innerHTML = '<option value="">-- Esperando Secretaría --</option>';
                    document.getElementById('direccion').disabled = true;
                    // Limpiar también el hidden de office
                    document.getElementById('id_cuenta_office').value = "";
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