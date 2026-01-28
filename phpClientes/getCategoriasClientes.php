<?php
/**
 * ARCHIVO: papeleria-api/phpCliente/getCategoriaClientes.php
 * OBJETIVO: Listar categorías y contar cuántos productos con stock tienen.
 */

// 1. HEADERS CORS (Indispensable para React)
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Content-Type: application/json; charset=UTF-8");

// Manejo de peticiones pre-flight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 2. IMPORTAR CONEXIÓN (Subiendo un nivel a la raíz)
if (!file_exists('../db.php')) {
    die(json_encode(["status" => "error", "message" => "Conexión db.php no encontrada en la raíz"]));
}
require_once '../db.php'; 

// 3. CONSULTA: Cuenta cuántos productos distintos con stock existen por categoría
// Usamos LEFT JOIN para mostrar categorías incluso si no tienen productos actualmente
$query = "SELECT c.id_categoria, c.nombre, COUNT(p.id_producto) as stock_tipos 
          FROM categoria c
          LEFT JOIN producto p ON c.id_categoria = p.id_categoria AND p.stock_actual > 0
          GROUP BY c.id_categoria, c.nombre
          ORDER BY c.nombre ASC";

$stmt = sqlsrv_query($conn, $query);

// 4. VALIDACIÓN DE ERRORES DE SQL SERVER
if ($stmt === false) {
    http_response_code(500);
    echo json_encode([
        "status" => "error", 
        "message" => "Error al obtener categorías", 
        "detalles" => sqlsrv_errors()
    ]);
    die();
}

// 5. PROCESAMIENTO DE DATOS
$categorias = [];
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $categorias[] = [
        "id" => $row['id_categoria'],
        "nombre" => $row['nombre'],
        "cantidad" => (int)$row['stock_tipos']
    ];
}

// 6. RESPUESTA JSON CON SOPORTE PARA TILDES (UTF-8)
echo json_encode($categorias, JSON_UNESCAPED_UNICODE);

// 7. CIERRE DE CONEXIÓN Y RECURSOS
sqlsrv_free_stmt($stmt);
sqlsrv_close($conn);
?>