<?php
/**
 * AZX-Finance - Dashboard Principal
 */
$pageTitle = 'Dashboard';
require_once __DIR__ . '/includes/header.php';

$db = getDB();
$userId = getCurrentUserId();
$currentMonth = date('m');
$currentYear = date('Y');

// Obtener totales del mes actual
$stmt = $db->prepare("
    SELECT COALESCE(SUM(monto), 0) as total 
    FROM ingresos 
    WHERE usuario_id = ? AND MONTH(fecha) = ? AND YEAR(fecha) = ?
");
$stmt->execute([$userId, $currentMonth, $currentYear]);
$totalIngresos = $stmt->fetch()['total'];

$stmt = $db->prepare("
    SELECT COALESCE(SUM(monto), 0) as total 
    FROM gastos 
    WHERE usuario_id = ? AND MONTH(fecha) = ? AND YEAR(fecha) = ?
");
$stmt->execute([$userId, $currentMonth, $currentYear]);
$totalGastos = $stmt->fetch()['total'];

$balance = $totalIngresos - $totalGastos;

// Obtener total de ahorros (bolsillos)
$stmt = $db->prepare("
    SELECT COALESCE(SUM(saldo), 0) as total 
    FROM bolsillos_ahorro 
    WHERE usuario_id = ? AND activo = 1
");
$stmt->execute([$userId]);
$totalAhorros = $stmt->fetch()['total'];

// Calcular SALDO TOTAL DISPONIBLE (todos los ingresos - todos los gastos)
$stmt = $db->prepare("SELECT COALESCE(SUM(monto), 0) as total FROM ingresos WHERE usuario_id = ?");
$stmt->execute([$userId]);
$totalIngresosHistorico = $stmt->fetch()['total'];

$stmt = $db->prepare("SELECT COALESCE(SUM(monto), 0) as total FROM gastos WHERE usuario_id = ?");
$stmt->execute([$userId]);
$totalGastosHistorico = $stmt->fetch()['total'];

$saldoDisponible = $totalIngresosHistorico - $totalGastosHistorico;

// Obtener total de deudas pendientes
$stmt = $db->prepare("
    SELECT COALESCE(SUM(monto_total - monto_pagado), 0) as total 
    FROM deudas 
    WHERE usuario_id = ? AND estado = 'activa'
");
$stmt->execute([$userId]);
$totalDeudas = $stmt->fetch()['total'];

// Obtener gastos por categoría del mes
$stmt = $db->prepare("
    SELECT cg.nombre, cg.color, cg.icono, COALESCE(SUM(g.monto), 0) as total
    FROM categorias_gastos cg
    LEFT JOIN gastos g ON cg.id = g.categoria_id 
        AND g.usuario_id = ? 
        AND MONTH(g.fecha) = ? 
        AND YEAR(g.fecha) = ?
    WHERE cg.usuario_id = ? OR cg.usuario_id IS NULL
    GROUP BY cg.id
    HAVING total > 0
    ORDER BY total DESC
    LIMIT 6
");
$stmt->execute([$userId, $currentMonth, $currentYear, $userId]);
$gastosPorCategoria = $stmt->fetchAll();

// Obtener metas activas
$stmt = $db->prepare("
    SELECT *, 
           ROUND((monto_actual / monto_objetivo) * 100, 1) as porcentaje,
           DATEDIFF(fecha_limite, CURDATE()) as dias_restantes
    FROM metas_ahorro 
    WHERE usuario_id = ? AND estado = 'activa'
    ORDER BY prioridad DESC
    LIMIT 4
");
$stmt->execute([$userId]);
$metasActivas = $stmt->fetchAll();

// Obtener últimas transacciones con ID para ordenar correctamente
$stmt = $db->prepare("
    SELECT * FROM (
        (SELECT 'ingreso' as tipo, i.id, i.monto, i.descripcion, i.fecha, ci.nombre as categoria, ci.icono, ci.color
         FROM ingresos i
         JOIN categorias_ingresos ci ON i.categoria_id = ci.id
         WHERE i.usuario_id = ?
         ORDER BY i.fecha DESC, i.id DESC
         LIMIT 15)
        UNION ALL
        (SELECT 'gasto' as tipo, g.id, g.monto, g.descripcion, g.fecha, cg.nombre as categoria, cg.icono, cg.color
         FROM gastos g
         JOIN categorias_gastos cg ON g.categoria_id = cg.id
         WHERE g.usuario_id = ?
         ORDER BY g.fecha DESC, g.id DESC
         LIMIT 15)
    ) as combined
    ORDER BY fecha DESC, id DESC
    LIMIT 10
");
$stmt->execute([$userId, $userId]);
$ultimasTransacciones = $stmt->fetchAll();

// Calcular saldo running (balance después de cada transacción)
// Empezamos con el saldo actual y vamos restando/sumando hacia atrás
$saldoRunning = $saldoDisponible;
$transaccionesConSaldo = [];

foreach ($ultimasTransacciones as $trans) {
    $trans['saldo_despues'] = $saldoRunning;
    $transaccionesConSaldo[] = $trans;
    
    // Revertir la transacción para obtener el saldo anterior
    if ($trans['tipo'] === 'ingreso') {
        $saldoRunning -= $trans['monto']; // Quitar el ingreso para el saldo anterior
    } else {
        $saldoRunning += $trans['monto']; // Devolver el gasto para el saldo anterior
    }
}

$ultimasTransacciones = $transaccionesConSaldo;

// Calcular score de salud financiera (simplificado)
$healthScore = 50; // Base
if ($totalIngresos > 0) {
    $tasaAhorro = (($totalIngresos - $totalGastos) / $totalIngresos) * 100;
    if ($tasaAhorro >= 20) $healthScore += 30;
    elseif ($tasaAhorro >= 10) $healthScore += 20;
    elseif ($tasaAhorro > 0) $healthScore += 10;
    else $healthScore -= 20;
}
if ($totalAhorros > $totalGastos) $healthScore += 10;
if ($totalDeudas == 0) $healthScore += 10;
$healthScore = max(0, min(100, $healthScore));

// Datos para gráfica de gastos por mes (últimos 6 meses)
$gastosUltimos6Meses = [];
$ingresosUltimos6Meses = [];
$mesesLabels = [];

for ($i = 5; $i >= 0; $i--) {
    $mes = date('m', strtotime("-$i months"));
    $anio = date('Y', strtotime("-$i months"));
    $mesesLabels[] = getMonthName((int)$mes);
    
    $stmt = $db->prepare("SELECT COALESCE(SUM(monto), 0) as total FROM gastos WHERE usuario_id = ? AND MONTH(fecha) = ? AND YEAR(fecha) = ?");
    $stmt->execute([$userId, $mes, $anio]);
    $gastosUltimos6Meses[] = $stmt->fetch()['total'];
    
    $stmt = $db->prepare("SELECT COALESCE(SUM(monto), 0) as total FROM ingresos WHERE usuario_id = ? AND MONTH(fecha) = ? AND YEAR(fecha) = ?");
    $stmt->execute([$userId, $mes, $anio]);
    $ingresosUltimos6Meses[] = $stmt->fetch()['total'];
}

// Obtener próximos pagos de deudas
$stmt = $db->prepare("
    SELECT nombre, cuota_mensual, fecha_vencimiento, 
           DATEDIFF(fecha_vencimiento, CURDATE()) as dias_restantes
    FROM deudas 
    WHERE usuario_id = ? AND estado = 'activa' AND cuota_mensual > 0
    ORDER BY fecha_vencimiento ASC
    LIMIT 3
");
$stmt->execute([$userId]);
$proximosPagos = $stmt->fetchAll();
?>

<!-- Page Header -->
<div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-3">
    <div>
        <h1>Dashboard</h1>
        <p>Bienvenido a tu panel de control financiero - <?= getMonthName((int)$currentMonth) ?> <?= $currentYear ?></p>
    </div>
    <div class="page-header-actions">
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#quickAddModal">
            <i class="fas fa-plus"></i> Agregar
        </button>
    </div>
</div>

<!-- SALDO DISPONIBLE ACTUAL - Widget Destacado -->
<div class="row g-4 mb-4">
    <div class="col-12">
        <div class="card" style="background: linear-gradient(135deg, #134e4a 0%, #0f766e 50%, #115e59 100%); border: none;">
            <div class="card-body py-4">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div>
                        <h6 class="text-white-50 mb-1"><i class="fas fa-wallet me-2"></i>SALDO DISPONIBLE ACTUAL</h6>
                        <h2 class="mb-0 text-white fw-bold" style="font-size: 2.5rem;">
                            <?= formatMoney($saldoDisponible) ?>
                        </h2>
                        <small class="text-white-50">Total histórico de ingresos menos gastos</small>
                    </div>
                    <div class="text-end">
                        <?php if ($saldoDisponible >= 0): ?>
                            <span class="badge bg-success px-3 py-2" style="font-size: 1rem;">
                                <i class="fas fa-check-circle me-1"></i> Saldo Positivo
                            </span>
                        <?php else: ?>
                            <span class="badge bg-danger px-3 py-2" style="font-size: 1rem;">
                                <i class="fas fa-exclamation-triangle me-1"></i> Saldo Negativo
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Stats Cards -->
<div class="row g-4 mb-4">
    <div class="col-lg-3 col-md-6">
        <div class="card stat-card">
            <div class="stat-icon bg-success-soft">
                <i class="fas fa-arrow-down"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Ingresos del Mes</div>
                <div class="stat-value text-success"><?= formatMoney($totalIngresos) ?></div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-6">
        <div class="card stat-card">
            <div class="stat-icon bg-danger-soft">
                <i class="fas fa-arrow-up"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Gastos del Mes</div>
                <div class="stat-value text-danger"><?= formatMoney($totalGastos) ?></div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-6">
        <div class="card stat-card">
            <div class="stat-icon bg-primary-soft">
                <i class="fas fa-wallet"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Balance del Mes</div>
                <div class="stat-value <?= $balance >= 0 ? 'text-success' : 'text-danger' ?>">
                    <?= formatMoney($balance) ?>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-6">
        <div class="card stat-card">
            <div class="stat-icon bg-info-soft">
                <i class="fas fa-piggy-bank"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Total Ahorrado</div>
                <div class="stat-value text-info"><?= formatMoney($totalAhorros) ?></div>
            </div>
        </div>
    </div>
</div>

<!-- Alertas Rápidas -->
<?php if ($totalDeudas > 0): ?>
<div class="alert-finance warning mb-4">
    <div class="alert-icon">
        <i class="fas fa-exclamation-triangle"></i>
    </div>
    <div class="alert-content">
        <strong>Deudas pendientes:</strong> Tienes <?= formatMoney($totalDeudas) ?> en deudas por pagar.
        <a href="<?= BASE_URL ?>pages/deudas.php" class="ms-2">Ver deudas →</a>
    </div>
</div>
<?php endif; ?>

<div class="row g-4 mb-4">
    <!-- Gráfica de Ingresos vs Gastos -->
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Ingresos vs Gastos</h5>
                <span class="text-muted">Últimos 6 meses</span>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="incomeExpenseChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Salud Financiera -->
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="card-title mb-0">Salud Financiera</h5>
            </div>
            <div class="card-body">
                <div class="health-score">
                    <div class="health-score-circle">
                        <canvas id="healthScoreChart"></canvas>
                        <div class="health-score-value"><?= $healthScore ?></div>
                    </div>
                    <div class="health-score-label">
                        <?php
                        if ($healthScore >= 80) echo '¡Excelente! 🌟';
                        elseif ($healthScore >= 60) echo 'Muy Bien 👍';
                        elseif ($healthScore >= 40) echo 'Regular 🤔';
                        else echo 'Necesitas mejorar 📈';
                        ?>
                    </div>
                </div>
                
                <div class="mt-4">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Tasa de ahorro</span>
                        <strong><?= $totalIngresos > 0 ? round((($totalIngresos - $totalGastos) / $totalIngresos) * 100, 1) : 0 ?>%</strong>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Metas activas</span>
                        <strong><?= count($metasActivas) ?></strong>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-muted">Deudas activas</span>
                        <strong><?= $totalDeudas > 0 ? formatMoney($totalDeudas) : 'Sin deudas' ?></strong>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- Gastos por Categoría -->
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Gastos por Categoría</h5>
                <a href="<?= BASE_URL ?>pages/gastos.php" class="btn btn-sm btn-soft-primary">Ver todo</a>
            </div>
            <div class="card-body">
                <?php if (count($gastosPorCategoria) > 0): ?>
                <div class="chart-container" style="height: 220px;">
                    <canvas id="expenseByCategoryChart"></canvas>
                </div>
                <?php else: ?>
                <div class="empty-state py-4">
                    <i class="fas fa-chart-pie"></i>
                    <p class="mb-0">No hay gastos este mes</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Metas de Ahorro -->
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Metas de Ahorro</h5>
                <a href="<?= BASE_URL ?>pages/metas.php" class="btn btn-sm btn-soft-primary">Ver todas</a>
            </div>
            <div class="card-body">
                <?php if (count($metasActivas) > 0): ?>
                    <?php foreach ($metasActivas as $meta): ?>
                    <div class="goal-card card mb-3" style="border-left-color: <?= $meta['color'] ?>;">
                        <div class="goal-header">
                            <div>
                                <div class="goal-title"><?= htmlspecialchars($meta['nombre']) ?></div>
                                <div class="goal-deadline">
                                    <i class="fas fa-clock me-1"></i>
                                    <?= $meta['dias_restantes'] > 0 ? $meta['dias_restantes'] . ' días restantes' : 'Vencida' ?>
                                </div>
                            </div>
                            <div class="goal-percentage" style="color: <?= $meta['color'] ?>">
                                <?= $meta['porcentaje'] ?>%
                            </div>
                        </div>
                        <div class="progress progress-lg">
                            <div class="progress-bar" style="width: <?= min(100, $meta['porcentaje']) ?>%; background: <?= $meta['color'] ?>"></div>
                        </div>
                        <div class="goal-amounts">
                            <span><?= formatMoney($meta['monto_actual']) ?></span>
                            <span><?= formatMoney($meta['monto_objetivo']) ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                <div class="empty-state py-4">
                    <i class="fas fa-bullseye"></i>
                    <p class="mb-0">No tienes metas activas</p>
                    <a href="<?= BASE_URL ?>pages/metas.php?add=1" class="btn btn-sm btn-primary mt-3">Crear meta</a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Últimas Transacciones -->
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="card-title mb-0">Últimas Transacciones</h5>
            </div>
            <div class="card-body p-0">
                <?php if (count($ultimasTransacciones) > 0): ?>
                <div class="list-group list-group-flush">
                    <?php 
                    $fechaActual = '';
                    foreach ($ultimasTransacciones as $trans): 
                        $fechaTrans = date('d M Y', strtotime($trans['fecha']));
                        if ($fechaTrans !== $fechaActual):
                            $fechaActual = $fechaTrans;
                    ?>
                    <div class="list-group-item bg-light py-1 px-3">
                        <small class="text-muted fw-bold"><?= $fechaTrans ?></small>
                    </div>
                    <?php endif; ?>
                    <div class="list-group-item d-flex justify-content-between align-items-center px-3 py-2" style="border-left: 3px solid <?= $trans['color'] ?>;">
                        <div class="d-flex align-items-center gap-2" style="min-width: 0; flex: 1;">
                            <span class="badge rounded-circle p-2" style="background: <?= $trans['color'] ?>20; color: <?= $trans['color'] ?>;">
                                <i class="fas <?= $trans['icono'] ?>" style="width: 14px; text-align: center;"></i>
                            </span>
                            <div style="min-width: 0;">
                                <div class="text-truncate fw-medium" style="max-width: 150px;" title="<?= htmlspecialchars($trans['descripcion'] ?: $trans['categoria']) ?>">
                                    <?= htmlspecialchars($trans['descripcion'] ?: $trans['categoria']) ?>
                                </div>
                                <small class="text-muted"><?= $trans['categoria'] ?></small>
                            </div>
                        </div>
                        <div class="text-end ms-2">
                            <div class="<?= $trans['tipo'] === 'ingreso' ? 'text-success' : 'text-danger' ?> fw-bold">
                                <?= $trans['tipo'] === 'ingreso' ? '+' : '-' ?><?= APP_CURRENCY_SYMBOL ?><?= number_format($trans['monto'], 2, '.', ',') ?>
                            </div>
                            <small class="<?= $trans['saldo_despues'] >= 0 ? 'text-secondary' : 'text-danger' ?>">
                                <?= APP_CURRENCY_SYMBOL ?><?= number_format($trans['saldo_despues'], 2, '.', ',') ?>
                            </small>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="empty-state py-4">
                    <i class="fas fa-receipt"></i>
                    <p class="mb-0">No hay transacciones</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Próximos Pagos -->
<?php if (count($proximosPagos) > 0): ?>
<div class="row g-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0"><i class="fas fa-calendar-alt me-2"></i>Próximos Pagos</h5>
                <a href="<?= BASE_URL ?>pages/deudas.php" class="btn btn-sm btn-soft-primary">Ver deudas</a>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <?php foreach ($proximosPagos as $pago): ?>
                    <div class="col-md-4">
                        <div class="card bg-light border-0">
                            <div class="card-body d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1"><?= htmlspecialchars($pago['nombre']) ?></h6>
                                    <small class="text-muted">
                                        Vence: <?= formatDate($pago['fecha_vencimiento']) ?>
                                        <?php if ($pago['dias_restantes'] <= 7): ?>
                                        <span class="badge bg-warning ms-1">Pronto</span>
                                        <?php endif; ?>
                                    </small>
                                </div>
                                <div class="text-end">
                                    <div class="fw-bold text-danger"><?= formatMoney($pago['cuota_mensual']) ?></div>
                                    <small class="text-muted"><?= $pago['dias_restantes'] ?> días</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Gráfica de Ingresos vs Gastos
    App.createBarChart('incomeExpenseChart', 
        <?= json_encode($mesesLabels) ?>,
        [
            {
                label: 'Ingresos',
                data: <?= json_encode($ingresosUltimos6Meses) ?>,
                backgroundColor: '#22c55e',
                borderRadius: 8
            },
            {
                label: 'Gastos',
                data: <?= json_encode($gastosUltimos6Meses) ?>,
                backgroundColor: '#ef4444',
                borderRadius: 8
            }
        ]
    );
    
    // Gráfica de Gastos por Categoría
    <?php if (count($gastosPorCategoria) > 0): ?>
    App.createDoughnutChart('expenseByCategoryChart',
        <?= json_encode(array_column($gastosPorCategoria, 'nombre')) ?>,
        <?= json_encode(array_map('floatval', array_column($gastosPorCategoria, 'total'))) ?>,
        <?= json_encode(array_column($gastosPorCategoria, 'color')) ?>
    );
    <?php endif; ?>
    
    // Gráfica de Salud Financiera
    new Chart(document.getElementById('healthScoreChart'), {
        type: 'doughnut',
        data: {
            datasets: [{
                data: [<?= $healthScore ?>, <?= 100 - $healthScore ?>],
                backgroundColor: [
                    <?= $healthScore >= 60 ? "'#22c55e'" : ($healthScore >= 40 ? "'#f59e0b'" : "'#ef4444'") ?>,
                    '#e2e8f0'
                ],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            cutout: '80%',
            plugins: {
                legend: { display: false },
                tooltip: { enabled: false }
            }
        }
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
