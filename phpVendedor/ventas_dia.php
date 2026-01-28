<?php
/**
 * ARCHIVO: papeleria-api/phpVendedor/ventas_dia.php
 * OBJETIVO: Listar historial de ventas para el vendedor.
 */

// 1. CONFIGURACIÓN DE CABECERAS (CORS)
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
// Se usa ../ porque db.php está fuera de la carpeta phpVendedor
if (!file_exists('../db.php')) {
    die(json_encode(["status" => "error", "message" => "No se encuentra el archivo de conexión"]));
}
require_once '../db.php'; 

// 3. CONSULTA SQL CON AGREGACIÓN
// Calculamos el total de cada venta multiplicando cantidad por precio histórico
$query = "SELECT 
            v.id_venta, 
            e.nombre AS estado_nombre,
            v.fecha,
            SUM(dv.cantidad * dv.precio_historico) AS total_venta
          FROM venta v
          INNER JOIN estado_venta e ON v.id_estado = e.id_estado
          LEFT JOIN detalle_venta dv ON v.id_venta = dv.id_venta
          GROUP BY v.id_venta, e.nombre, v.fecha
          ORDER BY v.fecha DESC";

$stmt = sqlsrv_query($conn, $query);

// 4. VALIDACIÓN DE ERRORES DE BASE DE DATOS
if ($stmt === false) {
    http_response_code(500);
    echo json_encode([
        "status" => "error", 
        "message" => "Error al consultar las ventas",
        "detalles" => sqlsrv_errors()
    ]);
    die();
}

$ventas = [];

// 5. FORMATEO DE RESULTADOS
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $ventas[] = [
        "id" => $row['id_venta'],
        // Convertimos el objeto DateTime de SQL Server a String para React
        "fecha" => $row['fecha'] ? $row['fecha']->format('d/m/Y H:i') : null,
        "estado" => strtoupper($row['estado_nombre']),
        "total" => (float)($row['total_venta'] ?? 0)
    ];
}

// 6. RESPUESTA FINAL
echo json_encode($ventas, JSON_UNESCAPED_UNICODE);

// 7. LIMPIEZA DE RECURSOS
sqlsrv_free_stmt($stmt);
sqlsrv_close($conn);
?>