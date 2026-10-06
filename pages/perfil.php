<?php
/**
 * AZX-Finance - Mi Perfil
 */
$pageTitle = 'Mi Perfil';
require_once __DIR__ . '/../includes/header.php';

$db = getDB();
$userId = getCurrentUserId();
$success = '';
$error = '';

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

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_profile') {
        $nombre = trim($_POST['nombre']);
        $email = trim($_POST['email']);
        $colorPerfil = $_POST['color_perfil'];
        $moneda = $_POST['moneda_principal'];
        
        // Validaciones
        if (empty($nombre) || empty($email)) {
            $error = 'El nombre y email son obligatorios';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'El email no es válido';
        } else {
            // Verificar email único (excepto el usuario actual)
            $stmt = $db->prepare("SELECT id FROM usuarios WHERE email = ? AND id != ?");
            $stmt->execute([$email, $userId]);
            if ($stmt->fetch()) {
                $error = 'Este email ya está en uso por otro perfil';
            } else {
                // Procesar avatar si se subió
                $avatar = $currentUser['avatar'];
                if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
                    $uploadDir = ROOT_PATH . 'uploads/avatars/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }
                    
                    $ext = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
                    $allowedExt = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                    
                    if (in_array($ext, $allowedExt)) {
                        // Eliminar avatar anterior si no es default
                        if ($currentUser['avatar'] && $currentUser['avatar'] !== 'default.png') {
                            $oldAvatar = $uploadDir . $currentUser['avatar'];
                            if (file_exists($oldAvatar)) {
                                unlink($oldAvatar);
                            }
                        }
                        
                        $avatar = 'avatar_' . $userId . '_' . time() . '.' . $ext;
                        move_uploaded_file($_FILES['avatar']['tmp_name'], $uploadDir . $avatar);
                    }
                }
                
                // Actualizar datos
                $stmt = $db->prepare("
                    UPDATE usuarios 
                    SET nombre = ?, email = ?, avatar = ?, color_perfil = ?, moneda_principal = ?
                    WHERE id = ?
                ");
                $stmt->execute([$nombre, $email, $avatar, $colorPerfil, $moneda, $userId]);
                
                // Actualizar sesión
                $_SESSION['user_name'] = $nombre;
                $_SESSION['user_avatar'] = $avatar;
                
                $success = 'Perfil actualizado correctamente';
                
                // Refrescar datos del usuario
                $stmt = $db->prepare("SELECT * FROM usuarios WHERE id = ?");
                $stmt->execute([$userId]);
                $currentUser = $stmt->fetch();
            }
        }
    }
    
    if ($_POST['action'] === 'change_password') {
        $currentPassword = $_POST['current_password'];
        $newPassword = $_POST['new_password'];
        $confirmPassword = $_POST['confirm_password'];
        
        if (!password_verify($currentPassword, $currentUser['password'])) {
            $error = 'La contraseña actual es incorrecta';
        } elseif (strlen($newPassword) < 4) {
            $error = 'La nueva contraseña debe tener al menos 4 caracteres';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'Las contraseñas nuevas no coinciden';
        } else {
            $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE usuarios SET password = ? WHERE id = ?");
            $stmt->execute([$newPasswordHash, $userId]);
            $success = 'Contraseña actualizada correctamente';
        }
    }
    
    if ($_POST['action'] === 'delete_avatar') {
        if ($currentUser['avatar'] && $currentUser['avatar'] !== 'default.png') {
            $avatarPath = ROOT_PATH . 'uploads/avatars/' . $currentUser['avatar'];
            if (file_exists($avatarPath)) {
                unlink($avatarPath);
            }
        }
        
        $stmt = $db->prepare("UPDATE usuarios SET avatar = 'default.png' WHERE id = ?");
        $stmt->execute([$userId]);
        
        $_SESSION['user_avatar'] = 'default.png';
        $currentUser['avatar'] = 'default.png';
        
        $success = 'Foto de perfil eliminada';
    }
}

// Obtener monedas disponibles
$monedas = $db->query("SELECT codigo, nombre, simbolo FROM monedas WHERE activa = 1 ORDER BY codigo")->fetchAll();
?>

<!-- Page Header -->
<div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-3">
    <div>
        <h1><i class="fas fa-user-circle text-primary me-2"></i>Mi Perfil</h1>
        <p>Administra tu información personal y preferencias</p>
    </div>
</div>

<?php if ($success): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row g-4">
    <!-- Información del Perfil -->
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-user-edit me-2"></i>Información del Perfil
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="update_profile">
                    
                    <div class="row g-4">
                        <!-- Avatar -->
                        <div class="col-12 text-center">
                            <div class="profile-avatar-large mb-3" id="avatarPreview" 
                                 style="border-color: <?= $currentUser['color_perfil'] ?>">
                                <?php if ($currentUser['avatar'] && $currentUser['avatar'] !== 'default.png' && file_exists(ROOT_PATH . "uploads/avatars/{$currentUser['avatar']}")): ?>
                                    <img src="<?= BASE_URL ?>uploads/avatars/<?= htmlspecialchars($currentUser['avatar']) ?>" alt="">
                                <?php else: ?>
                                    <div class="placeholder-avatar" style="background: <?= $currentUser['color_perfil'] ?>">
                                        <i class="fas fa-user"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="d-flex gap-2 justify-content-center">
                                <label class="btn btn-soft-primary">
                                    <i class="fas fa-camera me-1"></i>Cambiar Foto
                                    <input type="file" name="avatar" accept="image/*" style="display: none;" 
                                           onchange="previewAvatar(this)">
                                </label>
                                <?php if ($currentUser['avatar'] && $currentUser['avatar'] !== 'default.png'): ?>
                                <button type="submit" name="action" value="delete_avatar" class="btn btn-soft-danger" 
                                        onclick="return confirm('¿Eliminar la foto de perfil?')">
                                    <i class="fas fa-trash me-1"></i>Eliminar
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Color del Perfil -->
                        <div class="col-12">
                            <label class="form-label">Color del Perfil</label>
                            <div class="d-flex flex-wrap gap-2">
                                <?php foreach ($coloresDisponibles as $color => $nombre): ?>
                                <label class="color-option-wrapper">
                                    <input type="radio" name="color_perfil" value="<?= $color ?>" 
                                           <?= $currentUser['color_perfil'] == $color ? 'checked' : '' ?>
                                           onchange="document.getElementById('avatarPreview').style.borderColor = this.value">
                                    <span class="color-option" style="background: <?= $color ?>" title="<?= $nombre ?>"></span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Nombre</label>
                            <input type="text" class="form-control" name="nombre" required
                                   value="<?= htmlspecialchars($currentUser['nombre']) ?>">
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" required
                                   value="<?= htmlspecialchars($currentUser['email']) ?>">
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Moneda Principal</label>
                            <select class="form-select" name="moneda_principal">
                                <?php foreach ($monedas as $m): ?>
                                <option value="<?= $m['codigo'] ?>" <?= $currentUser['moneda_principal'] == $m['codigo'] ? 'selected' : '' ?>>
                                    <?= $m['simbolo'] ?> <?= $m['codigo'] ?> - <?= $m['nombre'] ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Miembro desde</label>
                            <input type="text" class="form-control" readonly 
                                   value="<?= formatDate($currentUser['fecha_registro'], 'd M Y') ?>">
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i>Guardar Cambios
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Cambiar Contraseña y Estadísticas -->
    <div class="col-lg-4">
        <!-- Cambiar Contraseña -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-lock me-2"></i>Cambiar Contraseña
                </h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="change_password">
                    
                    <div class="mb-3">
                        <label class="form-label">Contraseña Actual</label>
                        <input type="password" class="form-control" name="current_password" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Nueva Contraseña</label>
                        <input type="password" class="form-control" name="new_password" required
                               minlength="4" placeholder="Mínimo 4 caracteres">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Confirmar Nueva Contraseña</label>
                        <input type="password" class="form-control" name="confirm_password" required>
                    </div>
                    
                    <button type="submit" class="btn btn-warning w-100">
                        <i class="fas fa-key me-1"></i>Cambiar Contraseña
                    </button>
                </form>
            </div>
        </div>
        
        <!-- Estadísticas del Perfil -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-chart-bar me-2"></i>Mis Estadísticas
                </h5>
            </div>
            <div class="card-body">
                <?php
                // Obtener estadísticas del usuario
                $stmt = $db->prepare("SELECT COUNT(*) FROM ingresos WHERE usuario_id = ?");
                $stmt->execute([$userId]);
                $totalIngresos = $stmt->fetchColumn();
                
                $stmt = $db->prepare("SELECT COUNT(*) FROM gastos WHERE usuario_id = ?");
                $stmt->execute([$userId]);
                $totalGastos = $stmt->fetchColumn();
                
                $stmt = $db->prepare("SELECT COUNT(*) FROM metas_ahorro WHERE usuario_id = ?");
                $stmt->execute([$userId]);
                $totalMetas = $stmt->fetchColumn();
                
                $stmt = $db->prepare("SELECT COUNT(*) FROM inversiones WHERE usuario_id = ?");
                $stmt->execute([$userId]);
                $totalInversiones = $stmt->fetchColumn();
                ?>
                
                <div class="row g-3 text-center">
                    <div class="col-6">
                        <div class="bg-success bg-opacity-10 rounded p-3">
                            <h4 class="text-success mb-0"><?= $totalIngresos ?></h4>
                            <small class="text-muted">Ingresos</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="bg-danger bg-opacity-10 rounded p-3">
                            <h4 class="text-danger mb-0"><?= $totalGastos ?></h4>
                            <small class="text-muted">Gastos</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="bg-primary bg-opacity-10 rounded p-3">
                            <h4 class="text-primary mb-0"><?= $totalMetas ?></h4>
                            <small class="text-muted">Metas</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="bg-warning bg-opacity-10 rounded p-3">
                            <h4 class="text-warning mb-0"><?= $totalInversiones ?></h4>
                            <small class="text-muted">Inversiones</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.profile-avatar-large {
    width: 150px;
    height: 150px;
    border-radius: 50%;
    border: 5px solid #ff6b00;
    margin: 0 auto;
    overflow: hidden;
    background: var(--bg-secondary);
}

.profile-avatar-large img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.profile-avatar-large .placeholder-avatar {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 4rem;
    color: #fff;
}

.color-option-wrapper {
    cursor: pointer;
}

.color-option-wrapper input {
    display: none;
}

.color-option {
    display: block;
    width: 35px;
    height: 35px;
    border-radius: 50%;
    border: 3px solid transparent;
    transition: transform 0.2s, border-color 0.2s;
}

.color-option-wrapper input:checked + .color-option {
    border-color: #fff;
    transform: scale(1.1);
    box-shadow: 0 0 10px rgba(0,0,0,0.3);
}

.color-option:hover {
    transform: scale(1.1);
}
</style>

<script>
function previewAvatar(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('avatarPreview').innerHTML = 
                `<img src="${e.target.result}" alt="Preview">`;
        };
        reader.readAsDataURL(input.files[0]);
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
