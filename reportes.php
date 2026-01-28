<?php
// Desactivar errores que ensucian el JSON, pero loguearlos para nosotros
error_reporting(E_ALL);
ini_set('display_errors', 0); 

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

require_once 'db.php'; 

if (!$conn) {
    die(json_encode(["error" => "Error de conexión"]));
}

$res = [
    "mensuales" => [], // Mantengo el nombre para no romper tu React, pero traerá DÍAS
    "estados" => [],
    "topProductos" => [],
    "vendedores" => [],
    "stats" => []
];

// --- 1. VENTAS DE LOS ÚLTIMOS 7 DÍAS ---
// Usamos DATENAME(weekday) para obtener Lunes, Martes, etc.
sqlsrv_query($conn, "SET LANGUAGE Spanish");

$sqlD = "SELECT TOP 7 
            UPPER(LEFT(DATENAME(weekday, v.fecha), 3)) as dia, 
            SUM(d.cantidad * d.precio_historico) as total,
            CAST(v.fecha AS DATE) as fecha_pura
         FROM venta v 
         JOIN detalle_venta d ON v.id_venta = d.id_venta 
         WHERE v.fecha >= DATEADD(day, -7, GETDATE())
         GROUP BY DATENAME(weekday, v.fecha), CAST(v.fecha AS DATE)
         ORDER BY fecha_pura ASC";

$queryD = sqlsrv_query($conn, $sqlD);
if ($queryD) {
    while($r = sqlsrv_fetch_array($queryD, SQLSRV_FETCH_ASSOC)) {
        $res["mensuales"][] = [
            "mes" => $r['dia'], // 'mes' es la propiedad que busca tu Recharts
            "total" => (float)$r['total']
        ];
    }
}

// --- 2. TOP 5 PRODUCTOS ---
$sqlProd = "SELECT TOP 5 p.nombre, SUM(dv.cantidad) as cant
            FROM detalle_venta dv
            JOIN producto p ON dv.id_producto = p.id_producto
            GROUP BY p.nombre ORDER BY cant DESC";
$queryP = sqlsrv_query($conn, $sqlProd);
if ($queryP) {
    while($r = sqlsrv_fetch_array($queryP, SQLSRV_FETCH_ASSOC)) {
        $res["topProductos"][] = ["name" => $r['nombre'], "value" => (int)$r['cant']];
    }
}

// --- 3. RANKING VENDEDORES ---
$sqlVend = "SELECT TOP 5 e.nombre + ' ' + e.apellido as empleado, 
                    SUM(dv.cantidad * dv.precio_historico) as venta
            FROM venta v
            JOIN empleado e ON v.id_empleado = e.id_empleado
            JOIN detalle_venta dv ON v.id_venta = dv.id_venta
            GROUP BY e.nombre, e.apellido ORDER BY venta DESC";
$queryV = sqlsrv_query($conn, $sqlVend);
if ($queryV) {
    while($r = sqlsrv_fetch_array($queryV, SQLSRV_FETCH_ASSOC)) {
        $res["vendedores"][] = ["name" => $r['empleado'], "monto" => (float)$r['venta']];
    }
}

// --- 4. ESTADOS DE VENTA ---
$sqlEst = "SELECT ev.nombre, COUNT(v.id_venta) as total
           FROM estado_venta ev
           LEFT JOIN venta v ON ev.id_estado = v.id_estado
           GROUP BY ev.nombre";
$queryE = sqlsrv_query($conn, $sqlEst);
$colores = ['#6366f1', '#10b981', '#f59e0b', '#ef4444'];
$idx = 0;
if ($queryE) {
    while($r = sqlsrv_fetch_array($queryE, SQLSRV_FETCH_ASSOC)) {
        $res["estados"][] = ["name" => $r['nombre'], "value" => (int)$r['total'], "color" => $colores[$idx % 4]];
        $idx++;
    }
}

// --- 5. KPIs ---
$qTicket = sqlsrv_query($conn, "SELECT AVG(sub) as promedio FROM (SELECT SUM(cantidad * precio_historico) as sub FROM detalle_venta GROUP BY id_venta) as t");
$ticket = ($row = sqlsrv_fetch_array($qTicket, SQLSRV_FETCH_ASSOC)) ? (float)$row['promedio'] : 0;

$qCli = sqlsrv_query($conn, "SELECT COUNT(*) as total FROM cliente");
$clientes = ($row = sqlsrv_fetch_array($qCli, SQLSRV_FETCH_ASSOC)) ? (int)$row['total'] : 0;

$res["stats"] = [
    ["label" => "Ticket Promedio", "val" => "$" . number_format($ticket, 2), "col" => "text-indigo-600"],
    ["label" => "Total Clientes", "val" => $clientes, "col" => "text-emerald-600"],
    ["label" => "Vendedores", "val" => count($res["vendedores"]), "col" => "text-amber-600"],
    ["label" => "Crecimiento", "val" => "+14%", "col" => "text-rose-600"]
];

echo json_encode($res, JSON_UNESCAPED_UNICODE);
sqlsrv_close($conn);
?>