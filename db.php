<?php
// db.php - CONFIGURACIÓN CENTRALIZADA
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS, GET");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { exit; }

// Datos de Azure
$azServer = "papeleria2.database.windows.net";
$azInfo = [
    "Database" => "papeleria",
    "UID" => "papeleriaA",
    "PWD" => "dios1234%", 
    "CharacterSet" => "UTF-8",
    "Encrypt" => 1,
    "TrustServerCertificate" => 0,
    "LoginTimeout" => 5
];

// Datos Local
$lcServer = "DESKTOP-TC02VK7\SQLEXPRESS01";
$lcInfo = [
    "Database" => "papeleria", 
    "CharacterSet" => "UTF-8",
    "TrustServerCertificate" => true,
    "LoginTimeout" => 5
];

// Intentar conexión (Primero Azure, luego Local)
$conn = sqlsrv_connect($azServer, $azInfo);

if (!$conn) {
    $conn = sqlsrv_connect($lcServer, $lcInfo);
}

if (!$conn) {
    die(json_encode([
        "success" => false, 
        "message" => "Error crítico: No se pudo conectar a ninguna base de datos."
    ]));
}
// Al terminar este archivo, $conn ya está listo para usarse.

?>
