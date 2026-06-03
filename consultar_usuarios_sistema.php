<?php
require_once 'session_check.php';
require_once 'config.php';
session_start();

// 1. SEGURIDAD: Solo Admin puede ver la lista de usuarios del sistema
if (!isset($_SESSION['usuario']) || $_SESSION['rol'] !== 'admin' && $_SESSION['rol'] !== 'masterweb') {
    header("Location: dashboard.php");
    exit();
}

$conn = get_db_connection();
$sql = "SELECT id, usuario, rol, created_at FROM usuarios ORDER BY id DESC"; // Ajusta 'created_at' si tu tabla tiene fecha de registro
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Usuarios del Sistema | SISGI</title>
    <link rel="stylesheet" href="css/estilos.css"> <link rel="stylesheet" href="css/all.min.css"> </head>
<body class="bg-gray-100 min-h-screen">

    <nav class="bg-brand-dark shadow-sm p-4 text-white">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            <h1 class="text-xl font-bold">Gestión de Accesos</h1>
            <a href="dashboard.php" class="bg-white/20 hover:bg-white/30 px-4 py-2 rounded-lg transition">Volver</a>
        </div>
    </nav>

    <main class="max-w-5xl mx-auto py-10 px-4">
        <div class="bg-white rounded-2xl shadow-xl overflow-hidden">
            <div class="p-6 border-b border-gray-100 flex justify-between items-center">
                <h2 class="text-2xl font-extrabold text-gray-800">Usuarios con Acceso al Dashboard</h2>
                <span class="bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-sm font-bold">
                    <?= $result->num_rows ?> Usuarios Registrados
                </span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead class="bg-gray-50 text-gray-500 uppercase text-xs font-bold">
                        <tr>
                            <th class="px-6 py-4">ID</th>
                            <th class="px-6 py-4">Usuario</th>
                            <th class="px-6 py-4">Rol / Permisos</th>
                            <th class="px-6 py-4 text-center">Estado</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php while($row = $result->fetch_assoc()): ?>
                        <tr class="hover:bg-gray-50 transition">
                            <td class="px-6 py-4 font-mono text-gray-400">#<?= $row['id'] ?></td>
                            <td class="px-6 py-4 font-bold text-gray-800"><?= htmlspecialchars($row['usuario']) ?></td>
                            <td class="px-6 py-4">
                                <span class="px-3 py-1 rounded-full text-xs font-bold 
                                    <?= $row['rol'] === 'admin' ? 'bg-purple-100 text-purple-700' : 'bg-emerald-100 text-emerald-700' ?>">
                                    <?= strtoupper($row['rol']) ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span class="inline-block w-2 h-2 rounded-full bg-green-500 mr-2"></span>
                                <span class="text-sm text-gray-600">Activo</span>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

</body>
</html>