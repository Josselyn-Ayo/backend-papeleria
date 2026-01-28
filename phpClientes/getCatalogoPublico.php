<?php
/**
 * ARCHIVO: papeleria-api/phpCliente/getCatalogoPublico.php
 * OBJETIVO: Listar productos disponibles para el cliente final (Catálogo).
 */

// 1. HEADERS CORS (Permite que la web de clientes consulte la API)
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Content-Type: application/json; charset=UTF-8");

// Manejo de peticiones pre-flight de los navegadores
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 2. IMPORTAR CONEXIÓN (Subiendo un nivel para llegar a la raíz)
if (!file_exists('../db.php')) {
    die(json_encode(["status" => "error", "message" => "Conexión db.php no encontrada"]));
}
require_once '../db.php'; 

// 3. CONSULTA: Solo productos con stock para no mostrar productos agotados
$query = "SELECT p.id_producto, p.nombre, p.descripcion, p.precio_actual, p.stock_actual, c.nombre as categoria_nombre 
          FROM producto p
          INNER JOIN categoria c ON p.id_categoria = c.id_categoria
          WHERE p.stock_actual > 0
          ORDER BY p.nombre ASC";

$stmt = sqlsrv_query($conn, $query);

// 4. VALIDACIÓN DE ERRORES DE SQL SERVER
if ($stmt === false) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Error al consultar productos disponibles",
        "detalles" => sqlsrv_errors()
    ]);
    die();
}

// 5. PROCESAMIENTO DE DATOS
$productos = [];
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $productos[] = [
        "id" => $row['id_producto'],
        "nombre" => $row['nombre'],
        "descripcion" => $row['descripcion'] ?? '',
        "precio" => (float)$row['precio_actual'],
        "stock" => (int)$row['stock_actual'],
        "categoria" => $row['categoria_nombre']
    ];
}

// 6. RESPUESTA JSON LIMPIA
// Si no hay productos, devolverá un array vacío [] facilitando el .length en React
echo json_encode($productos, JSON_UNESCAPED_UNICODE);

// 7. CIERRE DE CONEXIÓN
sqlsrv_free_stmt($stmt);
sqlsrv_close($conn);
?>