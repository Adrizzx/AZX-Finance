<?php
require_once __DIR__ . '/../config/config.php';

// Verificar login (excepto en login.php)
requireLogin();

// Obtener información del usuario actual
$db = getDB();
$userId = getCurrentUserId();
$stmt = $db->prepare("SELECT * FROM usuarios WHERE id = ?");
$stmt->execute([$userId]);
$currentUser = $stmt->fetch();

// Obtener página activa para el menú
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'AZX-Finance' ?> - Gestión Financiera Personal</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- CSS personalizado -->
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
    <!-- Script para cargar tema antes del render (evita flash) -->
    <script>
        (function() {
            const savedTheme = localStorage.getItem('azx-theme');
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            if (savedTheme) {
                document.documentElement.setAttribute('data-theme', savedTheme);
            } else if (prefersDark) {
                document.documentElement.setAttribute('data-theme', 'dark');
            }
        })();
    </script>
</head>
<body>
    <!-- Sidebar -->
    <nav class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="logo">
                <i class="fas fa-wallet"></i>
                <span>AZX-Finance</span>
            </div>
            <button class="sidebar-toggle d-lg-none" id="sidebarClose">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <div class="sidebar-menu">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'index' ? 'active' : '' ?>" href="<?= BASE_URL ?>index.php">
                        <i class="fas fa-home"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                
                <li class="nav-section">Transacciones</li>
                
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'ingresos' ? 'active' : '' ?>" href="<?= BASE_URL ?>pages/ingresos.php">
                        <i class="fas fa-arrow-down text-success"></i>
                        <span>Ingresos</span>
                    </a>
                </li>
                
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'gastos' ? 'active' : '' ?>" href="<?= BASE_URL ?>pages/gastos.php">
                        <i class="fas fa-arrow-up text-danger"></i>
                        <span>Gastos</span>
                    </a>
                </li>
                
                <li class="nav-section">Ahorro e Inversión</li>
                
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'metas' ? 'active' : '' ?>" href="<?= BASE_URL ?>pages/metas.php">
                        <i class="fas fa-bullseye"></i>
                        <span>Metas de Ahorro</span>
                    </a>
                </li>
                
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'ahorros' ? 'active' : '' ?>" href="<?= BASE_URL ?>pages/ahorros.php">
                        <i class="fas fa-piggy-bank"></i>
                        <span>Bolsillos</span>
                    </a>
                </li>
                
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'inversiones' ? 'active' : '' ?>" href="<?= BASE_URL ?>pages/inversiones.php">
                        <i class="fas fa-chart-line"></i>
                        <span>Inversiones</span>
                    </a>
                </li>
                
                <li class="nav-section">Planificación</li>
                
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'presupuesto' ? 'active' : '' ?>" href="<?= BASE_URL ?>pages/presupuesto.php">
                        <i class="fas fa-calculator"></i>
                        <span>Presupuesto</span>
                    </a>
                </li>
                
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'deudas' ? 'active' : '' ?>" href="<?= BASE_URL ?>pages/deudas.php">
                        <i class="fas fa-credit-card"></i>
                        <span>Deudas</span>
                    </a>
                </li>
                
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'prestamos' ? 'active' : '' ?>" href="<?= BASE_URL ?>pages/prestamos.php">
                        <i class="fas fa-hand-holding-usd"></i>
                        <span>Préstamos</span>
                    </a>
                </li>
                
                <li class="nav-section">Herramientas</li>
                
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'simuladores' ? 'active' : '' ?>" href="<?= BASE_URL ?>pages/simuladores.php">
                        <i class="fas fa-magic"></i>
                        <span>Simuladores</span>
                    </a>
                </li>
                
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'reportes' ? 'active' : '' ?>" href="<?= BASE_URL ?>pages/reportes.php">
                        <i class="fas fa-chart-pie"></i>
                        <span>Reportes</span>
                    </a>
                </li>
                
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'conversor' ? 'active' : '' ?>" href="<?= BASE_URL ?>pages/conversor.php">
                        <i class="fas fa-exchange-alt"></i>
                        <span>Conversor</span>
                    </a>
                </li>
            </ul>
        </div>
        
        <div class="sidebar-footer">
            <div class="dropdown w-100">
                <div class="user-info dropdown-toggle" data-bs-toggle="dropdown" role="button">
                    <div class="user-avatar" style="background-color: <?= $currentUser['color_perfil'] ?? '#ff6b00' ?>">
                        <?php if ($currentUser['avatar'] && $currentUser['avatar'] !== 'default.png' && file_exists(ROOT_PATH . "uploads/avatars/{$currentUser['avatar']}")): ?>
                            <img src="<?= BASE_URL ?>uploads/avatars/<?= htmlspecialchars($currentUser['avatar']) ?>" alt="">
                        <?php else: ?>
                            <i class="fas fa-user"></i>
                        <?php endif; ?>
                    </div>
                    <div class="user-details">
                        <span class="user-name"><?= htmlspecialchars($currentUser['nombre'] ?? 'Usuario') ?></span>
                        <span class="user-role">Mi Perfil</span>
                    </div>
                </div>
                <ul class="dropdown-menu dropdown-menu-dark w-100">
                    <li><a class="dropdown-item" href="<?= BASE_URL ?>pages/perfil.php"><i class="fas fa-user-edit me-2"></i>Editar Perfil</a></li>
                    <li><a class="dropdown-item" href="<?= BASE_URL ?>login.php?switch=1"><i class="fas fa-users me-2"></i>Cambiar Perfil</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="<?= BASE_URL ?>logout.php"><i class="fas fa-sign-out-alt me-2"></i>Cerrar Sesión</a></li>
                </ul>
            </div>
        </div>
    </nav>
    
    <!-- Main Content -->
    <main class="main-content">
        <!-- Top Navbar -->
        <nav class="top-navbar">
            <button class="sidebar-toggle d-lg-none" id="sidebarOpen">
                <i class="fas fa-bars"></i>
            </button>
            
            <div class="navbar-search">
                <i class="fas fa-search"></i>
                <input type="text" placeholder="Buscar transacciones...">
            </div>
            
            <div class="navbar-actions">
                <button class="theme-toggle" id="themeToggle" title="Cambiar tema">
                    <i class="fas fa-moon"></i>
                    <i class="fas fa-sun"></i>
                </button>
                <button class="btn-icon" title="Notificaciones">
                    <i class="fas fa-bell"></i>
                    <span class="badge">3</span>
                </button>
                <button class="btn-icon btn-add-quick" data-bs-toggle="modal" data-bs-target="#quickAddModal" title="Agregar rápido">
                    <i class="fas fa-plus"></i>
                </button>
                <div class="dropdown">
                    <div class="user-mini-avatar dropdown-toggle" data-bs-toggle="dropdown" role="button" 
                         style="background-color: <?= $currentUser['color_perfil'] ?? '#ff6b00' ?>">
                        <?php if ($currentUser['avatar'] && $currentUser['avatar'] !== 'default.png' && file_exists(ROOT_PATH . "uploads/avatars/{$currentUser['avatar']}")): ?>
                            <img src="<?= BASE_URL ?>uploads/avatars/<?= htmlspecialchars($currentUser['avatar']) ?>" alt="">
                        <?php else: ?>
                            <i class="fas fa-user"></i>
                        <?php endif; ?>
                    </div>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li class="dropdown-header"><?= htmlspecialchars($currentUser['nombre'] ?? 'Usuario') ?></li>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>pages/perfil.php"><i class="fas fa-user-edit me-2"></i>Mi Perfil</a></li>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>login.php?switch=1"><i class="fas fa-users me-2"></i>Cambiar Perfil</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="<?= BASE_URL ?>logout.php"><i class="fas fa-sign-out-alt me-2"></i>Cerrar Sesión</a></li>
                    </ul>
                </div>
            </div>
        </nav>
        
        <!-- Page Content -->
        <div class="page-content">
