<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

// CORRECCIÓN: Salimos de la carpeta 'phpAsistente' para buscar 'db.php' en la raíz
require_once '../db.php'; 

// Validamos que la conexión central ($conn) esté funcionando
if (!$conn) { 
    die(json_encode([
        "error" => "Conexión fallida",
        "detalle" => "No se pudo conectar al servidor de Azure desde db.php"
    ])); 
}

// Consulta ajustada para traer productos con su categoría
$query = "SELECT p.id_producto, p.codigo_barras, p.nombre, p.descripcion, 
                 p.precio_actual, p.stock_actual, c.nombre AS nombre_categoria 
          FROM producto p
          INNER JOIN categoria c ON p.id_categoria = c.id_categoria
          ORDER BY p.nombre ASC";

$stmt = sqlsrv_query($conn, $query);

// VALIDACIÓN CRÍTICA: Si la consulta falla, capturamos el error
if ($stmt === false) {
    die(json_encode([
        "error" => "Error en la consulta SQL",
        "detalles" => sqlsrv_errors()
    ]));
}

$productos = [];
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $productos[] = [
        "id" => $row['id_producto'],
        "codigo" => $row['codigo_barras'],
        "nombre" => $row['nombre'],
        "descripcion" => $row['descripcion'],
        "precio" => (float)$row['precio_actual'],
        "stock" => (int)$row['stock_actual'],
        "categoria" => $row['nombre_categoria']
    ];
}

// Respuesta en formato JSON compatible con tu frontend
echo json_encode($productos, JSON_UNESCAPED_UNICODE);

// Cierre de recursos
sqlsrv_free_stmt($stmt);
sqlsrv_close($conn);
?>
