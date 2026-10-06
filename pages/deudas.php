<?php
/**
 * AZX-Finance - Gestión de Deudas
 */

// Cargar configuración y base de datos ANTES del header para poder hacer redirects
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

$db = getDB();
$userId = getCurrentUserId();

$errors = [];

// Procesar formulario (ANTES del header para poder hacer redirect)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add' || $_POST['action'] === 'edit') {
        // Validaciones del servidor
        $nombre = trim($_POST['nombre'] ?? '');
        $montoTotal = floatval($_POST['monto_total'] ?? 0);
        $tasaInteres = floatval($_POST['tasa_interes'] ?? 0);
        $cuotaMensual = floatval($_POST['cuota_mensual'] ?? 0);
        $fechaInicio = $_POST['fecha_inicio'] ?? '';
        $fechaVencimiento = $_POST['fecha_vencimiento'] ?? '';
        
        if (empty($nombre) || strlen($nombre) < 2) {
            $errors[] = 'El nombre de la deuda es obligatorio (mínimo 2 caracteres)';
        }
        
        if ($montoTotal <= 0) {
            $errors[] = 'El monto total debe ser mayor a 0';
        }
        
        if ($tasaInteres < 0 || $tasaInteres > 100) {
            $errors[] = 'La tasa de interés debe estar entre 0% y 100%';
        }
        
        if ($cuotaMensual < 0) {
            $errors[] = 'La cuota mensual no puede ser negativa';
        }
        
        // Validar fechas
        if (!empty($fechaInicio) && !empty($fechaVencimiento)) {
            $inicio = strtotime($fechaInicio);
            $vencimiento = strtotime($fechaVencimiento);
            if ($vencimiento < $inicio) {
                $errors[] = 'La fecha de vencimiento no puede ser anterior a la fecha de inicio';
            }
        }
        
        // Si hay errores, no procesar
        if (!empty($errors)) {
            // Los errores se mostrarán en la página
        } else {
            if ($_POST['action'] === 'add') {
                $stmt = $db->prepare("
                    INSERT INTO deudas (usuario_id, nombre, descripcion, monto_total, tasa_interes, cuota_mensual, 
                                       fecha_inicio, fecha_vencimiento, acreedor, tipo, prioridad)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $userId,
                    $nombre,
                    trim($_POST['descripcion'] ?? ''),
                    $montoTotal,
                    $tasaInteres ?: null,
                    $cuotaMensual ?: null,
                    $fechaInicio ?: date('Y-m-d'),
                    $fechaVencimiento ?: null,
                    trim($_POST['acreedor'] ?? ''),
                    $_POST['tipo'] ?? 'otro',
                    $_POST['prioridad'] ?? 'media'
                ]);
                header('Location: deudas.php?success=added');
                exit;
            }
            
            if ($_POST['action'] === 'edit') {
                $stmt = $db->prepare("
                    UPDATE deudas 
                    SET nombre = ?, descripcion = ?, monto_total = ?, tasa_interes = ?, cuota_mensual = ?,
                        fecha_inicio = ?, fecha_vencimiento = ?, acreedor = ?, tipo = ?, prioridad = ?, estado = ?
                    WHERE id = ? AND usuario_id = ?
                ");
                $stmt->execute([
                    $nombre,
                    trim($_POST['descripcion'] ?? ''),
                    $montoTotal,
                    $tasaInteres ?: null,
                    $cuotaMensual ?: null,
                    $fechaInicio ?: date('Y-m-d'),
                    $fechaVencimiento ?: null,
                    trim($_POST['acreedor'] ?? ''),
                    $_POST['tipo'] ?? 'otro',
                    $_POST['prioridad'] ?? 'media',
                    $_POST['estado'] ?? 'activa',
                    $_POST['id'],
                    $userId
                ]);
                header('Location: deudas.php?success=updated');
                exit;
            }
        }
    }
    
    if ($_POST['action'] === 'pago') {
        // Validar pago
        $montoPago = floatval($_POST['monto_pago'] ?? 0);
        $fechaPago = $_POST['fecha_pago'] ?? '';
        
        if ($montoPago <= 0) {
            $errors[] = 'El monto del pago debe ser mayor a 0';
        }
        
        if (empty($fechaPago)) {
            $errors[] = 'La fecha del pago es obligatoria';
        }
        
        if (empty($errors)) {
            // Registrar pago
            $stmt = $db->prepare("INSERT INTO pagos_deudas (deuda_id, monto, fecha, nota) VALUES (?, ?, ?, ?)");
            $stmt->execute([
                $_POST['deuda_id'],
                $montoPago,
                $fechaPago,
                trim($_POST['nota_pago'] ?? '')
            ]);
            
            // Actualizar monto pagado
            $stmt = $db->prepare("UPDATE deudas SET monto_pagado = monto_pagado + ? WHERE id = ?");
            $stmt->execute([$montoPago, $_POST['deuda_id']]);
            
            // Verificar si se pagó completa
            $stmt = $db->prepare("SELECT monto_pagado, monto_total FROM deudas WHERE id = ?");
            $stmt->execute([$_POST['deuda_id']]);
            $deuda = $stmt->fetch();
            
            if ($deuda['monto_pagado'] >= $deuda['monto_total']) {
                $stmt = $db->prepare("UPDATE deudas SET estado = 'pagada' WHERE id = ?");
                $stmt->execute([$_POST['deuda_id']]);
            }
            
            header('Location: deudas.php?success=pago');
            exit;
        }
    }
}

// Eliminar deuda
if (isset($_GET['delete'])) {
    $stmt = $db->prepare("DELETE FROM deudas WHERE id = ? AND usuario_id = ?");
    $stmt->execute([$_GET['delete'], $userId]);
    header('Location: deudas.php?success=deleted');
    exit;
}

// Ahora sí incluir el header (ya no habrá redirects después de este punto)
$pageTitle = 'Deudas';
require_once __DIR__ . '/../includes/header.php';

// Obtener deudas activas
$stmt = $db->prepare("
    SELECT d.*, 
           (d.monto_total - d.monto_pagado) as saldo_pendiente,
           ROUND((d.monto_pagado / d.monto_total) * 100, 1) as porcentaje_pagado,
           DATEDIFF(d.fecha_vencimiento, CURDATE()) as dias_restantes
    FROM deudas d
    WHERE d.usuario_id = ? AND d.estado = 'activa'
    ORDER BY d.prioridad DESC, d.fecha_vencimiento ASC
");
$stmt->execute([$userId]);
$deudasActivas = $stmt->fetchAll();

// Deudas pagadas
$stmt = $db->prepare("
    SELECT * FROM deudas 
    WHERE usuario_id = ? AND estado = 'pagada'
    ORDER BY fecha_vencimiento DESC
    LIMIT 5
");
$stmt->execute([$userId]);
$deudasPagadas = $stmt->fetchAll();

// Totales
$totalDeuda = array_sum(array_column($deudasActivas, 'monto_total'));
$totalPagado = array_sum(array_column($deudasActivas, 'monto_pagado'));
$totalPendiente = $totalDeuda - $totalPagado;
$cuotasMensuales = array_sum(array_column($deudasActivas, 'cuota_mensual'));

// Tipos de deuda
$tiposDeuda = [
    'tarjeta_credito' => 'Tarjeta de Crédito',
    'prestamo_personal' => 'Préstamo Personal',
    'hipoteca' => 'Hipoteca',
    'prestamo_amigo' => 'Préstamo de Amigo/Familiar',
    'otro' => 'Otro'
];

// Obtener deuda para editar
$deudaEditar = null;
if (isset($_GET['edit'])) {
    $stmt = $db->prepare("SELECT * FROM deudas WHERE id = ? AND usuario_id = ?");
    $stmt->execute([$_GET['edit'], $userId]);
    $deudaEditar = $stmt->fetch();
}

$showModal = isset($_GET['add']) || $deudaEditar;
$showPagoModal = isset($_GET['pago']);
?>

<!-- Page Header -->
<div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-3">
    <div>
        <h1><i class="fas fa-credit-card text-warning me-2"></i>Gestión de Deudas</h1>
        <p>Controla y paga tus deudas de manera estratégica</p>
    </div>
    <div class="page-header-actions">
        <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#deudaModal">
            <i class="fas fa-plus"></i> Nueva Deuda
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
        case 'added': echo 'Deuda registrada correctamente.'; break;
        case 'updated': echo 'Deuda actualizada correctamente.'; break;
        case 'deleted': echo 'Deuda eliminada correctamente.'; break;
        case 'pago': echo '¡Pago registrado! Sigue reduciendo tu deuda.'; break;
    }
    ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Stats Cards -->
<div class="row g-4 mb-4">
    <div class="col-lg-3 col-md-6">
        <div class="card stat-card">
            <div class="stat-icon bg-danger-soft">
                <i class="fas fa-file-invoice-dollar"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Deuda Total</div>
                <div class="stat-value text-danger"><?= formatMoney($totalDeuda) ?></div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="card stat-card">
            <div class="stat-icon bg-success-soft">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Total Pagado</div>
                <div class="stat-value text-success"><?= formatMoney($totalPagado) ?></div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="card stat-card">
            <div class="stat-icon bg-warning-soft">
                <i class="fas fa-hourglass-half"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Pendiente</div>
                <div class="stat-value"><?= formatMoney($totalPendiente) ?></div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="card stat-card">
            <div class="stat-icon bg-info-soft">
                <i class="fas fa-calendar-check"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Cuotas Mensuales</div>
                <div class="stat-value"><?= formatMoney($cuotasMensuales) ?></div>
            </div>
        </div>
    </div>
</div>

<!-- Lista de Deudas Activas -->
<?php if (count($deudasActivas) > 0): ?>
<div class="row g-4 mb-4">
    <?php foreach ($deudasActivas as $deuda): ?>
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <h5 class="mb-1"><?= htmlspecialchars($deuda['nombre']) ?></h5>
                        <div class="d-flex gap-2 flex-wrap">
                            <span class="badge bg-secondary"><?= $tiposDeuda[$deuda['tipo']] ?></span>
                            <span class="badge badge-soft-<?= $deuda['prioridad'] === 'urgente' ? 'danger' : ($deuda['prioridad'] === 'alta' ? 'warning' : 'info') ?>">
                                <?= ucfirst($deuda['prioridad']) ?>
                            </span>
                            <?php if ($deuda['dias_restantes'] <= 30 && $deuda['dias_restantes'] > 0): ?>
                            <span class="badge badge-soft-warning">
                                <i class="fas fa-clock me-1"></i><?= $deuda['dias_restantes'] ?> días
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
                                <a class="dropdown-item" href="?pago=<?= $deuda['id'] ?>">
                                    <i class="fas fa-money-bill me-2 text-success"></i>Registrar pago
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="?edit=<?= $deuda['id'] ?>">
                                    <i class="fas fa-edit me-2"></i>Editar
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <button class="dropdown-item text-danger" 
                                        data-delete="?delete=<?= $deuda['id'] ?>" 
                                        data-name="esta deuda">
                                    <i class="fas fa-trash me-2"></i>Eliminar
                                </button>
                            </li>
                        </ul>
                    </div>
                </div>
                
                <?php if ($deuda['acreedor']): ?>
                <p class="text-muted small mb-2">
                    <i class="fas fa-building me-1"></i><?= htmlspecialchars($deuda['acreedor']) ?>
                </p>
                <?php endif; ?>
                
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted">Progreso de pago</span>
                    <span class="fw-bold <?= $deuda['porcentaje_pagado'] >= 75 ? 'text-success' : '' ?>">
                        <?= $deuda['porcentaje_pagado'] ?>%
                    </span>
                </div>
                
                <div class="progress progress-lg mb-3">
                    <div class="progress-bar bg-success" style="width: <?= $deuda['porcentaje_pagado'] ?>%"></div>
                </div>
                
                <div class="row text-center mb-3">
                    <div class="col-4">
                        <small class="text-muted d-block">Pagado</small>
                        <strong class="text-success"><?= formatMoney($deuda['monto_pagado']) ?></strong>
                    </div>
                    <div class="col-4">
                        <small class="text-muted d-block">Pendiente</small>
                        <strong class="text-danger"><?= formatMoney($deuda['saldo_pendiente']) ?></strong>
                    </div>
                    <div class="col-4">
                        <small class="text-muted d-block">Total</small>
                        <strong><?= formatMoney($deuda['monto_total']) ?></strong>
                    </div>
                </div>
                
                <?php if ($deuda['cuota_mensual']): ?>
                <div class="bg-light rounded p-2 mb-3 text-center">
                    <small class="text-muted">Cuota mensual: </small>
                    <strong class="text-primary"><?= formatMoney($deuda['cuota_mensual']) ?></strong>
                    <?php if ($deuda['tasa_interes']): ?>
                    <small class="text-muted ms-2">(<?= $deuda['tasa_interes'] ?>% interés)</small>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <div class="d-flex gap-2">
                    <a href="?pago=<?= $deuda['id'] ?>" class="btn btn-success flex-grow-1">
                        <i class="fas fa-money-bill me-1"></i> Registrar Pago
                    </a>
                </div>
                
                <?php if ($deuda['fecha_vencimiento']): ?>
                <div class="mt-2 text-center">
                    <small class="text-muted">
                        <i class="fas fa-calendar me-1"></i>
                        Vence: <?= formatDate($deuda['fecha_vencimiento'], 'd M Y') ?>
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
            <i class="fas fa-check-double text-success"></i>
            <h4>¡Sin deudas activas!</h4>
            <p>Excelente, no tienes deudas pendientes. Mantén tus finanzas saludables.</p>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Estrategias de Pago -->
<?php if (count($deudasActivas) > 1): ?>
<div class="card mb-4">
    <div class="card-header">
        <h5 class="card-title mb-0">
            <i class="fas fa-lightbulb text-warning me-2"></i>Estrategia de Pago Recomendada
        </h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <div class="border rounded p-3 h-100">
                    <h6 class="text-primary mb-2"><i class="fas fa-snowflake me-2"></i>Método Bola de Nieve</h6>
                    <p class="small text-muted mb-2">Paga primero la deuda más pequeña para ganar motivación.</p>
                    <?php
                    $deudasOrdenadas = $deudasActivas;
                    usort($deudasOrdenadas, fn($a, $b) => $a['saldo_pendiente'] <=> $b['saldo_pendiente']);
                    ?>
                    <strong>Orden sugerido:</strong>
                    <ol class="small mb-0 mt-1">
                        <?php foreach (array_slice($deudasOrdenadas, 0, 3) as $d): ?>
                        <li><?= htmlspecialchars($d['nombre']) ?> (<?= formatMoney($d['saldo_pendiente']) ?>)</li>
                        <?php endforeach; ?>
                    </ol>
                </div>
            </div>
            <div class="col-md-6 mt-3 mt-md-0">
                <div class="border rounded p-3 h-100">
                    <h6 class="text-danger mb-2"><i class="fas fa-bolt me-2"></i>Método Avalancha</h6>
                    <p class="small text-muted mb-2">Paga primero la deuda con mayor interés para ahorrar dinero.</p>
                    <?php
                    $deudasOrdenadas = $deudasActivas;
                    usort($deudasOrdenadas, fn($a, $b) => $b['tasa_interes'] <=> $a['tasa_interes']);
                    ?>
                    <strong>Orden sugerido:</strong>
                    <ol class="small mb-0 mt-1">
                        <?php foreach (array_slice($deudasOrdenadas, 0, 3) as $d): ?>
                        <li><?= htmlspecialchars($d['nombre']) ?> (<?= $d['tasa_interes'] ?>% interés)</li>
                        <?php endforeach; ?>
                    </ol>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Modal Nueva/Editar Deuda -->
<div class="modal fade <?= $showModal ? 'show' : '' ?>" id="deudaModal" tabindex="-1" <?= $showModal ? 'style="display: block;"' : '' ?>>
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="<?= $deudaEditar ? 'edit' : 'add' ?>">
                <?php if ($deudaEditar): ?>
                <input type="hidden" name="id" value="<?= $deudaEditar['id'] ?>">
                <?php endif; ?>
                
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-credit-card text-warning me-2"></i>
                        <?= $deudaEditar ? 'Editar Deuda' : 'Nueva Deuda' ?>
                    </h5>
                    <a href="deudas.php" class="btn-close"></a>
                </div>
                
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label">Nombre de la Deuda</label>
                            <input type="text" class="form-control" name="nombre" required
                                   value="<?= htmlspecialchars($deudaEditar['nombre'] ?? '') ?>"
                                   placeholder="Ej: Tarjeta Visa, Préstamo Banco">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Tipo</label>
                            <select class="form-select" name="tipo">
                                <?php foreach ($tiposDeuda as $key => $val): ?>
                                <option value="<?= $key ?>" <?= ($deudaEditar['tipo'] ?? '') == $key ? 'selected' : '' ?>>
                                    <?= $val ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-12">
                            <label class="form-label">Descripción</label>
                            <textarea class="form-control" name="descripcion" rows="2"><?= htmlspecialchars($deudaEditar['descripcion'] ?? '') ?></textarea>
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label">Monto Total</label>
                            <div class="input-group">
                                <span class="input-group-text"><?= APP_CURRENCY_SYMBOL ?></span>
                                <input type="number" class="form-control" name="monto_total" step="0.01" required
                                       value="<?= $deudaEditar['monto_total'] ?? '' ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Cuota Mensual</label>
                            <div class="input-group">
                                <span class="input-group-text"><?= APP_CURRENCY_SYMBOL ?></span>
                                <input type="number" class="form-control" name="cuota_mensual" step="0.01"
                                       value="<?= $deudaEditar['cuota_mensual'] ?? '' ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Tasa Interés (%)</label>
                            <input type="number" class="form-control" name="tasa_interes" step="0.01"
                                   value="<?= $deudaEditar['tasa_interes'] ?? '' ?>" placeholder="0.00">
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Acreedor</label>
                            <input type="text" class="form-control" name="acreedor"
                                   value="<?= htmlspecialchars($deudaEditar['acreedor'] ?? '') ?>"
                                   placeholder="¿A quién le debes?">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Prioridad</label>
                            <select class="form-select" name="prioridad">
                                <option value="baja" <?= ($deudaEditar['prioridad'] ?? '') == 'baja' ? 'selected' : '' ?>>Baja</option>
                                <option value="media" <?= ($deudaEditar['prioridad'] ?? 'media') == 'media' ? 'selected' : '' ?>>Media</option>
                                <option value="alta" <?= ($deudaEditar['prioridad'] ?? '') == 'alta' ? 'selected' : '' ?>>Alta</option>
                                <option value="urgente" <?= ($deudaEditar['prioridad'] ?? '') == 'urgente' ? 'selected' : '' ?>>Urgente</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Fecha de Inicio</label>
                            <input type="date" class="form-control" name="fecha_inicio"
                                   value="<?= $deudaEditar['fecha_inicio'] ?? date('Y-m-d') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Fecha de Vencimiento</label>
                            <input type="date" class="form-control" name="fecha_vencimiento"
                                   value="<?= $deudaEditar['fecha_vencimiento'] ?? '' ?>">
                        </div>
                        
                        <?php if ($deudaEditar): ?>
                        <div class="col-12">
                            <label class="form-label">Estado</label>
                            <select class="form-select" name="estado">
                                <option value="activa" <?= $deudaEditar['estado'] == 'activa' ? 'selected' : '' ?>>Activa</option>
                                <option value="pausada" <?= $deudaEditar['estado'] == 'pausada' ? 'selected' : '' ?>>Pausada</option>
                                <option value="pagada" <?= $deudaEditar['estado'] == 'pagada' ? 'selected' : '' ?>>Pagada</option>
                            </select>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <a href="deudas.php" class="btn btn-secondary">Cancelar</a>
                    <button type="submit" class="btn btn-warning">
                        <i class="fas fa-save me-1"></i>
                        <?= $deudaEditar ? 'Actualizar' : 'Guardar' ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php if ($showModal): ?><div class="modal-backdrop fade show"></div><?php endif; ?>

<!-- Modal Pago -->
<?php if ($showPagoModal): 
    $stmt = $db->prepare("SELECT * FROM deudas WHERE id = ? AND usuario_id = ?");
    $stmt->execute([$_GET['pago'], $userId]);
    $deudaPago = $stmt->fetch();
?>
<div class="modal fade show" id="pagoModal" tabindex="-1" style="display: block;">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="pago">
                <input type="hidden" name="deuda_id" value="<?= $deudaPago['id'] ?>">
                
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-money-bill text-success me-2"></i>Registrar Pago
                    </h5>
                    <a href="deudas.php" class="btn-close"></a>
                </div>
                
                <div class="modal-body">
                    <div class="bg-light rounded p-3 mb-4">
                        <h6 class="mb-1"><?= htmlspecialchars($deudaPago['nombre']) ?></h6>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Saldo pendiente:</span>
                            <strong class="text-danger"><?= formatMoney($deudaPago['monto_total'] - $deudaPago['monto_pagado']) ?></strong>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Monto del Pago</label>
                        <div class="input-group">
                            <span class="input-group-text"><?= APP_CURRENCY_SYMBOL ?></span>
                            <input type="number" class="form-control" name="monto_pago" step="0.01" required
                                   value="<?= $deudaPago['cuota_mensual'] ?>" placeholder="0.00">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Fecha del Pago</label>
                        <input type="date" class="form-control" name="fecha_pago" required value="<?= date('Y-m-d') ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Nota</label>
                        <input type="text" class="form-control" name="nota_pago" placeholder="Ej: Pago de febrero">
                    </div>
                </div>
                
                <div class="modal-footer">
                    <a href="deudas.php" class="btn btn-secondary">Cancelar</a>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-check me-1"></i> Registrar Pago
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<div class="modal-backdrop fade show"></div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
