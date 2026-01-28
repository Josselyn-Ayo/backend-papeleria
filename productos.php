<?php
// 1. IMPORTAR CONEXIÓN (Trae la lógica Azure/Local y los Headers CORS)
require_once 'db.php'; 

// 2. OBTENER LA ACCIÓN
$accion = $_GET['accion'] ?? '';

// --- ACCIÓN: LISTAR CATEGORÍAS ---
if ($accion === 'categorias') {
    $sql = "SELECT id_categoria, nombre FROM categoria ORDER BY nombre ASC";
    $stmt = sqlsrv_query($conn, $sql);
    
    if ($stmt === false) {
        die(json_encode(["status" => "error", "message" => sqlsrv_errors()]));
    }

    $categorias = [];
    while($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $categorias[] = $row;
    }
    echo json_encode($categorias, JSON_UNESCAPED_UNICODE);
}

// --- ACCIÓN: INSERTAR CATEGORÍA ---
else if ($accion === 'insertar_categoria' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!isset($data['nombre'])) {
        die(json_encode(["status" => "error", "message" => "Nombre de categoría requerido"]));
    }

    $sql = "INSERT INTO categoria (nombre) VALUES (?)";
    $stmt = sqlsrv_query($conn, $sql, array($data['nombre']));
    echo json_encode($stmt ? ["status" => "success"] : ["status" => "error", "details" => sqlsrv_errors()]);
}

// --- ACCIÓN: LISTAR PRODUCTOS ---
else if ($accion === 'productos') {
    $sql = "SELECT p.*, c.nombre as categoria_nombre 
            FROM producto p 
            LEFT JOIN categoria c ON p.id_categoria = c.id_categoria";
    $stmt = sqlsrv_query($conn, $sql);
    
    if ($stmt === false) {
        die(json_encode(["status" => "error", "message" => sqlsrv_errors()]));
    }

    $res = [];
    while($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) { 
        // Formateo de números para que React no los reciba como strings
        $row['precio_actual'] = isset($row['precio_actual']) ? (float)$row['precio_actual'] : 0.0;
        $row['stock_actual'] = isset($row['stock_actual']) ? (int)$row['stock_actual'] : 0;
        $res[] = $row; 
    }
    echo json_encode($res, JSON_UNESCAPED_UNICODE);
}

// --- ACCIÓN: INSERTAR PRODUCTO ---
else if ($accion === 'insertar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $sql = "INSERT INTO producto (codigo_barras, nombre, descripcion, precio_actual, stock_actual, id_categoria, id_proveedor) 
            VALUES (?, ?, ?, ?, ?, ?, ?)";
    
    $params = array(
        $data['codigo_barras'], 
        $data['nombre'], 
        $data['descripcion'], 
        $data['precio_actual'], 
        $data['stock_actual'], 
        $data['id_categoria'], 
        1 // ID de proveedor por defecto
    );
    
    $stmt = sqlsrv_query($conn, $sql, $params);
    echo json_encode($stmt ? ["status" => "success"] : ["status" => "error", "details" => sqlsrv_errors()]);
}

// --- ACCIÓN: ACTUALIZAR PRODUCTO ---
else if ($accion === 'actualizar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $sql = "UPDATE producto SET 
                codigo_barras = ?, 
                nombre = ?, 
                descripcion = ?, 
                precio_actual = ?, 
                stock_actual = ?, 
                id_categoria = ? 
            WHERE id_producto = ?";
            
    $params = array(
        $data['codigo_barras'], 
        $data['nombre'], 
        $data['descripcion'], 
        $data['precio_actual'], 
        $data['stock_actual'], 
        $data['id_categoria'], 
        $data['id_producto']
    );
    
    $stmt = sqlsrv_query($conn, $sql, $params);
    echo json_encode($stmt ? ["status" => "success"] : ["status" => "error", "details" => sqlsrv_errors()]);
}

// --- ACCIÓN: ELIMINAR PRODUCTO ---
else if ($accion === 'eliminar') {
    $id = $_GET['id'] ?? null;
    if (!$id) {
        die(json_encode(["status" => "error", "message" => "ID no proporcionado"]));
    }

    $sql = "DELETE FROM producto WHERE id_producto = ?";
    $stmt = sqlsrv_query($conn, $sql, array($id));
    echo json_encode($stmt ? ["status" => "success"] : ["status" => "error", "details" => sqlsrv_errors()]);
}

// 3. CERRAR CONEXIÓN AL FINALIZAR
sqlsrv_close($conn);
?>