<?php
/**
 * AZX-Finance - Selección de Perfil (Estilo Streaming)
 */
require_once __DIR__ . '/config/config.php';

// Si el usuario quiere cambiar de perfil, cerrar sesión actual
if (isset($_GET['switch']) && $_GET['switch'] == '1') {
    session_destroy();
    session_start();
}
// Si ya hay sesión activa y no está cambiando de perfil, redirigir al dashboard
elseif (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$db = getDB();
$error = '';
$showPasswordModal = false;
$selectedProfile = null;

// Procesar login con contraseña
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'login') {
        $userId = (int)$_POST['user_id'];
        $password = $_POST['password'];
        
        $stmt = $db->prepare("SELECT * FROM usuarios WHERE id = ? AND activo = 1");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            // Login exitoso
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['nombre'];
            $_SESSION['user_avatar'] = $user['avatar'];
            
            // Actualizar último acceso
            $stmt = $db->prepare("UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = ?");
            $stmt->execute([$userId]);
            
            header('Location: index.php');
            exit;
        } else {
            $error = 'Contraseña incorrecta';
            $showPasswordModal = true;
            
            // Recuperar datos del perfil seleccionado
            $stmt = $db->prepare("SELECT * FROM usuarios WHERE id = ?");
            $stmt->execute([$userId]);
            $selectedProfile = $stmt->fetch();
        }
    }
}

// Obtener solo los 5 perfiles más recientes para la vista
$stmt = $db->prepare("
    SELECT id, nombre, email, avatar, color_perfil, ultimo_acceso 
    FROM usuarios 
    WHERE activo = 1 
    ORDER BY ultimo_acceso DESC, id DESC 
    LIMIT ?
");
$stmt->execute([MAX_PROFILES_DISPLAY]);
$perfiles = $stmt->fetchAll();

// Contar total de usuarios
$totalUsuarios = $db->query("SELECT COUNT(*) FROM usuarios WHERE activo = 1")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>¿Quién está usando? - AZX-Finance</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #14b8a6;
            --primary-hover: #0d9488;
            --secondary-color: #06b6d4;
            --bg-dark: #0f172a;
            --bg-gradient: linear-gradient(135deg, #134e4a 0%, #0f766e 30%, #115e59 60%, #0f172a 100%);
            --card-bg: rgba(15, 23, 42, 0.6);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg-gradient);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: #fff;
            padding: 20px;
        }
        
        .login-container {
            text-align: center;
            max-width: 900px;
            width: 100%;
        }
        
        .logo {
            margin-bottom: 20px;
        }
        
        .logo-icon {
            width: 70px;
            height: 70px;
            margin: 0 auto 15px;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 10px 30px rgba(20, 184, 166, 0.3);
        }
        
        .logo-icon svg {
            width: 40px;
            height: 40px;
            fill: #fff;
        }
        
        .logo h1 {
            font-size: 2rem;
            font-weight: 700;
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .question {
            font-size: 2.5rem;
            font-weight: 300;
            margin-bottom: 50px;
            color: #ffffff;
        }
        
        .profiles-grid {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 30px;
            margin-bottom: 40px;
        }
        
        .profile-card {
            display: flex;
            flex-direction: column;
            align-items: center;
            cursor: pointer;
            transition: transform 0.3s ease;
        }
        
        .profile-card:hover {
            transform: scale(1.1);
        }
        
        .profile-avatar {
            width: 130px;
            height: 130px;
            border-radius: 50%;
            border: 4px solid transparent;
            overflow: hidden;
            margin-bottom: 15px;
            position: relative;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
            background: var(--card-bg);
        }
        
        .profile-card:hover .profile-avatar {
            border-color: var(--primary-color);
            box-shadow: 0 0 30px rgba(20, 184, 166, 0.5);
        }
        
        .profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .profile-avatar .placeholder-avatar {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            color: #fff;
        }
        
        .profile-name {
            font-size: 1.1rem;
            color: #b0b0b0;
            transition: color 0.3s ease;
        }
        
        .profile-card:hover .profile-name {
            color: #fff;
        }
        
        .add-profile {
            border: 3px dashed rgba(255, 255, 255, 0.3);
            background: transparent;
        }
        
        .add-profile:hover {
            border-color: var(--primary-color);
        }
        
        .add-profile .placeholder-avatar {
            font-size: 3.5rem;
            color: rgba(255, 255, 255, 0.5);
        }
        
        .add-profile:hover .placeholder-avatar {
            color: var(--primary-color);
        }
        
        .more-users {
            margin-top: 20px;
            color: #808080;
            font-size: 0.9rem;
        }
        
        .more-users a {
            color: var(--primary-color);
            text-decoration: none;
        }
        
        .more-users a:hover {
            text-decoration: underline;
        }
        
        /* Modal de contraseña */
        .password-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            backdrop-filter: blur(5px);
        }
        
        .password-modal-content {
            background: linear-gradient(145deg, #1e1e3f, #2a2a5a);
            padding: 40px;
            border-radius: 20px;
            text-align: center;
            max-width: 400px;
            width: 90%;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
        }
        
        .modal-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            margin: 0 auto 20px;
            overflow: hidden;
            border: 3px solid var(--primary-color);
        }
        
        .modal-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .password-input {
            background: rgba(255, 255, 255, 0.1);
            border: 2px solid rgba(255, 255, 255, 0.2);
            border-radius: 10px;
            padding: 15px 20px;
            color: #fff;
            width: 100%;
            font-size: 1rem;
            margin-bottom: 20px;
            transition: border-color 0.3s ease;
        }
        
        .password-input:focus {
            outline: none;
            border-color: var(--primary-color);
        }
        
        .password-input::placeholder {
            color: rgba(255, 255, 255, 0.5);
        }
        
        .btn-login {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: #fff;
            border: none;
            padding: 15px 40px;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
            box-shadow: 0 4px 15px rgba(20, 184, 166, 0.3);
        }
        
        .btn-login:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(20, 184, 166, 0.4);
            filter: brightness(1.1);
        }
        
        .btn-cancel {
            background: transparent;
            color: #94a3b8;
            border: none;
            padding: 10px;
            cursor: pointer;
            margin-top: 15px;
            font-size: 0.9rem;
            transition: color 0.3s ease;
        }
        
        .btn-cancel:hover {
            color: var(--primary-color);
        }
        
        .error-message {
            background: rgba(220, 53, 69, 0.2);
            color: #ff6b6b;
            padding: 10px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.9rem;
        }
        
        @media (max-width: 768px) {
            .question {
                font-size: 1.8rem;
            }
            
            .profile-avatar {
                width: 100px;
                height: 100px;
            }
            
            .profiles-grid {
                gap: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo">
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
        
        <h2 class="question">¿Quién está usando?</h2>
        
        <div class="profiles-grid">
            <?php foreach ($perfiles as $perfil): ?>
            <div class="profile-card" onclick="selectProfile(<?= $perfil['id'] ?>, '<?= htmlspecialchars($perfil['nombre']) ?>', '<?= htmlspecialchars($perfil['avatar']) ?>', '<?= $perfil['color_perfil'] ?>')">
                <div class="profile-avatar" style="border-color: <?= $perfil['color_perfil'] ?>">
                    <?php if ($perfil['avatar'] && $perfil['avatar'] !== 'default.png' && file_exists("uploads/avatars/{$perfil['avatar']}")): ?>
                        <img src="uploads/avatars/<?= htmlspecialchars($perfil['avatar']) ?>" alt="<?= htmlspecialchars($perfil['nombre']) ?>">
                    <?php else: ?>
                        <div class="placeholder-avatar" style="background: <?= $perfil['color_perfil'] ?>">
                            <i class="fas fa-user"></i>
                        </div>
                    <?php endif; ?>
                </div>
                <span class="profile-name"><?= htmlspecialchars($perfil['nombre']) ?></span>
            </div>
            <?php endforeach; ?>
            
            <!-- Añadir nuevo perfil -->
                <!-- Añadir nuevo perfil: siempre disponible -->
            <div class="profile-card" onclick="window.location.href='registro.php'">
                <div class="profile-avatar add-profile">
                    <div class="placeholder-avatar">
                        <i class="fas fa-plus"></i>
                    </div>
                </div>
                <span class="profile-name">Añadir Perfil</span>
            </div>
        </div>
        
        <?php if ($totalUsuarios > MAX_PROFILES_DISPLAY): ?>
        <p class="more-users">
            Hay <?= $totalUsuarios - MAX_PROFILES_DISPLAY ?> perfiles más. 
            <a href="todos-perfiles.php">Ver todos</a>
        </p>
        <?php endif; ?>
    </div>
    
    <!-- Modal de Contraseña -->
    <div class="password-modal" id="passwordModal" style="display: <?= $showPasswordModal ? 'flex' : 'none' ?>;">
        <div class="password-modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="login">
                <input type="hidden" name="user_id" id="modalUserId" value="<?= $selectedProfile['id'] ?? '' ?>">
                
                <div class="modal-avatar" id="modalAvatar">
                    <?php if ($selectedProfile && $selectedProfile['avatar'] !== 'default.png'): ?>
                        <img src="uploads/avatars/<?= htmlspecialchars($selectedProfile['avatar']) ?>" alt="">
                    <?php else: ?>
                        <div class="placeholder-avatar" style="background: <?= $selectedProfile['color_perfil'] ?? '#14b8a6' ?>">
                            <i class="fas fa-user"></i>
                        </div>
                    <?php endif; ?>
                </div>
                
                <h4 id="modalUserName" style="margin-bottom: 25px;"><?= htmlspecialchars($selectedProfile['nombre'] ?? '') ?></h4>
                
                <?php if ($error): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle me-1"></i> <?= htmlspecialchars($error) ?>
                </div>
                <?php endif; ?>
                
                <input type="password" name="password" class="password-input" placeholder="Ingresa tu contraseña" required autofocus>
                
                <button type="submit" class="btn-login">
                    <i class="fas fa-sign-in-alt me-2"></i>Entrar
                </button>
                
                <button type="button" class="btn-cancel" onclick="closeModal()">Cancelar</button>
            </form>
        </div>
    </div>
    
    <script>
        function selectProfile(userId, userName, avatar, color) {
            document.getElementById('modalUserId').value = userId;
            document.getElementById('modalUserName').textContent = userName;
            
            const avatarContainer = document.getElementById('modalAvatar');
            if (avatar && avatar !== 'default.png') {
                avatarContainer.innerHTML = `<img src="uploads/avatars/${avatar}" alt="">`;
            } else {
                avatarContainer.innerHTML = `<div class="placeholder-avatar" style="background: ${color}"><i class="fas fa-user"></i></div>`;
            }
            
            avatarContainer.style.borderColor = color;
            
            document.getElementById('passwordModal').style.display = 'flex';
            document.querySelector('.password-input').focus();
        }
        
        function closeModal() {
            document.getElementById('passwordModal').style.display = 'none';
            document.querySelector('.password-input').value = '';
        }
        
        // Cerrar modal con Escape
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeModal();
            }
        });
        
        // Cerrar modal al hacer clic fuera
        document.getElementById('passwordModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
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
