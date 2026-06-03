<?php 
require_once 'session_check.php';
require_once 'config.php'; 
session_start();
if (!isset($_SESSION['usuario'])) { header("Location: index.php"); exit(); }
$rol_usuario = $_SESSION['rol'] ?? 'tecnico'; 
$esAdmin = ($rol_usuario === 'admin' || $rol_usuario === 'masterweb'); 
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Direcciones</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/style.css"> 
    <script src="js/sweetalert2.all.min.js"></script>
    <style>
        body { background-color: #f4f6f9; font-family: 'Segoe UI', sans-serif; margin: 0; }
        .main-container { padding: 30px; max-width: 1200px; margin: 0 auto; }
        
        /* HEADER Y TOOLBAR */
        .toolbar { 
            display: flex; justify-content: space-between; align-items: center; 
            margin-bottom: 25px; gap: 15px; flex-wrap: wrap;
        }
        .search-box { flex-grow: 1; max-width: 500px; position: relative; }
        .search-input { 
            width: 100%; height: 45px; padding-left: 15px; border: 1px solid #ddd; 
            border-radius: 8px; font-size: 16px; box-shadow: 0 2px 4px rgba(0,0,0,0.02);
        }

        .btn-add { background: #27ae60; color: white; padding: 10px 20px; border-radius: 6px; border: none; font-weight: 600; cursor: pointer; text-decoration: none;}
        .btn-add:hover { background: #219150; }

        /* ESTILOS DEL ACORDEÓN */
        .accordion-container { display: flex; flex-direction: column; gap: 10px; }

        .accordion-item {
            background: white; border: 1px solid #e0e0e0; border-radius: 4px; 
            overflow: hidden; transition: box-shadow 0.2s;
        }
        .accordion-item:hover { box-shadow: 0 2px 8px rgba(0,0,0,0.05); }

        /* Cabecera (Secretaría) */
        .accordion-header {
            padding: 18px 20px; cursor: pointer; display: flex; 
            justify-content: space-between; align-items: center;
            background: white; border-bottom: 1px solid transparent; 
            transition: background 0.2s;
        }
        .accordion-header:hover { background: #f9f9f9; }
        .accordion-header h3 { margin: 0; font-size: 16px; color: #333; font-weight: 600; }
        
        .icon-toggle { 
            font-size: 1.2em; color: #721538; transition: transform 0.3s ease; 
        }
        
        /* Cuerpo (Direcciones) */
        .accordion-body {
            display: none; background: #fafafa; padding: 0; 
            border-top: 1px solid #eee;
        }
        
        /* Tabla interna de direcciones */
        .inner-table { width: 100%; border-collapse: collapse; }
        .inner-table td { 
            padding: 12px 25px; border-bottom: 1px solid #eee; 
            color: #555; font-size: 0.95em; 
        }
        .inner-table tr:last-child td { border-bottom: none; }
        .inner-table tr:hover { background: #f0f0f0; }

        /* Estado Activo */
        .accordion-item.active .accordion-header { border-bottom: 1px solid #e0e0e0; background: #fff; }
        .accordion-item.active .accordion-body { display: block; }
        .accordion-item.active .icon-toggle { transform: rotate(180deg); }

        /* Botón editar pequeño */
        .btn-mini-edit { 
            background: #3498db; color: white; border: none; padding: 5px 10px; 
            border-radius: 4px; font-size: 0.8em; cursor: pointer; float: right;
        }
        
        /* Modales */
        .swal-form-group { margin-bottom: 15px; text-align: left; }
        .swal-label { display: block; margin-bottom: 5px; font-weight: bold; font-size: 0.9em; }
        .swal-select, .swal-input { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
    </style>
</head>
<body>

<div class="main-container">
    <div class="toolbar">
        <div class="search-box">
            <input type="text" id="searchInput" class="search-input" placeholder="Buscar dirección o secretaría...">
        </div>
        <?php if ($esAdmin): ?>
            <button onclick="modalAgregar()" class="btn-add">+ Nueva Dirección</button>
        <?php endif; ?>
    </div>

    <div id="accordion-container" class="accordion-container">
        <div style="text-align:center; padding:20px; color:#888;">Cargando...</div>
    </div>
</div>

<script>
    const esAdmin = <?= $esAdmin ? 'true' : 'false' ?>;
    let datosGlobales = []; 
    let listaSecretarias = []; 

    document.addEventListener('DOMContentLoaded', () => {
        cargarTodo(); 
        
        document.getElementById('searchInput').addEventListener('input', (e) => {
            renderizarAcordeon(e.target.value);
        });
    });

    function cargarTodo() {
        fetch('acciones_direcciones.php?action=get_secretarias')
            .then(r => r.json()).then(data => listaSecretarias = data);

        fetch('acciones_direcciones.php?action=read')
            .then(r => r.json())
            .then(res => {
                datosGlobales = res.data;
                renderizarAcordeon();
            });
    }

    // --- RENDERIZADO DEL ACORDEÓN ---
    function renderizarAcordeon(filtro = '') {
        const contenedor = document.getElementById('accordion-container');
        contenedor.innerHTML = '';
        filtro = filtro.toLowerCase();

        let hayResultados = false;

        datosGlobales.forEach(sec => {
            const nombreSec = sec.nombres.toLowerCase();
            
            // Filtramos las direcciones que coinciden
            let direccionesAMostrar = sec.direcciones.filter(d => 
                d.nombres_direcciones.toLowerCase().includes(filtro)
            );

            // Si hay filtro activo y no coincide ni secretaría ni direcciones, saltamos
            if (filtro && !nombreSec.includes(filtro) && direccionesAMostrar.length === 0) {
                return; 
            }

            // Si el filtro coincide con la Secretaría, mostramos TODAS sus direcciones (aunque no coincidan con el texto)
            if (filtro && nombreSec.includes(filtro)) {
                direccionesAMostrar = sec.direcciones;
            }
            
            // --- AQUÍ APLICAMOS EL ORDENAMIENTO ESPECIAL ---
            direccionesAMostrar.sort((a, b) => {
                const nombreA = a.nombres_direcciones.toLowerCase();
                const nombreB = b.nombres_direcciones.toLowerCase();

                // 1. REGLA DE ORO: "Síndico" siempre va primero
                const esSindicoA = nombreA.includes('sindico') || nombreA.includes('síndico');
                const esSindicoB = nombreB.includes('sindico') || nombreB.includes('síndico');
                
                if (esSindicoA && !esSindicoB) return -1; // A gana
                if (!esSindicoA && esSindicoB) return 1;  // B gana

                // 2. REGLA NUMÉRICA: Extraer números (1°, 10°, 2°)
                // Buscamos si hay números en el nombre
                const numeroA = parseInt((nombreA.match(/\d+/) || [0])[0]);
                const numeroB = parseInt((nombreB.match(/\d+/) || [0])[0]);

                // Si ambos tienen números positivos (ej: "1° Regiduría" vs "10° Regiduría")
                if (numeroA > 0 && numeroB > 0) {
                    return numeroA - numeroB; // Ordenar numéricamente (1, 2, 3... 10)
                }

                // 3. REGLA FINAL: Alfabético normal para el resto
                return nombreA.localeCompare(nombreB);
            });
            // -----------------------------------------------

            hayResultados = true;
            const count = direccionesAMostrar.length;

            let htmlDirecciones = '';
            if (count > 0) {
                htmlDirecciones = '<table class="inner-table">';
                direccionesAMostrar.forEach(dir => {
                    let btn = '';
                    if (esAdmin) {
                        const nombreSafe = dir.nombres_direcciones.replace(/'/g, "&apos;");
                        btn = `<button class="btn-mini-edit" onclick="modalEditar(${dir.id_direcciones}, '${nombreSafe}', ${sec.id_secretaria})">✏️ Editar</button>`;
                    }
                    htmlDirecciones += `<tr>
                        <td>${dir.nombres_direcciones}</td>
                        <td width="80">${btn}</td>
                    </tr>`;
                });
                htmlDirecciones += '</table>';
            } else {
                htmlDirecciones = '<div style="padding:15px; color:#999; font-style:italic;">No hay direcciones registradas.</div>';
            }

            const item = document.createElement('div');
            item.className = 'accordion-item';
            // Abrimos el acordeón si hay filtro activo para ver los resultados
            if (filtro) item.classList.add('active');

            item.innerHTML = `
                <div class="accordion-header" onclick="toggleAcordeon(this)">
                    <h3>${sec.nombres} <small style="color:#999; font-weight:normal; font-size:0.8em;">(${count})</small></h3>
                    <span class="icon-toggle">▼</span>
                </div>
                <div class="accordion-body">
                    ${htmlDirecciones}
                </div>
            `;
            contenedor.appendChild(item);
        });

        if (!hayResultados) {
            contenedor.innerHTML = '<div style="text-align:center; padding:30px; color:#888;">No se encontraron resultados</div>';
        }
    }

    function toggleAcordeon(elementoHeader) {
        const item = elementoHeader.parentElement;
        item.classList.toggle('active');
    }

    function getFormHtml(nombreVal = '', idSecVal = '') {
        let options = '<option value="">-- Selecciona --</option>';
        listaSecretarias.forEach(sec => {
            const selected = sec.id_secretaria == idSecVal ? 'selected' : '';
            options += `<option value="${sec.id_secretaria}" ${selected}>${sec.nombres}</option>`;
        });
        return `
            <div class="swal-form-group"><label class="swal-label">Secretaría:</label><select id="swal-sec" class="swal-select">${options}</select></div>
            <div class="swal-form-group"><label class="swal-label">Nombre Dirección:</label><input id="swal-nom" class="swal-input" value="${nombreVal}"></div>
        `;
    }

    function modalAgregar() {
        Swal.fire({
            title: 'Nueva Dirección', html: getFormHtml(), showCancelButton: true, confirmButtonText: 'Guardar', confirmButtonColor: '#27ae60',
            preConfirm: () => {
                const id_sec = document.getElementById('swal-sec').value;
                const nom = document.getElementById('swal-nom').value;
                if (!id_sec || !nom) return Swal.showValidationMessage('Complete los campos');
                return { id_secretaria: id_sec, nombres_direcciones: nom };
            }
        }).then((r) => { if (r.isConfirmed) enviarDatos('create', r.value); });
    }

    function modalEditar(id, nombre, idSec) {
        Swal.fire({
            title: 'Editar', html: getFormHtml(nombre, idSec), showCancelButton: true, confirmButtonText: 'Actualizar', confirmButtonColor: '#3498db',
            preConfirm: () => {
                const id_s = document.getElementById('swal-sec').value;
                const nom = document.getElementById('swal-nom').value;
                return { id_direcciones: id, id_secretaria: id_s, nombres_direcciones: nom };
            }
        }).then((r) => { if (r.isConfirmed) enviarDatos('update', r.value); });
    }

    function enviarDatos(accion, datos) {
        const fd = new FormData();
        fd.append('action', accion);
        for(let k in datos) fd.append(k, datos[k]);
        
        fetch('acciones_direcciones.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => {
                if(d.success) { Swal.fire('Listo', '', 'success'); cargarTodo(); }
                else Swal.fire('Error', '', 'error');
            });
    }
</script>

</body>
</html>