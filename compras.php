<?php
// 1. IMPORTAR CONEXIÓN (Trae Azure/Local y Headers CORS)
require_once 'db.php'; 

// Importar PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

$accion = $_GET['accion'] ?? '';

// --- 1. INICIALIZAR (Cargar Combos para el Formulario) ---
if ($accion === 'inicializar') {
    $res = ["proveedores" => [], "productos" => [], "empleados" => []];

    $q1 = sqlsrv_query($conn, "SELECT id_proveedor, nombre_empresa, email_contacto FROM proveedor");
    while ($r = sqlsrv_fetch_array($q1, SQLSRV_FETCH_ASSOC)) $res["proveedores"][] = $r;

    $q2 = sqlsrv_query($conn, "SELECT id_producto, nombre FROM producto");
    while ($r = sqlsrv_fetch_array($q2, SQLSRV_FETCH_ASSOC)) $res["productos"][] = $r;

    $q3 = sqlsrv_query($conn, "SELECT id_empleado, nombre FROM empleado");
    while ($r = sqlsrv_fetch_array($q3, SQLSRV_FETCH_ASSOC)) $res["empleados"][] = $r;

    echo json_encode($res, JSON_UNESCAPED_UNICODE);
    exit;
}

// --- 2. GUARDAR COMPRA Y ENVIAR NOTA POR CORREO ---
if ($accion === 'guardar') {
    $data = json_decode(file_get_contents("php://input"), true);

    if (empty($data['id_proveedor']) || empty($data['id_empleado']) || empty($data['carrito'])) {
        echo json_encode(["status" => "error", "message" => "Datos incompletos para procesar la compra"]);
        exit;
    }

    // Iniciamos transacción para que si algo falla, no se guarde nada a medias
    sqlsrv_begin_transaction($conn);

    try {
        $numOrden = "ORD-" . date("His");

        // A. Insertar Cabecera de Compra
        $sqlC = "INSERT INTO compra (numero_orden, fecha, id_proveedor, id_empleado) 
                 OUTPUT INSERTED.id_compra VALUES (?, GETDATE(), ?, ?)";
        $stmtC = sqlsrv_query($conn, $sqlC, [$numOrden, $data['id_proveedor'], $data['id_empleado']]);

        if (!$stmtC) {
            $err = sqlsrv_errors();
            throw new Exception("Error en Cabecera: " . $err[0]['message']);
        }

        $row = sqlsrv_fetch_array($stmtC, SQLSRV_FETCH_ASSOC);
        $idCompra = $row['id_compra'];

        $totalGeneral = 0;
        $filasHtml = "";

        // B. Insertar Detalles y preparar HTML del correo
        foreach ($data['carrito'] as $p) {
            $subtotal = $p['cantidad'] * $p['costo'];
            $totalGeneral += $subtotal;

            $sqlD = "INSERT INTO detalle_compra (id_compra, id_producto, cantidad, costo_unitario) VALUES (?, ?, ?, ?)";
            $resD = sqlsrv_query($conn, $sqlD, [$idCompra, $p['id_producto'], $p['cantidad'], $p['costo']]);

            if (!$resD) {
                $errD = sqlsrv_errors();
                throw new Exception("Error en producto ".$p['nombre'].": ".$errD[0]['message']);
            }

            $filasHtml .= "
                <tr>
                    <td style='padding: 12px; border-bottom: 1px solid #edf2f7;'>{$p['nombre']}</td>
                    <td style='padding: 12px; border-bottom: 1px solid #edf2f7; text-align: center;'>{$p['cantidad']}</td>
                    <td style='padding: 12px; border-bottom: 1px solid #edf2f7; text-align: right;'>$ " . number_format($p['costo'], 2) . "</td>
                    <td style='padding: 12px; border-bottom: 1px solid #edf2f7; text-align: right; font-weight: bold;'>$ " . number_format($subtotal, 2) . "</td>
                </tr>";
        }

        // Si todo salió bien en la base de datos, guardamos cambios
        sqlsrv_commit($conn);

        // C. ENVÍO DE CORREO (Solo si el proveedor tiene email)
        if (!empty($data['email_p'])) {
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = 'anabelayo2017@gmail.com'; 
                $mail->Password = 'iqtjetvaumrzatjw'; // RECUERDA: Cambiar esto a una "Contraseña de Aplicación"
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = 587;
                $mail->CharSet = 'UTF-8';

                $mail->setFrom('anabelayo2017@gmail.com', 'Papelería Danielita');
                $mail->addAddress($data['email_p']);
                $mail->isHTML(true);
                $mail->Subject = "ORDEN DE COMPRA: #$numOrden";

                $totalFormateado = number_format($totalGeneral, 2);
                $fechaEnvio = date("d/m/Y H:i");

                $mail->Body = "
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: auto; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden;'>
                    <div style='background-color: #059669; padding: 20px; text-align: center; color: white;'>
                        <h2 style='margin: 0;'>ORDEN DE COMPRA</h2>
                        <p style='margin: 5px 0 0 0; font-size: 12px;'>PAPELERÍA DANIELITA</p>
                    </div>
                    <div style='padding: 20px;'>
                        <p><strong>Orden:</strong> #$numOrden</p>
                        <p><strong>Fecha:</strong> $fechaEnvio</p>
                        <table style='width: 100%; border-collapse: collapse;'>
                            <thead>
                                <tr style='background-color: #f1f5f9;'>
                                    <th style='padding: 10px; text-align: left;'>Producto</th>
                                    <th style='padding: 10px;'>Cant.</th>
                                    <th style='padding: 10px;'>Precio</th>
                                    <th style='padding: 10px;'>Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>$filasHtml</tbody>
                        </table>
                        <h3 style='text-align: right; color: #059669;'>Total: $ $totalFormateado</h3>
                    </div>
                </div>";

                $mail->send();
            } catch (Exception $eMail) {
                // No detenemos el proceso si falla el correo, pero lo registramos
                error_log("Error enviando correo: " . $mail->ErrorInfo);
            }
        }

        echo json_encode(["status" => "success", "message" => "Compra registrada correctamente"]);

    } catch (Exception $e) {
        sqlsrv_rollback($conn); // Si algo falló, deshacemos los inserts
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
    exit;
}

sqlsrv_close($conn);
?>