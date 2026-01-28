<?php
// 1. IMPORTAR CONEXIÓN INTELIGENTE (Azure/Local + Headers CORS)
require_once 'db.php'; 

$accion = $_GET['accion'] ?? '';

// --- ACCIÓN: LISTAR CARGOS (Para llenar selectores en React) ---
if ($accion === 'cargos') {
    $sql = "SELECT id_cargo, nombre FROM cargo ORDER BY nombre ASC";
    $stmt = sqlsrv_query($conn, $sql);
    
    if ($stmt === false) {
        die(json_encode(["status" => "error", "message" => sqlsrv_errors()]));
    }

    $res = [];
    while($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) { 
        $res[] = $row; 
    }
    echo json_encode($res, JSON_UNESCAPED_UNICODE);
}

// --- ACCIÓN: LISTAR EMPLEADOS ---
else if ($accion === 'listar') {
    $sql = "SELECT e.*, c.nombre as cargo_nombre 
            FROM empleado e 
            INNER JOIN cargo c ON e.id_cargo = c.id_cargo
            ORDER BY e.id_empleado DESC";
    $stmt = sqlsrv_query($conn, $sql);
    
    if ($stmt === false) {
        die(json_encode(["status" => "error", "message" => sqlsrv_errors()]));
    }

    $res = [];
    while($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) { 
        $res[] = $row; 
    }
    echo json_encode($res, JSON_UNESCAPED_UNICODE);
}

// --- ACCIÓN: INSERTAR EMPLEADO ---
else if ($accion === 'insertar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $sql = "INSERT INTO empleado (nombre, apellido, email, id_cargo, id_usuario) VALUES (?, ?, ?, ?, ?)";
    $params = array(
        $data['nombre'], 
        $data['apellido'], 
        $data['email'], 
        $data['id_cargo'], 
        $data['id_usuario']
    );
    
    $stmt = sqlsrv_query($conn, $sql, $params);
    echo json_encode($stmt ? ["status" => "success"] : ["status" => "error", "errors" => sqlsrv_errors()]);
}

// --- ACCIÓN: ACTUALIZAR EMPLEADO ---
else if ($accion === 'actualizar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $sql = "UPDATE empleado SET nombre=?, apellido=?, email=?, id_cargo=?, id_usuario=? WHERE id_empleado=?";
    $params = array(
        $data['nombre'], 
        $data['apellido'], 
        $data['email'], 
        $data['id_cargo'], 
        $data['id_usuario'], 
        $data['id_empleado']
    );
    
    $stmt = sqlsrv_query($conn, $sql, $params);
    echo json_encode($stmt ? ["status" => "success"] : ["status" => "error", "errors" => sqlsrv_errors()]);
}

// --- ACCIÓN: ELIMINAR EMPLEADO ---
else if ($accion === 'eliminar') {
    $id = $_GET['id'] ?? null;
    
    if (!$id) {
        die(json_encode(["status" => "error", "message" => "ID de empleado no proporcionado"]));
    }

    $sql = "DELETE FROM empleado WHERE id_empleado = ?";
    $stmt = sqlsrv_query($conn, $sql, array($id));
    echo json_encode($stmt ? ["status" => "success"] : ["status" => "error", "errors" => sqlsrv_errors()]);
}

// Cierre de conexión
sqlsrv_close($conn);
?>