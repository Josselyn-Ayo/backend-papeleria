<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

$serverName = "DESKTOP-TC02VK7\SQLEXPRESS01"; 
$connectionInfo = array("Database" => "papeleria", "CharacterSet" => "UTF-8", "TrustServerCertificate" => true);
$conn = sqlsrv_connect($serverName, $connectionInfo);

if (!$conn) { die(json_encode(["error" => "Conexión fallida"])); }

// IMPORTANTE: Verifica si tu tabla categoria tiene 'nombre' o 'nombre_categoria'
// He ajustado la consulta para ser más segura
$query = "SELECT p.id_producto, p.codigo_barras, p.nombre, p.descripcion, 
                 p.precio_actual, p.stock_actual, c.nombre AS nombre_categoria 
          FROM producto p
          INNER JOIN categoria c ON p.id_categoria = c.id_categoria
          ORDER BY p.nombre ASC";

$stmt = sqlsrv_query($conn, $query);

// VALIDACIÓN CRÍTICA: Si la consulta falla, capturamos el error de SQL Server
if ($stmt === false) {
    die(json_encode([
        "error" => "Error en la consulta SQL",
        "detalles" => sqlsrv_errors()
    ]));
}

$productos = [];
// Ahora solo entra aquí si $stmt es un recurso válido
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

echo json_encode($productos);
sqlsrv_close($conn);
?>