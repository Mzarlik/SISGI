<?php
// limpiar_usuarios.php
require_once 'session_check.php';
require_once 'config.php';

function normalize_name_clean($name) {
    $name = mb_strtoupper($name, 'UTF-8');
    $unwanted_array = array(
        'Š'=>'S', 'š'=>'s', 'Ž'=>'Z', 'ž'=>'z', 'À'=>'A', 'Á'=>'A', 'Â'=>'A', 'Ã'=>'A', 'Ä'=>'A', 'Å'=>'A', 'Æ'=>'A', 'Ç'=>'C',
        'È'=>'E', 'É'=>'E', 'Ê'=>'E', 'Ë'=>'E', 'Ì'=>'I', 'Í'=>'I', 'Î'=>'I', 'Ï'=>'I', 'Ñ'=>'N', 'Ò'=>'O', 'Ó'=>'O', 'Ô'=>'O',
        'Õ'=>'O', 'Ö'=>'O', 'Ø'=>'O', 'Ù'=>'U', 'Ú'=>'U', 'Û'=>'U', 'Ü'=>'U', 'Ý'=>'Y', 'Þ'=>'B', 'ß'=>'Ss', 'à'=>'a', 'á'=>'a',
        'â'=>'a', 'ã'=>'a', 'ä'=>'a', 'å'=>'a', 'æ'=>'a', 'ç'=>'c', 'è'=>'e', 'é'=>'e', 'ê'=>'e', 'ë'=>'e', 'ì'=>'i', 'í'=>'i',
        'î'=>'i', 'ï'=>'i', 'ð'=>'o', 'ñ'=>'n', 'ò'=>'o', 'ó'=>'o', 'ô'=>'o', 'õ'=>'o', 'ö'=>'o', 'ø'=>'o', 'ù'=>'u', 'ú'=>'u',
        'û'=>'u', 'ý'=>'y', 'þ'=>'b', 'ÿ'=>'y'
    );
    $name = strtr($name, $unwanted_array);
    $name = preg_replace('/[^A-Z0-9\s]/', '', $name);
    $name = preg_replace('/\s+/', ' ', $name);
    return trim($name);
}

function get_name_similarity_score($name1, $name2) {
    $n1 = normalize_name_clean($name1);
    $n2 = normalize_name_clean($name2);
    
    $words1 = array_filter(explode(' ', $n1));
    $words2 = array_filter(explode(' ', $n2));
    
    if (empty($words1) || empty($words2)) return 0;
    
    $matched_words = 0;
    $used_indices = [];
    foreach ($words1 as $w1) {
        foreach ($words2 as $idx => $w2) {
            if (in_array($idx, $used_indices)) continue;
            
            if ($w1 === $w2) {
                $matched_words++;
                $used_indices[] = $idx;
                break;
            }
            
            $len1 = strlen($w1);
            $len2 = strlen($w2);
            $max_len = max($len1, $len2);
            $lev = levenshtein($w1, $w2);
            
            $allowed_typos = ($max_len > 4) ? 2 : 1;
            if ($lev <= $allowed_typos) {
                $matched_words++;
                $used_indices[] = $idx;
                break;
            }
        }
    }
    
    $min_words = min(count($words1), count($words2));
    $overlap_ratio = $matched_words / $min_words;
    
    sort($words1);
    sort($words2);
    $sorted1 = implode(' ', $words1);
    $sorted2 = implode(' ', $words2);
    $sorted_lev = levenshtein($sorted1, $sorted2);
    
    $max_sorted_len = max(strlen($sorted1), strlen($sorted2));
    $sorted_score = $max_sorted_len > 0 ? (1 - ($sorted_lev / $max_sorted_len)) : 0;
    
    return ($overlap_ratio * 0.7) + ($sorted_score * 0.3);
}

// Solo administradores o técnicos
$roles_permitidos = ['admin', 'tecnico'];
if (!isset($_SESSION['usuario']) || !in_array($_SESSION['rol'] ?? '', $roles_permitidos)) {
    header("Location: dashboard.php");
    exit();
}

$conn = get_db_connection();
if (!$conn) {
    die("Error de conexión a la base de datos.");
}

// 1. ENDPOINT AJAX PARA REALIZAR LA UNIFICACIÓN
if (isset($_POST['action']) && $_POST['action'] === 'unificar') {
    header('Content-Type: application/json');
    $original = trim($_POST['original'] ?? '');
    $nuevo = trim($_POST['nuevo'] ?? '');
    
    if (empty($original) || empty($nuevo)) {
        echo json_encode(['success' => false, 'error' => 'Parámetros incompletos.']);
        exit;
    }
    
    // Iniciar transacción para seguridad
    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("UPDATE inventario_soporte SET personal_asignado = ? WHERE personal_asignado = ?");
        $stmt->bind_param("ss", $nuevo, $original);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        
        $conn->commit();
        echo json_encode(['success' => true, 'updated_count' => $affected]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    $stmt->close();
    $conn->close();
    exit;
}

// 2. OBTENER TODOS LOS USUARIOS REGISTRADOS Y NORMALIZADOS
$sql_users = "SELECT id, num_empleado, cargo,
              TRIM(REPLACE(CONCAT(nombres, ' ', COALESCE(apellido_paterno,''), ' ', COALESCE(apellido_materno,'')), '  ', ' ')) as nombre_completo
              FROM registros_ad 
              ORDER BY nombre_completo ASC";
$res_users = $conn->query($sql_users);
$all_users = [];
if ($res_users) {
    while ($u = $res_users->fetch_assoc()) {
        $all_users[] = $u;
    }
}

// 3. OBTENER NOMBRES NO VINCULADOS EN EL INVENTARIO
$sql_unmapped = "SELECT inv.personal_asignado, COUNT(*) as cnt 
                 FROM inventario_soporte inv
                 LEFT JOIN registros_ad r ON TRIM(REPLACE(CONCAT(r.nombres, ' ', COALESCE(r.apellido_paterno,''), ' ', COALESCE(r.apellido_materno,'')), '  ', ' ')) = inv.personal_asignado
                 WHERE r.id IS NULL 
                   AND inv.personal_asignado IS NOT NULL 
                   AND inv.personal_asignado != '' 
                   AND inv.personal_asignado NOT IN ('STOCK', 'SIN ASIGNAR')
                 GROUP BY inv.personal_asignado
                 ORDER BY cnt DESC";
$res_unmapped = $conn->query($sql_unmapped);
$unmapped_names = [];
if ($res_unmapped) {
    while ($row = $res_unmapped->fetch_assoc()) {
        $name = trim($row['personal_asignado']);
        
        // Buscar el match más probable usando nuestro score inteligente de similitud
        $best_match = null;
        $max_score = 0;
        
        foreach ($all_users as $u) {
            $score = get_name_similarity_score($name, $u['nombre_completo']);
            if ($score > $max_score) {
                $max_score = $score;
                $best_match = $u;
            }
        }
        
        $row['original'] = $name;
        $row['count'] = (int)$row['cnt'];
        // Sugerimos el usuario si la similitud es >= 0.55
        $row['recommended'] = ($max_score >= 0.55) ? $best_match : null;
        $row['match_score'] = $max_score;
        
        $unmapped_names[] = $row;
    }
}

include 'header.php';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Depuración de Nombres e Inventario</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="js/tailwindcss.js"></script>
    <link rel="stylesheet" href="css/all.min.css">
    <script src="js/sweetalert2.all.min.js"></script>
    
    <style>
        body { background-color: #d6d1ca; font-family: 'Montserrat', 'Segoe UI', sans-serif; }
        .text-brand { color: #721538; }
        .bg-brand { background-color: #721538; }
        .bg-brand:hover { background-color: #942f54; }
        
        /* Animación de fadeout al unificar */
        .fade-out {
            opacity: 0;
            transform: scale(0.95);
            transition: all 0.5s ease;
        }
    </style>
</head>
<body class="p-4 sm:p-8">

<div class="max-w-6xl mx-auto space-y-6">
    
    <!-- ENCABEZADO -->
    <div class="flex flex-col sm:flex-row justify-between items-center bg-white p-6 rounded-2xl shadow-md border-l-8 border-[#721538]">
        <div>
            <h2 class="text-2xl sm:text-3xl font-extrabold text-brand flex items-center gap-3">
                <i class="fas fa-magic"></i> Depurar Nombres de Inventario
            </h2>
            <p class="text-sm text-gray-500 mt-1">
                Corrige variantes ortográficas o nombres mal escritos en el inventario para que se vinculen correctamente con los usuarios del sistema.
            </p>
        </div>
        <div class="mt-4 sm:mt-0 flex gap-2">
            <a href="consultar_usuarios.php" class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-2.5 px-6 rounded-full shadow transition flex items-center gap-2">
                <i class="fas fa-arrow-left"></i> Volver a Usuarios
            </a>
        </div>
    </div>

    <!-- TARJETAS DE MÉTRICAS -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-white p-5 rounded-xl shadow-sm border border-gray-100 flex items-center gap-4">
            <div class="p-3 bg-red-100 text-red-700 rounded-lg text-2xl">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div>
                <div class="text-2xl font-bold text-gray-800"><?php echo count($unmapped_names); ?></div>
                <div class="text-xs text-gray-500 uppercase font-semibold">Nombres Inconsistentes</div>
            </div>
        </div>
        
        <div class="bg-white p-5 rounded-xl shadow-sm border border-gray-100 flex items-center gap-4">
            <div class="p-3 bg-indigo-100 text-indigo-700 rounded-lg text-2xl">
                <i class="fas fa-laptop-house"></i>
            </div>
            <div>
                <div class="text-2xl font-bold text-gray-800">
                    <?php 
                    $unmapped_items_count = 0;
                    foreach($unmapped_names as $unm) { $unmapped_items_count += $unm['count']; }
                    echo $unmapped_items_count;
                    ?>
                </div>
                <div class="text-xs text-gray-500 uppercase font-semibold">Bienes sin Vinculación</div>
            </div>
        </div>

        <div class="bg-white p-5 rounded-xl shadow-sm border border-gray-100 flex items-center gap-4">
            <div class="p-3 bg-green-100 text-green-700 rounded-lg text-2xl">
                <i class="fas fa-check-circle"></i>
            </div>
            <div>
                <div class="text-2xl font-bold text-gray-800">
                    <?php 
                    $mapped_items_res = $conn->query("SELECT COUNT(*) FROM inventario_soporte inv JOIN registros_ad r ON TRIM(REPLACE(CONCAT(r.nombres, ' ', COALESCE(r.apellido_paterno,''), ' ', COALESCE(r.apellido_materno,'')), '  ', ' ')) = inv.personal_asignado");
                    echo $mapped_items_res ? $mapped_items_res->fetch_row()[0] : 0;
                    ?>
                </div>
                <div class="text-xs text-gray-500 uppercase font-semibold">Bienes Vinculados</div>
            </div>
        </div>
    </div>

    <!-- EXPLICACIÓN / ALERTA -->
    <div class="bg-[#721538]/5 border border-[#721538]/20 p-4 rounded-xl text-sm text-[#721538] flex items-start gap-3">
        <i class="fas fa-info-circle text-lg mt-0.5"></i>
        <div>
            <span class="font-bold">¿Cómo funciona?</span> Abajo se muestran los nombres tal como se escribieron en el inventario que no coinciden con la lista de usuarios. El sistema busca de manera inteligente el usuario registrado más similar y te lo sugiere. Al pulsar "Unificar", se renombrarán automáticamente todos los bienes asignados a ese nombre incorrecto por el formato correcto del usuario.
        </div>
    </div>

    <!-- LISTA DE INCONSISTENCIAS -->
    <div class="bg-white rounded-2xl shadow-md p-6">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-lg font-bold text-gray-800">Correcciones Pendientes</h3>
            <span class="text-xs bg-gray-100 text-gray-600 px-3 py-1 rounded-full font-medium">Mostrando unificables</span>
        </div>

        <?php if (empty($unmapped_names)): ?>
            <div class="text-center py-12 text-gray-400">
                <i class="fas fa-glass-cheers text-5xl mb-3 text-green-500 animate-bounce"></i>
                <h4 class="text-lg font-bold text-gray-700">¡Todo en orden!</h4>
                <p class="text-sm">Todos los nombres en el inventario coinciden exactamente con los usuarios registrados.</p>
            </div>
        <?php else: ?>
            <div class="space-y-4" id="cards-container">
                <?php foreach($unmapped_names as $index => $item): ?>
                    <div id="card-<?php echo $index; ?>" class="p-4 bg-gray-50 rounded-xl border border-gray-200 flex flex-col md:flex-row justify-between items-start md:items-center gap-4 transition duration-300 hover:shadow-md hover:bg-white">
                        
                        <div class="space-y-1">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="font-bold text-gray-800 text-sm md:text-base">"<?php echo htmlspecialchars($item['original']); ?>"</span>
                                <span class="bg-red-100 text-red-700 text-xs px-2 py-0.5 rounded-full font-bold">
                                    <i class="fas fa-laptop mr-1"></i> <?php echo $item['count']; ?> <?php echo $item['count'] === 1 ? 'bien' : 'bienes'; ?>
                                </span>
                            </div>
                            <p class="text-xs text-gray-500">No coincide con ningún usuario activo</p>
                        </div>
                        
                        <div class="w-full md:w-auto flex flex-col sm:flex-row items-stretch sm:items-center gap-2">
                            <div class="flex-grow">
                                <label class="block text-[10px] uppercase font-bold text-gray-400 mb-1">Unificar con:</label>
                                <select id="select-<?php echo $index; ?>" class="w-full sm:w-80 p-2 border border-gray-300 rounded focus:ring-2 focus:ring-[#721538] focus:outline-none text-xs bg-white">
                                    <option value="">-- Seleccionar Usuario --</option>
                                    <?php foreach($all_users as $u): ?>
                                        <?php 
                                        $selected = "";
                                        $recom_lbl = "";
                                        if ($item['recommended'] && $item['recommended']['id'] == $u['id']) {
                                            $selected = "selected";
                                            $recom_lbl = " ⭐ (Sugerido)";
                                        }
                                        ?>
                                        <option value="<?php echo htmlspecialchars($u['nombre_completo']); ?>" <?php echo $selected; ?>>
                                            <?php echo htmlspecialchars($u['nombre_completo']) . $recom_lbl; ?> (Emp: <?php echo $u['num_empleado'] ?: '---'; ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="pt-5">
                                <button type="button" onclick="unificarBienes(<?php echo $index; ?>, '<?php echo htmlspecialchars(addslashes($item['original'])); ?>')" class="w-full bg-brand text-white font-bold py-2 px-4 rounded text-xs shadow transition flex items-center justify-center gap-1.5 cursor-pointer">
                                    <i class="fas fa-magic"></i> Unificar
                                </button>
                            </div>
                        </div>

                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

</div>

<script>
function unificarBienes(index, nombreOriginal) {
    const select = document.getElementById(`select-${index}`);
    const nombreNuevo = select.value;
    
    if (!nombreNuevo) {
        Swal.fire({
            title: 'Atención',
            text: 'Por favor, selecciona un usuario para realizar la unificación.',
            icon: 'warning',
            confirmButtonColor: '#721538'
        });
        return;
    }
    
    Swal.fire({
        title: '¿Confirmar unificación?',
        html: `¿Estás seguro de renombrar todos los bienes de <b>"${nombreOriginal}"</b> a <b>"${nombreNuevo}"</b>?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#721538',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Sí, unificar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Procesando...',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading()
            });
            
            const formData = new FormData();
            formData.append('action', 'unificar');
            formData.append('original', nombreOriginal);
            formData.append('nuevo', nombreNuevo);
            
            fetch('limpiar_usuarios.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                Swal.close();
                if (data.success) {
                    Swal.fire({
                        title: '¡Unificado con éxito!',
                        text: `Se actualizaron ${data.updated_count} bienes en el inventario.`,
                        icon: 'success',
                        timer: 2000,
                        showConfirmButton: false
                    });
                    
                    // Ocultar la tarjeta con animación
                    const card = document.getElementById(`card-${index}`);
                    card.classList.add('fade-out');
                    setTimeout(() => {
                        card.remove();
                        // Si no quedan tarjetas, mostrar recarga o mensaje vacío
                        const container = document.getElementById('cards-container');
                        if (container.children.length === 0) {
                            location.reload();
                        }
                    }, 500);
                } else {
                    Swal.fire('Error', data.error || 'Ocurrió un error al procesar la unificación.', 'error');
                }
            })
            .catch(err => {
                Swal.close();
                console.error(err);
                Swal.fire('Error', 'No se pudo conectar con el servidor.', 'error');
            });
        }
    });
}
</script>

</body>
</html>
<?php
$conn->close();
?>
