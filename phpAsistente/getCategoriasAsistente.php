<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

$serverName = "DESKTOP-TC02VK7\SQLEXPRESS01"; 
$connectionInfo = array("Database" => "papeleria", "CharacterSet" => "UTF-8", "TrustServerCertificate" => true);
$conn = sqlsrv_connect($serverName, $connectionInfo);

if (!$conn) { die(json_encode(["error" => "Conexión fallida"])); }

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

echo json_encode($categorias);
sqlsrv_close($conn);
?>