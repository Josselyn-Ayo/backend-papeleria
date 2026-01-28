<?php
// 1. IMPORTAMOS LA CONEXIÓN INTELIGENTE (Azure o Local)
require_once 'db.php'; 

// 2. RECEPCIÓN DE DATOS (JSON) DE REACT
$json = file_get_contents('php://input');
$data = json_decode($json);

if ($data && isset($data->usuario) && isset($data->password)) {
    $u = $data->usuario;
    $p = $data->password; // La clave que el usuario escribe en el formulario

    // 3. BUSCAMOS AL USUARIO
    $sql = "SELECT u.username, u.contrasena_hash, r.nombre as rol 
            FROM usuario u 
            INNER JOIN rol r ON u.id_rol = r.id_rol 
            WHERE u.username = ?";
    
    $params = array($u); 
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        die(json_encode(["success" => false, "message" => "Error en la consulta SQL."]));
    }

    if ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $db_pass = $row['contrasena_hash'];
        $login_exitoso = false;

        // --- LÓGICA DE VALIDACIÓN MULTI-FORMATO ---

        // CASO A: Es un Hash de PHP moderno (Como el de Emily: $2y$10$...)
        if (strpos($db_pass, '$2y$') === 0) {
            if (password_verify($p, $db_pass)) {
                $login_exitoso = true;
            }
        } 
        // CASO B: Es texto plano o binario corrupto (Como asistente2 o los símbolos raros)
        else {
            // Comparamos el texto plano directamente
            if ($p === $db_pass) {
                $login_exitoso = true;
            }
        }

        // 4. RESPUESTA AL FRONTEND
        if ($login_exitoso) {
            echo json_encode([
                "success" => true, 
                "usuario" => $row['username'], 
                "rol" => $row['rol'],
                "nota" => "Login exitoso"
            ]);
        } else {
            echo json_encode([
                "success" => false, 
                "message" => "La contraseña no coincide con nuestros registros."
            ]);
        }

    } else {
        echo json_encode(["success" => false, "message" => "El usuario no existe."]);
    }
} else {
    echo json_encode(["success" => false, "message" => "Datos incompletos enviados desde el cliente."]);
}

// Cerramos la conexión
if ($conn) { sqlsrv_close($conn); }
?>