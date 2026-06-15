<?php
// Evitar iniciar la sesión si ya fue iniciada previamente
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Validar que el usuario esté autenticado
// Si no existe la variable de sesión 'usuario', lo mandamos al login
if (!isset($_SESSION['usuario'])) {
    header("Location: index.php");
    exit();
}

// 2. Lógica de inactividad (Cierre automático)
$inactividad = 900; // 900 segundos = 15 minutos

if (isset($_SESSION['ultimo_acceso'])) {
    $vida_sesion = time() - $_SESSION['ultimo_acceso'];
    
    if ($vida_sesion > $inactividad) {
        // Destruir la sesión por inactividad
        session_unset();
        session_destroy();
        
        // Redirigir al login con un parámetro en la URL para mostrar un mensaje
        header("Location: index.php?mensaje=sesion_expirada");
        exit();
    }
}

// Actualizar el "temporizador" con el último clic o recarga de la página
$_SESSION['ultimo_acceso'] = time();
?>