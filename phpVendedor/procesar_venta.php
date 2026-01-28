<?php
// 1. HEADERS CORS (Indispensable para que React no bloquee la petición)
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 2. IMPORTAR CONEXIÓN (Subiendo un nivel a la raíz)
require_once '../db.php'; 

// 3. CONFIGURACIÓN DE PHPMAILER (Asegurando rutas correctas)
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Se asume que la carpeta PHPMailer está en la raíz de 'papeleria-api'
require '../PHPMailer/src/Exception.php';
require '../PHPMailer/src/PHPMailer.php';
require '../PHPMailer/src/SMTP.php';

$accion = $_GET['accion'] ?? '';

// --- 1. INICIALIZAR DATOS (FORMAS PAGO Y VENDEDORES) ---
if ($accion === 'inicializar') {
    $res = ["formas_pago" => [], "vendedores" => []];
    
    // Obtener Formas de Pago
    $qPago = sqlsrv_query($conn, "SELECT id_forma_pago, nombre FROM forma_pago");
    if($qPago) {
        while ($r = sqlsrv_fetch_array($qPago, SQLSRV_FETCH_ASSOC)) { 
            $res["formas_pago"][] = $r; 
        }
    }
    
    // Obtener Vendedores (Empleados con cargo de vendedor, id_cargo = 2)
    $qVend = sqlsrv_query($conn, "SELECT id_empleado, nombre, apellido FROM empleado WHERE id_cargo = 2");
    if($qVend) {
        while ($r = sqlsrv_fetch_array($qVend, SQLSRV_FETCH_ASSOC)) { 
            $res["vendedores"][] = $r; 
        }
    }
    
    echo json_encode($res, JSON_UNESCAPED_UNICODE);
    exit;
}

// --- 2. GUARDAR VENTA ---
if ($accion === 'guardar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    
    if (empty($data['id_cliente']) || empty($data['id_forma_pago']) || empty($data['carrito'])) {
        echo json_encode(["status" => "error", "message" => "Datos incompletos para procesar la venta"]);
        exit;
    }

    sqlsrv_begin_transaction($conn);
    try {
        // Generar número de factura único
        $numFactura = "FAC-" . strtoupper(substr(md5(uniqid()), 0, 8));
        
        // Insertar Venta Principal
        $sqlV = "DECLARE @Temp TABLE (id INT);
                 INSERT INTO venta (codigo_factura, id_cliente, id_empleado, id_forma_pago, id_estado, fecha) 
                 OUTPUT INSERTED.id_venta INTO @Temp
                 VALUES (?, ?, ?, ?, ?, GETDATE());
                 SELECT id FROM @Temp;";
        
        $paramsV = [
            $numFactura, 
            $data['id_cliente'], 
            $data['id_empleado'] ?? 1, 
            $data['id_forma_pago'], 
            1 // Estado: Pagado/Finalizado
        ];

        $stmtV = sqlsrv_query($conn, $sqlV, $paramsV);
        if ($stmtV === false) { throw new Exception("Error en Venta: " . sqlsrv_errors()[0]['message']); }

        sqlsrv_next_result($stmtV); 
        $rowV = sqlsrv_fetch_array($stmtV, SQLSRV_FETCH_ASSOC);
        if (!$rowV) throw new Exception("No se pudo generar el ID de venta.");
        $idVenta = $rowV['id'];

        $totalGeneral = 0;
        $filasHtml = "";

        // Insertar Detalles de la venta
        foreach ($data['carrito'] as $p) {
            $subtotal = $p['cantidad'] * $p['precio_actual'];
            $totalGeneral += $subtotal;
            
            $sqlD = "INSERT INTO detalle_venta (id_venta, id_producto, cantidad, precio_historico) VALUES (?, ?, ?, ?)";
            $resD = sqlsrv_query($conn, $sqlD, [$idVenta, $p['id_producto'], $p['cantidad'], $p['precio_actual']]);
            
            if (!$resD) throw new Exception("Error al insertar producto: " . $p['nombre']);

            // Fila para el recibo HTML
            $filasHtml .= "
                <tr>
                    <td style='padding: 12px 10px; border-bottom: 1px solid #edf2f7;'>
                        <span style='display: block; font-weight: bold; color: #2d3748; font-size: 13px;'>{$p['nombre']}</span>
                    </td>
                    <td style='padding: 12px 10px; border-bottom: 1px solid #edf2f7; text-align: center;'>{$p['cantidad']}</td>
                    <td style='padding: 12px 10px; border-bottom: 1px solid #edf2f7; text-align: right; font-weight: bold; color: #f97316;'>$" . number_format($subtotal, 2) . "</td>
                </tr>";
        }

        // Obtener datos del cliente y método de pago para el correo
        $sqlInfo = "SELECT c.nombre, c.apellido, c.email, f.nombre as metodo 
                    FROM cliente c
                    JOIN forma_pago f ON f.id_forma_pago = ?
                    WHERE c.id_cliente = ?";
        $resInfo = sqlsrv_query($conn, $sqlInfo, [$data['id_forma_pago'], $data['id_cliente']]);
        $info = sqlsrv_fetch_array($resInfo, SQLSRV_FETCH_ASSOC);

        // Confirmar transacción en BD
        sqlsrv_commit($conn);

        // --- ENVÍO DE RECIBO DIGITAL ---
        if ($info && !empty($info['email'])) {
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = 'anabelayo2017@gmail.com';
                $mail->Password = 'iqtjetvaumrzatjw';
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = 587;
                $mail->CharSet = 'UTF-8';
                $mail->setFrom('anabelayo2017@gmail.com', 'Papelería Danielita');
                $mail->addAddress($info['email']);
                $mail->isHTML(true);
                $mail->Subject = "Recibo de Compra #$numFactura";

                $mail->Body = "
                <div style='background-color: #f8fafc; padding: 40px 10px; font-family: sans-serif;'>
                    <div style='max-width: 550px; margin: 0 auto; background: white; border-radius: 15px; border: 1px solid #e2e8f0; overflow: hidden;'>
                        <div style='background: #f97316; padding: 30px; text-align: center; color: white;'>
                            <h2 style='margin: 0;'>¡Gracias por tu compra!</h2>
                            <p>Papelería Danielita - Recibo Digital</p>
                        </div>
                        <div style='padding: 30px;'>
                            <p><b>Cliente:</b> {$info['nombre']} {$info['apellido']}</p>
                            <p><b>Factura:</b> $numFactura | <b>Fecha:</b> " . date('d/m/Y') . "</p>
                            <table style='width: 100%; border-collapse: collapse; margin-top: 20px;'>
                                <thead>
                                    <tr style='border-bottom: 2px solid #f1f5f9; color: #64748b; font-size: 11px;'>
                                        <th style='text-align: left; padding-bottom: 10px;'>PRODUCTO</th>
                                        <th style='text-align: center; padding-bottom: 10px;'>CANT.</th>
                                        <th style='text-align: right; padding-bottom: 10px;'>TOTAL</th>
                                    </tr>
                                </thead>
                                <tbody>$filasHtml</tbody>
                            </table>
                            <div style='margin-top: 25px; background: #fff7ed; padding: 20px; text-align: right; border-radius: 12px;'>
                                <p style='margin: 0; font-size: 12px; color: #9a3412;'>PAGO CON: " . strtoupper($info['metodo']) . "</p>
                                <p style='margin: 5px 0 0; font-size: 24px; font-weight: bold; color: #ea580c;'>$" . number_format($totalGeneral, 2) . "</p>
                            </div>
                        </div>
                    </div>
                </div>";

                $mail->send();
            } catch (Exception $eMail) {
                error_log("No se pudo enviar el correo: " . $mail->ErrorInfo);
            }
        }

        echo json_encode(["status" => "success", "factura" => $numFactura, "message" => "Venta procesada con éxito"]);

    } catch (Exception $e) {
        sqlsrv_rollback($conn);
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
    sqlsrv_close($conn);
    exit;
}
?>