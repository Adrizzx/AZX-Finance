<?php
/**
 * AZX-Finance - Registro de Nuevo Perfil
 */
require_once __DIR__ . '/config/config.php';

$db = getDB();
$error = '';
$success = false;

// Colores disponibles para perfiles
$coloresDisponibles = [
    '#ff6b00' => 'Naranja',
    '#6f42c1' => 'Morado',
    '#17a2b8' => 'Cyan',
    '#28a745' => 'Verde',
    '#dc3545' => 'Rojo',
    '#ffc107' => 'Amarillo',
    '#e83e8c' => 'Rosa',
    '#20c997' => 'Turquesa'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];
    $colorPerfil = $_POST['color_perfil'];
    
    // Validaciones
    if (empty($nombre) || empty($email) || empty($password)) {
        $error = 'Todos los campos son obligatorios';
    } elseif ($password !== $confirmPassword) {
        $error = 'Las contraseñas no coinciden';
    } elseif (strlen($password) < 8) {
        $error = 'La contraseña debe tener al menos 8 caracteres';
    } elseif (!preg_match('/[A-Z]/', $password)) {
        $error = 'La contraseña debe contener al menos una letra mayúscula';
    } elseif (!preg_match('/[a-z]/', $password)) {
        $error = 'La contraseña debe contener al menos una letra minúscula';
    } elseif (!preg_match('/[0-9]/', $password)) {
        $error = 'La contraseña debe contener al menos un número';
    } elseif (!preg_match('/[!@#$%^&*(),.?":{}|<>_\-\[\]\\;\'`~\/+=]/', $password)) {
        $error = 'La contraseña debe contener al menos un carácter especial (!@#$%^&*.,etc)';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'El email no es válido';
    } else {
        // Verificar si el email ya existe
        $stmt = $db->prepare("SELECT id FROM usuarios WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = 'Este email ya está registrado';
        } else {
            // Crear la contraseña hasheada
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            
            // Procesar avatar si se subió
            $avatar = 'default.png';
            if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = __DIR__ . '/uploads/avatars/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                $ext = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
                $allowedExt = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                
                if (in_array($ext, $allowedExt)) {
                    $avatar = uniqid('avatar_') . '.' . $ext;
                    move_uploaded_file($_FILES['avatar']['tmp_name'], $uploadDir . $avatar);
                }
            }
            
            // Insertar usuario
            $stmt = $db->prepare("
                INSERT INTO usuarios (nombre, email, password, avatar, color_perfil, ultimo_acceso) 
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$nombre, $email, $passwordHash, $avatar, $colorPerfil]);
            
            $success = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nuevo Perfil - AZX-Finance</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #14b8a6;
            --primary-hover: #0d9488;
            --secondary-color: #06b6d4;
            --bg-gradient: linear-gradient(135deg, #134e4a 0%, #0f766e 30%, #115e59 60%, #0f172a 100%);
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg-gradient);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            padding: 20px;
        }
        
        .register-container {
            background: rgba(15, 23, 42, 0.7);
            backdrop-filter: blur(15px);
            border-radius: 24px;
            padding: 40px;
            max-width: 500px;
            width: 100%;
            border: 1px solid rgba(20, 184, 166, 0.2);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.4);
        }
        
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .logo-icon {
            width: 50px;
            height: 50px;
            margin: 0 auto 10px;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 8px 20px rgba(20, 184, 166, 0.3);
        }
        
        .logo-icon svg {
            width: 28px;
            height: 28px;
            fill: #fff;
        }
        
        .logo h1 {
            font-size: 1.5rem;
            margin-top: 10px;
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        h2 {
            text-align: center;
            margin-bottom: 30px;
            font-weight: 300;
        }
        
        .form-label {
            color: #94a3b8;
            font-size: 0.9rem;
        }
        
        .form-control {
            background: rgba(15, 23, 42, 0.6);
            border: 2px solid rgba(20, 184, 166, 0.2);
            border-radius: 12px;
            padding: 12px 15px;
            color: #fff;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            background: rgba(15, 23, 42, 0.8);
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(20, 184, 166, 0.15);
            color: #fff;
        }
        
        .form-control::placeholder {
            color: rgba(148, 163, 184, 0.5);
        }
        
        .avatar-preview {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            border: 3px solid var(--primary-color);
            margin: 0 auto 20px;
            overflow: hidden;
            position: relative;
            cursor: pointer;
            background: rgba(15, 23, 42, 0.6);
            transition: all 0.3s ease;
        }
        
        .avatar-preview:hover {
            border-color: var(--secondary-color);
            box-shadow: 0 0 20px rgba(20, 184, 166, 0.3);
        }
        
        .avatar-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .avatar-preview .placeholder {
            width: 100%;
            height: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: rgba(255, 255, 255, 0.5);
        }
        
        .avatar-preview .placeholder i {
            font-size: 2.5rem;
            margin-bottom: 5px;
        }
        
        .avatar-preview:hover .placeholder {
            color: var(--primary-color);
        }
        
        .color-selector {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            justify-content: center;
        }
        
        .color-option {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            cursor: pointer;
            border: 3px solid transparent;
            transition: transform 0.2s, border-color 0.2s;
        }
        
        .color-option:hover {
            transform: scale(1.1);
        }
        
        .color-option.selected {
            border-color: #fff;
            transform: scale(1.1);
        }
        
        .btn-register {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: #fff;
            border: none;
            padding: 15px;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            width: 100%;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(20, 184, 166, 0.3);
        }
        
        .btn-register:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(20, 184, 166, 0.4);
            filter: brightness(1.1);
        }
        
        .btn-back {
            display: block;
            text-align: center;
            color: #94a3b8;
            text-decoration: none;
            margin-top: 20px;
            padding: 10px;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        .btn-back:hover {
            color: var(--primary-color);
            background: rgba(20, 184, 166, 0.1);
        }
        
        .alert {
            border-radius: 10px;
            border: none;
        }
        
        .alert-danger {
            background: rgba(220, 53, 69, 0.2);
            color: #ff6b6b;
        }
        
        .alert-success {
            background: rgba(40, 167, 69, 0.2);
            color: #6fff6f;
        }
        
        .success-container {
            text-align: center;
            padding: 40px 20px;
        }
        
        /* Password requirements styles */
        .password-requirements {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        
        .req-item {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            color: #888;
            font-size: 0.75rem;
        }
        
        .req-item i {
            font-size: 0.65rem;
        }
        
        .req-item.valid {
            color: #28a745;
        }
        
        .req-item.invalid {
            color: #dc3545;
        }
        
        /* Email feedback styles */
        .email-feedback {
            margin-top: 5px;
        }
        
        .email-available {
            color: #28a745;
        }
        
        .email-taken {
            color: #dc3545;
        }
        
        .success-icon {
            font-size: 5rem;
            color: #28a745;
            margin-bottom: 20px;
        }

        /* ============================================
           MASCOTA ANIMADA - PANDA
           ============================================ */
        .panda-container {
            width: 100px;
            height: 100px;
            margin: 0 auto 10px;
            position: relative;
        }

        .panda {
            position: relative;
            width: 100%;
            height: 100%;
        }

        /* Cabeza */
        .panda-head {
            width: 90px;
            height: 80px;
            background: #fff;
            border-radius: 50%;
            position: absolute;
            left: 50%;
            top: 15px;
            transform: translateX(-50%);
            border: 3px solid #2d2d2d;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }

        /* Orejas */
        .panda-ear {
            width: 28px;
            height: 28px;
            background: #2d2d2d;
            border-radius: 50%;
            position: absolute;
            top: 5px;
        }

        .panda-ear.left {
            left: 8px;
        }

        .panda-ear.right {
            right: 8px;
        }

        /* Manchas de ojos */
        .panda-eye-patch {
            width: 28px;
            height: 24px;
            background: #2d2d2d;
            border-radius: 50%;
            position: absolute;
            top: 28px;
        }

        .panda-eye-patch.left {
            left: 12px;
            transform: rotate(-10deg);
        }

        .panda-eye-patch.right {
            right: 12px;
            transform: rotate(10deg);
        }

        /* Ojos */
        .panda-eye {
            width: 14px;
            height: 14px;
            background: #fff;
            border-radius: 50%;
            position: absolute;
            top: 32px;
            overflow: hidden;
        }

        .panda-eye.left {
            left: 20px;
        }

        .panda-eye.right {
            right: 20px;
        }

        .panda-pupil {
            width: 8px;
            height: 8px;
            background: #1a1a1a;
            border-radius: 50%;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            transition: transform 0.1s ease-out;
        }

        .panda-pupil::after {
            content: '';
            width: 3px;
            height: 3px;
            background: #fff;
            border-radius: 50%;
            position: absolute;
            top: 1px;
            left: 1px;
        }

        /* Ojos felices (cerrados) */
        .panda.happy .panda-eye {
            height: 4px;
            top: 37px;
            background: transparent;
        }

        .panda.happy .panda-pupil {
            display: none;
        }

        .panda.happy .panda-eye::before {
            content: '';
            position: absolute;
            width: 14px;
            height: 8px;
            border: 3px solid #fff;
            border-bottom: none;
            border-radius: 50% 50% 0 0;
            top: -4px;
            left: 0;
        }

        /* Nariz */
        .panda-nose {
            width: 12px;
            height: 10px;
            background: #2d2d2d;
            border-radius: 50%;
            position: absolute;
            bottom: 22px;
            left: 50%;
            transform: translateX(-50%);
        }

        /* Boca normal */
        .panda-mouth {
            width: 20px;
            height: 10px;
            position: absolute;
            bottom: 10px;
            left: 50%;
            transform: translateX(-50%);
            overflow: visible;
        }

        .panda-mouth::before,
        .panda-mouth::after {
            content: '';
            position: absolute;
            width: 10px;
            height: 6px;
            border: 2px solid #2d2d2d;
            border-top: none;
            border-radius: 0 0 50% 50%;
        }

        .panda-mouth::before {
            left: 0;
        }

        .panda-mouth::after {
            right: 0;
        }

        /* Boca triste */
        .panda.sad .panda-mouth::before,
        .panda.sad .panda-mouth::after {
            border: 2px solid #2d2d2d;
            border-bottom: none;
            border-radius: 50% 50% 0 0;
        }

        /* Boca feliz grande */
        .panda.happy .panda-mouth {
            width: 24px;
            height: 12px;
        }

        .panda.happy .panda-mouth::before,
        .panda.happy .panda-mouth::after {
            width: 12px;
            height: 10px;
            border-radius: 0 0 50% 50%;
            border: 2px solid #2d2d2d;
            border-top: none;
        }

        /* Manos para tapar ojos */
        .panda-hands {
            position: absolute;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            pointer-events: none;
            z-index: 10;
        }

        .panda-hand {
            position: absolute;
            width: 35px;
            height: 28px;
            background: #2d2d2d;
            border-radius: 50%;
            transition: all 0.35s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        }

        .panda-hand.left {
            left: 0px;
            top: 35px;
            transform: translateX(-50px) rotate(-15deg);
        }

        .panda-hand.right {
            right: 0px;
            top: 35px;
            transform: translateX(50px) rotate(15deg);
        }

        /* Estado: tapando ojos */
        .panda.covering .panda-hand.left {
            transform: translateX(12px) rotate(-5deg);
        }

        .panda.covering .panda-hand.right {
            transform: translateX(-12px) rotate(5deg);
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="logo">
            <!-- Mascota Panda -->
            <div class="panda-container">
                <div class="panda" id="panda">
                    <!-- Orejas -->
                    <div class="panda-ear left"></div>
                    <div class="panda-ear right"></div>
                    
                    <!-- Cabeza -->
                    <div class="panda-head">
                        <!-- Manchas de ojos -->
                        <div class="panda-eye-patch left"></div>
                        <div class="panda-eye-patch right"></div>
                        
                        <!-- Ojos -->
                        <div class="panda-eye left">
                            <div class="panda-pupil" id="pupilLeft"></div>
                        </div>
                        <div class="panda-eye right">
                            <div class="panda-pupil" id="pupilRight"></div>
                        </div>
                        
                        <!-- Nariz y boca -->
                        <div class="panda-nose"></div>
                        <div class="panda-mouth"></div>
                    </div>
                    
                    <!-- Manos para tapar ojos -->
                    <div class="panda-hands">
                        <div class="panda-hand left"></div>
                        <div class="panda-hand right"></div>
                    </div>
                </div>
            </div>
            <div class="logo-icon">
                <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path d="M21 7.5V18.5C21 19.88 19.88 21 18.5 21H5.5C4.12 21 3 19.88 3 18.5V5.5C3 4.12 4.12 3 5.5 3H15.5L21 7.5Z" fill="none" stroke="currentColor" stroke-width="1.5"/>
                    <path d="M21 9H16C14.9 9 14 8.1 14 7V3" fill="none" stroke="currentColor" stroke-width="1.5"/>
                    <circle cx="12" cy="14" r="3" fill="currentColor"/>
                    <path d="M9 14H6M18 14H15" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                </svg>
            </div>
            <h1>AZX-Finance</h1>
        </div>
        
        <?php if ($success): ?>
        <div class="success-container">
            <i class="fas fa-check-circle success-icon"></i>
            <h3>¡Perfil Creado!</h3>
            <p class="text-muted">Tu perfil ha sido creado exitosamente.</p>
            <a href="login.php" class="btn btn-register mt-3">
                <i class="fas fa-sign-in-alt me-2"></i>Ir al Login
            </a>
        </div>
        <?php else: ?>
        
        <h2>Crear Nuevo Perfil</h2>
        
        <?php if ($error): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>
        
        <form method="POST" enctype="multipart/form-data">
            <!-- Avatar -->
            <div class="text-center mb-4">
                <label for="avatarInput" class="avatar-preview" id="avatarPreview">
                    <div class="placeholder">
                        <i class="fas fa-camera"></i>
                        <small>Subir foto</small>
                    </div>
                </label>
                <input type="file" id="avatarInput" name="avatar" accept="image/*" style="display: none;">
            </div>
            
            <!-- Color del perfil -->
            <div class="mb-4">
                <label class="form-label d-block text-center">Color del Perfil</label>
                <div class="color-selector">
                    <?php foreach ($coloresDisponibles as $color => $nombre): ?>
                    <div class="color-option <?= $color === '#ff6b00' ? 'selected' : '' ?>" 
                         style="background: <?= $color ?>"
                         data-color="<?= $color ?>"
                         title="<?= $nombre ?>"
                         onclick="selectColor(this)"></div>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" name="color_perfil" id="colorPerfil" value="#ff6b00">
            </div>
            
            <div class="mb-3">
                <label class="form-label">Nombre</label>
                <input type="text" class="form-control" name="nombre" placeholder="Tu nombre" 
                       value="<?= htmlspecialchars($_POST['nombre'] ?? '') ?>" required>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Email</label>
                <input type="email" class="form-control" name="email" id="emailInput" placeholder="tu@email.com" 
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required
                       onblur="checkEmailAvailability()">
                <small class="email-feedback" id="emailFeedback" style="display: none;"></small>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Contraseña</label>
                <input type="password" class="form-control" name="password" id="passwordInput" 
                       placeholder="Mín. 8 caracteres, mayúscula, número y especial" required
                       oninput="validatePassword()">
                <div class="password-requirements mt-2" id="passwordRequirements">
                    <small class="req-item" id="reqLength"><i class="fas fa-circle"></i> Mínimo 8 caracteres</small>
                    <small class="req-item" id="reqUpper"><i class="fas fa-circle"></i> Una mayúscula</small>
                    <small class="req-item" id="reqLower"><i class="fas fa-circle"></i> Una minúscula</small>
                    <small class="req-item" id="reqNumber"><i class="fas fa-circle"></i> Un número</small>
                    <small class="req-item" id="reqSpecial"><i class="fas fa-circle"></i> Un carácter especial (!@#$%)</small>
                </div>
            </div>
            
            <div class="mb-4">
                <label class="form-label">Confirmar Contraseña</label>
                <input type="password" class="form-control" name="confirm_password" id="confirmPasswordInput"
                       placeholder="Repite la contraseña" required oninput="validateConfirmPassword()">
                <small class="text-danger" id="confirmPasswordError" style="display: none;">
                    <i class="fas fa-times-circle"></i> Las contraseñas no coinciden
                </small>
            </div>
            
            <button type="submit" class="btn-register">
                <i class="fas fa-user-plus me-2"></i>Crear Perfil
            </button>
        </form>
        
        <a href="login.php" class="btn-back">
            <i class="fas fa-arrow-left me-2"></i>Volver al login
        </a>
        
        <?php endif; ?>
    </div>
    
    <script>
        // Preview de avatar
        document.getElementById('avatarInput').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('avatarPreview').innerHTML = 
                        `<img src="${e.target.result}" alt="Preview">`;
                };
                reader.readAsDataURL(file);
            }
        });
        
        // Selector de color
        function selectColor(element) {
            document.querySelectorAll('.color-option').forEach(el => el.classList.remove('selected'));
            element.classList.add('selected');
            document.getElementById('colorPerfil').value = element.dataset.color;
            document.getElementById('avatarPreview').style.borderColor = element.dataset.color;
        }
        
        // Validación de contraseña en tiempo real
        function validatePassword() {
            const password = document.getElementById('passwordInput').value;
            
            // Requisitos
            const requirements = {
                length: password.length >= 8,
                upper: /[A-Z]/.test(password),
                lower: /[a-z]/.test(password),
                number: /[0-9]/.test(password),
                special: /[!@#$%^&*(),.?":{}|<>_\-\[\]\\;'`~\/+=]/.test(password)
            };
            
            // Actualizar indicadores visuales
            updateRequirement('reqLength', requirements.length);
            updateRequirement('reqUpper', requirements.upper);
            updateRequirement('reqLower', requirements.lower);
            updateRequirement('reqNumber', requirements.number);
            updateRequirement('reqSpecial', requirements.special);
            
            // Validar confirmación si ya tiene contenido
            const confirmInput = document.getElementById('confirmPasswordInput');
            if (confirmInput.value) {
                validateConfirmPassword();
            }
            
            return Object.values(requirements).every(r => r);
        }
        
        function updateRequirement(id, isValid) {
            const element = document.getElementById(id);
            const icon = element.querySelector('i');
            element.classList.remove('valid', 'invalid');
            
            if (isValid) {
                element.classList.add('valid');
                icon.className = 'fas fa-check-circle';
            } else if (document.getElementById('passwordInput').value.length > 0) {
                element.classList.add('invalid');
                icon.className = 'fas fa-times-circle';
            } else {
                icon.className = 'fas fa-circle';
            }
        }
        
        // Validar que las contraseñas coincidan
        function validateConfirmPassword() {
            const password = document.getElementById('passwordInput').value;
            const confirm = document.getElementById('confirmPasswordInput').value;
            const errorMsg = document.getElementById('confirmPasswordError');
            
            if (confirm && password !== confirm) {
                errorMsg.style.display = 'block';
                return false;
            } else {
                errorMsg.style.display = 'none';
                return true;
            }
        }
        
        // Verificar disponibilidad de email
        let emailCheckTimeout;
        function checkEmailAvailability() {
            const email = document.getElementById('emailInput').value.trim();
            const feedback = document.getElementById('emailFeedback');
            
            // Validar formato de email
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!email || !emailRegex.test(email)) {
                feedback.style.display = 'none';
                return;
            }
            
            feedback.style.display = 'block';
            feedback.className = 'email-feedback';
            feedback.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Verificando...';
            
            // Usar API para verificar email
            clearTimeout(emailCheckTimeout);
            emailCheckTimeout = setTimeout(() => {
                fetch('api/check_email.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'email=' + encodeURIComponent(email)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.available) {
                        feedback.className = 'email-feedback email-available';
                        feedback.innerHTML = '<i class="fas fa-check-circle"></i> Email disponible';
                    } else {
                        feedback.className = 'email-feedback email-taken';
                        feedback.innerHTML = '<i class="fas fa-times-circle"></i> Este email ya está registrado';
                    }
                })
                .catch(() => {
                    feedback.style.display = 'none';
                });
            }, 300);
        }
        
        // Validar formulario antes de enviar
        document.querySelector('form')?.addEventListener('submit', function(e) {
            if (!validatePassword() || !validateConfirmPassword()) {
                e.preventDefault();
                alert('Por favor, corrige los errores en el formulario antes de continuar.');
            }
        });

        // ============================================
        // PANDA ANIMADO - INTERACTIVIDAD
        // ============================================
        const panda = document.getElementById('panda');
        const pupilLeft = document.getElementById('pupilLeft');
        const pupilRight = document.getElementById('pupilRight');
        const nameInput = document.querySelector('input[name="nombre"]');
        const emailInput = document.getElementById('emailInput');
        const passwordInput = document.getElementById('passwordInput');
        const confirmPasswordInput = document.getElementById('confirmPasswordInput');

        // Variables para seguimiento de ojos
        let isPasswordField = false;
        let maxPupilMove = 3;

        // Función para cambiar estado emocional del panda
        function setPandaEmotion(emotion) {
            if (!panda) return;
            panda.classList.remove('happy', 'sad', 'covering');
            if (emotion) {
                panda.classList.add(emotion);
            }
        }

        // Función para mover pupilas
        function movePupils(inputElement) {
            if (!inputElement || isPasswordField || !panda) return;
            
            const inputRect = inputElement.getBoundingClientRect();
            const pandaRect = panda.getBoundingClientRect();
            
            const pandaCenterX = pandaRect.left + pandaRect.width / 2;
            const pandaCenterY = pandaRect.top + pandaRect.height / 2;
            const inputCenterX = inputRect.left + inputRect.width / 2;
            const inputCenterY = inputRect.top + inputRect.height / 2;
            
            const deltaX = inputCenterX - pandaCenterX;
            const deltaY = inputCenterY - pandaCenterY;
            
            const distance = Math.sqrt(deltaX * deltaX + deltaY * deltaY);
            const normalizedX = (deltaX / distance) * maxPupilMove;
            const normalizedY = (deltaY / distance) * maxPupilMove;
            
            const transformValue = `translate(calc(-50% + ${normalizedX}px), calc(-50% + ${normalizedY}px))`;
            if (pupilLeft) pupilLeft.style.transform = transformValue;
            if (pupilRight) pupilRight.style.transform = transformValue;
        }

        // Seguir el texto mientras se escribe
        function followTextInput(event) {
            if (isPasswordField) return;
            const input = event.target;
            const inputRect = input.getBoundingClientRect();
            const pandaRect = panda.getBoundingClientRect();
            
            const textLength = input.value.length;
            const charWidth = 8;
            const cursorX = inputRect.left + 15 + Math.min(textLength * charWidth, inputRect.width - 30);
            const cursorY = inputRect.top + inputRect.height / 2;
            
            const pandaCenterX = pandaRect.left + pandaRect.width / 2;
            const pandaCenterY = pandaRect.top + pandaRect.height / 2;
            
            const deltaX = cursorX - pandaCenterX;
            const deltaY = cursorY - pandaCenterY;
            
            const moveX = Math.max(-maxPupilMove, Math.min(maxPupilMove, deltaX / 25));
            const moveY = Math.max(-maxPupilMove, Math.min(maxPupilMove, deltaY / 15));
            
            const transformValue = `translate(calc(-50% + ${moveX}px), calc(-50% + ${moveY}px))`;
            if (pupilLeft) pupilLeft.style.transform = transformValue;
            if (pupilRight) pupilRight.style.transform = transformValue;
        }

        // Tapar ojos
        function coverEyes() {
            isPasswordField = true;
            setPandaEmotion('covering');
        }

        // Destapar ojos
        function uncoverEyes() {
            isPasswordField = false;
            if (panda) panda.classList.remove('covering');
            resetPupils();
        }

        // Resetear pupilas al centro
        function resetPupils() {
            const transformValue = 'translate(-50%, -50%)';
            if (pupilLeft) pupilLeft.style.transform = transformValue;
            if (pupilRight) pupilRight.style.transform = transformValue;
        }

        // Verificar validez de campos y actualizar emoción
        function checkFieldsAndUpdateEmotion() {
            if (isPasswordField) return;
            
            const password = passwordInput?.value || '';
            const confirmPassword = confirmPasswordInput?.value || '';
            const email = emailInput?.value || '';
            const name = nameInput?.value || '';
            
            // Verificar si hay errores visibles
            const confirmError = document.getElementById('confirmPasswordError');
            const emailFeedback = document.getElementById('emailFeedback');
            
            const hasConfirmError = confirmError && confirmError.style.display !== 'none';
            const hasEmailError = emailFeedback && emailFeedback.classList.contains('email-taken');
            
            // Verificar requisitos de contraseña
            let passwordValid = true;
            if (password.length > 0) {
                passwordValid = password.length >= 8 && 
                               /[A-Z]/.test(password) && 
                               /[a-z]/.test(password) && 
                               /[0-9]/.test(password) && 
                               /[!@#$%^&*(),.?":{}|<>_\-\[\]\\;'`~\/+=]/.test(password);
            }
            
            // Verificar coincidencia de contraseñas
            const passwordsMatch = !confirmPassword || password === confirmPassword;
            
            // Email válido
            const emailValid = !email || /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
            
            // Determinar emoción
            if (hasConfirmError || hasEmailError || !passwordsMatch || (password && !passwordValid)) {
                setPandaEmotion('sad');
            } else if (name && email && emailValid && password && passwordValid && confirmPassword && passwordsMatch) {
                setPandaEmotion('happy');
            } else {
                setPandaEmotion('');
            }
        }

        // Event listeners para campos de texto
        if (nameInput) {
            nameInput.addEventListener('focus', () => { setPandaEmotion(''); movePupils(nameInput); });
            nameInput.addEventListener('input', (e) => { followTextInput(e); checkFieldsAndUpdateEmotion(); });
            nameInput.addEventListener('blur', () => { resetPupils(); checkFieldsAndUpdateEmotion(); });
        }

        if (emailInput) {
            emailInput.addEventListener('focus', () => { setPandaEmotion(''); movePupils(emailInput); });
            emailInput.addEventListener('input', (e) => { followTextInput(e); setTimeout(checkFieldsAndUpdateEmotion, 500); });
            emailInput.addEventListener('blur', () => { resetPupils(); setTimeout(checkFieldsAndUpdateEmotion, 400); });
        }

        // Event listeners para campos de contraseña
        if (passwordInput) {
            passwordInput.addEventListener('focus', coverEyes);
            passwordInput.addEventListener('blur', () => { uncoverEyes(); checkFieldsAndUpdateEmotion(); });
            passwordInput.addEventListener('input', () => setTimeout(checkFieldsAndUpdateEmotion, 100));
        }

        if (confirmPasswordInput) {
            confirmPasswordInput.addEventListener('focus', coverEyes);
            confirmPasswordInput.addEventListener('blur', () => { uncoverEyes(); checkFieldsAndUpdateEmotion(); });
            confirmPasswordInput.addEventListener('input', () => setTimeout(checkFieldsAndUpdateEmotion, 100));
        }

        // Seguir el mouse cuando no hay focus
        document.addEventListener('mousemove', (e) => {
            const activeElement = document.activeElement;
            const isTyping = activeElement && (
                activeElement === nameInput || 
                activeElement === emailInput ||
                activeElement === passwordInput ||
                activeElement === confirmPasswordInput
            );
            
            if (!isTyping && !isPasswordField && panda) {
                const pandaRect = panda.getBoundingClientRect();
                const pandaCenterX = pandaRect.left + pandaRect.width / 2;
                const pandaCenterY = pandaRect.top + pandaRect.height / 2;
                
                const deltaX = e.clientX - pandaCenterX;
                const deltaY = e.clientY - pandaCenterY;
                
                const moveX = Math.max(-maxPupilMove, Math.min(maxPupilMove, deltaX / 60));
                const moveY = Math.max(-maxPupilMove, Math.min(maxPupilMove, deltaY / 60));
                
                const transformValue = `translate(calc(-50% + ${moveX}px), calc(-50% + ${moveY}px))`;
                if (pupilLeft) pupilLeft.style.transform = transformValue;
                if (pupilRight) pupilRight.style.transform = transformValue;
            }
        });

        // Deshabilitar clic derecho en toda la página
        document.addEventListener('contextmenu', function(e) {
            e.preventDefault();
            return false;
        });

        // Deshabilitar atajos de teclado para inspeccionar
        document.addEventListener('keydown', function(e) {
            // F12
            if (e.key === 'F12') {
                e.preventDefault();
                return false;
            }
            // Ctrl+Shift+I (Inspeccionar)
            if (e.ctrlKey && e.shiftKey && e.key === 'I') {
                e.preventDefault();
                return false;
            }
            // Ctrl+Shift+J (Consola)
            if (e.ctrlKey && e.shiftKey && e.key === 'J') {
                e.preventDefault();
                return false;
            }
            // Ctrl+U (Ver código fuente)
            if (e.ctrlKey && e.key === 'u') {
                e.preventDefault();
                return false;
            }
        });
    </script>
</body>
</html>
