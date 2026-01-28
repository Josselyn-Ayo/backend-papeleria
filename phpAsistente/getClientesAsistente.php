<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

/* IMPORTANTE: Como este archivo está dentro de 'phpAsistente', 
   subimos un nivel con '../' para encontrar el db.php en la raíz.
*/
require_once '../db.php'; 

// Verificamos si la conexión ($conn) definida en db.php existe
if (!$conn) { 
    die(json_encode([
        "error" => "Conexión fallida desde el servidor",
        "detalle" => "Asegúrate de que db.php tenga los datos de Azure y no los de tu PC local."
    ])); 
}

// Consulta para el rol de asistente
$query = "SELECT id_cliente, nombre, apellido, email, telefono FROM cliente ORDER BY nombre ASC";
$stmt = sqlsrv_query($conn, $query);

if ($stmt === false) {
    die(json_encode(["error" => sqlsrv_errors()]));
}

$clientes = [];
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $clientes[] = [
        "id" => $row['id_cliente'],
        "nombre" => $row['nombre'],
        "apellido" => $row['apellido'],
        "email" => $row['email'] ?? 'Sin correo',
        "telefono" => $row['telefono'] ?? 'Sin teléfono'
    ];
}

// Enviamos la respuesta
echo json_encode($clientes, JSON_UNESCAPED_UNICODE);

// Cerramos la conexión
sqlsrv_free_stmt($stmt);
sqlsrv_close($conn);
?>
