<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>¡Pago Exitoso!</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #28a745, #20c997);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .success-card {
            background: white;
            border-radius: 20px;
            padding: 3rem;
            text-align: center;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            max-width: 500px;
            width: 90%;
            animation: slideUp 0.8s ease-out;
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .success-icon {
            font-size: 4rem;
            color: #28a745;
            margin-bottom: 1.5rem;
            animation: bounce 1s infinite;
        }
        
        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% {
                transform: translateY(0);
            }
            40% {
                transform: translateY(-10px);
            }
            60% {
                transform: translateY(-5px);
            }
        }
        
        .btn-custom {
            background: linear-gradient(135deg, #28a745, #20c997);
            border: none;
            padding: 12px 30px;
            border-radius: 50px;
            color: white;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            margin: 10px;
            transition: all 0.3s ease;
        }
        
        .btn-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.4);
            color: white;
        }
        
        .order-details {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1.5rem;
            margin: 1.5rem 0;
        }
        
        .checkmark {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background-color: #28a745;
            position: relative;
            margin: 0 auto 1rem;
        }
        
        .checkmark::after {
            content: '';
            position: absolute;
            top: 25px;
            left: 30px;
            width: 15px;
            height: 25px;
            border: solid white;
            border-width: 0 3px 3px 0;
            transform: rotate(45deg);
            animation: checkmark 0.6s ease-in-out 0.3s both;
        }
        
        @keyframes checkmark {
            0% {
                opacity: 0;
                transform: rotate(45deg) scale(0);
            }
            100% {
                opacity: 1;
                transform: rotate(45deg) scale(1);
            }
        }
    </style>
</head>
<body>
    <div class="success-card">
        <div class="checkmark"></div>
        
        <h1 class="text-success mb-3">¡Pago Exitoso!</h1>
        <p class="lead text-muted mb-4">
            Tu pago ha sido procesado correctamente. Recibirás una confirmación por correo electrónico.
        </p>
        
        <div class="order-details">
            <h5 class="mb-3">
                <i class="fas fa-receipt me-2"></i>
                Detalles del pedido
            </h5>
            <div id="order-info">
                <!-- Los detalles se cargarán aquí -->
            </div>
        </div>
        
        <div class="mt-4">
            <p class="text-muted">
                <i class="fas fa-truck me-2"></i>
                Tu pedido será procesado y enviado en las próximas 24-48 horas.
            </p>
        </div>
        
        <div class="mt-4">
            <a href="index.php" class="btn-custom">
                <i class="fas fa-home me-2"></i>
                Volver al inicio
            </a>
            <a href="mis-pedidos.php" class="btn-custom">
                <i class="fas fa-list me-2"></i>
                Ver mis pedidos
            </a>
        </div>
        
        <div class="mt-4 pt-3 border-top">
            <small class="text-muted">
                <i class="fas fa-envelope me-2"></i>
                ¿Problemas? Contáctanos: soporte@tutienda.com
            </small>
        </div>
    </div>
    
    <script>
        // Obtener información del pedido desde la URL
        const urlParams = new URLSearchParams(window.location.search);
        const reference = urlParams.get('reference');
        
        if (reference) {
            // Cargar detalles del pedido
            fetch(`obtener_pedido.php?reference=${reference}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('order-info').innerHTML = `
                        <div class="row text-start">
                            <div class="col-6"><strong>Número de orden:</strong></div>
                            <div class="col-6">${data.order.reference}</div>
                            <div class="col-6"><strong>Total pagado:</strong></div>
                            <div class="col-6">$${data.order.amount.toLocaleString()}</div>
                            <div class="col-6"><strong>Fecha:</strong></div>
                            <div class="col-6">${new Date(data.order.created_at).toLocaleDateString()}</div>
                        </div>
                    `;
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
        }
    </script>