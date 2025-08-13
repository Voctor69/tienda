<?php
// webhook.php - Recibe notificaciones de Wompi
require_once 'config.php'; // Aquí defines tu conexión a BD

// Obtener el payload del webhook
$payload = file_get_contents('php://input');
$event = json_decode($payload, true);

// Log para debugging
error_log("Webhook recibido: " . $payload);

if (!$event || !isset($event['event']) || !isset($event['data'])) {
    http_response_code(400);
    exit('Invalid payload');
}

$conexion = mysqli_connect("localhost", "root", "", "testbdpa", 3306);

if (!$conexion) {
    error_log("Error de conexión en webhook: " . mysqli_connect_error());
    http_response_code(500);
    exit('Database error');
}

// Procesar diferentes tipos de eventos
switch ($event['event']) {
    case 'transaction.updated':
        $transaction = $event['data']['transaction'];
        $transaction_id = $transaction['id'];
        $status = $transaction['status'];
        $reference = $transaction['reference'];
        
        error_log("Transacción actualizada: $transaction_id - Status: $status");
        
        if ($status === 'APPROVED') {
            // Actualizar estado en BD
            $update_query = "UPDATE transacciones SET status = 'approved' WHERE reference = ?";
            $stmt = mysqli_prepare($conexion, $update_query);
            mysqli_stmt_bind_param($stmt, "s", $reference);
            mysqli_stmt_execute($stmt);
            
            // Obtener datos de la transacción para descontar stock
            $get_transaction = "SELECT session_id FROM transacciones WHERE reference = ? AND status = 'approved'";
            $stmt2 = mysqli_prepare($conexion, $get_transaction);
            mysqli_stmt_bind_param($stmt2, "s", $reference);
            mysqli_stmt_execute($stmt2);
            $result = mysqli_stmt_get_result($stmt2);
            $trans_data = mysqli_fetch_assoc($result);
            
            if ($trans_data) {
                $session_id = $trans_data['session_id'];
                
                // Obtener productos del carrito para descontar stock
                $cart_query = "
                    SELECT rc.producto_id, SUM(rc.cantidad) as cantidad_total
                    FROM reserva_carrito rc
                    WHERE rc.session_id = ? AND rc.estado = 'reservado'
                    GROUP BY rc.producto_id
                ";
                $stmt3 = mysqli_prepare($conexion, $cart_query);
                mysqli_stmt_bind_param($stmt3, "s", $session_id);
                mysqli_stmt_execute($stmt3);
                $cart_result = mysqli_stmt_get_result($stmt3);
                
                // Descontar stock de cada producto
                while ($product = mysqli_fetch_assoc($cart_result)) {
                    $producto_id = $product['producto_id'];
                    $cantidad = $product['cantidad_total'];
                    
                    $update_stock = "UPDATE producto SET stock = stock - ? WHERE codigo = ? AND stock >= ?";
                    $stmt4 = mysqli_prepare($conexion, $update_stock);
                    mysqli_stmt_bind_param($stmt4, "isi", $cantidad, $producto_id, $cantidad);
                    mysqli_stmt_execute($stmt4);
                    
                    if (mysqli_stmt_affected_rows($stmt4) > 0) {
                        error_log("Stock descontado: Producto $producto_id - Cantidad $cantidad");
                    } else {
                        error_log("Error: No se pudo descontar stock para producto $producto_id");
                    }
                }
                
                // Marcar productos del carrito como vendidos
                $update_cart = "UPDATE reserva_carrito SET estado = 'vendido' WHERE session_id = ? AND estado = 'reservado'";
                $stmt5 = mysqli_prepare($conexion, $update_cart);
                mysqli_stmt_bind_param($stmt5, "s", $session_id);
                mysqli_stmt_execute($stmt5);
            }
            
            error_log("Pago aprobado y stock actualizado para referencia: $reference");
        }
        
        break;
        
    case 'transaction.declined':
        $transaction = $event['data']['transaction'];
        $reference = $transaction['reference'];
        
        // Actualizar estado a rechazado
        $update_query = "UPDATE transacciones SET status = 'declined' WHERE reference = ?";
        $stmt = mysqli_prepare($conexion, $update_query);
        mysqli_stmt_bind_param($stmt, "s", $reference);
        mysqli_stmt_execute($stmt);
        
        error_log("Pago rechazado para referencia: $reference");
        break;
}

mysqli_close($conexion);

// Responder OK a Wompi
http_response_code(200);
echo 'OK';
?>