<?php
/**
 * ARCHIVO: papeleria-api/phpProveedor/getHistorialCompras.php
 * DESCRIPCIÓN: Consulta el historial de facturas y productos suministrados por proveedores.
 */

// 1. HEADERS CORS (Indispensable para React)
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 2. IMPORTAR CONEXIÓN (Subiendo un nivel a la raíz)
// Importante: db.php debe estar en la carpeta raíz
if (!file_exists('../db.php')) {
    die(json_encode(["status" => "error", "message" => "No se encuentra el archivo de conexión ../db.php"]));
}
require_once '../db.php'; 

// 3. OBTENER CABECERAS DE COMPRAS (Relación Compra-Proveedor)
$queryCompras = "SELECT c.id_compra, c.numero_orden, c.fecha, pr.nombre_empresa 
                 FROM compra c 
                 LEFT JOIN proveedor pr ON c.id_proveedor = pr.id_proveedor 
                 ORDER BY c.id_compra DESC";

$stmtCompras = sqlsrv_query($conn, $queryCompras);

if ($stmtCompras === false) {
    http_response_code(500);
    die(json_encode(["status" => "error", "message" => sqlsrv_errors()]));
}

$resultado = [];

// 4. PROCESAR CADA COMPRA Y SUS DETALLES
while ($row = sqlsrv_fetch_array($stmtCompras, SQLSRV_FETCH_ASSOC)) {
    $id_compra = $row['id_compra'];
    
    // Buscar detalles de los productos comprados al proveedor
    $queryDetalles = "SELECT p.nombre, dc.cantidad, dc.costo_unitario 
                      FROM detalle_compra dc
                      LEFT JOIN producto p ON dc.id_producto = p.id_producto
                      WHERE dc.id_compra = ?";
    
    $stmtDetalles = sqlsrv_query($conn, $queryDetalles, array($id_compra));
    $detalles = [];
    $totalCompraAcumulado = 0;

    if ($stmtDetalles) {
        while ($det = sqlsrv_fetch_array($stmtDetalles, SQLSRV_FETCH_ASSOC)) {
            $cant = (int)$det['cantidad'];
            $precio = (float)$det['costo_unitario'];
            $subtotalFila = $cant * $precio;
            
            $totalCompraAcumulado += $subtotalFila;

            $detalles[] = [
                "producto" => $det['nombre'] ?? 'Producto Desconocido',
                "cantidad" => $cant,
                "costo" => $precio,
                "subtotal" => $subtotalFila
            ];
        }
    }

    // 5. CONSTRUIR OBJETO JSON PARA EL FRONTEND
    $resultado[] = [
        "id" => $id_compra,
        "orden" => $row['numero_orden'] ?? 'S/N',
        "fecha" => $row['fecha'] ? $row['fecha']->format('d/m/Y') : '---',
        "proveedor" => $row['nombre_empresa'] ?? 'SIN PROVEEDOR',
        "total" => (float)$totalCompraAcumulado,
        "items" => $detalles
    ];
}

// 6. RESPUESTA FINAL
echo json_encode($resultado, JSON_UNESCAPED_UNICODE);

// 7. CERRAR CONEXIÓN
sqlsrv_close($conn);
?>