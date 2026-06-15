<?php
require_once 'session_check.php';
require_once 'config.php';

// 1. INICIAR SESIÓN (Crucial para saber quién es el usuario actual)

header('Content-Type: application/json');
date_default_timezone_set('America/Cancun'); 

$accion = $_REQUEST['accion'] ?? '';
$conn = get_db_connection();

// Capturamos usuario y rol de la sesión actual
$usuario_actual = $_SESSION['usuario'] ?? '';
$rol_actual = $_SESSION['rol'] ?? '';

if (!$conn) {
    echo json_encode(['status' => 'error', 'message' => 'Error de conexión']);
    exit;
}

switch ($accion) {
    
    // ... (TUS CASOS 'usuarios', 'listar', 'guardar', 'eliminar' SE QUEDAN IGUAL) ...
    // Solo copiaremos aquí los casos anteriores resumidos para contexto,
    // pero asegúrate de mantener tu código de 'guardar' corregido que hicimos antes.

    case 'usuarios':
        $usuarios = [];
        $usuarios_lower = [];

        // Obtener únicamente los usuarios de registros_ad (Usuarios SATQ)
        $sql2 = "SELECT TRIM(REPLACE(CONCAT(nombres, ' ', COALESCE(apellido_paterno,''), ' ', COALESCE(apellido_materno,'')), '  ', ' ')) as nombre_completo FROM registros_ad";
        $res2 = $conn->query($sql2);
        if ($res2) {
            while ($row = $res2->fetch_assoc()) {
                $name = trim($row['nombre_completo']);
                $name_lower = strtolower($name);
                if ($name !== '' && !in_array($name_lower, $usuarios_lower)) {
                    $usuarios[] = $name;
                    $usuarios_lower[] = $name_lower;
                }
            }
        }

        // Ordenar alfabéticamente de forma insensible a mayúsculas
        usort($usuarios, 'strcasecmp');

        echo json_encode($usuarios);
        break;

    case 'listar':
        // Los admins ven todo. Los técnicos también suelen ver todo en el calendario para coordinar,
        // pero si quisieras restringir la VISTA del calendario, se haría aquí.
        // Por ahora dejamos que vean todo el calendario.
        $sql = "SELECT id, titulo, fecha_inicio, fecha_fin, tipo_evento, direccion_destino, asignado_a FROM eventos_calendario";
        $result = $conn->query($sql);
        $eventos = [];
        while ($row = $result->fetch_assoc()) {
            $color = '#3788d8'; 
            if ($row['tipo_evento'] == 'mantenimiento') $color = '#f59e0b';
            if ($row['tipo_evento'] == 'antivirus') $color = '#ef4444';
            if ($row['tipo_evento'] == 'office') $color = '#3b82f6';
            if ($row['tipo_evento'] == 'revision') $color = '#10b981';

            $eventos[] = [
                'id' => $row['id'],
                'title' => $row['titulo'],
                'start' => $row['fecha_inicio'],
                'end' => $row['fecha_fin'],
                'backgroundColor' => $color,
                'borderColor' => $color,
                'extendedProps' => [
                    'tipo' => $row['tipo_evento'],
                    'direccion_completa' => $row['direccion_destino'],
                    'asignado_a' => $row['asignado_a']
                ]
            ];
        }
        echo json_encode($eventos);
        break;

    case 'guardar':
    case 'editar':
        // ... (PEGA AQUÍ TU LÓGICA DE GUARDAR CORREGIDA DEL PASO ANTERIOR) ...
        // Para no hacer el código gigante aquí, asumo que mantienes el bloque
        // que corregimos con el date() y strtotime().
        
        // --- RESUMEN DE TU BLOQUE GUARDAR PARA QUE NO SE PIERDA ---
        $titulo = $conn->real_escape_string($_POST['titulo']);
        $inicio = date('Y-m-d H:i:s', strtotime($_POST['start']));
        $fin = date('Y-m-d H:i:s', strtotime($_POST['end']));
        $tipo = $conn->real_escape_string($_POST['tipo']);
        $secretaria = $_POST['secretaria'] ?? '';
        $direccion = $_POST['direccion'] ?? '';
        $ubicacion_completa = $conn->real_escape_string(($secretaria ? $secretaria . " - " : "") . $direccion);
        $asignado = $conn->real_escape_string($_POST['asignado_a']);
        if ($asignado === '') $asignado = NULL; 
        
        if ($accion === 'guardar') {
            $sql = "INSERT INTO eventos_calendario (titulo, fecha_inicio, fecha_fin, tipo_evento, direccion_destino, asignado_a) 
                    VALUES ('$titulo', '$inicio', '$fin', '$tipo', '$ubicacion_completa', '$asignado')";
        } else {
            $id = (int)$_POST['id'];
            $sqlDir = $direccion ? ", direccion_destino='$ubicacion_completa'" : "";
            $partAsignado = $asignado ? "'$asignado'" : "NULL";
            $sql = "UPDATE eventos_calendario SET titulo='$titulo', fecha_inicio='$inicio', fecha_fin='$fin', tipo_evento='$tipo', asignado_a=$partAsignado $sqlDir WHERE id=$id";
        }
        if ($conn->query($sql)) echo json_encode(['status' => 'success']);
        else echo json_encode(['status' => 'error', 'message' => $conn->error]);
        break;
        // -----------------------------------------------------------

    case 'eliminar':
        $id = (int)$_POST['id'];
        $conn->query("DELETE FROM eventos_calendario WHERE id=$id");
        echo json_encode(['status' => 'success']);
        break;

    // --- AQUÍ ESTÁ LA MAGIA DE LAS NOTIFICACIONES ---
    case 'notificaciones':
        
        // Lógica de filtro:
        // 1. Si es ADMIN o MASTERWEB -> Ve TODO (para supervisar).
        // 2. Si es TÉCNICO -> Ve solo lo ASIGNADO A ÉL o a TODOS.
        
        $filtro_usuario = "";
        
        if ($rol_actual !== 'admin' && $rol_actual !== 'masterweb') {
            // Es un usuario normal, filtramos
            $filtro_usuario = "AND (asignado_a = '$usuario_actual' OR asignado_a = 'TODOS')";
        }
        
        // Query final
        $sql = "SELECT titulo, tipo_evento, fecha_inicio, asignado_a
                FROM eventos_calendario 
                WHERE fecha_inicio >= NOW() 
                $filtro_usuario
                ORDER BY fecha_inicio ASC LIMIT 5";
        
        $res = $conn->query($sql);
        $items = [];
        
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                // Etiqueta visual para saber por qué me notifican
                $etiqueta = ($row['asignado_a'] === 'TODOS') ? 'Equipo' : 'Personal';
                if ($row['asignado_a'] && $row['asignado_a'] !== 'TODOS' && $rol_actual === 'admin') {
                     $etiqueta = 'Asig: ' . $row['asignado_a']; // Admin ve a quién se lo asignó
                }

                $items[] = [
                    'titulo' => $row['titulo'],
                    'tiempo' => date('d/m H:i', strtotime($row['fecha_inicio'])),
                    'tipo' => $row['tipo_evento'],
                    'asignacion' => $etiqueta
                ];
            }
        }
        echo json_encode(['total' => count($items), 'items' => $items]);
        break;
}
$conn->close();
?>