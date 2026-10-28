<?php
session_start();
header("Content-Type: application/json; charset=UTF-8");
require_once __DIR__ . '/../config/db.php';

// Solo el Gerente puede administrar usuarios
if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['rol'] !== 'Gerente') {
    http_response_code(403);
    echo json_encode(["error" => "No autorizado"]);
    exit;
}

$metodo = $_SERVER['REQUEST_METHOD'];

if ($metodo === 'GET') {
    // Listar empleados (rol Personal)
    $stmt = $pdo->query("SELECT id_usuario, nombre, usuario, rol, fecha_creacion FROM usuarios WHERE rol = 'Personal'");
    echo json_encode($stmt->fetchAll());
} elseif ($metodo === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    
    if (!empty($data['nombre']) && !empty($data['usuario']) && !empty($data['password'])) {
        $hash = password_hash($data['password'], PASSWORD_DEFAULT);
        // Forzamos que las cuentas creadas aquí sean exclusivamente de tipo 'Personal'
        $rol = 'Personal';

        try {
            $stmt = $pdo->prepare("INSERT INTO usuarios (nombre, usuario, password, rol) VALUES (?, ?, ?, ?)");
            $stmt->execute([$data['nombre'], $data['usuario'], $hash, $rol]);
            echo json_encode(["status" => "success", "mensaje" => "Cuenta de empleado creada con éxito"]);
        } catch (\PDOException $e) {
            http_response_code(400);
            echo json_encode(["error" => "El nombre de usuario ya está registrado"]);
        }
    } else {
        http_response_code(400);
        echo json_encode(["error" => "Faltan datos obligatorios"]);
    } 
    
    } elseif ($metodo === 'DELETE') {
    // Solo el Gerente puede borrar empleados
    if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['rol'] !== 'Gerente') {
        http_response_code(403);
        echo json_encode(["error" => "No autorizado"]);
        exit;
    }

    $data = json_decode(file_get_contents("php://input"), true);
    $id_usuario = $data['id_usuario'] ?? $_GET['id_usuario'] ?? null;

    if (!empty($id_usuario)) {
        // Evitar que el gerente se borre a sí mismo por error
        if ($id_usuario == $_SESSION['usuario']['id']) {
            http_response_code(400);
            echo json_encode(["error" => "No puedes eliminar tu propia cuenta de Gerente"]);
            exit;
        }

        $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id_usuario = ? AND rol = 'Personal'");
        $stmt->execute([$id_usuario]);
        echo json_encode(["status" => "success", "mensaje" => "Empleado eliminado correctamente"]);
    } else {
        http_response_code(400);
        echo json_encode(["error" => "ID de empleado requerido"]);
    }
}

