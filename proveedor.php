<?php
// 1. IMPORTAR CONEXIÓN INTELIGENTE (Azure/Local + Headers CORS)
require_once 'db.php'; 

// 2. IDENTIFICAR EL MÉTODO HTTP
$method = $_SERVER['REQUEST_METHOD'];

switch($method) {
    case 'GET':
        // LISTAR PROVEEDORES
        $sql = "SELECT id_proveedor, nombre_empresa, nombre_contacto, telefono, email_contacto 
                FROM proveedor 
                ORDER BY id_proveedor DESC";
        $query = sqlsrv_query($conn, $sql);
        
        if ($query === false) {
            die(json_encode(["status" => "error", "message" => sqlsrv_errors()]));
        }

        $res = [];
        while($row = sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC)) {
            $res[] = $row;
        }
        echo json_encode($res, JSON_UNESCAPED_UNICODE);
        break;

    case 'POST':
        // CREAR O ACTUALIZAR PROVEEDOR
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['nombre_empresa'])) {
            echo json_encode(["status" => "error", "message" => "El nombre de la empresa es obligatorio"]);
            break;
        }

        if (isset($data['id_proveedor']) && !empty($data['id_proveedor'])) {
            // Lógica de UPDATE
            $sql = "UPDATE proveedor SET nombre_empresa=?, nombre_contacto=?, telefono=?, email_contacto=? WHERE id_proveedor=?";
            $params = [$data['nombre_empresa'], $data['nombre_contacto'], $data['telefono'], $data['email_contacto'], $data['id_proveedor']];
        } else {
            // Lógica de INSERT
            $sql = "INSERT INTO proveedor (nombre_empresa, nombre_contacto, telefono, email_contacto) VALUES (?, ?, ?, ?)";
            $params = [$data['nombre_empresa'], $data['nombre_contacto'], $data['telefono'], $data['email_contacto']];
        }
        
        $stmt = sqlsrv_query($conn, $sql, $params);
        
        if ($stmt) {
            echo json_encode(["status" => "success", "message" => "Datos guardados correctamente"]);
        } else {
            echo json_encode(["status" => "error", "detalles" => sqlsrv_errors()]);
        }
        break;

    case 'DELETE':
        // ELIMINAR PROVEEDOR
        $id = $_GET['id'] ?? null;
        
        if (!$id) {
            echo json_encode(["status" => "error", "message" => "ID no proporcionado"]);
            break;
        }

        $sql = "DELETE FROM proveedor WHERE id_proveedor = ?";
        $stmt = sqlsrv_query($conn, $sql, [$id]);
        
        if ($stmt) {
            echo json_encode(["status" => "success", "message" => "Proveedor eliminado"]);
        } else {
            // Error común: El proveedor tiene productos asociados
            echo json_encode([
                "status" => "error", 
                "message" => "No se puede eliminar: el proveedor tiene productos o compras vinculadas.",
                "detalles" => sqlsrv_errors()
            ]);
        }
        break;
}

// 3. CERRAR CONEXIÓN
if ($conn) {
    sqlsrv_close($conn);
}
?>