<?php
// 1. IMPORTAR CONEXIÓN INTELIGENTE (Trae Azure/Local y Headers CORS)
require_once 'db.php'; 

// 2. IDENTIFICAR EL MÉTODO HTTP
$metodo = $_SERVER['REQUEST_METHOD'];

switch($metodo) {
    case 'GET':
        // LISTAR MÉTODOS DE PAGO
        $sql = "SELECT id_forma_pago, nombre FROM forma_pago ORDER BY id_forma_pago DESC";
        $query = sqlsrv_query($conn, $sql);
        
        if ($query === false) {
            die(json_encode(["status" => "error", "message" => sqlsrv_errors()]));
        }

        $res = [];
        while($row = sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC)) {
            $res[] = $row;
        }
        // Usamos JSON_UNESCAPED_UNICODE para tildes en "Crédito", "Efectivo", etc.
        echo json_encode($res, JSON_UNESCAPED_UNICODE);
        break;

    case 'POST':
        // CREAR NUEVO MÉTODO
        $data = json_decode(file_get_contents("php://input"), true);
        
        if (empty($data['nombre'])) {
            echo json_encode(["status" => "error", "message" => "El nombre del método de pago es requerido"]);
            break;
        }

        $sql = "INSERT INTO forma_pago (nombre) VALUES (?)";
        $params = array($data['nombre']);
        $stmt = sqlsrv_query($conn, $sql, $params);

        if ($stmt) {
            echo json_encode(["status" => "success", "message" => "Método de pago creado con éxito"]);
        } else {
            $errors = sqlsrv_errors();
            echo json_encode(["status" => "error", "message" => "Error al guardar", "detalles" => $errors]);
        }
        break;

    case 'DELETE':
        // ELIMINAR MÉTODO
        $id = $_GET['id'] ?? null;
        
        if (!$id) {
            echo json_encode(["status" => "error", "message" => "ID no proporcionado"]);
            break;
        }

        $sql = "DELETE FROM forma_pago WHERE id_forma_pago = ?";
        $stmt = sqlsrv_query($conn, $sql, array($id));

        if ($stmt) {
            echo json_encode(["status" => "success", "message" => "Método eliminado"]);
        } else {
            // Error común: El método de pago ya está asignado a una venta
            echo json_encode([
                "status" => "error", 
                "message" => "No se pudo eliminar. El método podría estar siendo usado en registros de ventas.",
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