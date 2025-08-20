<?php
// Iniciar sesión solo si no está ya activa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

// Verificar autenticación
if (!isset($_SESSION['usuario_id']) || empty($_SESSION['usuario_id'])) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    
    echo json_encode([
        "success" => false,
        "error" => "auth_required",
        "message" => "Debe iniciar sesión para continuar"
    ]);
    exit;
}

// CLAVES DE PRODUCCIÓN WOMPI - TUS CLAVES REALES
define('WOMPI_PUBLIC_KEY', 'pub_prod_7XHbDz3WYra9t2e8FDjViDjKvmIY6dRZ');
define('WOMPI_PRIVATE_KEY', 'prv_prod_Lai62Wtxj225XlXw0VJSC2KOTtfRI4KX');

$conexion = mysqli_connect("localhost", "root", "", "testbdpa", 3306);

// Verificar conexión a la base de datos
if (!$conexion) {
    echo json_encode([
        'success' => false,
        'error' => 'database_error',
        'message' => 'Error de conexión a la base de datos: ' . mysqli_connect_error()
    ]);
    exit;
}

$session_id = session_id();
$now = date('Y-m-d H:i:s');

// PASO 1: Limpiar reservas expiradas automáticamente
mysqli_query($conexion, "UPDATE reserva_carrito SET estado='liberado' WHERE expiracion < '$now' AND estado='reservado'");

// PASO 2: Verificar que hay productos válidos en el carrito DESPUÉS de limpiar
$cart_products = [];
$cart_query = "
    SELECT rc.producto_id AS codigo, p.nombre, p.valor, SUM(rc.cantidad) as quantity
    FROM reserva_carrito rc
    JOIN producto p ON rc.producto_id = p.codigo
    WHERE rc.session_id='$session_id' AND rc.estado='reservado' AND rc.expiracion > '$now'
    GROUP BY rc.producto_id, p.nombre, p.valor
";

$cart_result = mysqli_query($conexion, $cart_query);

if (!$cart_result) {
    echo json_encode([
        'success' => false,
        'error' => 'cart_query_error',
        'message' => 'Error en la consulta del carrito: ' . mysqli_error($conexion)
    ]);
    exit;
}

while ($row = mysqli_fetch_assoc($cart_result)) {
    $cart_products[] = $row;
}

// Si el carrito está vacío después de limpiar reservas expiradas
if (empty($cart_products)) {
    echo json_encode([
        'success' => false,
        'error' => 'empty_cart',
        'message' => 'Tu sesión de carrito ha expirado. Por favor, vuelve a agregar los productos al carrito.',
        'expired_cart' => true
    ]);
    exit;
}

// PASO 3: Verificar que aún hay stock suficiente para los productos reservados
foreach ($cart_products as $item) {
    $stock_query = "SELECT stock FROM producto WHERE codigo = " . intval($item['codigo']);
    $stock_result = mysqli_query($conexion, $stock_query);
    $stock_row = mysqli_fetch_assoc($stock_result);
    
    if (!$stock_row || $stock_row['stock'] < $item['quantity']) {
        mysqli_query($conexion, "UPDATE reserva_carrito SET estado='liberado' 
                                WHERE session_id='$session_id' AND producto_id=" . intval($item['codigo']) . " AND estado='reservado'");
        
        echo json_encode([
            'success' => false,
            'error' => 'insufficient_stock',
            'message' => 'El producto "' . $item['nombre'] . '" ya no tiene stock suficiente. Se ha eliminado del carrito.',
            'stock_issue' => true
        ]);
        exit;
    }
}

// PASO 4: Calcular total y procesar con Wompi
$total = 0;
$description = [];
foreach ($cart_products as $row) {
    $total += floatval($row['valor']) * intval($row['quantity']);
    $description[] = $row['nombre'] . " x" . $row['quantity'];
}

// Wompi requiere el monto en CENTAVOS (multiplicar por 100)
$amount_in_cents = intval(round($total * 100));
$currency = "COP";
$reference = "ORDER-" . uniqid();
$cart_description = implode(", ", $description);

// ENDPOINT DE PRODUCCIÓN WOMPI
$wompi_url = "https://production.wompi.co/v1/payment_links";

// URL de redirección - ajusta según tu dominio
$success_url = "https://baf756ee4478.ngrok-free.app/tienda/src/views/success.php";

// Estructurar correctamente los datos para Wompi
$data = [
    "name" => "Compra Kongelados - " . $reference,
    "description" => $cart_description,
    "single_use" => true,
    "collect_shipping" => false,
    "currency" => $currency,
    "amount_in_cents" => $amount_in_cents,
    "redirect_url" => $success_url,
    "expires_at" => date('Y-m-d\TH:i:s\Z', strtotime('+1 day')),
];

// Log para debugging
error_log("Datos enviados a Wompi: " . json_encode($data));

// Realizar la petición a la API de Wompi
$ch = curl_init($wompi_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . WOMPI_PRIVATE_KEY,
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_VERBOSE, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if (curl_error($ch)) {
    error_log("cURL Error: " . curl_error($ch));
    echo json_encode([
        'success' => false,
        'error' => 'connection_error',
        'message' => 'Error de conexión con Wompi. Intenta nuevamente.'
    ]);
    curl_close($ch);
    exit;
}

curl_close($ch);

// Log para debugging
error_log("Wompi Response Code: " . $httpcode);
error_log("Wompi Response Body: " . $response);

if ($httpcode !== 201 && $httpcode !== 200) {
    $error_data = json_decode($response, true);
    $error_message = 'Error procesando el pago';
    
    if (isset($error_data['error'])) {
        if (isset($error_data['error']['type'])) {
            switch ($error_data['error']['type']) {
                case 'INVALID_REQUEST_ERROR':
                    $error_message = 'Datos de pago inválidos';
                    break;
                case 'AUTHENTICATION_ERROR':
                    $error_message = 'Error de autenticación con Wompi';
                    break;
                case 'RESOURCE_NOT_FOUND':
                    $error_message = 'Recurso no encontrado en Wompi';
                    break;
                default:
                    $error_message = 'Error comunicando con Wompi';
            }
        }
        
        if (isset($error_data['error']['messages']) && is_array($error_data['error']['messages'])) {
            $error_message .= ': ' . implode(', ', $error_data['error']['messages']);
        }
        
        error_log("Error completo de Wompi: " . json_encode($error_data));
    }
    
    echo json_encode([
        'success' => false,
        'error' => 'payment_error',
        'message' => $error_message,
        'http_code' => $httpcode
    ]);
    exit;
}

$responseData = json_decode($response, true);

if (isset($responseData['data']['id'])) {
    $payment_link_id = $responseData['data']['id'];
    
    // Construir URL de pago
    $payment_url = '';
    if (isset($responseData['data']['permalink'])) {
        $payment_url = $responseData['data']['permalink'];
    } elseif (isset($responseData['data']['url'])) {
        $payment_url = $responseData['data']['url'];
    } else {
        $payment_url = "https://checkout.wompi.co/l/" . $payment_link_id;
    }
    
    // Guardar la transacción en la base de datos
    $insert_query = "INSERT INTO transacciones (session_id, reference, payment_link_id, amount, status, created_at) 
                     VALUES (?, ?, ?, ?, 'pending', NOW())";
    
    $stmt = mysqli_prepare($conexion, $insert_query);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "sssd", $session_id, $reference, $payment_link_id, $total);
        if (!mysqli_stmt_execute($stmt)) {
            error_log("Error guardando transacción: " . mysqli_error($conexion));
        }
        mysqli_stmt_close($stmt);
    }
    
    // PASO 5: Extender la expiración de las reservas actuales
    $extended_expiration = date('Y-m-d H:i:s', strtotime('+1 hour'));
    $extend_query = "UPDATE reserva_carrito SET expiracion = '$extended_expiration' 
                    WHERE session_id='$session_id' AND estado='reservado'";
    mysqli_query($conexion, $extend_query);
    
    echo json_encode([
        'success' => true,
        'payment_link_id' => $payment_link_id,
        'payment_url' => $payment_url,
        'reference' => $reference,
        'amount' => $total,
        'products_count' => count($cart_products)
    ]);
    exit;
} else {
    echo json_encode([
        'success' => false,
        'error' => 'payment_link_creation_failed',
        'message' => 'No se pudo crear el enlace de pago',
        'details' => 'La respuesta del servidor no contiene la información necesaria'
    ]);
    exit;
}

// Cerrar conexión
mysqli_close($conexion);
?>