<?php
// 1. HEADERS CORS (Indispensable para React)
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

// 2. IMPORTAR CONEXIÓN (Subiendo un nivel)
require_once '../db.php'; 

// 3. CONSULTA OPTIMIZADA
$sql = "SELECT p.id_producto, p.codigo_barras, p.nombre, p.descripcion, 
               p.precio_actual, p.stock_actual, c.nombre AS categoria_nombre 
        FROM producto p
        INNER JOIN categoria c ON p.id_categoria = c.id_categoria
        ORDER BY p.nombre ASC";

$stmt = sqlsrv_query($conn, $sql);

// 4. MANEJO DE ERRORES
if ($stmt === false) {
    http_response_code(500);
    echo json_encode([
        "status" => "error", 
        "message" => "Error al obtener la lista de productos", 
        "detalles" => sqlsrv_errors()
    ]);
    die();
}

// 5. PROCESAMIENTO DE DATOS
$productos = array();
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    // Forzamos tipos numéricos para cálculos en el carrito de React
    $row['precio_actual'] = (float)$row['precio_actual'];
    $row['stock_actual'] = (int)$row['stock_actual'];
    $productos[] = $row;
}

// 6. RESPUESTA JSON
echo json_encode($productos, JSON_UNESCAPED_UNICODE);

sqlsrv_free_stmt($stmt);
sqlsrv_close($conn);
?>