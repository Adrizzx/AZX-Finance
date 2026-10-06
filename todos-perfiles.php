<?php
/**
 * AZX-Finance - Todos los Perfiles
 */
require_once __DIR__ . '/config/config.php';

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
            
            $stmt = $db->prepare("SELECT * FROM usuarios WHERE id = ?");
            $stmt->execute([$userId]);
            $selectedProfile = $stmt->fetch();
        }
    }
}

// Búsqueda
$search = isset($_GET['q']) ? trim($_GET['q']) : '';

// Obtener todos los perfiles
$sql = "SELECT id, nombre, email, avatar, color_perfil, ultimo_acceso, fecha_registro 
        FROM usuarios WHERE activo = 1";
$params = [];

if ($search) {
    $sql .= " AND (nombre LIKE ? OR email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY ultimo_acceso DESC, nombre ASC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$perfiles = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Todos los Perfiles - AZX-Finance</title>
    
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
            color: #fff;
            padding: 30px;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 40px;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .logo {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .logo-icon {
            width: 50px;
            height: 50px;
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
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .search-box {
            display: flex;
            gap: 10px;
        }
        
        .search-box input {
            background: rgba(15, 23, 42, 0.6);
            border: 2px solid rgba(20, 184, 166, 0.2);
            border-radius: 12px;
            padding: 10px 20px;
            color: #fff;
            min-width: 250px;
            transition: all 0.3s ease;
        }
        
        .search-box input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(20, 184, 166, 0.15);
        }
        
        .search-box input::placeholder {
            color: rgba(148, 163, 184, 0.5);
        }
        
        .search-box button {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: #fff;
            border: none;
            padding: 10px 20px;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(20, 184, 166, 0.3);
        }
        
        .search-box button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(20, 184, 166, 0.4);
        }
        
        h2 {
            color: #94a3b8;
            margin-bottom: 30px;
            font-weight: 300;
        }
        
        .profiles-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 25px;
        }
        
        .profile-card {
            background: rgba(15, 23, 42, 0.6);
            border-radius: 18px;
            padding: 25px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            border: 2px solid rgba(20, 184, 166, 0.1);
        }
        
        .profile-card:hover {
            transform: translateY(-8px);
            background: rgba(15, 23, 42, 0.8);
            border-color: var(--primary-color);
            box-shadow: 0 15px 40px rgba(20, 184, 166, 0.2);
        }
        
        .profile-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            border: 3px solid var(--primary-color);
            margin: 0 auto 15px;
            overflow: hidden;
            background: rgba(15, 23, 42, 0.6);
            transition: all 0.3s ease;
        }
        
        .profile-card:hover .profile-avatar {
            box-shadow: 0 0 25px rgba(20, 184, 166, 0.4);
        }
        
        .profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .profile-avatar .placeholder {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            color: #fff;
        }
        
        .profile-name {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .profile-info {
            font-size: 0.8rem;
            color: #808080;
        }
        
        .btn-back {
            color: #94a3b8;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 20px;
            padding: 8px 16px;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        .btn-back:hover {
            color: var(--primary-color);
            background: rgba(20, 184, 166, 0.1);
        }
        
        .empty-state {
            text-align: center;
            padding: 60px;
            color: #808080;
        }
        
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.5;
        }
        
        /* Modal */
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
        }
        
        .modal-avatar {
            width: 80px;
            height: 80px;
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
        }
        
        .password-input:focus {
            outline: none;
            border-color: var(--primary-color);
        }
        
        .btn-login {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: #fff;
            border: none;
            padding: 15px 40px;
            border-radius: 12px;
            width: 100%;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(20, 184, 166, 0.3);
        }
        
        .btn-login:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(20, 184, 166, 0.4);
        }
        
        .btn-cancel {
            background: transparent;
            color: #94a3b8;
            border: none;
            padding: 10px;
            cursor: pointer;
            margin-top: 15px;
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
        }
    </style>
</head>
<body>
    <a href="login.php" class="btn-back">
        <i class="fas fa-arrow-left"></i> Volver
    </a>
    
    <div class="header">
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
        
        <form class="search-box" method="GET">
            <input type="text" name="q" placeholder="Buscar perfil..." value="<?= htmlspecialchars($search) ?>">
            <button type="submit"><i class="fas fa-search"></i></button>
        </form>
    </div>
    
    <h2>
        <?php if ($search): ?>
            Resultados para "<?= htmlspecialchars($search) ?>" (<?= count($perfiles) ?>)
        <?php else: ?>
            Todos los Perfiles (<?= count($perfiles) ?>)
        <?php endif; ?>
    </h2>
    
    <?php if (count($perfiles) > 0): ?>
    <div class="profiles-grid">
        <?php foreach ($perfiles as $perfil): ?>
        <div class="profile-card" onclick="selectProfile(<?= $perfil['id'] ?>, '<?= htmlspecialchars($perfil['nombre']) ?>', '<?= htmlspecialchars($perfil['avatar']) ?>', '<?= $perfil['color_perfil'] ?>')">
            <div class="profile-avatar" style="border-color: <?= $perfil['color_perfil'] ?>">
                <?php if ($perfil['avatar'] && $perfil['avatar'] !== 'default.png'): ?>
                    <img src="uploads/avatars/<?= htmlspecialchars($perfil['avatar']) ?>" alt="">
                <?php else: ?>
                    <div class="placeholder" style="background: <?= $perfil['color_perfil'] ?>">
                        <i class="fas fa-user"></i>
                    </div>
                <?php endif; ?>
            </div>
            <div class="profile-name"><?= htmlspecialchars($perfil['nombre']) ?></div>
            <div class="profile-info">
                <?php if ($perfil['ultimo_acceso']): ?>
                    Último acceso: <?= date('d/m/Y', strtotime($perfil['ultimo_acceso'])) ?>
                <?php else: ?>
                    Nunca ha ingresado
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
        
        <!-- Añadir nuevo perfil -->
        <div class="profile-card" onclick="window.location.href='registro.php'" style="border: 2px dashed rgba(255,255,255,0.3);">
            <div class="profile-avatar" style="background: transparent; border: none;">
                <div class="placeholder" style="background: transparent; color: rgba(255,255,255,0.5);">
                    <i class="fas fa-plus"></i>
                </div>
            </div>
            <div class="profile-name" style="color: #808080;">Añadir Perfil</div>
        </div>
    </div>
    <?php else: ?>
    <div class="empty-state">
        <i class="fas fa-users-slash"></i>
        <h3>No se encontraron perfiles</h3>
        <p>Intenta con otro término de búsqueda</p>
    </div>
    <?php endif; ?>
    
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
                        <div class="placeholder" style="background: <?= $selectedProfile['color_perfil'] ?? '#14b8a6' ?>; width: 100%; height: 100%; display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-user" style="font-size: 2rem; color: #fff;"></i>
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
                avatarContainer.innerHTML = `<div class="placeholder" style="background: ${color}; width: 100%; height: 100%; display: flex; align-items: center; justify-content: center;">
                    <i class="fas fa-user" style="font-size: 2rem; color: #fff;"></i>
                </div>`;
            }
            
            avatarContainer.style.borderColor = color;
            document.getElementById('passwordModal').style.display = 'flex';
            document.querySelector('.password-input').focus();
        }
        
        function closeModal() {
            document.getElementById('passwordModal').style.display = 'none';
            document.querySelector('.password-input').value = '';
        }
        
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeModal();
        });
        
        document.getElementById('passwordModal').addEventListener('click', function(e) {
            if (e.target === this) closeModal();
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
