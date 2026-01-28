<?php
// 1. IMPORTAR CONEXIÓN (Trae Azure/Local y Headers CORS)
require_once 'db.php'; 

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

$accion = $_GET['accion'] ?? '';

// --- 1. LISTAR USUARIOS ---
if ($accion === 'usuarios') {
    $sql = "SELECT u.id_usuario, u.username, u.id_rol, r.nombre as rol_nombre 
            FROM usuario u 
            INNER JOIN rol r ON u.id_rol = r.id_rol 
            ORDER BY u.id_usuario DESC";
    $stmt = sqlsrv_query($conn, $sql);
    $res = [];
    while($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) { $res[] = $row; }
    echo json_encode($res, JSON_UNESCAPED_UNICODE);
}

// --- 2. INSERTAR (CON ENCRIPTACIÓN Y ENVÍO DE CORREO) ---
else if ($accion === 'insertar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $id_rol = $data['id_rol'];
    $username = $data['username'];
    $emailDestino = $data['email'];

    // Obtener nombre del rol para la contraseña base
    $sqlRol = "SELECT nombre FROM rol WHERE id_rol = ?";
    $stmtRol = sqlsrv_query($conn, $sqlRol, array($id_rol));
    $rowRol = sqlsrv_fetch_array($stmtRol, SQLSRV_FETCH_ASSOC);
    $nombreRolBase = strtolower($rowRol['nombre'] ?? 'usuario');

    // Contar usuarios del mismo rol para el número correlativo
    $sqlCuenta = "SELECT COUNT(*) as total FROM usuario WHERE id_rol = ?";
    $stmtCuenta = sqlsrv_query($conn, $sqlCuenta, array($id_rol));
    $rowCount = sqlsrv_fetch_array($stmtCuenta, SQLSRV_FETCH_ASSOC);
    $siguienteNum = ($rowCount['total'] ?? 0) + 1;
    
    // Contraseña sugerida: rol + número (ej. vendedor1)
    $passwordLimpia = $nombreRolBase . $siguienteNum;
    
    // ENCRIPTACIÓN SEGURA (Compatible con el login.php que hicimos)
    $passwordEncriptada = password_hash($passwordLimpia, PASSWORD_BCRYPT);

    // ENVÍO DE CORREO
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
        $mail->addAddress($emailDestino);
        $mail->isHTML(true);
        $mail->Subject = '✨ Acceso al Sistema - Papelería Danielita ✨';
        $mail->Body = "
        <div style='background: linear-gradient(135deg, #0f172a 0%, #0ea5e9 100%); padding: 50px 20px; font-family: sans-serif;'>
            <div style='max-width: 500px; margin: auto; background: #ffffff; padding: 40px; border-radius: 30px; text-align: center;'>
                <h1 style='color: #0f172a;'>Papelería Danielita</h1>
                <p style='color: #64748b;'>¡Hola <b>$username</b>! Tus credenciales de acceso son:</p>
                <div style='background: #f8fafc; padding: 25px; border-radius: 20px; border: 1px solid #e2e8f0; text-align: left;'>
                    <p><b>Usuario:</b> $username</p>
                    <p><b>Contraseña Temporal:</b> <span style='color: #0284c7; font-weight: bold;'>$passwordLimpia</span></p>
                </div>
                <p style='font-size: 12px; color: #94a3b8; margin-top: 20px;'>Por seguridad, te recomendamos cambiar tu contraseña al ingresar.</p>
            </div>
        </div>";
        $mail->send();
    } catch (Exception $e) {
        // El error de correo no detiene la creación en BD, pero podrías loguearlo
    }

    // Guardar en Base de Datos
    $sqlInsert = "INSERT INTO usuario (username, contrasena_hash, id_rol) VALUES (?, ?, ?)";
    $params = array($username, $passwordEncriptada, $id_rol);
    $stmt = sqlsrv_query($conn, $sqlInsert, $params);
    
    echo json_encode($stmt ? ["status" => "success"] : ["status" => "error", "details" => sqlsrv_errors()]);
}

// --- 3. ELIMINAR USUARIO ---
else if ($accion === 'eliminar') {
    $id = $_GET['id'] ?? null;
    
    if (!$id) {
        die(json_encode(["status" => "error", "message" => "Falta el ID del usuario"]));
    }

    $sql = "DELETE FROM usuario WHERE id_usuario = ?";
    $stmt = sqlsrv_query($conn, $sql, array($id));

    echo json_encode($stmt ? ["status" => "success"] : ["status" => "error", "details" => sqlsrv_errors()]);
}

// --- 4. LISTAR ROLES ---
else if ($accion === 'roles') {
    $sql = "SELECT id_rol, nombre FROM rol";
    $stmt = sqlsrv_query($conn, $sql);
    $roles = [];
    while($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) { $roles[] = $row; }
    echo json_encode($roles, JSON_UNESCAPED_UNICODE);
}

sqlsrv_close($conn);
?>