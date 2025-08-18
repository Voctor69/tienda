<?php
session_start();
$conexion = mysqli_connect("localhost", "root", "", "testbdpa", 3306);

// cargar conexión a la base de datos
require_once(__DIR__ . "/../config/conexion.php");

$request = $_SERVER['REQUEST_URI'];

if (strpos($request, '/checkout') !== false) {
    require __DIR__ . '/../src/controllers/checkout.php';
    exit;
}


if (!$conexion) {
    echo "No se conectó con la base de datos";
    exit;
}

$session_id = session_id();
$now = date('Y-m-d H:i:s');

// Libera reservas expiradas
mysqli_query($conexion, "UPDATE reserva_carrito SET estado='liberado' WHERE expiracion < '$now' AND estado='reservado'");

// Obtener categorías para el select
$categorias = [];
$cat_result = mysqli_query($conexion, "SELECT codigo, nombre FROM categoria");
while ($cat = mysqli_fetch_assoc($cat_result)) {
    $categorias[] = $cat;
}

// Obtener categoría seleccionada y búsqueda
$categoria_filtro = isset($_GET['categoria']) ? intval($_GET['categoria']) : 0;
$buscar = isset($_GET['buscar']) ? trim($_GET['buscar']) : '';

// Construir consulta de productos según filtro y búsqueda
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
    // Busca en nombre, presentacion, marca
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    $id = intval($_POST['product_id']);
    $qty = intval($_POST['quantity']);
    if ($qty < 1) $qty = 1;

    // Suma el stock reservado por otros carritos activos
    $reserved_query = "SELECT SUM(cantidad) as reservado FROM reserva_carrito WHERE producto_id=$id AND estado='reservado'";
    $reserved_result = mysqli_query($conexion, $reserved_query);
    $reserved = intval(mysqli_fetch_assoc($reserved_result)['reservado'] ?? 0);

    // Stock actual del producto
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

    // Reserva el stock por 30 minutos
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
    // Libera reservas de ese producto para la sesión actual
    mysqli_query($conexion, "UPDATE reserva_carrito SET estado='liberado' WHERE producto_id=$id AND session_id='$session_id' AND estado='reservado'");
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit;
}

$cart_products = [];
$cart_total = 0;
// Obtén los productos reservados para esta sesión
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

// Endpoint AJAX para autocompletar producto
if (isset($_GET['ajax_search']) && isset($_GET['term'])) {
    $term = mysqli_real_escape_string($conexion, $_GET['term']);
    $query = "
        SELECT codigo, nombre 
        FROM producto 
        WHERE nombre LIKE '%$term%' 
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

// Endpoint AJAX para filtrar productos por categoría
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
    ?>
    <?php while ($producto = mysqli_fetch_assoc($result)): ?>
      <div class="col">
        <div class="product-item">
          <figure>
            <a href="index.php" title="<?php echo htmlspecialchars($producto['nombre']); ?>"> 
              <img src="<?php echo !empty($producto['imagen']) ? 'public/assets/images/' . htmlspecialchars($producto['imagen']) : 'public/assets/images/product-default.png'; ?>" 
                  alt="<?php echo htmlspecialchars($producto['nombre']); ?>" class="tab-image">
            </a>
          </figure>
          <div class="d-flex flex-column text-center">
            <h3 class="fs-6 fw-normal"><?php echo htmlspecialchars($producto['nombre']); ?></h3>
            <div>
              <span class="rating">
                <svg width="18" height="18" class="text-warning"><use xlink:href="#star-full"></use></svg>
                <svg width="18" height="18" class="text-warning"><use xlink:href="#star-full"></use></svg>
                <svg width="18" height="18" class="text-warning"><use xlink:href="#star-full"></use></svg>
                <svg width="18" height="18" class="text-warning"><use xlink:href="#star-full"></use></svg>
                <svg width="18" height="18" class="text-warning"><use xlink:href="#star-half"></use></svg>
              </span>
              <span>(<?php echo rand(15, 300); ?>)</span>
            </div>
            <div class="mb-2">
              <?php if ($producto['stock'] <= 0): ?>
                <span class="badge bg-danger">Sin stock</span>
              <?php endif; ?>
            </div>
            <div class="d-flex justify-content-center align-items-center gap-2">
              <span class="text-dark fw-semibold"><?php echo number_format($producto['valor'], 2); ?> COP</span>
            </div>
            <div class="button-area p-3 pt-0">
              <div class="row g-1 mt-2">
                <div class="col-3">
                  <input type="number" name="quantity" class="form-control border-dark-subtle input-number quantity"
                    value="1" min="1" max="<?php echo $producto['stock']; ?>"
                    <?php if ($producto['stock'] <= 0) echo 'disabled'; ?>>
                </div>
                <div class="col-7">
                  <a href="#" class="btn btn-primary rounded-1 p-2 fs-7 btn-cart"
                    data-producto-id="<?php echo $producto['codigo']; ?>"
                    <?php if ($producto['stock'] <= 0) echo 'disabled tabindex="-1" aria-disabled="true"'; ?>>
                    <svg width="18" height="18"><use xlink:href="#cart"></use></svg> Añadir al carrito
                  </a>
                </div>
                <div class="col-2">
                  <a href="#" class="btn btn-outline-dark rounded-1 p-2 fs-6"><svg width="18" height="18"><use xlink:href="#heart"></use></svg></a>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    <?php endwhile; ?>
    <?php
    echo ob_get_clean();
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
  <head>
    <title>Kongelados</title>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="format-detection" content="telephone=no">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="author" content="Kongelados">
    <meta name="keywords" content="">
    <meta name="description" content="">

    <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.0/dist/jquery.min.js"></script>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@9/swiper-bundle.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-KK94CHFLLe+nY2dmCWGMq91rCGa5gtU4mk92HdvYe+M/SXH301p5ILy+dN9+nJOZ" crossorigin="anonymous">
    <link rel="stylesheet" type="text/css" href="public/assets/css/vendor.css">
    <link rel="stylesheet" type="text/css" href="public/assets/css/style.css">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;700&family=Open+Sans:ital,wght@0,400;0,700;1,400;1,700&display=swap" rel="stylesheet">

  </head>
  <body>

    <svg xmlns="http://www.w3.org/2000/svg" style="display: none;">
      <defs>
        <symbol xmlns="http://www.w3.org/2000/svg" id="facebook" viewBox="0 0 24 24"><path fill="currentColor" d="M15.12 5.32H17V2.14A26.11 26.11 0 0 0 14.26 2c-2.72 0-4.58 1.66-4.58 4.7v2.62H6.61v3.56h3.07V22h3.68v-9.12h3.06l.46-3.56h-3.52V7.05c0-1.05.28-1.73 1.76-1.73Z"/></symbol>
        <symbol xmlns="http://www.w3.org/2000/svg" id="instagram" viewBox="0 0 24 24"><path fill="currentColor" d="M17.34 5.46a1.2 1.2 0 1 0 1.2 1.2a1.2 1.2 0 0 0-1.2-1.2Zm4.6 2.42a7.59 7.59 0 0 0-.46-2.43a4.94 4.94 0 0 0-1.16-1.77a4.7 4.7 0 0 0-1.77-1.15a7.3 7.3 0 0 0-2.43-.47C15.06 2 14.72 2 12 2s-3.06 0-4.12.06a7.3 7.3 0 0 0-2.43.47a4.78 4.78 0 0 0-1.77 1.15a4.7 4.7 0 0 0-1.15 1.77a7.3 7.3 0 0 0-.47 2.43C2 8.94 2 9.28 2 12s0 3.06.06 4.12a7.3 7.3 0 0 0 .47 2.43a4.7 4.7 0 0 0 1.15 1.77a4.78 4.78 0 0 0 1.77 1.15a7.3 7.3 0 0 0 2.43.47C8.94 22 9.28 22 12 22s3.06 0 4.12-.06a7.3 7.3 0 0 0 2.43-.47a4.7 4.7 0 0 0 1.77-1.15a4.85 4.85 0 0 0 1.16-1.77a7.59 7.59 0 0 0 .46-2.43c0-1.06.06-1.4.06-4.12s0-3.06-.06-4.12ZM20.14 16a5.61 5.61 0 0 1-.34 1.86a3.06 3.06 0 0 1-.75 1.15a3.19 3.19 0 0 1-1.15.75a5.61 5.61 0 0 1-1.86.34c-1 .05-1.37.06-4 .06s-3 0-4-.06a5.73 5.73 0 0 1-1.94-.3a3.27 3.27 0 0 1-1.1-.75a3 3 0 0 1-.74-1.15a5.54 5.54 0 0 1-.4-1.9c0-1-.06-1.37-.06-4s0-3 .06-4a5.54 5.54 0 0 1 .35-1.9A3 3 0 0 1 5 5a3.14 3.14 0 0 1 1.1-.8A5.73 5.73 0 0 1 8 3.86c1 0 1.37-.06 4-.06s3 0 4 .06a5.61 5.61 0 0 1 1.86.34a3.06 3.06 0 0 1 1.19.8a3.06 3.06 0 0 1 .75 1.1a5.61 5.61 0 0 1 .34 1.9c.05 1 .06 1.37.06 4s-.01 3-.06 4ZM12 6.87A5.13 5.13 0 1 0 17.14 12A5.12 5.12 0 0 0 12 6.87Zm0 8.46A3.33 3.33 0 1 1 15.33 12A3.33 3.33 0 0 1 12 15.33Z"/></symbol>
        

        <symbol xmlns="http://www.w3.org/2000/svg" id="menu" viewBox="0 0 24 24"><path fill="currentColor" d="M2 6a1 1 0 0 1 1-1h18a1 1 0 1 1 0 2H3a1 1 0 0 1-1-1m0 6.032a1 1 0 0 1 1-1h18a1 1 0 1 1 0 2H3a1 1 0 0 1-1-1m1 5.033a1 1 0 1 0 0 2h18a1 1 0 0 0 0-2z"/></symbol>
        <symbol xmlns="http://www.w3.org/2000/svg" id="link" viewBox="0 0 24 24"><path fill="currentColor" d="M12 19a1 1 0 1 0-1-1a1 1 0 0 0 1 1Zm5 0a1 1 0 1 0-1-1a1 1 0 0 0 1 1Zm0-4a1 1 0 1 0-1-1a1 1 0 0 0 1 1Zm-5 0a1 1 0 1 0-1-1a1 1 0 0 0 1 1Zm7-12h-1V2a1 1 0 0 0-2 0v1H8V2a1 1 0 0 0-2 0v1H5a3 3 0 0 0-3 3v14a3 3 0 0 0 3 3h14a3 3 0 0 0 3-3V6a3 3 0 0 0-3-3Zm1 17a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-9h16Zm0-11H4V6a1 1 0 0 1 1-1h1v1a1 1 0 0 0 2 0V5h8v1a1 1 0 0 0 2 0V5h1a1 1 0 0 1 1 1ZM7 15a1 1 0 1 0-1-1a1 1 0 0 0 1 1Zm0 4a1 1 0 1 0-1-1a1 1 0 0 0 1 1Z"/></symbol>
        <symbol xmlns="http://www.w3.org/2000/svg" id="arrow-right" viewBox="0 0 24 24"><path fill="currentColor" d="M17.92 11.62a1 1 0 0 0-.21-.33l-5-5a1 1 0 0 0-1.42 1.42l3.3 3.29H7a1 1 0 0 0 0 2h7.59l-3.3 3.29a1 1 0 0 0 0 1.42a1 1 0 0 0 1.42 0l5-5a1 1 0 0 0 .21-.33a1 1 0 0 0 0-.76Z"/></symbol>
        <symbol xmlns="http://www.w3.org/2000/svg" id="category" viewBox="0 0 24 24"><path fill="currentColor" d="M19 5.5h-6.28l-.32-1a3 3 0 0 0-2.84-2H5a3 3 0 0 0-3 3v13a3 3 0 0 0 3 3h14a3 3 0 0 0 3-3v-10a3 3 0 0 0-3-3Zm1 13a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-13a1 1 0 0 1 1-1h4.56a1 1 0 0 1 .95.68l.54 1.64a1 1 0 0 0 .95.68h7a1 1 0 0 1 1 1Z"/></symbol>
        <symbol xmlns="http://www.w3.org/2000/svg" id="calendar" viewBox="0 0 24 24"><path fill="currentColor" d="M19 4h-2V3a1 1 0 0 0-2 0v1H9V3a1 1 0 0 0-2 0v1H5a3 3 0 0 0-3 3v12a3 3 0 0 0 3 3h14a3 3 0 0 0 3-3V7a3 3 0 0 0-3-3Zm1 15a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-7h16Zm0-9H4V7a1 1 0 0 1 1-1h2v1a1 1 0 0 0 2 0V6h6v1a1 1 0 0 0 2 0V6h2a1 1 0 0 1 1 1Z"/></symbol>
        <symbol xmlns="http://www.w3.org/2000/svg" id="heart" viewBox="0 0 24 24"><path fill="currentColor" d="M20.16 4.61A6.27 6.27 0 0 0 12 4a6.27 6.27 0 0 0-8.16 9.48l7.45 7.45a1 1 0 0 0 1.42 0l7.45-7.45a6.27 6.27 0 0 0 0-8.87Zm-1.41 7.46L12 18.81l-6.75-6.74a4.28 4.28 0 0 1 3-7.3a4.25 4.25 0 0 1 3 1.25a1 1 0 0 0 1.42 0a4.27 4.27 0 0 1 6 6.05Z"/></symbol>
        <symbol xmlns="http://www.w3.org/2000/svg" id="plus" viewBox="0 0 24 24"><path fill="currentColor" d="M19 11h-6V5a1 1 0 0 0-2 0v6H5a1 1 0 0 0 0 2h6v6a1 1 0 0 0 2 0v-6h6a1 1 0 0 0 0-2Z"/></symbol>
        <symbol xmlns="http://www.w3.org/2000/svg" id="minus" viewBox="0 0 24 24"><path fill="currentColor" d="M19 11H5a1 1 0 0 0 0 2h14a1 1 0 0 0 0-2Z"/></symbol>
        <symbol xmlns="http://www.w3.org/2000/svg" id="cart" viewBox="0 0 24 24"><path fill="currentColor" d="M8.5 19a1.5 1.5 0 1 0 1.5 1.5A1.5 1.5 0 0 0 8.5 19ZM19 16H7a1 1 0 0 1 0-2h8.491a3.013 3.013 0 0 0 2.885-2.176l1.585-5.55A1 1 0 0 0 19 5H6.74a3.007 3.007 0 0 0-2.82-2H3a1 1 0 0 0 0 2h.921a1.005 1.005 0 0 1 .962.725l.155.545v.005l1.641 5.742A3 3 0 0 0 7 18h12a1 1 0 0 0 0-2Zm-1.326-9l-1.22 4.274a1.005 1.005 0 0 1-.963.726H8.754l-.255-.892L7.326 7ZM16.5 19a1.5 1.5 0 1 0 1.5 1.5a1.5 1.5 0 0 0-1.5-1.5Z"/></symbol>
        <symbol xmlns="http://www.w3.org/2000/svg" id="check" viewBox="0 0 24 24"><path fill="currentColor" d="M18.71 7.21a1 1 0 0 0-1.42 0l-7.45 7.46l-3.13-3.14A1 1 0 1 0 5.29 13l3.84 3.84a1 1 0 0 0 1.42 0l8.16-8.16a1 1 0 0 0 0-1.47Z"/></symbol>
        <symbol xmlns="http://www.w3.org/2000/svg" id="trash" viewBox="0 0 24 24"><path fill="currentColor" d="M10 18a1 1 0 0 0 1-1v-6a1 1 0 0 0-2 0v6a1 1 0 0 0 1 1ZM20 6h-4V5a3 3 0 0 0-3-3h-2a3 3 0 0 0-3 3v1H4a1 1 0 0 0 0 2h1v11a3 3 0 0 0 3 3h8a3 3 0 0 0 3-3V8h1a1 1 0 0 0 0-2ZM10 5a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v1h-4Zm7 14a1 1 0 0 1-1 1H8a1 1 0 0 1-1-1V8h10Zm-3-1a1 1 0 0 0 1-1v-6a1 1 0 0 0-2 0v6a1 1 0 0 0 1 1Z"/></symbol>
        <symbol xmlns="http://www.w3.org/2000/svg" id="search" viewBox="0 0 24 24"><path fill="currentColor" d="M21.71 20.29L18 16.61A9 9 0 1 0 16.61 18l3.68 3.68a1 1 0 0 0 1.42 0a1 1 0 0 0 0-1.39ZM11 18a7 7 0 1 1 7-7a7 7 0 0 1-7 7Z"/></symbol>
        <symbol xmlns="http://www.w3.org/2000/svg" id="close" viewBox="0 0 15 15"><path fill="currentColor" d="M7.953 3.788a.5.5 0 0 0-.906 0L6.08 5.85l-2.154.33a.5.5 0 0 0-.283.843l1.574 1.613l-.373 2.284a.5.5 0 0 0 .736.518l1.92-1.063l1.921 1.063a.5.5 0 0 0 .736-.519l-.373-2.283l1.574-1.613a.5.5 0 0 0-.283-.844L8.921 5.85l-.968-2.062Z"/></symbol>
        
        <symbol xmlns="http://www.w3.org/2000/svg" id="package" viewBox="0 0 48 48"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" d="m24 13.264l7.288 4.21L24 21.681l-7.288-4.209Z"/><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" d="M16.712 17.473v8.418L24 30.101l7.288-4.21v-8.418M24 30.1v-8.418"/><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" d="M40.905 21.405a16.905 16.905 0 1 0-23.389 15.611L24 43.5l6.484-6.484a16.906 16.906 0 0 0 10.42-15.611"/></symbol>
        <symbol xmlns="http://www.w3.org/2000/svg" id="secure" viewBox="0 0 48 48"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" d="M14.134 36V20.11h19.732M19.279 36h14.587V25.45"/><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" d="m19.246 26.606l4.135 4.135l5.373-5.372m-8.934-9.282a4.087 4.087 0 1 1 8.174 0m0 0v4.023m-8.172-4.108v4.108"/><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" d="M30.288 44.566a21.516 21.516 0 1 1 9.69-6.18"/></symbol>
        <symbol xmlns="http://www.w3.org/2000/svg" id="quality" viewBox="0 0 48 48"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" d="m30.59 13.45l4.77 2.94L24 34.68l-10.33-7l3.11-4.6l5.52 3.71l8.26-13.38Z"/><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" d="M24 4.5s-11.26 2-15.25 2v20a11.16 11.16 0 0 0 .8 4.1a15 15 0 0 0 2 3.61a22 22 0 0 0 2.81 3.07a34.47 34.47 0 0 0 3 2.48a34 34 0 0 0 2.89 1.86c1 .59 1.71 1 2.13 1.19l1 .49a1.44 1.44 0 0 0 1.24 0l1-.49c.42-.2 1.13-.6 2.13-1.19a34 34 0 0 0 2.89-1.86a34.47 34.47 0 0 0 3-2.48a22 22 0 0 0 2.81-3.07a15 15 0 0 0 2-3.61a11.16 11.16 0 0 0 .8-4.1v-20c-3.99.03-15.25-2-15.25-2"/></symbol>
        <symbol xmlns="http://www.w3.org/2000/svg" id="savings" viewBox="0 0 48 48"><circle cx="24" cy="24" r="21.5" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"/><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" d="M12.5 23.684a3.298 3.298 0 0 1 5.63-2.332l3.212 3.212h0l8.53-8.53a3.298 3.298 0 0 1 5.628 2.333h0c0 .875-.348 1.714-.966 2.333L22.983 32.25a2.321 2.321 0 0 1-3.283 0l-6.234-6.233a3.298 3.298 0 0 1-.966-2.333"/></symbol>
        <symbol xmlns="http://www.w3.org/2000/svg" id="offers" viewBox="0 0 48 48"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" d="m41.556 39.297l-22.022 3.11a1.097 1.097 0 0 1-1.245-.97l-2.352-22.311a1.097 1.097 0 0 1 1.08-1.213l24.238-.229a1.097 1.097 0 0 1 1.108 1.09l.137 19.429c.004.55-.4 1.017-.944 1.094M26.1 25.258v2.579m8.494-2.731v2.175"/><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" d="M34.343 32.346c-1.437.828-1.926 1.198-2.774 1.988c-1.19-.457-2.284-1.228-3.797-1.456m-15.953 8.721l-3.49-1.6a1.12 1.12 0 0 1-.643-.863L5.511 23.593c-.056-.4.108-.8.43-1.046l3.15-2.406a1.257 1.257 0 0 1 2.014.874l1.966 19.69a.887.887 0 0 1-1.252.894m11.989-28.112c.214-.456.964-1.716 2.76-3.618c3.108-3.323 4.26-4.288 4.26-4.288s1.42.75 3.27 3.109c1.876 2.358 1.93 3.832 1.93 3.832s.67-.08-4.797 1.688c-3.055.991-4.368 1.152-4.931 1.152"/><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" d="M26.97 17.828v-.054c0-.884-.241-1.715-.67-2.412c-.563-.91-1.447-1.608-2.492-1.876a3.58 3.58 0 0 0-1.072-.16c-.429 0-.858.053-1.233.214c-1.152.348-2.063 1.18-2.573 2.278a4.747 4.747 0 0 0-.428 1.956v.134"/><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" d="M18.93 15.818c-.562-.107-1.5-.349-3.135-.884c-2.304-.75-3.43-1.528-3.43-1.528s-.456-1.393 1.045-3.296s2.653-2.52 2.653-2.52s.911.778 3.43 3.485c1.26 1.313 1.796 2.09 2.01 2.465h.027"/></symbol>
        
        <symbol xmlns="http://www.w3.org/2000/svg" id="user" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="9" r="3"/><circle cx="12" cy="12" r="10"/><path stroke-linecap="round" d="M17.97 20c-.16-2.892-1.045-5-5.97-5s-5.81 2.108-5.97 5"/></g></symbol>
        <symbol xmlns="http://www.w3.org/2000/svg" id="wishlist" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 16.09v-4.992c0-4.29 0-6.433-1.318-7.766C18.364 2 16.242 2 12 2C7.757 2 5.636 2 4.318 3.332C3 4.665 3 6.81 3 11.098v4.993c0 3.096 0 4.645.734 5.321c.35.323.792.526 1.263.58c.987.113 2.14-.907 4.445-2.946c1.02-.901 1.529-1.352 2.118-1.47c.29-.06.59-.06.88 0c.59.118 1.099.569 2.118 1.47c2.305 2.039 3.458 3.059 4.445 2.945c.47-.053.913-.256 1.263-.579c.734-.676.734-2.224.734-5.321Z"/><path stroke-linecap="round" d="M15 6H9"/></g></symbol>
        <symbol xmlns="http://www.w3.org/2000/svg" id="shopping-bag" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3.864 16.455c-.858-3.432-1.287-5.147-.386-6.301C4.378 9 6.148 9 9.685 9h4.63c3.538 0 5.306 0 6.207 1.154c.901 1.153.472 2.87-.386 6.301c-.546 2.183-.818 3.274-1.632 3.91c-.814.635-1.939.635-4.189.635h-4.63c-2.25 0-3.375 0-4.189-.635c-.814-.636-1.087-1.727-1.632-3.91Z"/><path d="m19.5 9.5l-.71-2.605c-.274-1.005-.411-1.507-.692-1.886A2.5 2.5 0 0 0 17 4.172C16.56 4 16.04 4 15 4M4.5 9.5l.71-2.605c.274-1.005.411-1.507.692-1.886A2.5 2.5 0 0 1 7 4.172C7.44 4 7.96 4 9 4"/><path d="M9 4a1 1 0 0 1 1-1h4a1 1 0 1 1 0 2h-4a1 1 0 0 1-1-1Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M8 13v4m8-4v4m-4-4v4"/></g></symbol>


        <symbol xmlns="http://www.w3.org/2000/svg" id="delivery" viewBox="0 0 32 32"><path fill="currentColor" d="m29.92 16.61l-3-7A1 1 0 0 0 26 9h-3V7a1 1 0 0 0-1-1H3a1 1 0 0 0-1 1v17a1 1 0 0 0 1 1h2.14a4 4 0 0 0 7.72 0h6.28a4 4 0 0 0 7.72 0H29a1 1 0 0 0 1-1v-7a1 1 0 0 0-.08-.39M23 11h2.34l2.14 5H23ZM9 26a2 2 0 1 1 2-2a2 2 0 0 1-2 2m10.14-3h-6.28a4 4 0 0 0-7.72 0H4V8h17v12.56A4 4 0 0 0 19.14 23M23 26a2 2 0 1 1 2-2a2 2 0 0 1-2 2m5-3h-1.14A4 4 0 0 0 23 20v-2h5Z"/></symbol>
        <symbol xmlns="http://www.w3.org/2000/svg" id="organic" viewBox="0 0 24 24"><path fill="currentColor" d="M0 2.84c1.402 2.71 1.445 5.241 2.977 10.4c1.855 5.341 8.703 5.701 9.21 5.711c.46.726 1.513 1.704 3.926 2.21l.268-1.272c-2.082-.436-2.844-1.239-3.106-1.68l-.005.006c.087-.484 1.523-5.377-1.323-9.352C7.182 3.583 0 2.84 0 2.84m24 .84c-3.898.611-4.293-.92-11.473 3.093a11.879 11.879 0 0 1 2.625 10.05c3.723-1.486 5.166-3.976 5.606-6.466c0 0 1.27-4.716 3.242-6.677M12.527 6.773l-.002-.002v.004zM2.643 5.22s5.422 1.426 8.543 11.543c-2.945-.889-4.203-3.796-4.63-5.168h.006a15.863 15.863 0 0 0-3.92-6.375z"/></symbol>
        <symbol xmlns="http://www.w3.org/2000/svg" id="fresh" viewBox="0 0 24 24"><g fill="none"><path d="M24 0v24H0V0zM12.594 23.258l-.012.002l-.071.035l-.02.004l-.014-.004l-.071-.036c-.01-.003-.019 0-.024.006l-.004.01l-.017.428l.005.02l.01.013l.104.074l.015.004l.012-.004l.104-.074l.012-.016l.004-.017l-.017-.427c-.002-.01-.009-.017-.016-.018m.264-.113l-.014.002l-.184.093l-.01.01l-.003.011l.018.43l.005.012l.008.008l.201.092c.012.004.023 0 .029-.008l.004-.014l-.034-.614c-.003-.012-.01-.02-.02-.022m-.715.002a.023.023 0 0 0-.027.006l-.006.014l-.034.614c0 .012.007.02.017.024l.015-.002l.201-.093l.01-.008l.003-.011l.018-.43l-.003-.012l-.01-.01z"/><path fill="currentColor" d="M20 9a1 1 0 0 1 1 1v1a8 8 0 0 1-8 8H9.414l.793.793a1 1 0 0 1-1.414 1.414l-2.496-2.496a.997.997 0 0 1-.287-.567L6 17.991a.996.996 0 0 1 .237-.638l.056-.06l2.5-2.5a1 1 0 0 1 1.414 1.414L9.414 17H13a6 6 0 0 0 6-6v-1a1 1 0 0 1 1-1m-4.793-6.207l2.5 2.5a1 1 0 0 1 0 1.414l-2.5 2.5a1 1 0 1 1-1.414-1.414L14.586 7H11a6 6 0 0 0-6 6v1a1 1 0 0 0-2 0v-1a8 8 0 0 1 8-8h3.586l-.793-.793a1 1 0 0 1 1.414-1.414"/></g></symbol>

        <symbol xmlns="http://www.w3.org/2000/svg" id="star-full" viewBox="0 0 24 24"><path fill="currentColor" d="m3.1 11.3l3.6 3.3l-1 4.6c-.1.6.1 1.2.6 1.5c.2.2.5.3.8.3c.2 0 .4 0 .6-.1c0 0 .1 0 .1-.1l4.1-2.3l4.1 2.3s.1 0 .1.1c.5.2 1.1.2 1.5-.1c.5-.3.7-.9.6-1.5l-1-4.6c.4-.3 1-.9 1.6-1.5l1.9-1.7l.1-.1c.4-.4.5-1 .3-1.5s-.6-.9-1.2-1h-.1l-4.7-.5l-1.9-4.3s0-.1-.1-.1c-.1-.7-.6-1-1.1-1c-.5 0-1 .3-1.3.8c0 0 0 .1-.1.1L8.7 8.2L4 8.7h-.1c-.5.1-1 .5-1.2 1c-.1.6 0 1.2.4 1.6"/></symbol>
        <symbol xmlns="http://www.w3.org/2000/svg" id="star-half" viewBox="0 0 24 24"><path fill="currentColor" d="m3.1 11.3l3.6 3.3l-1 4.6c-.1.6.1 1.2.6 1.5c.2.2.5.3.8.3c.2 0 .4 0 .6-.1c0 0 .1 0 .1-.1l4.1-2.3l4.1 2.3s.1 0 .1.1c.5.2 1.1.2 1.5-.1c.5-.3.7-.9.6-1.5l-1-4.6c.4-.3 1-.9 1.6-1.5l1.9-1.7l.1-.1c.4-.4.5-1 .3-1.5s-.6-.9-1.2-1h-.1l-4.7-.5l-1.9-4.3s0-.1-.1-.1c-.1-.7-.6-1-1.1-1c-.5 0-1 .3-1.3.8c0 0 0 .1-.1.1L8.7 8.2L4 8.7h-.1c-.5.1-1 .5-1.2 1c-.1.6 0 1.2.4 1.6m8.9 5V5.8l1.7 3.8c.1.3.5.5.8.6l4.2.5l-3.1 2.8c-.3.2-.4.6-.3 1c0 .2.5 2.2.8 4.1l-3.6-2.1c-.2-.2-.3-.2-.5-.2"/></symbol>

      </defs>
    </svg>

    <div class="preloader-wrapper">
      <div class="preloader">
      </div>
    </div>

    <div class="offcanvas offcanvas-end" data-bs-scroll="true" tabindex="-1" id="offcanvasCart">
      <div class="offcanvas-header justify-content-center">
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
      </div>
      <div class="offcanvas-body">
        <div class="order-md-last">
          <h4 class="d-flex justify-content-between align-items-center mb-3">
            <span class="text-primary">Tu carrito</span>
            <span class="badge bg-primary rounded-pill"><?php echo array_sum($_SESSION['cart'] ?? []); ?></span>
          </h4>
          <ul class="list-group mb-3" id="cart-list">
            <?php if (!empty($cart_products)): ?>
              <?php foreach ($cart_products as $item): ?>
  <li class="list-group-item d-flex justify-content-between lh-sm align-items-center">
    <div>
      <h6 class="my-0"><?php echo htmlspecialchars($item['nombre']); ?></h6>
      <small class="text-body-secondary">Cantidad: <?php echo $item['quantity']; ?></small>
    </div>
    <span class="text-body-secondary me-2"><?php echo number_format($item['subtotal'], 2); ?> COP</span>
    <form method="post" style="display:inline;">
      <input type="hidden" name="remove_from_cart" value="1">
      <input type="hidden" name="product_id" value="<?php echo $item['codigo']; ?>">
      <button type="submit" class="btn btn-sm btn-danger" title="Eliminar">
        &times;
      </button>
    </form>
  </li>
<?php endforeach; ?>
              <li class="list-group-item d-flex justify-content-between">
                <span>Total</span>
                <strong><?php echo number_format($cart_total, 2); ?> COP</strong>
              </li>
            <?php else: ?>
              <li class="list-group-item text-center">El carrito está vacío.</li>
            <?php endif; ?>
          </ul>
          <button onclick="procesarPago()" class="w-100 btn btn-primary btn-lg" id="btn-pagar">Proceder con el pago</button>

<script>
function procesarPago() {
    // Cambiar texto del botón
    document.getElementById('btn-pagar').disabled = true;
    document.getElementById('btn-pagar').innerText = 'Procesando...';
    
    fetch('tienda/src/controllers/checkout.php', {
        method: 'POST'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Redirigir a la página de pago de Wompi
            window.location.href = data.payment_url;
        } else {
            if (data.error === 'auth_required') {
                // Redirige al login si no está autenticado
                window.location.href = 'tienda/src/controllers/acceso.php';
            } else {
                alert('Error: ' + data.error);
                // Restaurar botón
                document.getElementById('btn-pagar').disabled = false;
            document.getElementById('btn-pagar').innerText = 'Proceder con el pago';
        }
      }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error de conexión. Intenta nuevamente.');
        // Restaurar botón
        document.getElementById('btn-pagar').disabled = false;
        document.getElementById('btn-pagar').innerText = 'Proceder con el pago';
    });
}
</script>
        </div>
      </div>
    </div>
    
    

    <header>
      <div class="container-fluid">
        <div class="row py-3 border-bottom">
          
          <div class="col-sm-4 col-lg-2 text-center text-sm-start d-flex gap-3 justify-content-center justify-content-md-start">
            <div class="d-flex align-items-center my-3 my-sm-0">
              <a href="index.php">
                
                <img src="public/assets/images/CONGELADOSIA-removebg-preview.png" alt="logo" class="img-fluid">
              </a>
            </div>
            
          </div>
          
          <div class="col-sm-6 offset-sm-2 offset-md-0 col-lg-4">
            <div class="search-bar row bg-light p-2 rounded-4 align-items-center">
              <div class="col-md-4 d-none d-md-block">
                <select class="form-select border-0 bg-transparent" name="categoria" id="categoria-select">
                  <option value="0"<?php if ($categoria_filtro == 0) echo ' selected'; ?>>Todas las Categorias</option>
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
                    <input type="text" class="form-control stylish-input" name="buscar" id="buscador-productos" placeholder="Buscar productos..." autocomplete="off">
                    <span class="input-group-text stylish-icon">
                      <svg width="24" height="24"><use xlink:href="#search"></use></svg>
                    </span>
                  </div>
                  <div id="autocomplete-list" class="autocomplete-items stylish-autocomplete"></div>
                </form>
              </div>
            </div>
          </div>

          <div class="col-lg-4">
            <ul class="navbar-nav list-unstyled d-flex flex-row gap-3 gap-lg-5 justify-content-center flex-wrap align-items-center mb-0 fw-bold text-uppercase text-dark">
              <li class="nav-item active">
                <a href="index.html" class="nav-link">Home</a>
              </li>
              <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle pe-3" role="button" id="pages" data-bs-toggle="dropdown" aria-expanded="false">Pages</a>
                <ul class="dropdown-menu border-0 p-3 rounded-0 shadow" aria-labelledby="pages">
                  <li><a href="index.html" class="dropdown-item">About Us </a></li>
                  <li><a href="index.html" class="dropdown-item">Shop </a></li>
                  <li><a href="index.html" class="dropdown-item">Single Product </a></li>
                  <li><a href="index.html" class="dropdown-item">Cart </a></li>
                  <li><a href="index.html" class="dropdown-item">Checkout </a></li>
                  <li><a href="index.html" class="dropdown-item">Blog </a></li>
                  <li><a href="index.html" class="dropdown-item">Single Post </a></li>
                  <li><a href="index.html" class="dropdown-item">Styles </a></li>
                  <li><a href="index.html" class="dropdown-item">Contact </a></li>
                  <li><a href="index.html" class="dropdown-item">Thank You </a></li>
                  <li><a href="index.html" class="dropdown-item">My Account </a></li>
                  <li><a href="index.html" class="dropdown-item">404 Error </a></li>
                </ul>
              </li>
            </ul>
          </div>
          
          <div class="col-sm-8 col-lg-2 d-flex gap-5 align-items-center justify-content-center justify-content-sm-end">
            <ul class="d-flex justify-content-end list-unstyled m-0">
              <li>
                <a href="#" class="p-2 mx-1">
                  <svg width="24" height="24"><use xlink:href="#user"></use></svg>
                </a>
              </li>
              <li>
                <a href="#" class="p-2 mx-1">
                  <svg width="24" height="24"><use xlink:href="#wishlist"></use></svg>
                </a>
              </li>
              <li>
                <a href="#" class="p-2 mx-1" data-bs-toggle="offcanvas" data-bs-target="#offcanvasCart" aria-controls="offcanvasCart">
                  <svg width="24" height="24"><use xlink:href="#shopping-bag"></use></svg>
                </a>
              </li>
            </ul>
          </div>

        </div>
      </div>
    </header>
    
    <section style="background-image: url('public/assets/images/banner-1.jpg');background-repeat: no-repeat;background-size: cover;">
      <div class="container-lg">
        <div class="row">
          <div class="col-lg-6 pt-5 mt-5">
            <h2 class="display-1 ls-1"><span class="fw-bold text-primary">Organic</span> Foods at your <span class="fw-bold">Doorsteps</span></h2>
            <p class="fs-4">Dignissim massa diam elementum.</p>
            <div class="d-flex gap-3">
              <a href="#" class="btn btn-primary text-uppercase fs-6 rounded-pill px-4 py-3 mt-3">Start Shopping</a>
              <a href="#" class="btn btn-dark text-uppercase fs-6 rounded-pill px-4 py-3 mt-3">Join Now</a>
            </div>
            <div class="row my-5">
              <div class="col">
                <div class="row text-dark">
                  <div class="col-auto"><p class="fs-1 fw-bold lh-sm mb-0">14k+</p></div>
                  <div class="col"><p class="text-uppercase lh-sm mb-0">Product Varieties</p></div>
                </div>
              </div>
              <div class="col">
                <div class="row text-dark">
                  <div class="col-auto"><p class="fs-1 fw-bold lh-sm mb-0">50k+</p></div>
                  <div class="col"><p class="text-uppercase lh-sm mb-0">Happy Customers</p></div>
                </div>
              </div>
              <div class="col">
                <div class="row text-dark">
                  <div class="col-auto"><p class="fs-1 fw-bold lh-sm mb-0">10+</p></div>
                  <div class="col"><p class="text-uppercase lh-sm mb-0">Store Locations</p></div>
                </div>
              </div>
            </div>
          </div>
        </div>
        
        <div class="row row-cols-1 row-cols-sm-3 row-cols-lg-3 g-0 justify-content-center">
          <div class="col">
            <div class="card border-0 bg-primary rounded-0 p-4 text-light">
              <div class="row">
                <div class="col-md-3 text-center">
                  <svg width="60" height="60"><use xlink:href="#fresh"></use></svg>
                </div>
                <div class="col-md-9">
                  <div class="card-body p-0">
                    <h5 class="text-light">Fresh from farm</h5>
                    <p class="card-text">Lorem ipsum dolor sit amet, consectetur adipi elit.</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="col">
            <div class="card border-0 bg-secondary rounded-0 p-4 text-light">
              <div class="row">
                <div class="col-md-3 text-center">
                  <svg width="60" height="60"><use xlink:href="#organic"></use></svg>
                </div>
                <div class="col-md-9">
                  <div class="card-body p-0">
                    <h5 class="text-light">100% Organic</h5>
                    <p class="card-text">Lorem ipsum dolor sit amet, consectetur adipi elit.</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="col">
            <div class="card border-0 bg-danger rounded-0 p-4 text-light">
              <div class="row">
                <div class="col-md-3 text-center">
                  <svg width="60" height="60"><use xlink:href="#delivery"></use></svg>
                </div>
                <div class="col-md-9">
                  <div class="card-body p-0">
                    <h5 class="text-light">Free delivery</h5>
                    <p class="card-text">Lorem ipsum dolor sit amet, consectetur adipi elit.</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      
      </div>
    </section>

    <section class="py-5 overflow-hidden">
      <div class="container-lg">
        <div class="row">
          <div class="col-md-12">

            <div class="section-header d-flex flex-wrap justify-content-between mb-5">
              <h2 class="section-title">Categorias</h2>

              
            </div>
            
          </div>
        </div>
        <div class="row">
          <div class="col-md-12">

            <div class="category-carousel swiper">
              <div class="swiper-wrapper">
                <a href="category.html" class="nav-link swiper-slide text-center">
                  <img src="public/assets/images/category-thumb-1.jpg" class="rounded-circle" alt="Category Thumbnail">
                  <h4 class="fs-6 mt-3 fw-normal category-title">Pulpas</h4>
                </a>
                <a href="category.html" class="nav-link swiper-slide text-center">
                  <img src="public/assets/images/category-thumb-2.jpg" class="rounded-circle" alt="Category Thumbnail">
                  <h4 class="fs-6 mt-3 fw-normal category-title">Frutas congeladas</h4>
                </a>
                <a href="category.html" class="nav-link swiper-slide text-center">
                  <img src="public/assets/images/category-thumb-3.jpg" class="rounded-circle" alt="Category Thumbnail">
                  <h4 class="fs-6 mt-3 fw-normal category-title">Vegetales congelados</h4>
                </a>
                <a href="category.html" class="nav-link swiper-slide text-center">
                  <img src="public/assets/images/category-thumb-4.jpg" class="rounded-circle" alt="Category Thumbnail">
                  <h4 class="fs-6 mt-3 fw-normal category-title">Congelados para freir</h4>
                </a>
                <a href="category.html" class="nav-link swiper-slide text-center">
                  <img src="public/assets/images/category-thumb-5.jpg" class="rounded-circle" alt="Category Thumbnail">
                  <h4 class="fs-6 mt-3 fw-normal category-title">Otros</h4>
                </a>

                
              </div>
            </div>

          </div>
        </div>
      </div>
    </section>

    <section class="pb-5">
      <div class="container-lg">
        <div class="row">
          <div class="col-md-12">
            <div class="section-header d-flex flex-wrap justify-content-between my-4">   
              <h2 class="section-title">Productos Mejor Vendidos</h2>
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
                      <img src="<?php echo !empty($producto['imagen']) ? 'public/assets/images/' . htmlspecialchars($producto['imagen']) : 'images/product-default.png'; ?>" 
                          alt="<?php echo htmlspecialchars($producto['nombre']); ?>" class="tab-image">
                    </a>
                  </figure>
                  <div class="d-flex flex-column text-center">
                    <h3 class="fs-6 fw-normal"><?php echo htmlspecialchars($producto['nombre']); ?></h3>
                    <!-- Added line to display unit size and unit -->
                    <div class="text-dark"><?php echo htmlspecialchars($producto['tamañoUnidad']) . ' ' . htmlspecialchars($producto['unidad']); ?></div>
                    <div class="mb-2">
                      <?php if ($producto['stock'] <= 0): ?>
                        <span class="badge bg-danger">Sin stock</span>
                      <?php endif; ?>
                    </div>
                    <div class="d-flex justify-content-center align-items-center gap-2">
                      <span class="text-dark fw-semibold"><?php echo number_format($producto['valor'], 2); ?> COP</span>
                    </div>
                    
                    <div class="button-area p-3 pt-0">
                      <div class="row g-1 mt-2">
                        <div class="col-3">
                          <input type="number" name="quantity" class="form-control border-dark-subtle input-number quantity"
                            value="1" min="1" max="<?php echo $producto['stock']; ?>"
                            <?php if ($producto['stock'] <= 0) echo 'disabled'; ?>>
                        </div>
                        <div class="col-7">
                          <a href="#" class="btn btn-primary rounded-1 p-2 fs-7 btn-cart"
                            data-producto-id="<?php echo $producto['codigo']; ?>"
                            <?php if ($producto['stock'] <= 0) echo 'disabled tabindex="-1" aria-disabled="true"'; ?>>
                            <svg width="18" height="18"><use xlink:href="#cart"></use></svg> Añadir al carrito
                          </a>
                        </div>
                        <div class="col-2">
                          <a href="#" class="btn btn-outline-dark rounded-1 p-2 fs-6"><svg width="18" height="18"><use xlink:href="#heart"></use></svg></a>
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

    <section class="py-3">
      <div class="container-lg">
        <div class="row">
          <div class="col-md-12">

            <div class="banner-blocks">
            
              <div class="banner-ad d-flex align-items-center large bg-info block-1" style="background: url('public/assets/images/banner-ad-1.jpg') no-repeat; background-size: cover;">
                <div class="banner-content p-5">
                  <div class="content-wrapper text-light">
                    <h3 class="banner-title text-light">Items on SALE</h3>
                    <p>Discounts up to 30%</p>
                    <a href="#" class="btn-link text-white">Shop Now</a>
                  </div>
                </div>
              </div>
              
              <div class="banner-ad bg-success-subtle block-2" style="background:url('public/assets/images/banner-ad-2.jpg') no-repeat;background-size: cover">
                <div class="banner-content align-items-center p-5">
                  <div class="content-wrapper text-light">
                    <h3 class="banner-title text-light">Combo offers</h3>
                    <p>Discounts up to 50%</p>
                    <a href="#" class="btn-link text-white">Shop Now</a>
                  </div>
                </div>
              </div>

              <div class="banner-ad bg-danger block-3" style="background:url('public/assets/images/banner-ad-3.jpg') no-repeat;background-size: cover">
                <div class="banner-content align-items-center p-5">
                  <div class="content-wrapper text-light">
                    <h3 class="banner-title text-light">Discount Coupons</h3>
                    <p>Discounts up to 40%</p>
                    <a href="#" class="btn-link text-white">Shop Now</a>
                  </div>
                </div>
              </div>

            </div>
            <!-- / Banner Blocks -->
              
          </div>
        </div>
      </div>
    </section>

    
    

    <section class="py-5">
      <div class="container-lg">
        <div class="row row-cols-1 row-cols-sm-3 row-cols-lg-5">
          <div class="col">
            <div class="card mb-3 border border-dark-subtle p-3">
              <div class="text-dark mb-3">
                <svg width="32" height="32"><use xlink:href="#package"></use></svg>
              </div>
              <div class="card-body p-0">
                <h5>Free delivery</h5>
                <p class="card-text">Lorem ipsum dolor sit amet, consectetur adipi elit.</p>
              </div>
            </div>
          </div>
          <div class="col">
            <div class="card mb-3 border border-dark-subtle p-3">
              <div class="text-dark mb-3">
                <svg width="32" height="32"><use xlink:href="#secure"></use></svg>
              </div>
              <div class="card-body p-0">
                <h5>100% secure payment</h5>
                <p class="card-text">Lorem ipsum dolor sit amet, consectetur adipi elit.</p>
              </div>
            </div>
          </div>
          <div class="col">
            <div class="card mb-3 border border-dark-subtle p-3">
              <div class="text-dark mb-3">
                <svg width="32" height="32"><use xlink:href="#quality"></use></svg>
              </div>
              <div class="card-body p-0">
                <h5>Quality guarantee</h5>
                <p class="card-text">Lorem ipsum dolor sit amet, consectetur adipi elit.</p>
              </div>
            </div>
          </div>
          
          <div class="col">
            <div class="card mb-3 border border-dark-subtle p-3">
              <div class="text-dark mb-3">
                <svg width="32" height="32"><use xlink:href="#offers"></use></svg>
              </div>
              <div class="card-body p-0">
                <h5>Daily offers</h5>
                <p class="card-text">Lorem ipsum dolor sit amet, consectetur adipi elit.</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <footer class="py-5">
      <div class="container-lg">
        <div class="row">

          <div class="col-lg-3 col-md-6 col-sm-6">
            <div class="footer-menu">
              <img src="public/assets/images/CONGELADOSIA-removebg-preview.png" width="240" height="70" alt="logo">
              <div class="social-links mt-3">
                <ul class="d-flex list-unstyled gap-2">
                  <li>
                    <a href="#" class="btn btn-outline-light">
                      <svg width="16" height="16"><use xlink:href="#facebook"></use></svg>
                    </a>
                  </li>
            
                  <li>
                    <a href="#" class="btn btn-outline-light">
                      <svg width="16" height="16"><use xlink:href="#instagram"></use></svg>
                    </a>
                  </li>
                
                </ul>
              </div>
            </div>
          </div>

          <div class="col-md-2 col-sm-6">
            <div class="footer-menu">
              <h5 class="widget-title">Kongelados</h5>
              <ul class="menu-list list-unstyled">
                <li class="menu-item">
                  <a href="#" class="nav-link">About us</a>
                </li>
                <li class="menu-item">
                  <a href="#" class="nav-link">Conditions </a>
                </li>
                <li class="menu-item">
                  <a href="#" class="nav-link">Our Journals</a>
                </li>
                <li class="menu-item">
                  <a href="#" class="nav-link">Careers</a>
                </li>
                <li class="menu-item">
                  <a href="#" class="nav-link">Affiliate Programme</a>
                </li>
                <li class="menu-item">
                  <a href="#" class="nav-link">Ultras Press</a>
                </li>
              </ul>
            </div>
          </div>
          <div class="col-md-2 col-sm-6">
            <div class="footer-menu">
              <h5 class="widget-title">Quick Links</h5>
              <ul class="menu-list list-unstyled">
                <li class="menu-item">
                  <a href="#" class="nav-link">Offers</a>
                </li>
                <li class="menu-item">
                  <a href="#" class="nav-link">Discount Coupons</a>
                </li>
                <li class="menu-item">
                  <a href="#" class="nav-link">Stores</a>
                </li>
                <li class="menu-item">
                  <a href="#" class="nav-link">Track Order</a>
                </li>
                <li class="menu-item">
                  <a href="#" class="nav-link">Shop</a>
                </li>
                <li class="menu-item">
                  <a href="#" class="nav-link">Info</a>
                </li>
              </ul>
            </div>
          </div>
          <div class="col-md-2 col-sm-6">
            <div class="footer-menu">
              <h5 class="widget-title">Customer Service</h5>
              <ul class="menu-list list-unstyled">
                <li class="menu-item">
                  <a href="#" class="nav-link">FAQ</a>
                </li>
                <li class="menu-item">
                  <a href="#" class="nav-link">Contact</a>
                </li>
                <li class="menu-item">
                  <a href="#" class="nav-link">Privacy Policy</a>
                </li>
                <li class="menu-item">
                  <a href="#" class="nav-link">Returns & Refunds</a>
                </li>
                <li class="menu-item">
                  <a href="#" class="nav-link">Cookie Guidelines</a>
                </li>
                <li class="menu-item">
                  <a href="#" class="nav-link">Delivery Information</a>
                </li>
              </ul>
            </div>
          </div>
          
          
        </div>
      </div>
    </footer>
    <div id="footer-bottom">
      <div class="container-lg">
        <div class="row">
          <div class="col-md-6 copyright">
            <p>© 2025 Kongelados. Todos los derechos reservados.</p>
          </div>
        </div>
      </div>
    </div>
    <script src="public/assets/js/jquery-1.11.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/swiper@9/swiper-bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js" integrity="sha384-ENjdO4Dr2bkBIFxQpeoTz1HIcje39Wm4jDKdf19U8gI4ddQ3GYNS7NTKfAdVQSZe" crossorigin="anonymous"></script>
    <script src="public/assets/js/plugins.js"></script>
    <script src="public/assets/js/script.js"></script>
    
    <script>
    // Añadir producto al carrito vía AJAX
    $(document).on('click', '.btn-cart', function(e) {
      e.preventDefault();
      var $row = $(this).closest('.product-item');
      var productId = $(this).data('producto-id');
      var quantity = $row.find('input.quantity').val() || 1;
      var $btn = $(this);
      if ($btn.is('[disabled]')) {
        // Animación shake si está deshabilitado (sin stock)
        $btn.addClass('shake');
        setTimeout(function() { $btn.removeClass('shake'); }, 400);
        return;
      }
      $.post('', { add_to_cart: 1, product_id: productId, quantity: quantity }, function(resp) {
        if (resp && resp.success) {
          location.reload();
        } else if (resp && resp.error) {
          // Animación shake si el backend responde sin stock
          $btn.addClass('shake');
          setTimeout(function() { $btn.removeClass('shake'); }, 400);
          alert(resp.error);
        }
      }, 'json');
    });

    $(function() {
  // Filtrar productos por categoría vía AJAX
  $('#categoria-select').on('change', function() {
    var categoria = $(this).val();
    $('#productos-lista').html('<div class="text-center py-5 w-100">Cargando...</div>');
    $.get('', { ajax_categoria: 1, categoria: categoria }, function(html) {
      $('#productos-lista').html(html);
    });
  });
});

$(function() {
  var $input = $('#buscador-productos');
  var $list = $('#autocomplete-list');
  $input.on('input', function() {
    var val = $(this).val();
    $list.empty();
    if (val.length < 2) return;
    $.get('', { ajax_search: 1, term: val }, function(data) {
      if (data && data.length) {
        data.forEach(function(item) {
          var $div = $('<div>').text(item.nombre).attr('data-id', item.codigo);
          $list.append($div);
        });
      }
    }, 'json');
  });
  // Click en producto: redirige a la página del producto
  $list.on('click', 'div', function() {
    var id = $(this).data('id');
    window.location.href = 'producto.php?id=' + id;
  });
  // Oculta la lista si el input pierde foco
  $input.on('blur', function() {
    setTimeout(function(){ $list.empty(); }, 200);
  });
});
    </script>
  

  </body>
</html>