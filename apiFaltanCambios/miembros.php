<?php
session_start();

require_once __DIR__ . '/../config/db.php';

header("Content-Type: application/json; charset=UTF-8");

if (!isset($_SESSION['usuario'])) {
    http_response_code(401);
    echo json_encode(["error" => "No autenticado"]);
    exit;
}

$metodo = $_SERVER['REQUEST_METHOD'];

switch ($metodo) {
    case 'GET':
        $busqueda = $_GET['q'] ?? '';
        $empleado_id = $_GET['empleado_id'] ?? '';
        
        $sql = "SELECT m.id_miembro, m.nombre, m.email, m.telefono, m.estatus, m.fecha_registro, 
                       mem.id_membresia, mem.nombre AS membresia, mem.precio, u.nombre AS registrado_por, u.id_usuario 
                FROM miembros m 
                LEFT JOIN membresias mem ON m.id_membresia = mem.id_membresia 
                LEFT JOIN usuarios u ON m.id_usuario = u.id_usuario 
                WHERE 1=1";
        
        $params = [];
        
if ($busqueda !== '') {
    $sql .= " AND (
        m.nombre LIKE ?
        OR m.email LIKE ?
        OR m.telefono LIKE ?
        OR m.estatus LIKE ?
        OR mem.nombre LIKE ?
    )";

    $busqueda_param = "%$busqueda%";

    for ($i = 0; $i < 5; $i++) {
        $params[] = $busqueda_param;
    }
}

        if (!empty($empleado_id)) {
            $sql .= " AND m.id_usuario = ?";
            $params[] = $empleado_id;
        }

        $sql .= " ORDER BY m.id_miembro DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        echo json_encode($stmt->fetchAll());
        break;

    case 'POST':
        $data = json_decode(file_get_contents("php://input"), true);
        $id_usuario = $_SESSION['usuario']['id'];

        if (!empty($data['nombre']) && !empty($data['email']) && !empty($data['id_membresia'])) {
            $sql = "INSERT INTO miembros (nombre, email, telefono, id_membresia, id_usuario, estatus) VALUES (?, ?, ?, ?, ?, 'Activo')";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$data['nombre'], $data['email'], $data['telefono'] ?? null, $data['id_membresia'], $id_usuario]);
            echo json_encode(["status" => "success", "id" => $pdo->lastInsertId()]);
        } else {
            http_response_code(400);
            echo json_encode(["error" => "Datos incompletos"]);
        }
        break;

    case 'PUT':

    case 'PATCH':
        $data = json_decode(file_get_contents("php://input"), true);
        
        if (empty($data['id_miembro'])) {
            http_response_code(400);
            echo json_encode(["error" => "ID de miembro requerido"]);
            exit;
        }



        // Editar datos personales de un socio
        if (($data['accion'] ?? '') === 'editar') {
            $nombre = trim($data['nombre'] ?? '');
            $email = trim($data['email'] ?? '');
            $telefono = trim($data['telefono'] ?? '');

            if ($nombre === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                http_response_code(400);
                echo json_encode([
                    "error" => "El nombre y un correo válido son obligatorios"
                ]);
                exit;
            }

            $stmt = $pdo->prepare(
                "UPDATE miembros
                 SET nombre = ?, email = ?, telefono = ?
                 WHERE id_miembro = ?"
            );

            $stmt->execute([
                $nombre,
                $email,
                $telefono !== '' ? $telefono : null,
                $data['id_miembro']
            ]);

            echo json_encode([
                "status" => "success",
                "mensaje" => "Datos del socio actualizados correctamente"
            ]);
            exit;
        }


        // Actualizar estatus (Activo, Baja Temporal, Baja Definitiva)
        if (isset($data['estatus'])) {
            $estatus_validos = ['Activo', 'Baja Temporal', 'Baja Definitiva'];
            if (!in_array($data['estatus'], $estatus_validos)) {
                http_response_code(400);
                echo json_encode(["error" => "Estatus no válido"]);
                exit;
            }
            $stmt = $pdo->prepare("UPDATE miembros SET estatus = ? WHERE id_miembro = ?");
            $stmt->execute([$data['estatus'], $data['id_miembro']]);
            echo json_encode(["status" => "success", "mensaje" => "Estatus actualizado correctamente"]);
            exit;
        }

        // Cambiar plan de membresía
        if (isset($data['id_membresia'])) {
            $stmt = $pdo->prepare("UPDATE miembros SET id_membresia = ? WHERE id_miembro = ?");
            $stmt->execute([$data['id_membresia'], $data['id_miembro']]);
            echo json_encode(["status" => "success", "mensaje" => "Plan actualizado correctamente"]);
            exit;
        }

        http_response_code(400);
        echo json_encode(["error" => "No se especificó ninguna acción"]);


        break;


    case 'DELETE':
        $data = json_decode(file_get_contents("php://input"), true);
        $id_miembro = $data['id_miembro'] ?? null;

        if (!$id_miembro || !filter_var($id_miembro, FILTER_VALIDATE_INT) || $id_miembro < 1) {
            http_response_code(400);
            echo json_encode(["error" => "ID de miembro no válido"]);
            break;
        }

        $stmt = $pdo->prepare(
            "DELETE FROM miembros WHERE id_miembro = ?"
        );
        $stmt->execute([$id_miembro]);

        if ($stmt->rowCount() > 0) {
            echo json_encode([
                "status" => "success",
                "mensaje" => "Miembro eliminado correctamente"
            ]);
        } else {
            http_response_code(404);
            echo json_encode([
                "error" => "No se encontró el miembro"
            ]);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(["error" => "Método no permitido"]);
        break;

        
}