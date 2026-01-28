<?php
// 1. IMPORTAR CONEXIÓN (Trae Azure/Local y Headers CORS desde db.php)
require_once 'db.php'; 

// 2. IDENTIFICAR EL MÉTODO HTTP
$metodo = $_SERVER['REQUEST_METHOD'];

switch($metodo) {

    // --- LEER CLIENTES (GET) ---
    case 'GET':
        $sql = "SELECT id_cliente, nombre, apellido, email, telefono FROM cliente ORDER BY id_cliente DESC";
        $query = sqlsrv_query($conn, $sql);
        
        if ($query) {
            $clientes = [];
            while ($row = sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC)) {
                $clientes[] = $row;
            }
            echo json_encode($clientes, JSON_UNESCAPED_UNICODE);
        } else {
            echo json_encode(["status" => "error", "message" => "No se pudieron obtener los clientes", "detalles" => sqlsrv_errors()]);
        }
        break;

    // --- CREAR CLIENTE (POST) ---
    case 'POST':
        $data = json_decode(file_get_contents("php://input"), true);
        
        if (!empty($data['nombre']) && !empty($data['apellido'])) {
            $sql = "INSERT INTO cliente (nombre, apellido, email, telefono) VALUES (?, ?, ?, ?)";
            $params = array($data['nombre'], $data['apellido'], $data['email'], $data['telefono']);
            $stmt = sqlsrv_query($conn, $sql, $params);

            if ($stmt) {
                echo json_encode(["status" => "success", "message" => "Cliente creado"]);
            } else {
                echo json_encode(["status" => "error", "message" => "Error al insertar", "detalles" => sqlsrv_errors()]);
            }
        } else {
            echo json_encode(["status" => "error", "message" => "Nombre y apellido son campos obligatorios"]);
        }
        break;

    // --- ACTUALIZAR CLIENTE (PUT) ---
    case 'PUT':
        $data = json_decode(file_get_contents("php://input"), true);
        
        if (!empty($data['id_cliente'])) {
            $sql = "UPDATE cliente SET nombre = ?, apellido = ?, email = ?, telefono = ? WHERE id_cliente = ?";
            $params = array($data['nombre'], $data['apellido'], $data['email'], $data['telefono'], $data['id_cliente']);
            $stmt = sqlsrv_query($conn, $sql, $params);

            if ($stmt) {
                echo json_encode(["status" => "success", "message" => "Cliente actualizado"]);
            } else {
                echo json_encode(["status" => "error", "message" => "Error al actualizar", "detalles" => sqlsrv_errors()]);
            }
        } else {
            echo json_encode(["status" => "error", "message" => "ID de cliente no proporcionado"]);
        }
        break;

    // --- ELIMINAR CLIENTE (DELETE) ---
    case 'DELETE':
        // Recibe el ID por URL: clientes.php?id=5
        if (isset($_GET['id'])) {
            $id = $_GET['id'];
            $sql = "DELETE FROM cliente WHERE id_cliente = ?";
            $stmt = sqlsrv_query($conn, $sql, array($id));

            if ($stmt) {
                echo json_encode(["status" => "success", "message" => "Cliente eliminado"]);
            } else {
                echo json_encode(["status" => "error", "message" => "No se pudo eliminar el cliente", "detalles" => sqlsrv_errors()]);
            }
        }
        break;
}

// 3. CERRAR CONEXIÓN
if ($conn) {
    sqlsrv_close($conn);
}
?>