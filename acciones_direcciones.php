<?php
ob_start();
require_once 'session_check.php';
require_once 'config.php';
session_start();
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');

$conn = get_db_connection();
$conn->set_charset("utf8"); 

$method = $_SERVER['REQUEST_METHOD'];
$action = $_REQUEST['action'] ?? '';

try {
    // --- LECTURA (GET) ---
    if ($method === 'GET') {
        
        // A. OBTENER SECRETARÍAS (Para el Select del Modal)
        if ($action === 'get_secretarias') {
            ob_clean();
            $sql = "SELECT * FROM Secretarias ORDER BY nombres ASC";
            $res = $conn->query($sql);
            $data = [];
            while($r = $res->fetch_assoc()) { $data[] = $r; } 
            echo json_encode($data);
            exit;
        }

        // CÓDIGO NUEVO: Específico para el Select Dinámico
        if ($action === 'get_direcciones_por_secretaria') {
            ob_clean(); 
            $id_sec = $_GET['id_secretaria'] ?? 0;
            
            // Traemos solo lo necesario
            $stmt = $conn->prepare("SELECT id_direcciones, nombres_direcciones FROM Direcciones WHERE id_secretaria = ? ORDER BY nombres_direcciones ASC");
            $stmt->bind_param("i", $id_sec);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $data = [];
            while($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
            
            echo json_encode($data);
            exit;
        }


        // B. LECTURA JERÁRQUICA (Acordeón)
        if ($action === 'read') {
            ob_clean();
            
            // 1. Obtenemos TODAS las secretarías primero
            $sqlSec = "SELECT * FROM Secretarias ORDER BY nombres ASC";
            $resSec = $conn->query($sqlSec);
            
            $secretarias = [];
            // Usamos el ID como clave para insertar direcciones fácilmente
            while($s = $resSec->fetch_assoc()) {
                $s['direcciones'] = []; // Preparamos el array vacío
                $secretarias[ $s['id_secretaria'] ] = $s;
            }

            // 2. Obtenemos TODAS las direcciones
            $sqlDir = "SELECT * FROM Direcciones ORDER BY nombres_direcciones ASC";
            $resDir = $conn->query($sqlDir);
            
            while($d = $resDir->fetch_assoc()) {
                $idPadre = $d['id_secretaria'];
                // Si la secretaría existe en nuestro array, le agregamos la dirección
                if (isset($secretarias[$idPadre])) {
                    $secretarias[$idPadre]['direcciones'][] = $d;
                }
            }

            // Devolvemos el array re-indexado (sin claves numéricas)
            echo json_encode(['data' => array_values($secretarias)]);
            exit;
        }
    }

    // --- ESCRITURA (POST) - IGUAL QUE ANTES ---
    if ($method === 'POST') {
        ob_clean();
        $rol = $_SESSION['rol'] ?? '';
        if ($rol !== 'admin' && $rol !== 'masterweb') {
            echo json_encode(['success' => false, 'message' => 'No autorizado']); exit;
        }

        if ($action === 'create') {
            $nombre = $_POST['nombres_direcciones'] ?? '';
            $id_sec = $_POST['id_secretaria'] ?? '';
            if (empty($nombre) || empty($id_sec)) { echo json_encode(['success' => false]); exit; }
            $stmt = $conn->prepare("INSERT INTO Direcciones (nombres_direcciones, id_secretaria) VALUES (?, ?)");
            $stmt->bind_param("si", $nombre, $id_sec);
            if ($stmt->execute()) echo json_encode(['success' => true]); else echo json_encode(['success' => false]);
            $stmt->close();
        }
        elseif ($action === 'update') {
            $id = $_POST['id_direcciones'] ?? 0;
            $nombre = $_POST['nombres_direcciones'] ?? '';
            $id_sec = $_POST['id_secretaria'] ?? '';
            if (!$id || empty($nombre) || empty($id_sec)) { echo json_encode(['success' => false]); exit; }
            $stmt = $conn->prepare("UPDATE Direcciones SET nombres_direcciones = ?, id_secretaria = ? WHERE id_direcciones = ?");
            $stmt->bind_param("sii", $nombre, $id_sec, $id);
            if ($stmt->execute()) echo json_encode(['success' => true]); else echo json_encode(['success' => false]);
            $stmt->close();
        }
        exit;
    }

} catch (Exception $e) {
    ob_clean(); echo json_encode(['error' => $e->getMessage()]); exit;
}
$conn->close();
?>