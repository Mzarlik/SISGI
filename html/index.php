<?php
require_once 'config.php';

// 1. SEGURIDAD DE COOKIES
session_set_cookie_params([
    'lifetime' => 0, 'path' => '/', 'domain' => '', 
    'secure' => isset($_SERVER['HTTPS']), 'httponly' => true, 'samesite' => 'Strict'
]);

session_start();
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
// Configuración
$max_intentos = 5;
$minutos_bloqueo = 5; 

if (isset($_SESSION['usuario'])) {
    header("Location: /dashboard.php"); 
    exit();
}

$error = "";
$usuario_input = ""; 
$bloqueado_visual = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario_input = trim($_POST['usuario'] ?? ''); 
    $password_input = $_POST['password'] ?? '';

    if (empty($usuario_input) || empty($password_input)) {
        $error = "Por favor ingresa tus credenciales.";
    } else {
        $conn = get_db_connection();
        
        if ($conn === null) {
            $error = "Error de conexión. Contacte al administrador.";
        } else {
            $stmt = $conn->prepare("SELECT id, password, rol, intentos, bloqueo_hasta FROM usuarios WHERE usuario=?");
            
            if ($stmt === false) {
                 $error = "Error interno del sistema.";
            } else {
                $stmt->bind_param("s", $usuario_input);
                $stmt->execute();
                $stmt->store_result();

                if ($stmt->num_rows === 1) {
                    $stmt->bind_result($id, $hash, $rol, $intentos, $bloqueo_hasta);
                    $stmt->fetch();

                    // --- A) VERIFICAR SI LA CUENTA ESTÁ BLOQUEADA ---
                    $fecha_actual = new DateTime();
                    $fecha_bloqueo = $bloqueo_hasta ? new DateTime($bloqueo_hasta) : null;

                    // Si existe fecha de bloqueo Y todavía estamos dentro del tiempo
                    if ($fecha_bloqueo && $fecha_actual < $fecha_bloqueo) {
                        $bloqueado_visual = true;
                        $diff = $fecha_actual->diff($fecha_bloqueo);
                        $minutos_restantes = $diff->i + ($diff->h * 60) + 1;
                        $error = "⛔ Cuenta bloqueada. Intente de nuevo en $minutos_restantes minutos.";
                    
                    } else {
                        // --- B) CUENTA ACTIVA (O BLOQUEO YA EXPIRÓ) ---
                        
                        if (password_verify($password_input, $hash)) {
                            // ¡ÉXITO!
                            $conn->query("UPDATE usuarios SET intentos = 0, bloqueo_hasta = NULL WHERE id = $id");
                            
                            session_regenerate_id(true);
                            $_SESSION['usuario'] = $usuario_input;
                            $_SESSION['usuario_id'] = $id;
                            $_SESSION['rol'] = $rol;
                            
                            header("Location: /dashboard.php");
                            exit();
                        } else {
                            // ¡FALLO! - AQUÍ ESTÁ LA CORRECCIÓN CLAVE
                            
                            // Si el bloqueo anterior ya expiró, 'intentos' seguirá siendo 5 (o más).
                            // Si es así, debemos resetearlo a 0 para que este fallo cuente como el #1 de la nueva ronda.
                            if ($intentos >= $max_intentos) {
                                $intentos = 0; 
                            }

                            $nuevos_intentos = $intentos + 1;
                            
                            if ($nuevos_intentos >= $max_intentos) {
                                // Superó el límite -> BLOQUEAR AHORA
                                $tiempo_bloqueo = date('Y-m-d H:i:s', strtotime("+$minutos_bloqueo minutes"));
                                
                                $stmt_update = $conn->prepare("UPDATE usuarios SET intentos = ?, bloqueo_hasta = ? WHERE id = ?");
                                $stmt_update->bind_param("isi", $nuevos_intentos, $tiempo_bloqueo, $id);
                                $stmt_update->execute();
                                
                                $bloqueado_visual = true;
                                $error = "⛔ Has excedido los intentos. Cuenta bloqueada por $minutos_bloqueo minutos.";
                            } else {
                                // Solo aumentar contador (Aún tiene oportunidades)
                                // También limpiamos 'bloqueo_hasta' por si tenía un valor viejo expirado
                                $conn->query("UPDATE usuarios SET intentos = $nuevos_intentos, bloqueo_hasta = NULL WHERE id = $id");
                                
                                $restantes = $max_intentos - $nuevos_intentos;
                                $error = "Contraseña incorrecta. Tienes $restantes intentos restantes.";
                                sleep(1); 
                            }
                        }
                    }
                } else {
                    $error = "Usuario o contraseña incorrectos.";
                    sleep(2); 
                }
                $stmt->close();
            }
            $conn->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Iniciar Sesión | Sistema</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/estilos.css">
    <link rel="stylesheet" href="css/fonts.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    
    <style>
        .fade-in { animation: fadeIn 0.5s ease-out forwards; opacity: 0; transform: translateY(10px); }
        @keyframes fadeIn { to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body class="bg-brand-bg min-h-screen flex items-center justify-center p-4">

    <div class="bg-white w-full max-w-md rounded-2xl shadow-2xl overflow-hidden fade-in relative">
        <div class="h-2 bg-brand-dark w-full"></div>

        <div class="p-8 sm:p-10">
            <div class="text-center mb-8">
                <!-- LOGO -->
                <img src="img/SOLIDARIDAD1.png" alt="SOLIDARIDAD" class="h-32 mx-auto mb-4 object-contain">

                <h1 class="text-2xl font-bold text-gray-800 tracking-tight">Bienvenido</h1>
                
                <?php if ($bloqueado_visual): ?>
                    <p class="text-red-600 font-bold text-sm mt-1 animate-pulse">⚠️ ACCESO RESTRINGIDO ⚠️</p>
                <?php else: ?>
                    <p class="text-gray-500 text-sm mt-1">Ingresa tus credenciales para acceder</p>
                <?php endif; ?>
            </div>

            <?php if ($error): ?>
                <div class="flex items-start p-4 mb-6 text-sm <?= $bloqueado_visual ? 'text-red-800 bg-red-50 border-red-200' : 'text-red-700 bg-red-50 border-red-200' ?> border rounded-lg" role="alert">
                    <div class="mt-0.5 mr-3">
                        <i class="fas <?= $bloqueado_visual ? 'fa-lock' : 'fa-exclamation-triangle' ?> text-lg"></i>
                    </div>
                    <span class="font-medium"><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="" autocomplete="off" onsubmit="mostrarCargando()">
                
                <div class="mb-5 relative">
                    <label for="usuario" class="block mb-2 text-sm font-medium text-gray-700">Usuario</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-user text-gray-400"></i>
                        </div>
                        <input type="text" id="usuario" name="usuario" 
                               class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-brand-dark focus:border-brand-dark block w-full pl-10 p-2.5 transition-colors disabled:bg-gray-100 disabled:text-gray-400" 
                               placeholder="Usuario" 
                               value="<?= htmlspecialchars($usuario_input) ?>" 
                               required autofocus <?= $bloqueado_visual ? 'disabled' : '' ?>>
                    </div>
                </div>

                <div class="mb-6 relative">
                    <label for="password" class="block mb-2 text-sm font-medium text-gray-700">Contraseña</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-lock text-gray-400"></i>
                        </div>
                        <input type="password" id="password" name="password" 
                               class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-brand-dark focus:border-brand-dark block w-full pl-10 p-2.5 pr-10 transition-colors disabled:bg-gray-100 disabled:text-gray-400" 
                               placeholder="••••••••" 
                               required <?= $bloqueado_visual ? 'disabled' : '' ?>>
                        <button type="button" onclick="togglePassword()" class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-brand-dark cursor-pointer focus:outline-none" title="Ver contraseña" <?= $bloqueado_visual ? 'disabled' : '' ?>>
                            <i class="fas fa-eye" id="eyeIcon"></i>
                        </button>
                    </div>
                </div>

                <?php if (!$bloqueado_visual): ?>
                <button type="submit" id="btnSubmit" class="w-full text-white bg-brand-dark hover:bg-brand-light focus:ring-4 focus:outline-none focus:ring-red-200 font-medium rounded-lg text-sm px-5 py-3 text-center transition-all duration-200 shadow-md hover:shadow-lg flex justify-center items-center gap-2">
                    <span>Ingresar</span>
                    <i class="fas fa-arrow-right text-xs"></i>
                </button>
                <?php else: ?>
                <button type="button" onclick="window.location.reload();" class="w-full text-gray-700 bg-gray-200 hover:bg-gray-300 font-medium rounded-lg text-sm px-5 py-3 text-center transition-all flex justify-center items-center gap-2">
                    <i class="fas fa-sync-alt"></i> Actualizar estado
                </button>
                <?php endif; ?>

            </form>
        </div>

        <div class="bg-gray-50 px-8 py-4 border-t border-gray-100 text-center">
            <p class="text-xs text-gray-400">
                &copy; <?= date("Y") ?> Sistema de Gestión Interna
            </p>
        </div>
    </div>

    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const eyeIcon = document.getElementById('eyeIcon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                eyeIcon.classList.remove('fa-eye');
                eyeIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                eyeIcon.classList.remove('fa-eye-slash');
                eyeIcon.classList.add('fa-eye');
            }
        }

        function mostrarCargando() {
            const btn = document.getElementById('btnSubmit');
            if(btn) {
                const icon = btn.querySelector('i');
                const text = btn.querySelector('span');
                btn.disabled = true;
                btn.classList.add('opacity-75', 'cursor-not-allowed');
                if(icon) {
                    icon.classList.remove('fa-arrow-right');
                    icon.classList.add('fa-spinner', 'fa-spin');
                }
                if(text) text.innerText = "Verificando...";
            }
        }
    </script>

</body>
</html>



