<?php
// Conexión a la base de datos
$host = '127.0.0.1';
$dbname = 'testbdpa';
$username = 'root'; // Ajusta según tu configuración
$password = '';     // Ajusta según tu configuración

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

// Obtener el código del producto desde la URL
$codigo_producto = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($codigo_producto == 0) {
    die("Código de producto no válido");
}

// Consulta para obtener los datos del producto con su marca y categoría
$stmt = $pdo->prepare("
    SELECT p.*, m.nombre as marca_nombre, c.nombre as categoria_nombre 
    FROM producto p 
    INNER JOIN marca m ON p.marca = m.codigo 
    INNER JOIN categoria c ON p.categoria = c.codigo 
    WHERE p.codigo = ?
");
$stmt->execute([$codigo_producto]);
$producto = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$producto) {
    die("Producto no encontrado");
}

// Formatear el precio
$precio_formateado = number_format($producto['valor'], 0, ',', '.');

// Calcular puntos (aproximadamente precio / 7)
$puntos = number_format($producto['valor'] / 7, 0, ',', '.');

// Determinar la ruta de la imagen
$imagen_url = !empty($producto['imagen']) ? 'images/' . $producto['imagen'] : 'https://via.placeholder.com/400x400?text=Sin+Imagen';
?>
<html lang="es">
 <head>
  <meta charset="utf-8"/>
  <meta content="width=device-width, initial-scale=1" name="viewport"/>
  <title>
   <?php echo htmlspecialchars($producto['nombre']); ?> - Éxito
  </title>
  <script src="https://cdn.tailwindcss.com">
  </script>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet"/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&amp;display=swap" rel="stylesheet"/>
  <style>
   body {
      font-family: 'Inter', sans-serif;
    }
  </style>
 </head>
 <body class="bg-[#f0f6fc] min-h-screen">
  <!-- Header with increased height -->
  <header class="bg-[#0066b3] flex items-center px-4 sm:px-6 md:px-10 h-20">
   <div class="flex items-center space-x-2 text-white font-extrabold text-lg select-none">
    <img alt="Logo Éxito" class="h-10 w-auto" height="40" src="https://storage.googleapis.com/a1aa/image/15a5acf9-38cf-4014-d9ec-ac4d726ad378.jpg" width="80"/>
   </div>
   <form aria-label="Buscar en exito.com" class="flex flex-grow max-w-[600px] mx-4 sm:mx-6 md:mx-10" role="search">
    <input aria-label="Buscar en exito.com" class="flex-grow rounded-l border border-white px-3 py-2 text-gray-800 text-sm focus:outline-none" placeholder="Buscar en exito.com" type="search"/>
    <button aria-label="Buscar" class="bg-[#004a80] border border-l-0 border-white rounded-r px-3 flex items-center justify-center" type="submit">
     <i class="fas fa-search text-white text-sm">
     </i>
    </button>
   </form>
   <nav aria-label="Navegación principal" class="flex space-x-6 text-white text-sm font-semibold select-none">
    <a class="hover:underline flex items-center" href="#" title="Notificaciones">
     <svg aria-hidden="true" class="w-5 h-5" fill="currentColor" height="20" viewBox="0 0 24 24" width="20" xmlns="http://www.w3.org/2000/svg">
      <path d="M12 24c1.104 0 2-.896 2-2h-4c0 1.104.896 2 2 2zm6.364-6v-5c0-3.07-1.64-5.64-4.364-6.32V6c0-.828-.672-1.5-1.5-1.5S11 5.172 11 6v.68C8.276 7.36 6.636 9.93 6.636 13v5l-1.636 1.636v.364h14v-.364L18.364 18z"></path>
     </svg>
    </a>
    <a class="hover:underline flex items-center" href="#" title="Mi cuenta">
     <svg aria-hidden="true" class="w-5 h-5" fill="currentColor" height="20" viewBox="0 0 24 24" width="20" xmlns="http://www.w3.org/2000/svg">
      <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"></path>
     </svg>
    </a>
    <a class="hover:underline flex items-center" href="#" title="Carrito">
     <svg aria-hidden="true" class="w-5 h-5" fill="currentColor" height="20" viewBox="0 0 24 24" width="20" xmlns="http://www.w3.org/2000/svg">
      <path d="M7 18c-1.104 0-2 .896-2 2s.896 2 2 2 2-.896 2-2-.896-2-2-2zm10 0c-1.104 0-2 .896-2 2s.896 2 2 2 2-.896 2-2-.896-2-2-2zM7.16 14l.84-2h7.72l1.68 4H7.16zM6 6h15v2H6zM5 4h2l3.6 7.59-1.35 2.44C8.16 14.37 8 14.68 8 15a2 2 0 0 0 2 2h9v-2H10.42a.25.25 0 0 1-.24-.17l.03-.08L11.1 13h7.45a1 1 0 0 0 .92-.62l3.24-7.26-1.74-.86L18.42 11H7.16z"></path>
     </svg>
    </a>
   </nav>
  </header>
  <!-- Main content container -->
  <main class="max-w-6xl mx-auto mt-6 px-4 sm:px-6 md:px-10">
   <!-- Breadcrumb removed as requested -->
   <section class="flex flex-col md:flex-row gap-6">
    <!-- Image container -->
    <div class="flex-shrink-0 rounded-lg overflow-hidden bg-white md:w-[400px]">
     <img alt="<?php echo htmlspecialchars($producto['nombre']); ?>" class="w-full h-auto object-cover" height="400" src="<?php echo htmlspecialchars($imagen_url); ?>" width="400" 
          onerror="this.src='https://via.placeholder.com/400x400?text=Sin+Imagen'"/>
    </div>
    <!-- Product details -->
    <div class="flex-grow bg-white rounded-lg p-6 flex flex-col justify-between">
     <div>
      <h1 class="text-[#003366] font-bold text-lg leading-tight mb-1">
       <?php echo htmlspecialchars($producto['nombre']); ?>
      </h1>
      <p class="text-xs text-[#0066b3] font-semibold mb-4 uppercase tracking-wide">
       <?php echo htmlspecialchars($producto['marca_nombre']); ?> - PLU:
       <a class="hover:underline" href="#">
        <?php echo $producto['codigo']; ?>
       </a>
      </p>
      <p class="text-3xl font-bold text-[#003366] mb-3">
       $<?php echo $precio_formateado; ?>
      </p>
      <p class="text-sm text-[#0066b3] mb-4">
       Llévalo con
       <strong>
        <?php echo $puntos; ?>
       </strong>
       Puntos Colombia C
      </p>
      <p class="text-xs font-semibold text-[#003366] mb-1">
       Vendido por:
       <strong>
        Éxito
       </strong>
      </p>
      <p class="text-xs text-gray-500 mb-4">
       Imágenes de referencia. Sólo se vende el producto de la descripción.
      </p>
      
      <!-- Detalles adicionales del producto -->
      <div class="mb-4">
       <p class="text-xs font-semibold text-[#003366] mb-2">Detalles del producto:</p>
       <div class="text-xs text-gray-600 space-y-1">
        <p><strong>Categoría:</strong> <?php echo htmlspecialchars($producto['categoria_nombre']); ?></p>
        <p><strong>Presentación:</strong> <?php echo htmlspecialchars($producto['presentacion']); ?></p>
        <p><strong>Tamaño:</strong> <?php echo $producto['tamañoUnidad']; ?> <?php echo htmlspecialchars($producto['unidad']); ?></p>
        <p><strong>Stock disponible:</strong> 
         <span class="<?php echo $producto['stock'] > 0 ? 'text-green-600' : 'text-red-600'; ?>">
          <?php echo $producto['stock']; ?> unidades
         </span>
        </p>
       </div>
      </div>
      
      <!-- Delivery conditions box -->
      <div aria-label="Condiciones de entrega" class="border border-[#cbdcfb] rounded-lg bg-[#e9f0ff] p-4 text-[#003366] text-xs">
       <p class="font-bold mb-2">
        Condiciones de entrega:
       </p>
       <div class="flex items-center gap-2 mb-2">
        <i aria-hidden="true" class="fas fa-truck text-[#ff5a00] text-sm flex-shrink-0">
        </i>
        <p>
         Enviado por:
         <strong>
          Éxito
         </strong>
        </p>
       </div>
       <div class="flex items-center gap-2 mb-3">
        <i aria-hidden="true" class="fas fa-store text-[#003366] text-sm flex-shrink-0">
        </i>
        <p>
         Disponible para:
         <strong>
          Compra y recoge
         </strong>
        </p>
       </div>
       <p class="font-bold mb-1">
        Información adicional
       </p>
       <div class="flex items-center gap-2">
        <i aria-hidden="true" class="fas fa-info-circle text-[#003366] text-sm flex-shrink-0">
        </i>
        <p>
         Gana puntos Colombia
        </p>
       </div>
      </div>
     </div>
     <!-- Add button -->
     <?php if ($producto['stock'] > 0): ?>
     <button aria-label="Agregar al carrito" class="mt-6 bg-[#005a99] text-white font-semibold rounded-md py-3 w-full hover:bg-[#004a80] transition-colors flex items-center justify-center gap-2" type="button" onclick="agregarAlCarrito(<?php echo $producto['codigo']; ?>)">
      Agregar
      <i class="fas fa-shopping-cart">
      </i>
     </button>
     <?php else: ?>
     <button aria-label="Sin stock" class="mt-6 bg-gray-400 text-white font-semibold rounded-md py-3 w-full cursor-not-allowed flex items-center justify-center gap-2" type="button" disabled>
      Sin stock
      <i class="fas fa-times-circle">
      </i>
     </button>
     <?php endif; ?>
    </div>
   </section>
  </main>

  <script>
  function agregarAlCarrito(codigoProducto) {
    // Aquí puedes implementar la lógica para agregar al carrito
    alert('Producto agregado al carrito: ' + codigoProducto);
    // Por ejemplo, puedes hacer una petición AJAX para agregar el producto
    // o redirigir a una página de carrito
  }
  </script>
 </body>
</html>