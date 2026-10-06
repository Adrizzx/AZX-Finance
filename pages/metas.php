<?php
/**
 * AZX-Finance - Metas de Ahorro
 */

// Cargar configuración y base de datos ANTES del header para poder hacer redirects
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

$db = getDB();
$userId = getCurrentUserId();
$errors = [];

// Función para calcular el saldo disponible del usuario
function calcularSaldoDisponible($db, $userId) {
    // Total ingresos
    $stmt = $db->prepare("SELECT COALESCE(SUM(monto), 0) as total FROM ingresos WHERE usuario_id = ?");
    $stmt->execute([$userId]);
    $totalIngresos = $stmt->fetch()['total'];
    
    // Total gastos
    $stmt = $db->prepare("SELECT COALESCE(SUM(monto), 0) as total FROM gastos WHERE usuario_id = ?");
    $stmt->execute([$userId]);
    $totalGastos = $stmt->fetch()['total'];
    
    return $totalIngresos - $totalGastos;
}

// Obtener o crear categoría de gastos "Ahorro Metas"
$stmt = $db->prepare("SELECT id FROM categorias_gastos WHERE nombre = 'Ahorro Metas' AND (usuario_id = ? OR usuario_id IS NULL) LIMIT 1");
$stmt->execute([$userId]);
$catAhorro = $stmt->fetch();

if (!$catAhorro) {
    $stmt = $db->prepare("INSERT INTO categorias_gastos (nombre, icono, color, usuario_id) VALUES ('Ahorro Metas', 'fa-piggy-bank', '#28a745', ?)");
    $stmt->execute([$userId]);
    $categoriaAhorroId = $db->lastInsertId();
} else {
    $categoriaAhorroId = $catAhorro['id'];
}

// Procesar formulario de meta (ANTES del header para poder hacer redirect)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add' || $_POST['action'] === 'edit') {
        // Validaciones del servidor
        $nombre = trim($_POST['nombre'] ?? '');
        $montoObjetivo = floatval($_POST['monto_objetivo'] ?? 0);
        $fechaInicio = $_POST['fecha_inicio'] ?? '';
        $fechaLimite = $_POST['fecha_limite'] ?? '';
        
        if (empty($nombre) || strlen($nombre) < 2) {
            $errors[] = 'El nombre de la meta es obligatorio (mínimo 2 caracteres)';
        }
        
        if ($montoObjetivo <= 0) {
            $errors[] = 'El monto objetivo debe ser mayor a 0';
        }
        
        if (empty($fechaInicio)) {
            $errors[] = 'La fecha de inicio es obligatoria';
        }
        
        // Fecha límite es opcional
        // Validar fechas solo si ambas existen
        if (!empty($fechaInicio) && !empty($fechaLimite)) {
            $inicio = strtotime($fechaInicio);
            $limite = strtotime($fechaLimite);
            if ($limite < $inicio) {
                $errors[] = 'La fecha límite no puede ser anterior a la fecha de inicio';
            }
        }
        
        // Si hay errores, no procesar
        if (!empty($errors)) {
            // Los errores se mostrarán en la página
        } else {
            if ($_POST['action'] === 'add') {
                $stmt = $db->prepare("
                    INSERT INTO metas_ahorro (usuario_id, nombre, descripcion, monto_objetivo, fecha_inicio, fecha_limite, prioridad, color)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $userId,
                    $nombre,
                    trim($_POST['descripcion'] ?? ''),
                    $montoObjetivo,
                    $fechaInicio,
                    !empty($fechaLimite) ? $fechaLimite : null,
                    $_POST['prioridad'] ?? 'media',
                    $_POST['color'] ?? '#007bff'
                ]);
                header('Location: metas.php?success=added');
                exit;
            }
            
            if ($_POST['action'] === 'edit') {
                $stmt = $db->prepare("
                    UPDATE metas_ahorro 
                    SET nombre = ?, descripcion = ?, monto_objetivo = ?, fecha_inicio = ?, 
                        fecha_limite = ?, prioridad = ?, color = ?, estado = ?
                    WHERE id = ? AND usuario_id = ?
                ");
                $stmt->execute([
                    $nombre,
                    trim($_POST['descripcion'] ?? ''),
                    $montoObjetivo,
                    $fechaInicio,
                    !empty($fechaLimite) ? $fechaLimite : null,
                    $_POST['prioridad'] ?? 'media',
                    $_POST['color'] ?? '#007bff',
                    $_POST['estado'] ?? 'activa',
                    $_POST['id'],
                    $userId
                ]);
                header('Location: metas.php?success=updated');
                exit;
            }
        }
    }
    
    if ($_POST['action'] === 'aporte') {
        // Validar aporte
        $montoAporte = floatval($_POST['monto_aporte'] ?? 0);
        $fechaAporte = $_POST['fecha_aporte'] ?? '';
        
        if ($montoAporte <= 0) {
            $errors[] = 'El monto del aporte debe ser mayor a 0';
        }
        
        if (empty($fechaAporte)) {
            $errors[] = 'La fecha del aporte es obligatoria';
        }
        
        // Verificar que tenga saldo disponible
        $saldoActual = calcularSaldoDisponible($db, $userId);
        if ($montoAporte > $saldoActual) {
            $errors[] = 'No tienes suficiente saldo disponible. Tu saldo actual es ' . formatMoney($saldoActual);
        }
        
        if (empty($errors)) {
            // Obtener nombre de la meta para la descripción del gasto
            $stmt = $db->prepare("SELECT nombre FROM metas_ahorro WHERE id = ?");
            $stmt->execute([$_POST['meta_id']]);
            $metaNombre = $stmt->fetchColumn();
            
            // Registrar aporte a la meta
            $stmt = $db->prepare("INSERT INTO aportes_metas (meta_id, monto, fecha, nota) VALUES (?, ?, ?, ?)");
            $stmt->execute([
                $_POST['meta_id'],
                $montoAporte,
                $fechaAporte,
                trim($_POST['nota_aporte'] ?? '')
            ]);
            $aporteId = $db->lastInsertId();
            
            // Registrar como GASTO para descontar del saldo
            $stmt = $db->prepare("
                INSERT INTO gastos (usuario_id, categoria_id, monto, descripcion, fecha, referencia_tipo, referencia_id)
                VALUES (?, ?, ?, ?, ?, 'aporte_meta', ?)
            ");
            $stmt->execute([
                $userId,
                $categoriaAhorroId,
                $montoAporte,
                'Aporte a meta: ' . $metaNombre,
                $fechaAporte,
                $aporteId
            ]);
            
            // Actualizar monto actual de la meta
            $stmt = $db->prepare("UPDATE metas_ahorro SET monto_actual = monto_actual + ? WHERE id = ?");
            $stmt->execute([$montoAporte, $_POST['meta_id']]);
            
            // Verificar si se completó la meta
            $stmt = $db->prepare("SELECT monto_actual, monto_objetivo FROM metas_ahorro WHERE id = ?"  );
            $stmt->execute([$_POST['meta_id']]);
            $meta = $stmt->fetch();
            
            if ($meta['monto_actual'] >= $meta['monto_objetivo']) {
                $stmt = $db->prepare("UPDATE metas_ahorro SET estado = 'completada' WHERE id = ?");
                $stmt->execute([$_POST['meta_id']]);
            }
            
            header('Location: metas.php?success=aporte');
            exit;
        }
    }
}

// Eliminar meta
if (isset($_GET['delete'])) {
    // Primero eliminar los gastos asociados a los aportes de esta meta
    $stmt = $db->prepare("
        DELETE g FROM gastos g
        INNER JOIN aportes_metas a ON g.referencia_id = a.id AND g.referencia_tipo = 'aporte_meta'
        WHERE a.meta_id = ?
    ");
    $stmt->execute([$_GET['delete']]);
    
    // Eliminar los aportes
    $stmt = $db->prepare("DELETE FROM aportes_metas WHERE meta_id = ?");
    $stmt->execute([$_GET['delete']]);
    
    // Eliminar la meta
    $stmt = $db->prepare("DELETE FROM metas_ahorro WHERE id = ? AND usuario_id = ?");
    $stmt->execute([$_GET['delete'], $userId]);
    header('Location: metas.php?success=deleted');
    exit;
}

// Ahora sí incluir el header (ya no habrá redirects después de este punto)
$pageTitle = 'Metas de Ahorro';
require_once __DIR__ . '/../includes/header.php';

// Obtener metas activas
$stmt = $db->prepare("
    SELECT m.*, 
           ROUND((m.monto_actual / m.monto_objetivo) * 100, 1) as porcentaje,
           CASE WHEN m.fecha_limite IS NOT NULL THEN DATEDIFF(m.fecha_limite, CURDATE()) ELSE NULL END as dias_restantes,
           CASE 
               WHEN m.fecha_limite IS NOT NULL AND DATEDIFF(m.fecha_limite, CURDATE()) > 0 
               THEN ROUND((m.monto_objetivo - m.monto_actual) / (DATEDIFF(m.fecha_limite, CURDATE()) / 30), 2)
               ELSE NULL 
           END as ahorro_mensual_necesario,
           CASE 
               WHEN m.fecha_limite IS NOT NULL AND DATEDIFF(m.fecha_limite, CURDATE()) > 0 
               THEN ROUND((m.monto_objetivo - m.monto_actual) / (DATEDIFF(m.fecha_limite, CURDATE()) / 7), 2)
               ELSE NULL 
           END as ahorro_semanal_necesario
    FROM metas_ahorro m
    WHERE m.usuario_id = ? AND m.estado = 'activa'
    ORDER BY m.prioridad DESC, COALESCE(m.fecha_limite, '9999-12-31') ASC
");
$stmt->execute([$userId]);
$metasActivas = $stmt->fetchAll();

// Obtener metas completadas
$stmt = $db->prepare("
    SELECT * FROM metas_ahorro 
    WHERE usuario_id = ? AND estado = 'completada'
    ORDER BY fecha_limite DESC
    LIMIT 5
");
$stmt->execute([$userId]);
$metasCompletadas = $stmt->fetchAll();

// Totales
$totalObjetivo = array_sum(array_column($metasActivas, 'monto_objetivo'));
$totalAhorrado = array_sum(array_column($metasActivas, 'monto_actual'));
$progresoGeneral = $totalObjetivo > 0 ? round(($totalAhorrado / $totalObjetivo) * 100) : 0;

// Calcular saldo disponible
$saldoDisponible = calcularSaldoDisponible($db, $userId);

// Obtener meta para editar
$metaEditar = null;
if (isset($_GET['edit'])) {
    $stmt = $db->prepare("SELECT * FROM metas_ahorro WHERE id = ? AND usuario_id = ?");
    $stmt->execute([$_GET['edit'], $userId]);
    $metaEditar = $stmt->fetch();
}

// Obtener historial de aportes de una meta
$aportesMeta = [];
if (isset($_GET['ver'])) {
    $stmt = $db->prepare("
        SELECT a.*, m.nombre as meta_nombre 
        FROM aportes_metas a
        JOIN metas_ahorro m ON a.meta_id = m.id
        WHERE a.meta_id = ?
        ORDER BY a.fecha DESC
    ");
    $stmt->execute([$_GET['ver']]);
    $aportesMeta = $stmt->fetchAll();
    
    $stmt = $db->prepare("SELECT * FROM metas_ahorro WHERE id = ?");
    $stmt->execute([$_GET['ver']]);
    $metaVer = $stmt->fetch();
}

$showModal = isset($_GET['add']) || $metaEditar;
$showAporteModal = isset($_GET['aporte']);
?>

<!-- Page Header -->
<div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-3">
    <div>
        <h1><i class="fas fa-bullseye text-primary me-2"></i>Metas de Ahorro</h1>
        <p>Define y alcanza tus objetivos financieros</p>
    </div>
    <div class="page-header-actions">
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#metaModal">
            <i class="fas fa-plus"></i> Nueva Meta
        </button>
    </div>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <strong><i class="fas fa-exclamation-triangle me-2"></i>Errores de validación:</strong>
    <ul class="mb-0 mt-2">
        <?php foreach ($errors as $error): ?>
        <li><?= htmlspecialchars($error) ?></li>
        <?php endforeach; ?>
    </ul>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if (isset($_GET['success'])): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <?php
    switch ($_GET['success']) {
        case 'added': echo 'Meta creada correctamente.'; break;
        case 'updated': echo 'Meta actualizada correctamente.'; break;
        case 'deleted': echo 'Meta eliminada correctamente.'; break;
        case 'aporte': echo '¡Aporte registrado! Sigue así.'; break;
    }
    ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Alerta informativa -->
<div class="alert alert-info alert-dismissible fade show mb-4" role="alert">
    <i class="fas fa-info-circle me-2"></i>
    <strong>Tu saldo disponible: <?= formatMoney($saldoDisponible) ?></strong> — 
    Tienes <strong class="text-success"><?= formatMoney($totalAhorrado) ?></strong> apartados en metas de ahorro.
    Al hacer un aporte, el dinero se descuenta de tu saldo.
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>

<!-- Stats Cards -->
<div class="row g-4 mb-4">
    <div class="col-lg-3 col-md-6">
        <div class="card stat-card">
            <div class="stat-icon bg-secondary-soft">
                <i class="fas fa-wallet"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Saldo Disponible</div>
                <div class="stat-value <?= $saldoDisponible >= 0 ? 'text-primary' : 'text-danger' ?>"><?= formatMoney($saldoDisponible) ?></div>
                <small class="text-muted">Para aportar</small>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="card stat-card">
            <div class="stat-icon bg-success-soft">
                <i class="fas fa-piggy-bank"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Total Ahorrado</div>
                <div class="stat-value text-success"><?= formatMoney($totalAhorrado) ?></div>
                <small class="text-muted">En <?= count($metasActivas) ?> meta(s)</small>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="card stat-card">
            <div class="stat-icon bg-warning-soft">
                <i class="fas fa-crosshairs"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Falta por Ahorrar</div>
                <div class="stat-value text-warning"><?= formatMoney($totalObjetivo - $totalAhorrado) ?></div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="card stat-card">
            <div class="stat-icon bg-info-soft">
                <i class="fas fa-percentage"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Progreso General</div>
                <div class="stat-value"><?= $progresoGeneral ?>%</div>
            </div>
        </div>
    </div>
</div>

<!-- Lista de Metas Activas -->
<?php if (count($metasActivas) > 0): ?>
<div class="row g-4 mb-4">
    <?php foreach ($metasActivas as $meta): ?>
    <div class="col-lg-6">
        <div class="card goal-card h-100" style="border-left-color: <?= $meta['color'] ?>;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <h5 class="mb-1"><?= htmlspecialchars($meta['nombre']) ?></h5>
                        <div class="d-flex gap-2 flex-wrap">
                            <span class="badge badge-soft-<?= $meta['prioridad'] === 'urgente' ? 'danger' : ($meta['prioridad'] === 'alta' ? 'warning' : 'info') ?>">
                                <?= ucfirst($meta['prioridad']) ?>
                            </span>
                            <?php if ($meta['fecha_limite']): ?>
                                <?php if ($meta['dias_restantes'] <= 30 && $meta['dias_restantes'] > 0): ?>
                                <span class="badge badge-soft-warning">
                                    <i class="fas fa-clock me-1"></i><?= $meta['dias_restantes'] ?> días
                                </span>
                                <?php elseif ($meta['dias_restantes'] <= 0): ?>
                                <span class="badge badge-soft-danger">Vencida</span>
                                <?php endif; ?>
                            <?php else: ?>
                            <span class="badge badge-soft-secondary">
                                <i class="fas fa-infinity me-1"></i>Sin límite
                            </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="dropdown">
                        <button class="btn btn-sm btn-soft-primary" data-bs-toggle="dropdown">
                            <i class="fas fa-ellipsis-v"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li>
                                <a class="dropdown-item" href="?aporte=<?= $meta['id'] ?>">
                                    <i class="fas fa-plus-circle me-2"></i>Agregar aporte
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="?ver=<?= $meta['id'] ?>">
                                    <i class="fas fa-history me-2"></i>Ver historial
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="?edit=<?= $meta['id'] ?>">
                                    <i class="fas fa-edit me-2"></i>Editar
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <button class="dropdown-item text-danger" 
                                        data-delete="?delete=<?= $meta['id'] ?>" 
                                        data-name="esta meta">
                                    <i class="fas fa-trash me-2"></i>Eliminar
                                </button>
                            </li>
                        </ul>
                    </div>
                </div>
                
                <?php if ($meta['descripcion']): ?>
                <p class="text-muted small mb-3"><?= htmlspecialchars($meta['descripcion']) ?></p>
                <?php endif; ?>
                
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted">Progreso</span>
                    <span class="fw-bold" style="color: <?= $meta['color'] ?>"><?= $meta['porcentaje'] ?>%</span>
                </div>
                
                <div class="progress progress-lg mb-3">
                    <div class="progress-bar" style="width: <?= min(100, $meta['porcentaje']) ?>%; background: <?= $meta['color'] ?>"></div>
                </div>
                
                <div class="d-flex justify-content-between mb-3">
                    <div>
                        <small class="text-muted d-block">Ahorrado</small>
                        <strong class="text-success"><?= formatMoney($meta['monto_actual']) ?></strong>
                    </div>
                    <div class="text-center">
                        <small class="text-muted d-block">Falta</small>
                        <strong class="text-danger"><?= formatMoney($meta['monto_objetivo'] - $meta['monto_actual']) ?></strong>
                    </div>
                    <div class="text-end">
                        <small class="text-muted d-block">Objetivo</small>
                        <strong><?= formatMoney($meta['monto_objetivo']) ?></strong>
                    </div>
                </div>
                
                <?php if ($meta['fecha_limite'] && $meta['ahorro_mensual_necesario'] !== null): ?>
                <div class="bg-light rounded p-3">
                    <div class="row text-center">
                        <div class="col-6 border-end">
                            <small class="text-muted d-block">Ahorra por semana</small>
                            <strong class="text-primary"><?= formatMoney($meta['ahorro_semanal_necesario']) ?></strong>
                        </div>
                        <div class="col-6">
                            <small class="text-muted d-block">Ahorra por mes</small>
                            <strong class="text-primary"><?= formatMoney($meta['ahorro_mensual_necesario']) ?></strong>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="mt-3 d-flex gap-2">
                    <a href="?aporte=<?= $meta['id'] ?>" class="btn btn-sm btn-primary flex-grow-1">
                        <i class="fas fa-plus me-1"></i> Agregar Aporte
                    </a>
                    <a href="?ver=<?= $meta['id'] ?>" class="btn btn-sm btn-soft-primary">
                        <i class="fas fa-eye"></i>
                    </a>
                </div>
                
                <?php if ($meta['fecha_limite']): ?>
                <div class="mt-2 text-center">
                    <small class="text-muted">
                        <i class="fas fa-calendar me-1"></i>
                        Fecha límite: <?= formatDate($meta['fecha_limite'], 'd M Y') ?>
                    </small>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php else: ?>
<div class="card">
    <div class="card-body">
        <div class="empty-state">
            <i class="fas fa-bullseye"></i>
            <h4>No tienes metas activas</h4>
            <p>Crea tu primera meta de ahorro y empieza a alcanzar tus sueños.</p>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#metaModal">
                <i class="fas fa-plus"></i> Crear Meta
            </button>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Metas Completadas -->
<?php if (count($metasCompletadas) > 0): ?>
<div class="card mt-4">
    <div class="card-header">
        <h5 class="card-title mb-0">
            <i class="fas fa-trophy text-warning me-2"></i>Metas Completadas
        </h5>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <?php foreach ($metasCompletadas as $meta): ?>
            <div class="col-md-4">
                <div class="card bg-light border-0">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="rounded-circle bg-success text-white d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                            <i class="fas fa-check"></i>
                        </div>
                        <div>
                            <h6 class="mb-0"><?= htmlspecialchars($meta['nombre']) ?></h6>
                            <small class="text-muted"><?= formatMoney($meta['monto_objetivo']) ?></small>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Modal Nueva/Editar Meta -->
<div class="modal fade <?= $showModal ? 'show' : '' ?>" id="metaModal" tabindex="-1" <?= $showModal ? 'style="display: block;"' : '' ?>>
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="<?= $metaEditar ? 'edit' : 'add' ?>">
                <?php if ($metaEditar): ?>
                <input type="hidden" name="id" value="<?= $metaEditar['id'] ?>">
                <?php endif; ?>
                
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-bullseye text-primary me-2"></i>
                        <?= $metaEditar ? 'Editar Meta' : 'Nueva Meta de Ahorro' ?>
                    </h5>
                    <a href="metas.php" class="btn-close"></a>
                </div>
                
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label">Nombre de la Meta</label>
                            <input type="text" class="form-control" name="nombre" required
                                   value="<?= htmlspecialchars($metaEditar['nombre'] ?? '') ?>"
                                   placeholder="Ej: Carro Nuevo, Viaje a Europa, Fondo de Emergencia">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Color</label>
                            <input type="color" class="form-control form-control-color w-100" name="color"
                                   value="<?= $metaEditar['color'] ?? '#007bff' ?>">
                        </div>
                        
                        <div class="col-12">
                            <label class="form-label">Descripción</label>
                            <textarea class="form-control" name="descripcion" rows="2"
                                      placeholder="¿Por qué es importante esta meta para ti?"><?= htmlspecialchars($metaEditar['descripcion'] ?? '') ?></textarea>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Monto Objetivo</label>
                            <div class="input-group">
                                <span class="input-group-text"><?= APP_CURRENCY_SYMBOL ?></span>
                                <input type="number" class="form-control" name="monto_objetivo" step="0.01" required
                                       value="<?= $metaEditar['monto_objetivo'] ?? '' ?>"
                                       placeholder="15000.00">
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Prioridad</label>
                            <select class="form-select" name="prioridad">
                                <option value="baja" <?= ($metaEditar['prioridad'] ?? '') == 'baja' ? 'selected' : '' ?>>Baja</option>
                                <option value="media" <?= ($metaEditar['prioridad'] ?? 'media') == 'media' ? 'selected' : '' ?>>Media</option>
                                <option value="alta" <?= ($metaEditar['prioridad'] ?? '') == 'alta' ? 'selected' : '' ?>>Alta</option>
                                <option value="urgente" <?= ($metaEditar['prioridad'] ?? '') == 'urgente' ? 'selected' : '' ?>>Urgente</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Fecha de Inicio</label>
                            <input type="date" class="form-control" name="fecha_inicio" required
                                   value="<?= $metaEditar['fecha_inicio'] ?? date('Y-m-d') ?>">
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Fecha Límite <small class="text-muted">(opcional)</small></label>
                            <input type="date" class="form-control" name="fecha_limite"
                                   value="<?= $metaEditar['fecha_limite'] ?? '' ?>">
                            <small class="text-muted">Deja vacío si no tienes una fecha específica</small>
                        </div>
                        
                        <?php if ($metaEditar): ?>
                        <div class="col-12">
                            <label class="form-label">Estado</label>
                            <select class="form-select" name="estado">
                                <option value="activa" <?= $metaEditar['estado'] == 'activa' ? 'selected' : '' ?>>Activa</option>
                                <option value="pausada" <?= $metaEditar['estado'] == 'pausada' ? 'selected' : '' ?>>Pausada</option>
                                <option value="completada" <?= $metaEditar['estado'] == 'completada' ? 'selected' : '' ?>>Completada</option>
                                <option value="cancelada" <?= $metaEditar['estado'] == 'cancelada' ? 'selected' : '' ?>>Cancelada</option>
                            </select>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <a href="metas.php" class="btn btn-secondary">Cancelar</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i>
                        <?= $metaEditar ? 'Actualizar' : 'Crear Meta' ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php if ($showModal): ?><div class="modal-backdrop fade show"></div><?php endif; ?>

<!-- Modal Agregar Aporte -->
<?php if ($showAporteModal): 
    $stmt = $db->prepare("SELECT * FROM metas_ahorro WHERE id = ? AND usuario_id = ?");
    $stmt->execute([$_GET['aporte'], $userId]);
    $metaAporte = $stmt->fetch();
    $saldoParaAporte = calcularSaldoDisponible($db, $userId);
?>
<div class="modal fade show" id="aporteModal" tabindex="-1" style="display: block;">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="aporte">
                <input type="hidden" name="meta_id" value="<?= $metaAporte['id'] ?>">
                
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-plus-circle text-success me-2"></i>
                        Agregar Aporte
                    </h5>
                    <a href="metas.php" class="btn-close"></a>
                </div>
                
                <div class="modal-body">
                    <!-- Saldo disponible -->
                    <div class="alert alert-<?= $saldoParaAporte > 0 ? 'info' : 'warning' ?> py-2 mb-3">
                        <i class="fas fa-wallet me-2"></i>
                        <strong>Saldo disponible:</strong> <?= formatMoney($saldoParaAporte) ?>
                    </div>
                    
                    <div class="bg-light rounded p-3 mb-4">
                        <h6 class="mb-1"><?= htmlspecialchars($metaAporte['nombre']) ?></h6>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Progreso actual:</span>
                            <strong><?= formatMoney($metaAporte['monto_actual']) ?> / <?= formatMoney($metaAporte['monto_objetivo']) ?></strong>
                        </div>
                        <div class="progress mt-2" style="height: 8px;">
                            <div class="progress-bar" style="width: <?= min(100, ($metaAporte['monto_actual'] / $metaAporte['monto_objetivo']) * 100) ?>%; background: <?= $metaAporte['color'] ?>"></div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Monto del Aporte</label>
                        <div class="input-group">
                            <span class="input-group-text"><?= APP_CURRENCY_SYMBOL ?></span>
                            <input type="number" class="form-control" name="monto_aporte" step="0.01" required
                                   placeholder="100.00">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Fecha</label>
                        <input type="date" class="form-control" name="fecha_aporte" required
                               value="<?= date('Y-m-d') ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Nota (opcional)</label>
                        <input type="text" class="form-control" name="nota_aporte"
                               placeholder="Ej: Ahorro del mes de febrero">
                    </div>
                </div>
                
                <div class="modal-footer">
                    <a href="metas.php" class="btn btn-secondary">Cancelar</a>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-check me-1"></i> Registrar Aporte
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<div class="modal-backdrop fade show"></div>
<?php endif; ?>

<!-- Modal Ver Historial -->
<?php if (isset($_GET['ver']) && isset($metaVer)): ?>
<div class="modal fade show" id="historialModal" tabindex="-1" style="display: block;">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-history text-info me-2"></i>
                    Historial: <?= htmlspecialchars($metaVer['nombre']) ?>
                </h5>
                <a href="metas.php" class="btn-close"></a>
            </div>
            
            <div class="modal-body">
                <div class="bg-light rounded p-3 mb-4">
                    <div class="row text-center">
                        <div class="col-4">
                            <small class="text-muted d-block">Ahorrado</small>
                            <strong class="text-success fs-5"><?= formatMoney($metaVer['monto_actual']) ?></strong>
                        </div>
                        <div class="col-4">
                            <small class="text-muted d-block">Objetivo</small>
                            <strong class="fs-5"><?= formatMoney($metaVer['monto_objetivo']) ?></strong>
                        </div>
                        <div class="col-4">
                            <small class="text-muted d-block">Aportes</small>
                            <strong class="fs-5"><?= count($aportesMeta) ?></strong>
                        </div>
                    </div>
                </div>
                
                <?php if (count($aportesMeta) > 0): ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Monto</th>
                                <th>Nota</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($aportesMeta as $aporte): ?>
                            <tr>
                                <td><?= formatDate($aporte['fecha']) ?></td>
                                <td class="text-success fw-bold"><?= formatMoney($aporte['monto']) ?></td>
                                <td><?= htmlspecialchars($aporte['nota'] ?: '-') ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="text-center py-4">
                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                    <p class="text-muted">No hay aportes registrados</p>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="modal-footer">
                <a href="metas.php" class="btn btn-secondary">Cerrar</a>
                <a href="?aporte=<?= $metaVer['id'] ?>" class="btn btn-success">
                    <i class="fas fa-plus me-1"></i> Agregar Aporte
                </a>
            </div>
        </div>
    </div>
</div>
<div class="modal-backdrop fade show"></div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    <?php if (isset($_GET['add']) && !$metaEditar): ?>
    new bootstrap.Modal(document.getElementById('metaModal')).show();
    <?php endif; ?>
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
