<?php
session_start();
header("Content-Type: application/json; charset=UTF-8");
require_once __DIR__ . '/../config/db.php';

// Validar que sea Gerente
if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['rol'] !== 'Gerente') {
    http_response_code(403);
    echo json_encode(["error" => "Acceso no autorizado"]);
    exit;
}

// Recibir rango de fechas opcional (por defecto el mes actual)
$fecha_inicio = $_GET['inicio'] ?? date('Y-m-01');
$fecha_fin = $_GET['fin'] ?? date('Y-m-t');

try {
    $sql = "SELECT 
                u.id_usuario, 
                u.nombre AS empleado, 
                u.usuario,
                COUNT(m.id_miembro) AS total_suscripciones,
                COALESCE(SUM(mem.precio), 0) AS total_recaudado
            FROM usuarios u
            LEFT JOIN miembros m ON u.id_usuario = m.id_usuario 
                 AND DATE(m.fecha_registro) BETWEEN ? AND ?
            LEFT JOIN membresias mem ON m.id_membresia = mem.id_membresia
            WHERE u.rol = 'Personal'
            GROUP BY u.id_usuario, u.nombre, u.usuario
            ORDER BY total_suscripciones DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$fecha_inicio, $fecha_fin]);
    $reporte = $stmt->fetchAll();

    echo json_encode([
        "status" => "success",
        "periodo" => ["inicio" => $fecha_inicio, "fin" => $fecha_fin],
        "empleados" => $reporte
    ]);
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}