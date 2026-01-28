<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

// CORRECCIÓN: Se agregaron los dos puntos (../) para salir de la carpeta phpAsistente
require_once '../db.php'; 

// Verificamos si la conexión ($conn) definida en db.php existe
if (!$conn) { 
    die(json_encode([
        "error" => "Conexión fallida",
        "detalle" => "No se pudo establecer conexión con la base de datos."
    ])); 
}

// Traemos el nombre de la categoría y contamos cuántos productos tiene asociados
$query = "SELECT c.id_categoria, c.nombre, COUNT(p.id_producto) as total_productos 
          FROM categoria c
          LEFT JOIN producto p ON c.id_categoria = p.id_categoria
          GROUP BY c.id_categoria, c.nombre
          ORDER BY c.nombre ASC";

$stmt = sqlsrv_query($conn, $query);

if ($stmt === false) {
    die(json_encode(["error" => sqlsrv_errors()]));
}

$categorias = [];
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $categorias[] = [
        "id" => $row['id_categoria'],
        "nombre" => $row['nombre'],
        "total" => (int)$row['total_productos']
    ];
}

echo json_encode($categorias, JSON_UNESCAPED_UNICODE);

sqlsrv_free_stmt($stmt);
sqlsrv_close($conn);
?>
