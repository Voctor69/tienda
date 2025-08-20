<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: /tienda/acceso.php');
    exit;
}

$conexion = mysqli_connect("localhost", "root", "", "testbdpa", 3306);
if (!$conexion) {
    die("Error de conexión: " . mysqli_connect_error());
}

$usuario_id = $_SESSION['usuario_id'];
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Cambiar datos personales
    if (isset($_POST['update_data'])) {
        $nombres = trim($_POST['nombres'] ?? '');
        $apellidos = trim($_POST['apellidos'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');
        $direccion = trim($_POST['direccion'] ?? '');

        if (!$nombres || !$apellidos || !$telefono) {
            $error = "Nombres, apellidos y teléfono son obligatorios.";
        } else {
            $stmt = $conexion->prepare("UPDATE usuario SET nombres=?, apellidos=?, telefono=?, direccion=? WHERE documento=?");
            $stmt->bind_param("sssss", $nombres, $apellidos, $telefono, $direccion, $usuario_id);
            if ($stmt->execute()) {
                $success = "Datos actualizados correctamente.";
            } else {
                $error = "Error al actualizar datos.";
            }
            $stmt->close();
        }
    }
    // Cambiar contraseña
    if (isset($_POST['change_password'])) {
        $actual = trim($_POST['actual_clave'] ?? '');
        $nueva = trim($_POST['nueva_clave'] ?? '');
        $confirmar = trim($_POST['confirmar_clave'] ?? '');

        $stmt = $conexion->prepare("SELECT clave FROM usuario WHERE documento=?");
        $stmt->bind_param("s", $usuario_id);
        $stmt->execute();
        $stmt->bind_result($clave_hash);
        $stmt->fetch();
        $stmt->close();

        if (!$actual || !$nueva || !$confirmar) {
            $error = "Todos los campos de contraseña son obligatorios.";
        } elseif (!password_verify($actual, $clave_hash) && $actual !== $clave_hash) {
            $error = "La contraseña actual es incorrecta.";
        } elseif ($nueva !== $confirmar) {
            $error = "Las contraseñas nuevas no coinciden.";
        } elseif (strlen($nueva) < 6) {
            $error = "La nueva contraseña debe tener al menos 6 caracteres.";
        } else {
            $nuevo_hash = password_hash($nueva, PASSWORD_DEFAULT);
            $stmt = $conexion->prepare("UPDATE usuario SET clave=? WHERE documento=?");
            $stmt->bind_param("ss", $nuevo_hash, $usuario_id);
            if ($stmt->execute()) {
                $success = "Contraseña actualizada correctamente.";
            } else {
                $error = "Error al actualizar la contraseña.";
            }
            $stmt->close();
        }
    }
    // Cerrar sesión
    if (isset($_POST['logout'])) {
        session_destroy();
        header('Location: /tienda/');
        exit;
    }
}

// Obtener datos actuales del usuario
$stmt = $conexion->prepare("SELECT documento, nombres, apellidos, email, telefono, direccion FROM usuario WHERE documento=?");
$stmt->bind_param("s", $usuario_id);
$stmt->execute();
$result = $stmt->get_result();
$usuario = $result->fetch_assoc();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Mi Cuenta - Kongelados</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5">
    <h2 class="mb-4">Mi Cuenta</h2>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php elseif ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <form method="post" class="mb-4">
        <h4>Datos personales</h4>
        <div class="row mb-2">
            <div class="col-md-6">
                <label>Nombres</label>
                <input type="text" name="nombres" class="form-control" value="<?= htmlspecialchars($usuario['nombres']) ?>" required>
            </div>
            <div class="col-md-6">
                <label>Apellidos</label>
                <input type="text" name="apellidos" class="form-control" value="<?= htmlspecialchars($usuario['apellidos']) ?>" required>
            </div>
        </div>
        <div class="row mb-2">
            <div class="col-md-6">
                <label>Teléfono</label>
                <input type="text" name="telefono" class="form-control" value="<?= htmlspecialchars($usuario['telefono']) ?>" required>
            </div>
            <div class="col-md-6">
                <label>Dirección</label>
                <input type="text" name="direccion" class="form-control" value="<?= htmlspecialchars($usuario['direccion']) ?>">
            </div>
        </div>
        <div class="mb-2">
            <label>Email (no modificable)</label>
            <input type="email" class="form-control" value="<?= htmlspecialchars($usuario['email']) ?>" disabled>
        </div>
        <button type="submit" name="update_data" class="btn btn-primary mt-2">Guardar cambios</button>
    </form>
    <form method="post" class="mb-4">
        <h4>Cambiar contraseña</h4>
        <div class="row mb-2">
            <div class="col-md-4">
                <label>Contraseña actual</label>
                <input type="password" name="actual_clave" class="form-control" required>
            </div>
            <div class="col-md-4">
                <label>Nueva contraseña</label>
                <input type="password" name="nueva_clave" class="form-control" required>
            </div>
            <div class="col-md-4">
                <label>Confirmar nueva contraseña</label>
                <input type="password" name="confirmar_clave" class="form-control" required>
            </div>
        </div>
        <button type="submit" name="change_password" class="btn btn-warning mt-2">Cambiar contraseña</button>
    </form>
    <form method="post">
        <button type="submit" name="logout" class="btn btn-danger">Cerrar sesión</button>
    </form>
</div>
</body>
</html>
