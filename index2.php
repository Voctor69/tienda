<?php
session_start();
$conexion = mysqli_connect("localhost", "root", "", "testbdpa", 3306);

if (!$conexion) {
    echo "No se conectó con la base de datos";
    exit;
}

$session_id = session_id();
$now = date('Y-m-d H:i:s');

// Libera reservas expiradas automáticamente
mysqli_query($conexion, "UPDATE reserva_carrito SET estado='liberado' WHERE expiracion < '$now' AND estado='reservado'");

// ENDPOINTS AJAX
// Autocompletar productos
if (isset($_GET['ajax_search']) && isset($_GET['term'])) {
    $term = mysqli_real_escape_string($conexion, $_GET['term']);
    $query = "
        SELECT p.codigo, p.nombre, p.imagen, p.presentacion, m.nombre AS marca_nombre
        FROM producto p
        JOIN marca m ON p.marca = m.codigo
        WHERE p.nombre LIKE '%$term%' OR m.nombre LIKE '%$term%' OR p.presentacion LIKE '%$term%'
        LIMIT 10
    ";
    $result = mysqli_query($conexion, $query);
    $productos = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $productos[] = $row;
    }
    header('Content-Type: application/json');
    echo json_encode($productos);
    exit;
}

// Filtrar productos por categoría
if (isset($_GET['ajax_categoria']) && isset($_GET['categoria'])) {
    $categoria_filtro = intval($_GET['categoria']);
    $query = "
        SELECT p.codigo, p.nombre, p.valor, p.presentacion, p.tamañoUnidad, p.unidad, p.stock, p.imagen,
               c.codigo AS categoria_codigo, c.nombre AS categoria_nombre, m.nombre AS marca_nombre
        FROM producto p
        JOIN categoria c ON p.categoria = c.codigo
        JOIN marca m ON p.marca = m.codigo
    ";
    if ($categoria_filtro > 0) {
        $query .= " WHERE p.categoria = $categoria_filtro";
    }
    $result = mysqli_query($conexion, $query);
    
    ob_start();
    include 'product_grid_template.php'; // Separar template en archivo independiente
    echo ob_get_clean();
    exit;
}

// MANEJO DEL CARRITO
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    $id = intval($_POST['product_id']);
    $qty = intval($_POST['quantity']);
    if ($qty < 1) $qty = 1;

    // Verificar stock disponible
    $reserved_query = "SELECT SUM(cantidad) as reservado FROM reserva_carrito WHERE producto_id=$id AND estado='reservado'";
    $reserved_result = mysqli_query($conexion, $reserved_query);
    $reserved = intval(mysqli_fetch_assoc($reserved_result)['reservado'] ?? 0);

    $stock_check = mysqli_query($conexion, "SELECT stock FROM producto WHERE codigo = $id");
    $row_stock = mysqli_fetch_assoc($stock_check);
    $stock = intval($row_stock['stock'] ?? 0);

    $available_stock = $stock - $reserved;

    // Cantidad ya reservada por este usuario/sesión
    $my_reserved_query = "SELECT SUM(cantidad) as my_reserved FROM reserva_carrito WHERE producto_id=$id AND session_id='$session_id' AND estado='reservado'";
    $my_reserved_result = mysqli_query($conexion, $my_reserved_query);
    $my_reserved = intval(mysqli_fetch_assoc($my_reserved_result)['my_reserved'] ?? 0);

    if (($qty + $my_reserved) > $available_stock) {
        echo json_encode(['success' => false, 'error' => 'No hay suficiente stock disponible']);
        exit;
    }

    // Reservar producto por 30 minutos
    $expiracion = date('Y-m-d H:i:s', strtotime("+30 minutes"));
    mysqli_query($conexion, "
        INSERT INTO reserva_carrito (session_id, producto_id, cantidad, fecha_reserva, expiracion, estado)
        VALUES ('$session_id', $id, $qty, '$now', '$expiracion', 'reservado')
    ");

    echo json_encode(['success' => true]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_from_cart'])) {
    $id = intval($_POST['product_id']);
    mysqli_query($conexion, "UPDATE reserva_carrito SET estado='liberado' WHERE producto_id=$id AND session_id='$session_id' AND estado='reservado'");
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit;
}

// DATOS PRINCIPALES
// Obtener categorías
$categorias = [];
$cat_result = mysqli_query($conexion, "SELECT codigo, nombre FROM categoria");
while ($cat = mysqli_fetch_assoc($cat_result)) {
    $categorias[] = $cat;
}

// Filtros de búsqueda
$categoria_filtro = isset($_GET['categoria']) ? intval($_GET['categoria']) : 0;
$buscar = isset($_GET['buscar']) ? trim($_GET['buscar']) : '';

// Consulta de productos con filtros
$query = "
    SELECT p.codigo, p.nombre, p.valor, p.presentacion, p.tamañoUnidad, p.unidad, p.stock, p.imagen,
           c.codigo AS categoria_codigo, c.nombre AS categoria_nombre, m.nombre AS marca_nombre
    FROM producto p
    JOIN categoria c ON p.categoria = c.codigo
    JOIN marca m ON p.marca = m.codigo
";

$wheres = [];
if ($categoria_filtro > 0) {
    $wheres[] = "p.categoria = $categoria_filtro";
}
if ($buscar !== '') {
    $buscar_escaped = mysqli_real_escape_string($conexion, $buscar);
    $wheres[] = "(p.nombre LIKE '%$buscar_escaped%' OR p.presentacion LIKE '%$buscar_escaped%' OR m.nombre LIKE '%$buscar_escaped%')";
}
if (!empty($wheres)) {
    $query .= " WHERE " . implode(' AND ', $wheres);
}

$result = mysqli_query($conexion, $query);
if (!$result) {
    echo "Error en la consulta: " . mysqli_error($conexion);
    exit;
}

// Obtener productos del carrito
$cart_products = [];
$cart_total = 0;
$cart_query = "
    SELECT rc.producto_id AS codigo, p.nombre, p.valor, p.imagen, SUM(rc.cantidad) as quantity
    FROM reserva_carrito rc
    JOIN producto p ON rc.producto_id = p.codigo
    WHERE rc.session_id='$session_id' AND rc.estado='reservado'
    GROUP BY rc.producto_id, p.nombre, p.valor, p.imagen
";
$cart_result = mysqli_query($conexion, $cart_query);
while ($row = mysqli_fetch_assoc($cart_result)) {
    $row['subtotal'] = $row['valor'] * $row['quantity'];
    $cart_total += $row['subtotal'];
    $cart_products[] = $row;
}
$cart_count = array_sum(array_column($cart_products, 'quantity'));
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <title>Kongelados - Productos Congelados</title>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="format-detection" content="telephone=no">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="author" content="Kongelados">
    <meta name="description" content="Tienda online de productos congelados de alta calidad">

    <!-- CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@9/swiper-bundle.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="css/vendor.css">
    <link rel="stylesheet" type="text/css" href="style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;700&family=Open+Sans:ital,wght@0,400;0,700;1,400;1,700&display=swap" rel="stylesheet">

    <style>
        .shake { animation: shake 0.4s; }
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }
        .stylish-autocomplete {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #ddd;
            border-top: none;
            max-height: 200px;
            overflow-y: auto;
            z-index: 1000;
        }
        .stylish-autocomplete div {
            padding: 10px;
            cursor: pointer;
            border-bottom: 1px solid #eee;
        }
        .stylish-autocomplete div:hover {
            background-color: #f5f5f5;
        }
    </style>
</head>
<body>
    <!-- Preloader -->
    <div class="preloader-wrapper">
        <div class="preloader"></div>
    </div>

    <!-- Carrito Offcanvas -->
    <div class="offcanvas offcanvas-end" data-bs-scroll="true" tabindex="-1" id="offcanvasCart">
        <div class="offcanvas-header justify-content-center">
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Cerrar"></button>
        </div>
        <div class="offcanvas-body">
            <h4 class="d-flex justify-content-between align-items-center mb-3">
                <span class="text-primary">Tu carrito</span>
                <span class="badge bg-primary rounded-pill"><?php echo $cart_count; ?></span>
            </h4>
            
            <ul class="list-group mb-3" id="cart-list">
                <?php if (!empty($cart_products)): ?>
                    <?php foreach ($cart_products as $item): ?>
                        <li class="list-group-item d-flex justify-content-between lh-sm align-items-center">
                            <div>
                                <h6 class="my-0"><?php echo htmlspecialchars($item['nombre']); ?></h6>
                                <small class="text-body-secondary">Cantidad: <?php echo $item['quantity']; ?></small>
                            </div>
                            <span class="text-body-secondary me-2"><?php echo number_format($item['subtotal'], 0); ?> COP</span>
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="remove_from_cart" value="1">
                                <input type="hidden" name="product_id" value="<?php echo $item['codigo']; ?>">
                                <button type="submit" class="btn btn-sm btn-danger" title="Eliminar">&times;</button>
                            </form>
                        </li>
                    <?php endforeach; ?>
                    <li class="list-group-item d-flex justify-content-between">
                        <span>Total</span>
                        <strong><?php echo number_format($cart_total, 0); ?> COP</strong>
                    </li>
                <?php else: ?>
                    <li class="list-group-item text-center">El carrito está vacío.</li>
                <?php endif; ?>
            </ul>
            
            <?php if (!empty($cart_products)): ?>
                <button onclick="procesarPago()" class="w-100 btn btn-primary btn-lg" id="btn-pagar">
                    Proceder con el pago
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Header -->
    <header>
        <div class="container-fluid">
            <div class="row py-3 border-bottom">
                <!-- Logo -->
                <div class="col-sm-4 col-lg-2 text-center text-sm-start d-flex gap-3 justify-content-center justify-content-md-start">
                    <div class="d-flex align-items-center my-3 my-sm-0">
                        <a href="index.php">
                            <img src="images/CONGELADOSIA-removebg-preview.png" alt="Kongelados Logo" class="img-fluid">
                        </a>
                    </div>
                </div>
                
                <!-- Búsqueda -->
                <div class="col-sm-6 offset-sm-2 offset-md-0 col-lg-4">
                    <div class="search-bar row bg-light p-2 rounded-4 align-items-center">
                        <div class="col-md-4 d-none d-md-block">
                            <select class="form-select border-0 bg-transparent" name="categoria" id="categoria-select">
                                <option value="0"<?php if ($categoria_filtro == 0) echo ' selected'; ?>>Todas las Categorías</option>
                                <?php foreach ($categorias as $cat): ?>
                                    <option value="<?php echo $cat['codigo']; ?>"<?php if ($categoria_filtro == $cat['codigo']) echo ' selected'; ?>>
                                        <?php echo htmlspecialchars($cat['nombre']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-8 position-relative" style="z-index:100;">
                            <form id="search-form" action="index.php" method="get" autocomplete="off">
                                <div class="input-group">
                                    <input type="text" class="form-control stylish-input" name="buscar" id="buscador-productos" 
                                           placeholder="Buscar productos..." autocomplete="off" value="<?php echo htmlspecialchars($buscar); ?>">
                                    <span class="input-group-text stylish-icon">
                                        <svg width="24" height="24"><use xlink:href="#search"></use></svg>
                                    </span>
                                </div>
                                <div id="autocomplete-list" class="autocomplete-items stylish-autocomplete"></div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Navegación y Carrito -->
                <div class="col-lg-4">
                    <ul class="navbar-nav list-unstyled d-flex flex-row gap-3 gap-lg-5 justify-content-center flex-wrap align-items-center mb-0 fw-bold text-uppercase text-dark">
                        <li class="nav-item active">
                            <a href="index.php" class="nav-link">Inicio</a>
                        </li>
                    </ul>
                </div>
                
                <div class="col-sm-8 col-lg-2 d-flex gap-5 align-items-center justify-content-center justify-content-sm-end">
                    <ul class="d-flex justify-content-end list-unstyled m-0">
                        <li>
                            <a href="#" class="p-2 mx-1" data-bs-toggle="offcanvas" data-bs-target="#offcanvasCart" aria-controls="offcanvasCart">
                                <svg width="24" height="24"><use xlink:href="#shopping-bag"></use></svg>
                                <?php if ($cart_count > 0): ?>
                                    <span class="badge bg-danger rounded-pill position-absolute translate-middle"><?php echo $cart_count; ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </header>
    
    <!-- Banner Principal -->
    <section style="background-image: url('images/banner-1.jpg');background-repeat: no-repeat;background-size: cover;">
        <div class="container-lg">
            <div class="row">
                <div class="col-lg-6 pt-5 mt-5">
                    <h2 class="display-1 ls-1">
                        <span class="fw-bold text-primary">Productos</span> Congelados 
                        <span class="fw-bold">de Calidad</span>
                    </h2>
                    <p class="fs-4">Frescura y sabor en cada producto.</p>
                    <div class="d-flex gap-3">
                        <a href="#productos" class="btn btn-primary text-uppercase fs-6 rounded-pill px-4 py-3 mt-3">Ver Productos</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Sección de Productos -->
    <section class="pb-5" id="productos">
        <div class="container-lg">
            <div class="row">
                <div class="col-md-12">
                    <div class="section-header d-flex flex-wrap justify-content-between my-4">   
                        <h2 class="section-title">Nuestros Productos</h2>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-12">
                    <div class="product-grid row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-3 row-cols-xl-4 row-cols-xxl-5" id="productos-lista">
                        <?php while ($producto = mysqli_fetch_assoc($result)): ?>
                            <div class="col">
                                <div class="product-item">
                                    <figure>
                                        <a href="index.php" title="<?php echo htmlspecialchars($producto['nombre']); ?>"> 
                                            <img src="<?php echo !empty($producto['imagen']) ? 'images/' . htmlspecialchars($producto['imagen']) : 'images/product-default.png'; ?>" 
                                                alt="<?php echo htmlspecialchars($producto['nombre']); ?>" class="tab-image">
                                        </a>
                                    </figure>
                                    <div class="d-flex flex-column text-center">
                                        <h3 class="fs-6 fw-normal"><?php echo htmlspecialchars($producto['nombre']); ?></h3>
                                        <div class="text-muted small"><?php echo htmlspecialchars($producto['tamañoUnidad']) . ' ' . htmlspecialchars($producto['unidad']); ?></div>
                                        <div class="mb-2">
                                            <?php if ($producto['stock'] <= 0): ?>
                                                <span class="badge bg-danger">Sin stock</span>
                                            <?php else: ?>
                                                <span class="badge bg-success">Stock: <?php echo $producto['stock']; ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="d-flex justify-content-center align-items-center gap-2">
                                            <span class="text-dark fw-semibold">$<?php echo number_format($producto['valor'], 0); ?> COP</span>
                                        </div>
                                        
                                        <div class="button-area p-3 pt-0">
                                            <div class="row g-1 mt-2">
                                                <div class="col-3">
                                                    <input type="number" name="quantity" class="form-control border-dark-subtle input-number quantity"
                                                        value="1" min="1" max="<?php echo $producto['stock']; ?>"
                                                        <?php if ($producto['stock'] <= 0) echo 'disabled'; ?>>
                                                </div>
                                                <div class="col-9">
                                                    <a href="#" class="btn btn-primary rounded-1 p-2 fs-7 btn-cart w-100"
                                                        data-producto-id="<?php echo $producto['codigo']; ?>"
                                                        <?php if ($producto['stock'] <= 0) echo 'disabled tabindex="-1" aria-disabled="true"'; ?>>
                                                        <svg width="18" height="18"><use xlink:href="#cart"></use></svg> 
                                                        <?php echo ($producto['stock'] <= 0) ? 'Sin Stock' : 'Añadir'; ?>
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <div id="footer-bottom">
        <div class="container-lg">
            <div class="row">
                <div class="col-md-6 copyright">
                    <p>© 2025 Kongelados. Todos los derechos reservados.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="js/jquery-1.11.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/swiper@9/swiper-bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/plugins.js"></script>
    <script src="js/script.js"></script>
    
    <script>
    $(document).ready(function() {
        
        // PROCESAR PAGO AUTOMÁTICAMENTE DESPUÉS DEL LOGIN
        <?php if (isset($_SESSION['process_payment_after_login']) && $_SESSION['process_payment_after_login']): ?>
        <?php unset($_SESSION['process_payment_after_login']); // Limpiar flag ?>
        // Mostrar mensaje y procesar pago automáticamente
        setTimeout(function() {
            alert('¡Bienvenido! Procesando tu pago...');
            procesarPago();
        }, 1000);
        <?php endif; ?>
        
        // FUNCIÓN PARA PROCESAR PAGO
        window.procesarPago = function() {
            const btnPagar = document.getElementById('btn-pagar');
            const originalText = btnPagar.innerHTML;
            
            btnPagar.innerHTML = 'Procesando...';
            btnPagar.disabled = true;

            fetch('procesar_pago.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' }
            })
            .then(response => response.json())
            .then(data => {
                console.log('Respuesta del pago:', data);
                
                if (data.success) {
                    // ÉXITO: Redirigir a Wompi
                    alert(`Redirigiendo al pago. Total: $${data.amount.toLocaleString()} COP`);
                    window.location.href = data.payment_url;
                } else if (data.error === 'auth_required') {
                    // Necesita login
                    alert('Debes iniciar sesión para continuar con el pago');
                    window.location.href = 'acceso.php';
                } else if (data.expired_cart) {
                    // Carrito expirado
                    alert(data.error);
                    location.reload();
                } else if (data.stock_issue) {
                    // Problema de stock
                    alert(data.error);
                    location.reload();
                } else {
                    // Otros errores
                    alert('Error: ' + data.error);
                    if (data.details) alert('Detalles: ' + data.details);
                    console.error('Error completo:', data);
                }
            })
            .catch(error => {
                console.error('Error de red:', error);
                alert('Error de conexión. Intenta nuevamente.');
            })
            .finally(() => {
                // Restaurar botón
                btnPagar.innerHTML = originalText;
                btnPagar.disabled = false;
            });
        };

        // AÑADIR PRODUCTO AL CARRITO
        $(document).on('click', '.btn-cart', function(e) {
            e.preventDefault();
            
            if ($(this).is('[disabled]')) {
                $(this).addClass('shake');
                setTimeout(() => $(this).removeClass('shake'), 400);
                return;
            }

            const $row = $(this).closest('.product-item');
            const productId = $(this).data('producto-id');
            const quantity = $row.find('input.quantity').val() || 1;
            const $btn = $(this);
            
            const originalText = $btn.html();
            $btn.html('<div class="spinner-border spinner-border-sm" role="status"></div>');
            $btn.prop('disabled', true);

            $.post('', { 
                add_to_cart: 1, 
                product_id: productId, 
                quantity: quantity 
            }, function(resp) {
                if (resp && resp.success) {
                    // Mostrar mensaje de éxito
                    $btn.html('✓ Añadido');
                    $btn.removeClass('btn-primary').addClass('btn-success');
                    
                    // Recargar página después de 1 segundo
                    setTimeout(() => location.reload(), 1000);
                } else if (resp && resp.error) {
                    $btn.addClass('shake');
                    setTimeout(() => $btn.removeClass('shake'), 400);
                    alert(resp.error);
                    
                    // Restaurar botón
                    $btn.html(originalText);
                    $btn.prop('disabled', false);
                }
            }, 'json').fail(function() {
                alert('Error de conexión. Intenta nuevamente.');
                $btn.html(originalText);
                $btn.prop('disabled', false);
            });
        });

        // FILTRAR POR CATEGORÍA
        $('#categoria-select').on('change', function() {
            const categoria = $(this).val();
            $('#productos-lista').html('<div class="text-center py-5 w-100"><div class="spinner-border" role="status"></div><p>Cargando productos...</p></div>');
            
            $.get('', { ajax_categoria: 1, categoria: categoria }, function(html) {
                $('#productos-lista').html(html);
            }).fail(function() {
                $('#productos-lista').html('<div class="text-center py-5 w-100 text-danger">Error al cargar productos</div>');
            });
        });

        // AUTOCOMPLETADO DE BÚSQUEDA
        const $input = $('#buscador-productos');
        const $list = $('#autocomplete-list');
        
        $input.on('input', function() {
            const val = $(this).val();
            $list.empty();
            
            if (val.length < 2) return;
            
            $.get('', { ajax_search: 1, term: val }, function(data) {
                if (data && data.length) {
                    data.forEach(function(item) {
                        const $div = $('<div>')
                            .html(`<strong>${item.nombre}</strong><br><small>${item.marca_nombre} - ${item.presentacion}</small>`)
                            .attr('data-id', item.codigo);
                        $list.append($div);
                    });
                } else {
                    $list.append('<div class="text-muted">No se encontraron productos</div>');
                }
            }, 'json');
        });

        // Click en autocompletado
        $list.on('click', 'div[data-id]', function() {
            const id = $(this).data('id');
            window.location.href = 'producto.php?id=' + id;
        });

        // Ocultar autocompletado al perder foco
        $input.on('blur', function() {
            setTimeout(() => $list.empty(), 200);
        });

    });
    </script>
</body>
</html>