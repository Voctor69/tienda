<?php
session_start();
require("conexion.php");

// Variable para almacenar mensajes de error
$error_message = '';

// Eliminar Producto
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['eliminar'])) {
    $codigo = $_POST['codigo'];
    $sql = "DELETE FROM producto WHERE codigo = '$codigo'";

    try {
        if (!mysqli_query($conexion, $sql)) {
            throw new Exception(mysqli_error($conexion));
        }
        header("Location: registrarProducto.php");
        exit();
    } catch (Exception $e) {
        // Cambiar el mensaje de error
        $error_message = "Error al registrar producto: No se puede eliminar este producto, debido a que el stock no puede ser negativo.";
    }
}

// Registrar Producto
if ($_SERVER["REQUEST_METHOD"] == "POST" && !isset($_POST['eliminar'])) {
    $nombre = $_POST['nombre'];
    $valor = $_POST['valor'];
    $marca = $_POST['marca'];
    $categoria = $_POST['categoria'];
    $presentacion = $_POST['presentacion'];
    $unidad = $_POST['unidad'];
    $tamañoUnidad = $_POST['tamañoUnidad'];
    $stock = $_POST['stock'];
    $imagen = $_POST['imagen'];

    $sql = "INSERT INTO producto (nombre, valor, marca, categoria, presentacion, tamañoUnidad, unidad, stock, imagen) 
            VALUES ('$nombre', '$valor', '$marca', '$categoria', '$presentacion', '$tamañoUnidad', '$unidad', '$stock', '$imagen')";

    if (mysqli_query($conexion, $sql)) {
        header("Location: registrarProducto.php");
        exit();
    } else {
        echo "Error: " . mysqli_error($conexion);
    }
}



?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Registro de Productos</title>
    <link rel="stylesheet" href="css/styles.css">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
</head>
<body>
    
<br><br>
<div class="container-xl">
<?php if ($error_message): ?>
        <div class="alert alert-danger">
            <?php echo $error_message; ?>
        </div>
    <?php endif; ?>
    <div class="table-responsive">
        <div class="table-wrapper">
            <div class="table-title">
                <div class="row">
                    <div class="col-sm-6">
                        <h2>Registrar <b>Productos</b></h2>
                    </div>
                    <div class="col-sm-6 text-right">
                    <a href="#addProductModal" class="btn btn-success" data-toggle="modal">
                        <i class="material-icons">&#xE147;</i> <span>Agregar Producto</span>
                    </a>
                    </div>
                </div>
            </div>
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>Codigo</th>
                        <th>Nombre</th>
                        <th>Valor</th>
                        <th>Marca</th>
                        <th>Categoría</th>
                        <th>Presentación</th>
                        <th>Unidad</th>
                        <th>Tamaño de Unidad</th>
                        <th>Stock</th>
                        <th>Imagen</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $sql = "SELECT p.codigo, p.nombre, p.valor, m.nombre AS marca, c.nombre AS categoria, p.presentacion, p.tamañoUnidad, p.unidad, p.stock, p.imagen 
                            FROM producto p 
                            JOIN marca m ON p.marca = m.codigo 
                            JOIN categoria c ON p.categoria = c.codigo";
                    $resultado = mysqli_query($conexion, $sql);
                    while($row = mysqli_fetch_assoc($resultado)) {
                        $codigo = $row['codigo'];
                        $nombre = $row['nombre'];
                        $valor = $row['valor'];
                        $marca = $row['marca'];
                        $categoria = $row['categoria'];
                        $presentacion = $row['presentacion'];
                        $unidad = $row['unidad'];
                        $tamañoUnidad = $row['tamañoUnidad'];
                        $stock = $row['stock'];
                        $imagen = $row['imagen'];
                        echo "<tr>
                                <td>{$codigo}</td>
                                <td>{$nombre}</td>
                                <td>{$valor}</td>
                                <td>{$marca}</td>
                                <td>{$categoria}</td>
                                <td>{$presentacion}</td>
                                <td>{$unidad}</td>
                                <td>{$tamañoUnidad}</td>
                                <td>{$stock}</td>
                                <td><img src='images/{$imagen}' alt='Imagen de {$nombre}' style='max-width: 100px;'></td>
                                <td>
                                    <form method='post' action='registrarProducto.php' style='display:inline;'>
                                        <input type='hidden' name='codigo' value='{$codigo}'>
                                        <button type='submit' name='eliminar' class='delete' style='border:none; background:none; cursor:pointer;'>
                                            <i class='material-icons' title='Eliminar'>&#xE872;</i>
                                        </button>
                                    </form>
                                </td>
                              </tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Ingresar Producto -->
    <div id="addProductModal" class="modal fade">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post" action="registrarProducto.php">
                    <div class="modal-header">
                        <h4 class="modal-title">Agregar Producto</h4>
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="nombre">Nombre</label>
                            <input type="text" class="form-control" name="nombre" required pattern="[A-Za-z\s]+" title="Por favor, ingresa solo letras.">
                        </div>
                        <div class="form-group">
                            <label for="valor">Valor</label>
                            <input type="number" step="any" min="1" class="form-control" name="valor" required>
                        </div>
                        <div class="form-group">
                            <label for="marca">Marca</label>
                            <select class="form-control" name="marca" required>
                                <?php
                                $sql = "SELECT * FROM marca";
                                $resultado = mysqli_query($conexion, $sql);
                                while($row = mysqli_fetch_assoc($resultado)) {
                                    echo "<option value='{$row['codigo']}'>{$row['nombre']}</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="categoria">Categoría</label>
                            <select class="form-control" name="categoria" required>
                                <?php
                                $sql = "SELECT * FROM categoria";
                                $resultado = mysqli_query($conexion, $sql);
                                while($row = mysqli_fetch_assoc($resultado)) {
                                    echo "<option value='{$row['codigo']}'>{$row['nombre']}</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="presentacion">Presentación</label>
                            <select class="form-control" name="presentacion" required>
                              <option value="Botella de Vidrio">Botella de Vidrio</option>
                              <option value="Botella de Plastico">Botella de Plastico</option>
                              <option value="Lata">Lata</option>
                              <option value="Tetrapack">Tetrapack</option>
                              <option value="Paquete">Paquete</option>
                              <option value="Caja">Caja</option>
                              <option value="Bolsa">Bolsa</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="unidad">Unidad</label>
                            <select class="form-control" name="unidad" required>
                                <option value="Kg">Kg</option>
                                <option value="Gr">Gr</option>
                                <option value="Ml">Ml</option>
                                <option value="L">L</option>
                                <option value="Libra">Libra</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="tamañoUnidad">Tamaño de Unidad</label>
                            <input type="number" step="any" min="1" class="form-control" name="tamañoUnidad" required>
                        </div>
                        <div class="form-group">
                            <label for="imagen">Imagen</label>
                            <input type="file" class="form-control-file" name="imagen" accept="images/*" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <input type="submit" class="btn btn-success" value="Agregar">
                        <button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.0/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/js/bootstrap.min.js"></script>

</body>
</html>
<?php

?>