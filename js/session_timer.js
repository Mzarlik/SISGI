// Temporizador de inactividad de sesión global (15 minutos = 900 segundos)
(function() {
    const sessionTimeoutMs = 900 * 1000; // 15 minutos
    const warningTimeMs = 60 * 1000; // 1 minuto de advertencia
    const checkTime = sessionTimeoutMs - warningTimeMs; // 14 minutos
    let warningTimeout;
    let logoutTimeout;

    function resetTimers() {
        clearTimeout(warningTimeout);
        clearTimeout(logoutTimeout);
        warningTimeout = setTimeout(showWarningModal, checkTime);
    }

    function showWarningModal() {
        let timeLeft = 60;
        let countdownInterval;

        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: '¿Sigues ahí?',
                html: 'Tu sesión expirará por inactividad en <strong id="swal-countdown" style="color: #721538;">60</strong> segundos.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#721538',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Sí, mantener sesión',
                cancelButtonText: 'Cerrar sesión',
                allowOutsideClick: false,
                allowEscapeKey: false,
                didOpen: () => {
                    countdownInterval = setInterval(() => {
                        timeLeft--;
                        const countdownEl = document.getElementById('swal-countdown');
                        if (countdownEl) {
                            countdownEl.textContent = timeLeft;
                        }
                        if (timeLeft <= 0) {
                            clearInterval(countdownInterval);
                        }
                    }, 1000);
                },
                willClose: () => {
                    clearInterval(countdownInterval);
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    keepAlive();
                } else {
                    logout();
                }
            });
        } else {
            // Fallback en caso de que SweetAlert2 no esté cargado
            const keep = confirm("Tu sesión está a punto de expirar por inactividad. ¿Deseas mantener la sesión activa?");
            if (keep) {
                keepAlive();
            } else {
                logout();
            }
            return;
        }

        // Si no hay respuesta del usuario en los 60 segundos de aviso, cerrar sesión
        logoutTimeout = setTimeout(() => {
            if (typeof Swal !== 'undefined') {
                Swal.close();
            }
            window.location.href = 'logout.php?mensaje=sesion_expirada';
        }, warningTimeMs);
    }

    function keepAlive() {
        fetch('session_check.php')
            .then(response => {
                if (response.redirected || response.url.indexOf('index.php') !== -1) {
                    // La sesión ya había expirado en el backend (ej. laptop cerrada y reabierta)
                    window.location.href = 'logout.php?mensaje=sesion_expirada';
                } else {
                    resetTimers();
                }
            })
            .catch(err => {
                console.error('Error al actualizar la sesión:', err);
                // Intentamos reestablecer de todos modos por tolerancia a fallos de red temporales
                resetTimers();
            });
    }

    function logout() {
        window.location.href = 'logout.php';
    }

    // Iniciar temporizadores
    resetTimers();
})();
