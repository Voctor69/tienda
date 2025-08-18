<?php
session_start();
require("conexion.php");

// Variable para almacenar mensajes de error
$error_message = '';

// Eliminar Ingreso
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['eliminar'])) {
    $codigo = $_POST['codigo'];
    $sql = "DELETE FROM ingreso WHERE codigo = '$codigo'";

    try {
        if (!mysqli_query($conexion, $sql)) {
            throw new Exception(mysqli_error($conexion));
        }
        header("Location: registrarIngreso.php");
        exit();
    } catch (Exception $e) {
        // Cambiar el mensaje de error
        $error_message = "Error al registrar ingreso: No se puede eliminar este ingreso, debido a que el stock no puede ser negativo.";
    }
}

// Registrar Ingreso
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Asignar valores desde $_POST, si no existen se asigna un valor vacío
    $producto = isset($_POST['producto']) ? $_POST['producto'] : '';
    $cantidad = isset($_POST['cantidad']) ? $_POST['cantidad'] : '';
    $fechaDeIngreso = isset($_POST['fechaDeIngreso']) ? $_POST['fechaDeIngreso'] : '';
    $horaDeIngreso = isset($_POST['horaDeIngreso']) ? $_POST['horaDeIngreso'] : '';
    $fechaFabricacion = isset($_POST['fechaFabricacion']) ? $_POST['fechaFabricacion'] : '';
    $fechaVencimiento = isset($_POST['fechaVencimiento']) ? $_POST['fechaVencimiento'] : '';

    // Intentar insertar en la base de datos
    $sql = "INSERT INTO ingreso (producto, cantidad, fechaDeIngreso, horaDeIngreso, fechaFabricacion, fechaVencimiento) 
            VALUES ('$producto', '$cantidad', '$fechaDeIngreso', '$horaDeIngreso', '$fechaFabricacion', '$fechaVencimiento')";

    try {
        if (!mysqli_query($conexion, $sql)) {
            throw new Exception(mysqli_error($conexion));
        }
        header("Location: registrarIngreso.php");
        exit();
    } catch (Exception $e) {
        // Cambiar el mensaje de error
        $error_message = "Error al registrar ingreso: No se puede eliminar este ingreso, debido a que el stock no puede ser negativo.";
    }
}


?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Registro de Ingresos</title>
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
                        <h2>Registrar <b>Ingresos</b></h2>
                    </div>
                    <div class="col-sm-6 text-right">
                        <a href="#addIngresoModal" class="btn btn-success" data-toggle="modal">
                            <i class="material-icons">&#xE147;</i> <span>Agregar Ingreso</span>
                        </a>
                    </div>
                </div>
            </div>
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>Codigo</th>
                        <th>Producto</th>
                        <th>Cantidad</th>
                        <th>Fecha de Ingreso</th>
                        <th>Hora de Ingreso</th>
                        <th>Fecha de Fabricacion</th>
                        <th>Fecha de Vencimiento</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $sql = "SELECT i.codigo, i.fechaDeIngreso, i.cantidad, i.horaDeIngreso, i.fechaFabricacion, i.fechaVencimiento, p.nombre AS producto
                            FROM ingreso i 
                            JOIN producto p ON i.producto = p.codigo";
                    $resultado = mysqli_query($conexion, $sql);
                    while($row = mysqli_fetch_assoc($resultado)) {
                        echo "<tr>
                                <td>{$row['codigo']}</td>
                                <td>{$row['producto']}</td>
                                <td>{$row['cantidad']}</td>
                                <td>{$row['fechaDeIngreso']}</td>
                                <td>{$row['horaDeIngreso']}</td>
                                <td>{$row['fechaFabricacion']}</td>
                                <td>{$row['fechaVencimiento']}</td>
                                <td>
                                    <a href='actualizarIngreso.php'><i class='material-icons' style='color: black; title='Editar'>&#xE254;</i></a>
                                    <form method='post' action='registrarIngreso.php' style='display:inline;'>
                                        <input type='hidden' name='codigo' value='{$row['codigo']}'>
                                        <button type='submit' name='eliminar' class='delete' style='border:none; background:none; cursor:pointer;'>
                                            <i class='material-icons' title='Eliminar'>&#xE872;</ i>
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
    <div id="addIngresoModal" class="modal fade">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post" action="registrarIngreso.php">
                    <div class="modal-header">
                        <h4 class="modal-title">Agregar Ingreso</h4>
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="producto">Producto</label>
                            <select class="form-control" name="producto" required>
                                <option value="">Selecciona un producto</option>
                                <?php
                                $sql = "SELECT * FROM producto"; // Asegúrate de que la tabla 'producto' existe
                                $resultado = mysqli_query($conexion, $sql);
                                while($row = mysqli_fetch_assoc($resultado)) {
                                    echo "<option value='{$row['codigo']}'>{$row['nombre']}</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Cantidad</label>
                            <input type="number" name="cantidad" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>Fecha de Ingreso</label>
                            <input type="date" name="fechaDeIngreso" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>Hora de Ingreso</label>
                            <input type="time" name="horaDeIngreso" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>Fecha de Fabricacion</label>
                            <input type="date" name="fechaFabricacion" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>Fecha de Vencimiento</label>
                            <input type="date" name="fechaVencimiento" class="form-control" required>
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
