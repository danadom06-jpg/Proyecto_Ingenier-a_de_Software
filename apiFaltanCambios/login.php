<?php
session_start();
header("Content-Type: application/json; charset=UTF-8");
require_once "../config/db.php";

$data = json_decode(file_get_contents("php://input"), true);

if (!empty($data['usuario']) && !empty($data['password'])) {
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE usuario = ?");
    $stmt->execute([$data['usuario']]);
    $user = $stmt->fetch();

    // Verificamos la contraseña encriptada
    if ($user && password_verify($data['password'], $user['password'])) {
        $_SESSION['usuario'] = [
            'id' => $user['id_usuario'],
            'nombre' => $user['nombre'],
            'usuario' => $user['usuario'],
            'rol' => $user['rol']
        ];
        echo json_encode(["status" => "success", "rol" => $user['rol']]);
    } else {
        http_response_code(401);
        echo json_encode(["error" => "Usuario o contraseña incorrectos"]);
    }
} else {
    http_response_code(400);
    echo json_encode(["error" => "Faltan datos obligatorios"]);
}