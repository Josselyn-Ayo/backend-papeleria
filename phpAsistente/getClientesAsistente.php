<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

$serverName = "DESKTOP-TC02VK7\SQLEXPRESS01"; 
$connectionInfo = array("Database" => "papeleria", "CharacterSet" => "UTF-8", "TrustServerCertificate" => true);
$conn = sqlsrv_connect($serverName, $connectionInfo);

if (!$conn) { die(json_encode(["error" => "Conexión fallida"])); }

// Consulta simple para el rol de asistente (SELECT)
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

echo json_encode($clientes);
sqlsrv_close($conn);
?>