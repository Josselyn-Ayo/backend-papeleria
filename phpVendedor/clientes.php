<?php
/**
 * ARCHIVO: papeleria-api/phpVendedor/clientes.php
 * RESTRICCIÓN: Solo Lectura, Creación y Actualización.
 */

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS"); // Eliminado DELETE de los métodos permitidos
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

// Conexión subiendo un nivel
require_once '../db.php'; 

$metodo = $_SERVER['REQUEST_METHOD'];

switch($metodo) {
    case 'GET': 
        $sql = "SELECT id_cliente, nombre, apellido, email, telefono FROM cliente ORDER BY id_cliente DESC";
        $stmt = sqlsrv_query($conn, $sql);
        $clientes = [];
        while($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $clientes[] = $row;
        }
        echo json_encode($clientes, JSON_UNESCAPED_UNICODE);
        break;

    case 'POST': 
        $data = json_decode(file_get_contents("php://input"), true);
        if (empty($data['nombre'])) {
            echo json_encode(["status" => "error", "message" => "Nombre obligatorio"]);
            break;
        }
        $sql = "INSERT INTO cliente (nombre, apellido, email, telefono) VALUES (?, ?, ?, ?)";
        $params = array($data['nombre'], $data['apellido'], $data['email'], $data['telefono']);
        $stmt = sqlsrv_query($conn, $sql, $params);
        echo json_encode(["status" => $stmt ? "success" : "error"]);
        break;

    case 'PUT': 
        $data = json_decode(file_get_contents("php://input"), true);
        $sql = "UPDATE cliente SET nombre = ?, apellido = ?, email = ?, telefono = ? WHERE id_cliente = ?";
        $params = array($data['nombre'], $data['apellido'], $data['email'], $data['telefono'], $data['id_cliente']);
        $stmt = sqlsrv_query($conn, $sql, $params);
        echo json_encode(["status" => $stmt ? "success" : "error"]);
        break;

    default:
        // Si alguien intenta usar DELETE u otro método, devolvemos error 403 (Prohibido)
        http_response_code(403);
        echo json_encode(["status" => "error", "message" => "Acción no permitida para este nivel de usuario"]);
        break;
}

sqlsrv_close($conn);
?>