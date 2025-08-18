<?php
session_start();

$conexion = mysqli_connect("localhost","root","","testbdpa",3306);

if (!$conexion) {
    die("Error de conexión: " . mysqli_connect_error());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $documento = trim($_POST['documento'] ?? '');
    $nombres = trim($_POST['nombres'] ?? '');
    $apellidos = trim($_POST['apellidos'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $direccion = trim($_POST['direccion'] ?? '');
    $clave = trim($_POST['clave'] ?? '');
    $confirmar_clave = trim($_POST['confirmar_clave'] ?? '');

    if (!$email || !$clave) {
        $error = "Correo y clave son obligatorios.";
    } else {
        // Verificar si el usuario existe (LOGIN)
        $stmt = $conexion->prepare("SELECT documento, clave FROM usuario WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $resultado = $stmt->get_result();

        if ($resultado->num_rows > 0) {
            // Usuario existe - intentar login
            $usuario = $resultado->fetch_assoc();
            
            // Verificar si la contraseña está hasheada o no
            if (password_verify($clave, $usuario['clave']) || $clave === $usuario['clave']) {
                $_SESSION['usuario_id'] = $usuario['documento'];
                $_SESSION['usuario_email'] = $email;
                header("Location: checkout.php");
                exit();
            } else {
                $error = "Contraseña incorrecta.";
            }
        } else {
            // Usuario no existe - intentar registro
            if (!$documento || !$nombres || !$apellidos || !$telefono) {
                $error = "Para registrarse, todos los campos son obligatorios excepto dirección.";
            } elseif ($confirmar_clave && $clave !== $confirmar_clave) {
                $error = "Las contraseñas no coinciden.";
            } else {
                // Verificar si el documento ya existe
                $stmt_doc = $conexion->prepare("SELECT documento FROM usuario WHERE documento = ?");
                $stmt_doc->bind_param("s", $documento);
                $stmt_doc->execute();
                $resultado_doc = $stmt_doc->get_result();
                
                if ($resultado_doc->num_rows > 0) {
                    $error = "El documento ya está registrado.";
                } else {
                    // Hashear la contraseña para mayor seguridad
                    $hash = password_hash($clave, PASSWORD_DEFAULT);
                    $stmt_insert = $conexion->prepare("INSERT INTO usuario (documento, nombres, apellidos, email, telefono, direccion, clave) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt_insert->bind_param("sssssss", $documento, $nombres, $apellidos, $email, $telefono, $direccion, $hash);
                    
                    if ($stmt_insert->execute()) {
                        $_SESSION['usuario_id'] = $documento;
                        $_SESSION['usuario_email'] = $email;
                        header("Location: checkout.php");
                        exit();
                    } else {
                        $error = "Error al registrar: " . mysqli_error($conexion);
                    }
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Kongelados Login/Register</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
  <style>
    body {
      font-family: 'Poppins', sans-serif;
    }
    .active-section {
      transform: scale(1.05);
      filter: saturate(1.3);
      transition: all 0.3s ease;
      opacity: 1;
      pointer-events: auto;
    }
    .inactive-section {
      transform: scale(0.95);
      filter: saturate(0.5) brightness(0.85);
      transition: all 0.3s ease;
      opacity: 0.6;
      pointer-events: auto;
    }
    #toggleBtn {
      width: 40px;
      height: 40px;
      border-radius: 9999px;
      padding: 0;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: all 0.3s ease;
      position: relative;
      overflow: hidden;
    }
    #toggleBtn:hover {
      background-color: #3a44d6;
      transform: scale(1.1);
    }
    #toggleBtn::before {
      content: '⇄';
      font-size: 20px;
      font-weight: bold;
      transition: transform 0.3s ease;
    }
    #toggleBtn:hover::before {
      transform: rotate(180deg);
    }
    .form-section {
      background: linear-gradient(135deg, rgba(75, 85, 249, 0.02) 0%, rgba(75, 85, 249, 0.05) 100%);
      border: 1px solid rgba(75, 85, 249, 0.1);
      border-radius: 12px;
      padding: 24px;
    }
    .input-field {
      transition: all 0.3s ease;
    }
    .input-field:focus {
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(75, 85, 249, 0.15);
    }
    .btn-primary {
      background: linear-gradient(135deg, #4b55f9 0%, #3a44d6 100%);
      transition: all 0.3s ease;
    }
    .btn-primary:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(75, 85, 249, 0.4);
    }
    .error-message {
      animation: shake 0.5s ease-in-out;
    }
    @keyframes shake {
      0%, 100% { transform: translateX(0); }
      25% { transform: translateX(-5px); }
      75% { transform: translateX(5px); }
    }
  </style>
</head>
<body class="bg-gradient-to-br from-blue-50 to-indigo-100 min-h-screen flex flex-col items-center justify-center p-6">
  <div class="mb-8 max-w-6xl w-full bg-white rounded-2xl shadow-2xl p-10 grid grid-cols-1 md:grid-cols-2 gap-10 backdrop-blur-sm bg-white/90">
    
    <!-- Header -->
    <div class="col-span-full flex flex-col items-center mb-6">
      <h1 class="text-[#4b55f9] font-extrabold text-4xl md:text-5xl mb-4 bg-gradient-to-r from-[#4b55f9] to-[#3a44d6] bg-clip-text text-transparent">
        Kongelados
      </h1>
      <button id="toggleBtn" class="bg-[#4b55f9] text-white hover:bg-[#3a44d6]" aria-label="Alternar entre Iniciar sesión y Registrarse" title="Cambiar formulario"></button>
    </div>

    <!-- Formulario de Login -->
    <form method="POST" id="loginForm" class="space-y-6 col-span-1 md:col-span-1 active-section form-section">
      <h2 class="text-[#4b55f9] font-bold text-2xl mb-6 text-center">Iniciar Sesión</h2>
      
      <div class="space-y-4">
        <input 
          type="email" 
          name="email" 
          placeholder="Correo electrónico" 
          required 
          class="input-field w-full border border-gray-300 rounded-lg px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-[#4b55f9] focus:border-transparent"
          value="<?= isset($_POST['email']) && !isset($error) ? '' : ($_POST['email'] ?? '') ?>"
        >
        
        <input 
          type="password" 
          name="clave" 
          placeholder="Contraseña" 
          required 
          class="input-field w-full border border-gray-300 rounded-lg px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-[#4b55f9] focus:border-transparent"
        >
      </div>
      
      <button type="submit" class="btn-primary w-full text-white rounded-lg py-3 mt-6 text-sm font-semibold">
        Iniciar Sesión
      </button>
      
      <p class="text-sm mt-4 text-center text-gray-600">
        ¿No tienes una cuenta? 
        <a href="#" id="linkToRegister" class="text-[#4b55f9] font-semibold hover:underline">Regístrate aquí</a>
      </p>
    </form>

    <!-- Formulario de Registro -->
    <form method="POST" id="registerForm" class="space-y-4 col-span-1 md:col-span-1 inactive-section form-section">
      <h2 class="text-[#4b55f9] font-bold text-2xl mb-6 text-center">Crear Cuenta</h2>
      
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <input 
          type="text" 
          name="documento" 
          placeholder="Documento" 
          required 
          class="input-field w-full border border-gray-300 rounded-lg px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-[#4b55f9] focus:border-transparent"
          value="<?= $_POST['documento'] ?? '' ?>"
        >
        
        <input 
          type="text" 
          name="nombres" 
          placeholder="Nombres" 
          required 
          class="input-field w-full border border-gray-300 rounded-lg px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-[#4b55f9] focus:border-transparent"
          value="<?= $_POST['nombres'] ?? '' ?>"
        >
      </div>
      
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <input 
          type="text" 
          name="apellidos" 
          placeholder="Apellidos" 
          required 
          class="input-field w-full border border-gray-300 rounded-lg px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-[#4b55f9] focus:border-transparent"
          value="<?= $_POST['apellidos'] ?? '' ?>"
        >
        
        <input 
          type="text" 
          name="telefono" 
          placeholder="Teléfono" 
          required 
          class="input-field w-full border border-gray-300 rounded-lg px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-[#4b55f9] focus:border-transparent"
          value="<?= $_POST['telefono'] ?? '' ?>"
        >
      </div>
      
      <input 
        type="email" 
        name="email" 
        placeholder="Correo electrónico" 
        required 
        class="input-field w-full border border-gray-300 rounded-lg px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-[#4b55f9] focus:border-transparent"
        value="<?= $_POST['email'] ?? '' ?>"
      >
      
      <input 
        type="text" 
        name="direccion" 
        placeholder="Dirección (opcional)" 
        class="input-field w-full border border-gray-300 rounded-lg px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-[#4b55f9] focus:border-transparent"
        value="<?= $_POST['direccion'] ?? '' ?>"
      >
      
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <input 
          type="password" 
          name="clave" 
          placeholder="Contraseña" 
          required 
          class="input-field w-full border border-gray-300 rounded-lg px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-[#4b55f9] focus:border-transparent"
        >
        
        <input 
          type="password" 
          name="confirmar_clave" 
          placeholder="Confirmar Contraseña" 
          class="input-field w-full border border-gray-300 rounded-lg px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-[#4b55f9] focus:border-transparent"
        >
      </div>
      
      <button type="submit" class="btn-primary w-full text-white rounded-lg py-3 mt-6 text-sm font-semibold">
        Crear Cuenta
      </button>
      
      <p class="text-sm mt-4 text-center text-gray-600">
        ¿Ya tienes una cuenta? 
        <a href="#" id="linkToLogin" class="text-[#4b55f9] font-semibold hover:underline">Inicia sesión</a>
      </p>
    </form>

    <!-- Mensaje de error -->
    <?php if (isset($error)): ?>
      <div class="error-message bg-red-50 border border-red-200 text-red-700 px-6 py-4 rounded-lg text-center font-semibold col-span-full">
        <span class="text-red-500 mr-2">⚠️</span>
        <?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>
  </div>

  <script>
    const toggleBtn = document.getElementById('toggleBtn');
    const loginForm = document.getElementById('loginForm');
    const registerForm = document.getElementById('registerForm');
    const linkToRegister = document.getElementById('linkToRegister');
    const linkToLogin = document.getElementById('linkToLogin');

    let showingLogin = true;

    function toggleForms() {
      if (showingLogin) {
        // Mostrar registro
        registerForm.classList.add('active-section');
        registerForm.classList.remove('inactive-section');
        loginForm.classList.add('inactive-section');
        loginForm.classList.remove('active-section');
        toggleBtn.setAttribute('aria-label', 'Cambiar a Iniciar sesión');
      } else {
        // Mostrar login
        loginForm.classList.add('active-section');
        loginForm.classList.remove('inactive-section');
        registerForm.classList.add('inactive-section');
        registerForm.classList.remove('active-section');
        toggleBtn.setAttribute('aria-label', 'Cambiar a Registrarse');
      }
      showingLogin = !showingLogin;
    }

    toggleBtn.addEventListener('click', toggleForms);
    linkToRegister.addEventListener('click', e => {
      e.preventDefault();
      if (showingLogin) toggleForms();
    });
    linkToLogin.addEventListener('click', e => {
      e.preventDefault();
      if (!showingLogin) toggleForms();
    });

    // Auto-focus en el primer campo del formulario activo
    function setFocus() {
      const activeForm = showingLogin ? loginForm : registerForm;
      const firstInput = activeForm.querySelector('input');
      if (firstInput) firstInput.focus();
    }

    // Validación en tiempo real para el registro
    const confirmarClave = registerForm.querySelector('input[name="confirmar_clave"]');
    const clave = registerForm.querySelector('input[name="clave"]');

    if (confirmarClave && clave) {
      confirmarClave.addEventListener('input', function() {
        if (this.value && clave.value && this.value !== clave.value) {
          this.setCustomValidity('Las contraseñas no coinciden');
          this.style.borderColor = '#ef4444';
        } else {
          this.setCustomValidity('');
          this.style.borderColor = '';
        }
      });
    }

    

    // Mantener el formulario activo después de un error
    <?php if (isset($error) && !empty($_POST)): ?>
      <?php if (isset($_POST['documento']) || isset($_POST['nombres'])): ?>
        // Si hay campos de registro, mostrar formulario de registro
        if (showingLogin) toggleForms();
      <?php endif; ?>
    <?php endif; ?>
  </script>
</body>
</html>