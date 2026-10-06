<?php
/**
 * AZX-Finance - Inversiones
 */

// Cargar configuración y base de datos ANTES del header para poder hacer redirects
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

$db = getDB();
$userId = getCurrentUserId();

// Procesar formulario (ANTES del header para poder hacer redirect)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add') {
        $stmt = $db->prepare("
            INSERT INTO inversiones (usuario_id, tipo_id, nombre, descripcion, monto_invertido, monto_actual, 
                                    tasa_interes_anual, fecha_inicio, fecha_vencimiento, plataforma)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $userId,
            $_POST['tipo_id'],
            $_POST['nombre'],
            $_POST['descripcion'],
            $_POST['monto_invertido'],
            $_POST['monto_actual'] ?: $_POST['monto_invertido'],
            $_POST['tasa_interes_anual'] ?: null,
            $_POST['fecha_inicio'],
            $_POST['fecha_vencimiento'] ?: null,
            $_POST['plataforma']
        ]);
        header('Location: inversiones.php?success=added');
        exit;
    }
    
    if ($_POST['action'] === 'edit') {
        $stmt = $db->prepare("
            UPDATE inversiones 
            SET tipo_id = ?, nombre = ?, descripcion = ?, monto_invertido = ?, monto_actual = ?,
                tasa_interes_anual = ?, fecha_inicio = ?, fecha_vencimiento = ?, plataforma = ?, estado = ?
            WHERE id = ? AND usuario_id = ?
        ");
        $stmt->execute([
            $_POST['tipo_id'],
            $_POST['nombre'],
            $_POST['descripcion'],
            $_POST['monto_invertido'],
            $_POST['monto_actual'],
            $_POST['tasa_interes_anual'] ?: null,
            $_POST['fecha_inicio'],
            $_POST['fecha_vencimiento'] ?: null,
            $_POST['plataforma'],
            $_POST['estado'],
            $_POST['id'],
            $userId
        ]);
        header('Location: inversiones.php?success=updated');
        exit;
    }
    
    if ($_POST['action'] === 'actualizar_valor') {
        $stmt = $db->prepare("UPDATE inversiones SET monto_actual = ? WHERE id = ? AND usuario_id = ?");
        $stmt->execute([$_POST['nuevo_valor'], $_POST['inversion_id'], $userId]);
        header('Location: inversiones.php?success=value_updated');
        exit;
    }
}

// Eliminar inversión
if (isset($_GET['delete'])) {
    $stmt = $db->prepare("DELETE FROM inversiones WHERE id = ? AND usuario_id = ?");
    $stmt->execute([$_GET['delete'], $userId]);
    header('Location: inversiones.php?success=deleted');
    exit;
}

// Ahora sí incluir el header (ya no habrá redirects después de este punto)
$pageTitle = 'Inversiones';
require_once __DIR__ . '/../includes/header.php';

// Obtener tipos de inversión
$stmt = $db->query("SELECT * FROM tipos_inversion ORDER BY nombre");
$tiposInversion = $stmt->fetchAll();

// Obtener inversiones activas
$stmt = $db->prepare("
    SELECT i.*, ti.nombre as tipo_nombre, ti.icono, ti.color, ti.riesgo,
           (i.monto_actual - i.monto_invertido) as rendimiento,
           CASE WHEN i.monto_invertido > 0 
                THEN ROUND(((i.monto_actual - i.monto_invertido) / i.monto_invertido) * 100, 2)
                ELSE 0 
           END as rendimiento_porcentaje
    FROM inversiones i
    JOIN tipos_inversion ti ON i.tipo_id = ti.id
    WHERE i.usuario_id = ? AND i.estado = 'activa'
    ORDER BY i.monto_actual DESC
");
$stmt->execute([$userId]);
$inversiones = $stmt->fetchAll();

// Totales
$totalInvertido = array_sum(array_column($inversiones, 'monto_invertido'));
$totalActual = array_sum(array_column($inversiones, 'monto_actual'));
$rendimientoTotal = $totalActual - $totalInvertido;
$rendimientoPorcentaje = $totalInvertido > 0 ? round(($rendimientoTotal / $totalInvertido) * 100, 2) : 0;

// Inversiones por tipo
$stmt = $db->prepare("
    SELECT ti.nombre, ti.color, SUM(i.monto_actual) as total
    FROM inversiones i
    JOIN tipos_inversion ti ON i.tipo_id = ti.id
    WHERE i.usuario_id = ? AND i.estado = 'activa'
    GROUP BY ti.id
    ORDER BY total DESC
");
$stmt->execute([$userId]);
$inversionesPorTipo = $stmt->fetchAll();

// Obtener inversión para editar
$inversionEditar = null;
if (isset($_GET['edit'])) {
    $stmt = $db->prepare("SELECT * FROM inversiones WHERE id = ? AND usuario_id = ?");
    $stmt->execute([$_GET['edit'], $userId]);
    $inversionEditar = $stmt->fetch();
}

$showModal = isset($_GET['add']) || $inversionEditar;

// Colores de riesgo
$coloresRiesgo = [
    'bajo' => 'success',
    'medio' => 'warning',
    'alto' => 'danger',
    'muy_alto' => 'dark'
];
?>

<!-- Page Header -->
<div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-3">
    <div>
        <h1><i class="fas fa-chart-line text-success me-2"></i>Inversiones</h1>
        <p>Administra y monitorea tu portafolio de inversiones</p>
    </div>
    <div class="page-header-actions">
        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#inversionModal">
            <i class="fas fa-plus"></i> Nueva Inversión
        </button>
    </div>
</div>

<?php if (isset($_GET['success'])): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <?php
    switch ($_GET['success']) {
        case 'added': echo 'Inversión registrada correctamente.'; break;
        case 'updated': echo 'Inversión actualizada correctamente.'; break;
        case 'deleted': echo 'Inversión eliminada correctamente.'; break;
        case 'value_updated': echo 'Valor actualizado correctamente.'; break;
    }
    ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Stats Cards -->
<div class="row g-4 mb-4">
    <div class="col-lg-3 col-md-6">
        <div class="card stat-card">
            <div class="stat-icon bg-primary-soft">
                <i class="fas fa-coins"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Total Invertido</div>
                <div class="stat-value"><?= formatMoney($totalInvertido) ?></div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="card stat-card">
            <div class="stat-icon bg-success-soft">
                <i class="fas fa-wallet"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Valor Actual</div>
                <div class="stat-value text-success"><?= formatMoney($totalActual) ?></div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="card stat-card">
            <div class="stat-icon <?= $rendimientoTotal >= 0 ? 'bg-success-soft' : 'bg-danger-soft' ?>">
                <i class="fas fa-<?= $rendimientoTotal >= 0 ? 'arrow-up' : 'arrow-down' ?>"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Rendimiento</div>
                <div class="stat-value <?= $rendimientoTotal >= 0 ? 'text-success' : 'text-danger' ?>">
                    <?= $rendimientoTotal >= 0 ? '+' : '' ?><?= formatMoney($rendimientoTotal) ?>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="card stat-card">
            <div class="stat-icon bg-info-soft">
                <i class="fas fa-percentage"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Rentabilidad</div>
                <div class="stat-value <?= $rendimientoPorcentaje >= 0 ? 'text-success' : 'text-danger' ?>">
                    <?= $rendimientoPorcentaje >= 0 ? '+' : '' ?><?= $rendimientoPorcentaje ?>%
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Guía para Principiantes -->
<div class="card mb-4 border-info">
    <div class="card-header card-header-info">
        <h5 class="card-title mb-0">
            <i class="fas fa-lightbulb me-2"></i>¿Qué es invertir?
        </h5>
    </div>
    <div class="card-body">
        <p class="mb-3">Invertir significa poner tu dinero a trabajar para que crezca con el tiempo. Aquí hay algunos conceptos básicos:</p>
        <div class="row g-3">
            <div class="col-md-3">
                <div class="border rounded p-3 text-center h-100">
                    <i class="fas fa-university fa-2x text-success mb-2"></i>
                    <h6>CDT</h6>
                    <small class="text-muted">Bajo riesgo, rendimiento fijo garantizado</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="border rounded p-3 text-center h-100">
                    <i class="fas fa-chart-line fa-2x text-primary mb-2"></i>
                    <h6>Acciones</h6>
                    <small class="text-muted">Mayor riesgo, potencial de crecimiento</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="border rounded p-3 text-center h-100">
                    <i class="fas fa-layer-group fa-2x text-purple mb-2" style="color: #6f42c1;"></i>
                    <h6>Fondos</h6>
                    <small class="text-muted">Diversificación automática</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="border rounded p-3 text-center h-100">
                    <i class="fab fa-bitcoin fa-2x text-warning mb-2"></i>
                    <h6>Cripto</h6>
                    <small class="text-muted">Alto riesgo, alta volatilidad</small>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- Lista de Inversiones -->
    <div class="col-lg-8">
        <?php if (count($inversiones) > 0): ?>
        <div class="row g-4">
            <?php foreach ($inversiones as $inv): ?>
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <div class="rounded-circle d-inline-flex align-items-center justify-content-center mb-2" 
                                     style="width: 40px; height: 40px; background: <?= $inv['color'] ?>20; color: <?= $inv['color'] ?>">
                                    <i class="fas <?= $inv['icono'] ?>"></i>
                                </div>
                                <h5 class="mb-1"><?= htmlspecialchars($inv['nombre']) ?></h5>
                                <div class="d-flex gap-2 flex-wrap">
                                    <span class="badge" style="background: <?= $inv['color'] ?>20; color: <?= $inv['color'] ?>">
                                        <?= htmlspecialchars($inv['tipo_nombre']) ?>
                                    </span>
                                    <span class="badge bg-<?= $coloresRiesgo[$inv['riesgo']] ?>">
                                        Riesgo <?= ucfirst($inv['riesgo']) ?>
                                    </span>
                                </div>
                            </div>
                            <div class="dropdown">
                                <button class="btn btn-sm btn-soft-primary" data-bs-toggle="dropdown">
                                    <i class="fas fa-ellipsis-v"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li>
                                        <a class="dropdown-item" href="?actualizar=<?= $inv['id'] ?>">
                                            <i class="fas fa-sync me-2"></i>Actualizar valor
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="?edit=<?= $inv['id'] ?>">
                                            <i class="fas fa-edit me-2"></i>Editar
                                        </a>
                                    </li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <button class="dropdown-item text-danger" 
                                                data-delete="?delete=<?= $inv['id'] ?>" 
                                                data-name="esta inversión">
                                            <i class="fas fa-trash me-2"></i>Eliminar
                                        </button>
                                    </li>
                                </ul>
                            </div>
                        </div>
                        
                        <?php if ($inv['plataforma']): ?>
                        <p class="text-muted small mb-2">
                            <i class="fas fa-building me-1"></i><?= htmlspecialchars($inv['plataforma']) ?>
                        </p>
                        <?php endif; ?>
                        
                        <div class="row text-center mb-3">
                            <div class="col-6">
                                <small class="text-muted d-block">Invertido</small>
                                <strong><?= formatMoney($inv['monto_invertido']) ?></strong>
                            </div>
                            <div class="col-6">
                                <small class="text-muted d-block">Valor Actual</small>
                                <strong class="text-success"><?= formatMoney($inv['monto_actual']) ?></strong>
                            </div>
                        </div>
                        
                        <div class="bg-light rounded p-2 text-center">
                            <small class="text-muted">Rendimiento: </small>
                            <strong class="<?= $inv['rendimiento'] >= 0 ? 'text-success' : 'text-danger' ?>">
                                <?= $inv['rendimiento'] >= 0 ? '+' : '' ?><?= formatMoney($inv['rendimiento']) ?>
                                (<?= $inv['rendimiento_porcentaje'] >= 0 ? '+' : '' ?><?= $inv['rendimiento_porcentaje'] ?>%)
                            </strong>
                        </div>
                        
                        <?php if ($inv['tasa_interes_anual']): ?>
                        <div class="mt-2 text-center">
                            <small class="text-muted">
                                <i class="fas fa-percentage me-1"></i>
                                Tasa anual: <?= $inv['tasa_interes_anual'] ?>%
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
                    <i class="fas fa-chart-pie"></i>
                    <h4>No tienes inversiones registradas</h4>
                    <p>Empieza a construir tu portafolio de inversiones</p>
                    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#inversionModal">
                        <i class="fas fa-plus"></i> Agregar Inversión
                    </button>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Resumen por Tipo -->
    <div class="col-lg-4">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Portafolio por Tipo</h5>
            </div>
            <div class="card-body">
                <?php if (count($inversionesPorTipo) > 0): ?>
                <div class="chart-container" style="height: 200px;">
                    <canvas id="portafolioChart"></canvas>
                </div>
                <div class="mt-4">
                    <?php foreach ($inversionesPorTipo as $tipo): 
                        $porcentaje = $totalActual > 0 ? round(($tipo['total'] / $totalActual) * 100) : 0;
                    ?>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span>
                            <span class="d-inline-block rounded-circle me-2" 
                                  style="width: 10px; height: 10px; background: <?= $tipo['color'] ?>"></span>
                            <?= htmlspecialchars($tipo['nombre']) ?>
                        </span>
                        <span>
                            <strong><?= formatMoney($tipo['total']) ?></strong>
                            <small class="text-muted">(<?= $porcentaje ?>%)</small>
                        </span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="empty-state py-4">
                    <i class="fas fa-chart-pie"></i>
                    <p class="mb-0">Sin datos</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Calculadora de Interés Compuesto -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-calculator me-2"></i>Calculadora
                </h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label small">Capital Inicial</label>
                    <input type="number" class="form-control form-control-sm" id="calcCapital" value="1000">
                </div>
                <div class="mb-3">
                    <label class="form-label small">Aporte Mensual</label>
                    <input type="number" class="form-control form-control-sm" id="calcAporte" value="100">
                </div>
                <div class="mb-3">
                    <label class="form-label small">Tasa Anual (%)</label>
                    <input type="number" class="form-control form-control-sm" id="calcTasa" value="8" step="0.1">
                </div>
                <div class="mb-3">
                    <label class="form-label small">Años</label>
                    <input type="number" class="form-control form-control-sm" id="calcAnios" value="5">
                </div>
                <button class="btn btn-primary w-100" onclick="calcularInteresCompuesto()">Calcular</button>
                <div id="resultadoCalculo" class="mt-3 text-center" style="display: none;">
                    <small class="text-muted d-block">Valor futuro estimado:</small>
                    <h3 class="text-success mb-0" id="valorFuturo"></h3>
                    <small class="text-muted">Ganancia: <span id="gananciaTotal"></span></small>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Nueva/Editar Inversión -->
<div class="modal fade <?= $showModal ? 'show' : '' ?>" id="inversionModal" tabindex="-1" <?= $showModal ? 'style="display: block;"' : '' ?>>
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="<?= $inversionEditar ? 'edit' : 'add' ?>">
                <?php if ($inversionEditar): ?>
                <input type="hidden" name="id" value="<?= $inversionEditar['id'] ?>">
                <?php endif; ?>
                
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-chart-line text-success me-2"></i>
                        <?= $inversionEditar ? 'Editar Inversión' : 'Nueva Inversión' ?>
                    </h5>
                    <a href="inversiones.php" class="btn-close"></a>
                </div>
                
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label">Nombre</label>
                            <input type="text" class="form-control" name="nombre" required
                                   value="<?= htmlspecialchars($inversionEditar['nombre'] ?? '') ?>"
                                   placeholder="Ej: CDT Banco Nacional, Acciones Apple">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Tipo de Inversión</label>
                            <select class="form-select" name="tipo_id" required>
                                <option value="">Seleccionar...</option>
                                <?php foreach ($tiposInversion as $tipo): ?>
                                <option value="<?= $tipo['id'] ?>" <?= ($inversionEditar['tipo_id'] ?? '') == $tipo['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($tipo['nombre']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-12">
                            <label class="form-label">Descripción</label>
                            <textarea class="form-control" name="descripcion" rows="2"><?= htmlspecialchars($inversionEditar['descripcion'] ?? '') ?></textarea>
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label">Monto Invertido</label>
                            <div class="input-group">
                                <span class="input-group-text"><?= APP_CURRENCY_SYMBOL ?></span>
                                <input type="number" class="form-control" name="monto_invertido" step="0.01" required
                                       value="<?= $inversionEditar['monto_invertido'] ?? '' ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Valor Actual</label>
                            <div class="input-group">
                                <span class="input-group-text"><?= APP_CURRENCY_SYMBOL ?></span>
                                <input type="number" class="form-control" name="monto_actual" step="0.01"
                                       value="<?= $inversionEditar['monto_actual'] ?? '' ?>"
                                       placeholder="Igual al invertido">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Tasa Interés Anual (%)</label>
                            <input type="number" class="form-control" name="tasa_interes_anual" step="0.01"
                                   value="<?= $inversionEditar['tasa_interes_anual'] ?? '' ?>" placeholder="0.00">
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label">Plataforma</label>
                            <input type="text" class="form-control" name="plataforma"
                                   value="<?= htmlspecialchars($inversionEditar['plataforma'] ?? '') ?>"
                                   placeholder="Ej: Banco, eToro, Binance">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Fecha de Inicio</label>
                            <input type="date" class="form-control" name="fecha_inicio" required
                                   value="<?= $inversionEditar['fecha_inicio'] ?? date('Y-m-d') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Fecha Vencimiento</label>
                            <input type="date" class="form-control" name="fecha_vencimiento"
                                   value="<?= $inversionEditar['fecha_vencimiento'] ?? '' ?>">
                        </div>
                        
                        <?php if ($inversionEditar): ?>
                        <div class="col-12">
                            <label class="form-label">Estado</label>
                            <select class="form-select" name="estado">
                                <option value="activa" <?= $inversionEditar['estado'] == 'activa' ? 'selected' : '' ?>>Activa</option>
                                <option value="vendida" <?= $inversionEditar['estado'] == 'vendida' ? 'selected' : '' ?>>Vendida</option>
                                <option value="vencida" <?= $inversionEditar['estado'] == 'vencida' ? 'selected' : '' ?>>Vencida</option>
                                <option value="pausada" <?= $inversionEditar['estado'] == 'pausada' ? 'selected' : '' ?>>Pausada</option>
                            </select>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <a href="inversiones.php" class="btn btn-secondary">Cancelar</a>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save me-1"></i>
                        <?= $inversionEditar ? 'Actualizar' : 'Guardar' ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php if ($showModal): ?><div class="modal-backdrop fade show"></div><?php endif; ?>

<!-- Modal Actualizar Valor -->
<?php if (isset($_GET['actualizar'])): 
    $stmt = $db->prepare("SELECT * FROM inversiones WHERE id = ? AND usuario_id = ?");
    $stmt->execute([$_GET['actualizar'], $userId]);
    $invActualizar = $stmt->fetch();
?>
<div class="modal fade show" id="actualizarModal" tabindex="-1" style="display: block;">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="actualizar_valor">
                <input type="hidden" name="inversion_id" value="<?= $invActualizar['id'] ?>">
                
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-sync text-primary me-2"></i>Actualizar Valor
                    </h5>
                    <a href="inversiones.php" class="btn-close"></a>
                </div>
                
                <div class="modal-body">
                    <div class="bg-light rounded p-3 mb-4">
                        <h6 class="mb-1"><?= htmlspecialchars($invActualizar['nombre']) ?></h6>
                        <small class="text-muted">Valor actual: <?= formatMoney($invActualizar['monto_actual']) ?></small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Nuevo Valor</label>
                        <div class="input-group">
                            <span class="input-group-text"><?= APP_CURRENCY_SYMBOL ?></span>
                            <input type="number" class="form-control" name="nuevo_valor" step="0.01" required
                                   value="<?= $invActualizar['monto_actual'] ?>">
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <a href="inversiones.php" class="btn btn-secondary">Cancelar</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-check me-1"></i> Actualizar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<div class="modal-backdrop fade show"></div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    <?php if (count($inversionesPorTipo) > 0): ?>
    App.createDoughnutChart('portafolioChart',
        <?= json_encode(array_column($inversionesPorTipo, 'nombre')) ?>,
        <?= json_encode(array_map('floatval', array_column($inversionesPorTipo, 'total'))) ?>,
        <?= json_encode(array_column($inversionesPorTipo, 'color')) ?>
    );
    <?php endif; ?>
});

function calcularInteresCompuesto() {
    const capital = parseFloat(document.getElementById('calcCapital').value) || 0;
    const aporte = parseFloat(document.getElementById('calcAporte').value) || 0;
    const tasa = parseFloat(document.getElementById('calcTasa').value) / 100;
    const anios = parseInt(document.getElementById('calcAnios').value) || 1;
    
    const meses = anios * 12;
    const tasaMensual = tasa / 12;
    
    let valorFuturo = capital * Math.pow(1 + tasaMensual, meses);
    valorFuturo += aporte * ((Math.pow(1 + tasaMensual, meses) - 1) / tasaMensual);
    
    const totalInvertido = capital + (aporte * meses);
    const ganancia = valorFuturo - totalInvertido;
    
    document.getElementById('valorFuturo').textContent = App.formatMoney(valorFuturo);
    document.getElementById('gananciaTotal').textContent = App.formatMoney(ganancia);
    document.getElementById('resultadoCalculo').style.display = 'block';
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
