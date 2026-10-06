<?php
/**
 * AZX-Finance - Reportes y Estadísticas
 */
$pageTitle = 'Reportes';
require_once __DIR__ . '/../includes/header.php';

$db = getDB();
$userId = getCurrentUserId();

// Período seleccionado
$periodo = isset($_GET['periodo']) ? $_GET['periodo'] : 'mes';
$anio = isset($_GET['anio']) ? (int)$_GET['anio'] : (int)date('Y');
$mes = isset($_GET['mes']) ? (int)$_GET['mes'] : (int)date('m');

// Construir filtro de fechas según período
switch ($periodo) {
    case 'semana':
        $fechaInicio = date('Y-m-d', strtotime('monday this week'));
        $fechaFin = date('Y-m-d', strtotime('sunday this week'));
        $tituloPerodo = 'Esta semana';
        break;
    case 'mes':
        $fechaInicio = "$anio-$mes-01";
        $fechaFin = date('Y-m-t', strtotime($fechaInicio));
        $tituloPerodo = getMonthName($mes) . ' ' . $anio;
        break;
    case 'trimestre':
        $trimestre = ceil($mes / 3);
        $mesInicio = (($trimestre - 1) * 3) + 1;
        $mesFin = $trimestre * 3;
        $fechaInicio = "$anio-$mesInicio-01";
        $fechaFin = date('Y-m-t', strtotime("$anio-$mesFin-01"));
        $tituloPerodo = "Q$trimestre $anio";
        break;
    case 'anio':
        $fechaInicio = "$anio-01-01";
        $fechaFin = "$anio-12-31";
        $tituloPerodo = "Año $anio";
        break;
    default:
        $fechaInicio = "$anio-$mes-01";
        $fechaFin = date('Y-m-t', strtotime($fechaInicio));
        $tituloPerodo = getMonthName($mes) . ' ' . $anio;
}

// Ingresos del período
$stmt = $db->prepare("
    SELECT COALESCE(SUM(i.monto), 0) as total,
           COUNT(*) as cantidad
    FROM ingresos i
    WHERE i.usuario_id = ? AND i.fecha BETWEEN ? AND ?
");
$stmt->execute([$userId, $fechaInicio, $fechaFin]);
$ingresos = $stmt->fetch();

// Gastos del período
$stmt = $db->prepare("
    SELECT COALESCE(SUM(g.monto), 0) as total,
           COUNT(*) as cantidad
    FROM gastos g
    WHERE g.usuario_id = ? AND g.fecha BETWEEN ? AND ?
");
$stmt->execute([$userId, $fechaInicio, $fechaFin]);
$gastos = $stmt->fetch();

$balance = $ingresos['total'] - $gastos['total'];
$tasaAhorro = $ingresos['total'] > 0 ? round(($balance / $ingresos['total']) * 100, 1) : 0;

// Gastos por categoría
$stmt = $db->prepare("
    SELECT cg.nombre, cg.color, cg.icono, COALESCE(SUM(g.monto), 0) as total
    FROM gastos g
    JOIN categorias_gastos cg ON g.categoria_id = cg.id
    WHERE g.usuario_id = ? AND g.fecha BETWEEN ? AND ?
    GROUP BY cg.id
    ORDER BY total DESC
");
$stmt->execute([$userId, $fechaInicio, $fechaFin]);
$gastosPorCategoria = $stmt->fetchAll();

// Ingresos por categoría
$stmt = $db->prepare("
    SELECT ci.nombre, ci.color, ci.icono, COALESCE(SUM(i.monto), 0) as total
    FROM ingresos i
    JOIN categorias_ingresos ci ON i.categoria_id = ci.id
    WHERE i.usuario_id = ? AND i.fecha BETWEEN ? AND ?
    GROUP BY ci.id
    ORDER BY total DESC
");
$stmt->execute([$userId, $fechaInicio, $fechaFin]);
$ingresosPorCategoria = $stmt->fetchAll();

// Evolución mensual (últimos 12 meses)
$stmt = $db->prepare("
    SELECT 
        DATE_FORMAT(fecha, '%Y-%m') as mes,
        SUM(monto) as total
    FROM ingresos
    WHERE usuario_id = ? AND fecha >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    GROUP BY mes
    ORDER BY mes
");
$stmt->execute([$userId]);
$evolucionIngresos = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

$stmt = $db->prepare("
    SELECT 
        DATE_FORMAT(fecha, '%Y-%m') as mes,
        SUM(monto) as total
    FROM gastos
    WHERE usuario_id = ? AND fecha >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    GROUP BY mes
    ORDER BY mes
");
$stmt->execute([$userId]);
$evolucionGastos = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Construir datos para gráfica de evolución
$mesesEvolucion = [];
$datosIngresos = [];
$datosGastos = [];
$datosBalance = [];

for ($i = 11; $i >= 0; $i--) {
    $mesKey = date('Y-m', strtotime("-$i months"));
    $mesesEvolucion[] = getMonthName((int)date('m', strtotime("-$i months")));
    $ing = $evolucionIngresos[$mesKey] ?? 0;
    $gas = $evolucionGastos[$mesKey] ?? 0;
    $datosIngresos[] = (float)$ing;
    $datosGastos[] = (float)$gas;
    $datosBalance[] = (float)$ing - (float)$gas;
}

// Top 5 gastos más grandes
$stmt = $db->prepare("
    SELECT g.*, cg.nombre as categoria, cg.icono, cg.color
    FROM gastos g
    JOIN categorias_gastos cg ON g.categoria_id = cg.id
    WHERE g.usuario_id = ? AND g.fecha BETWEEN ? AND ?
    ORDER BY g.monto DESC
    LIMIT 5
");
$stmt->execute([$userId, $fechaInicio, $fechaFin]);
$topGastos = $stmt->fetchAll();

// Promedio diario de gastos
$diasPeriodo = max(1, (strtotime($fechaFin) - strtotime($fechaInicio)) / 86400);
$promedioDiario = $gastos['total'] / $diasPeriodo;

// Comparación con período anterior
$diasDiff = (strtotime($fechaFin) - strtotime($fechaInicio));
$fechaInicioAnt = date('Y-m-d', strtotime($fechaInicio) - $diasDiff - 86400);
$fechaFinAnt = date('Y-m-d', strtotime($fechaInicio) - 86400);

$stmt = $db->prepare("SELECT COALESCE(SUM(monto), 0) FROM ingresos WHERE usuario_id = ? AND fecha BETWEEN ? AND ?");
$stmt->execute([$userId, $fechaInicioAnt, $fechaFinAnt]);
$ingresosAnt = $stmt->fetchColumn();

$stmt = $db->prepare("SELECT COALESCE(SUM(monto), 0) FROM gastos WHERE usuario_id = ? AND fecha BETWEEN ? AND ?");
$stmt->execute([$userId, $fechaInicioAnt, $fechaFinAnt]);
$gastosAnt = $stmt->fetchColumn();

$varIngresos = $ingresosAnt > 0 ? round((($ingresos['total'] - $ingresosAnt) / $ingresosAnt) * 100, 1) : 0;
$varGastos = $gastosAnt > 0 ? round((($gastos['total'] - $gastosAnt) / $gastosAnt) * 100, 1) : 0;

// Gastos por día de la semana
$stmt = $db->prepare("
    SELECT DAYOFWEEK(fecha) as dia, SUM(monto) as total
    FROM gastos
    WHERE usuario_id = ? AND fecha BETWEEN ? AND ?
    GROUP BY dia
    ORDER BY dia
");
$stmt->execute([$userId, $fechaInicio, $fechaFin]);
$gastosPorDia = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

$diasSemana = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
$datosGastosDia = [];
for ($i = 1; $i <= 7; $i++) {
    $datosGastosDia[] = (float)($gastosPorDia[$i] ?? 0);
}
?>

<!-- Page Header -->
<div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-3">
    <div>
        <h1><i class="fas fa-chart-bar text-info me-2"></i>Reportes y Estadísticas</h1>
        <p>Análisis detallado de tus finanzas - <?= $tituloPerodo ?></p>
    </div>
    <div class="page-header-actions">
        <button class="btn btn-soft-info" onclick="window.print()">
            <i class="fas fa-print"></i> Imprimir
        </button>
    </div>
</div>

<!-- Filtros -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Período</label>
                <select name="periodo" class="form-select" onchange="toggleMesSelect(this.value)">
                    <option value="semana" <?= $periodo == 'semana' ? 'selected' : '' ?>>Esta Semana</option>
                    <option value="mes" <?= $periodo == 'mes' ? 'selected' : '' ?>>Mensual</option>
                    <option value="trimestre" <?= $periodo == 'trimestre' ? 'selected' : '' ?>>Trimestral</option>
                    <option value="anio" <?= $periodo == 'anio' ? 'selected' : '' ?>>Anual</option>
                </select>
            </div>
            <div class="col-md-3" id="mesSelect">
                <label class="form-label">Mes</label>
                <select name="mes" class="form-select">
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                    <option value="<?= $m ?>" <?= $mes == $m ? 'selected' : '' ?>><?= getMonthName($m) ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Año</label>
                <select name="anio" class="form-select">
                    <?php for ($a = date('Y'); $a >= date('Y') - 5; $a--): ?>
                    <option value="<?= $a ?>" <?= $anio == $a ? 'selected' : '' ?>><?= $a ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-search me-1"></i> Ver Reporte
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Resumen del Período -->
<div class="row g-4 mb-4">
    <div class="col-lg-3 col-md-6">
        <div class="card stat-card">
            <div class="stat-icon bg-success-soft">
                <i class="fas fa-arrow-down"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Ingresos</div>
                <div class="stat-value text-success"><?= formatMoney($ingresos['total']) ?></div>
                <small class="<?= $varIngresos >= 0 ? 'text-success' : 'text-danger' ?>">
                    <i class="fas fa-arrow-<?= $varIngresos >= 0 ? 'up' : 'down' ?>"></i>
                    <?= abs($varIngresos) ?>% vs anterior
                </small>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="card stat-card">
            <div class="stat-icon bg-danger-soft">
                <i class="fas fa-arrow-up"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Gastos</div>
                <div class="stat-value text-danger"><?= formatMoney($gastos['total']) ?></div>
                <small class="<?= $varGastos <= 0 ? 'text-success' : 'text-danger' ?>">
                    <i class="fas fa-arrow-<?= $varGastos <= 0 ? 'down' : 'up' ?>"></i>
                    <?= abs($varGastos) ?>% vs anterior
                </small>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="card stat-card">
            <div class="stat-icon <?= $balance >= 0 ? 'bg-success-soft' : 'bg-danger-soft' ?>">
                <i class="fas fa-balance-scale"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Balance</div>
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
                <div class="stat-label">Tasa de Ahorro</div>
                <div class="stat-value <?= $tasaAhorro >= 20 ? 'text-success' : ($tasaAhorro >= 0 ? 'text-warning' : 'text-danger') ?>">
                    <?= $tasaAhorro ?>%
                </div>
                <small class="text-muted">Recomendado: 20%+</small>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- Evolución Mensual -->
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Evolución de los Últimos 12 Meses</h5>
            </div>
            <div class="card-body">
                <div class="chart-container" style="height: 300px;">
                    <canvas id="evolucionChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- Gastos por Categoría -->
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Gastos por Categoría</h5>
                <span class="badge bg-danger"><?= formatMoney($gastos['total']) ?></span>
            </div>
            <div class="card-body">
                <?php if (count($gastosPorCategoria) > 0): ?>
                <div class="row">
                    <div class="col-md-6">
                        <div class="chart-container" style="height: 200px;">
                            <canvas id="gastosCategChart"></canvas>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <?php foreach ($gastosPorCategoria as $cat): 
                            $porcentaje = $gastos['total'] > 0 ? round(($cat['total'] / $gastos['total']) * 100, 1) : 0;
                        ?>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span>
                                <span class="d-inline-block rounded-circle me-2" 
                                      style="width: 10px; height: 10px; background: <?= $cat['color'] ?>"></span>
                                <?= htmlspecialchars($cat['nombre']) ?>
                            </span>
                            <span>
                                <span class="text-muted"><?= $porcentaje ?>%</span>
                            </span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php else: ?>
                <div class="empty-state py-4">
                    <i class="fas fa-chart-pie"></i>
                    <p class="mb-0">Sin datos de gastos</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Ingresos por Categoría -->
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Ingresos por Categoría</h5>
                <span class="badge bg-success"><?= formatMoney($ingresos['total']) ?></span>
            </div>
            <div class="card-body">
                <?php if (count($ingresosPorCategoria) > 0): ?>
                <div class="row">
                    <div class="col-md-6">
                        <div class="chart-container" style="height: 200px;">
                            <canvas id="ingresosCategChart"></canvas>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <?php foreach ($ingresosPorCategoria as $cat): 
                            $porcentaje = $ingresos['total'] > 0 ? round(($cat['total'] / $ingresos['total']) * 100, 1) : 0;
                        ?>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span>
                                <span class="d-inline-block rounded-circle me-2" 
                                      style="width: 10px; height: 10px; background: <?= $cat['color'] ?>"></span>
                                <?= htmlspecialchars($cat['nombre']) ?>
                            </span>
                            <span>
                                <span class="text-muted"><?= $porcentaje ?>%</span>
                            </span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php else: ?>
                <div class="empty-state py-4">
                    <i class="fas fa-chart-pie"></i>
                    <p class="mb-0">Sin datos de ingresos</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- Gastos por día de la semana -->
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="card-title mb-0">Gastos por Día de la Semana</h5>
            </div>
            <div class="card-body">
                <div class="chart-container" style="height: 200px;">
                    <canvas id="diasSemanaChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Top 5 Gastos -->
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="card-title mb-0">Top 5 Gastos Más Grandes</h5>
            </div>
            <div class="card-body">
                <?php if (count($topGastos) > 0): ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($topGastos as $i => $gasto): ?>
                    <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <div>
                            <span class="badge bg-dark me-2">#<?= $i + 1 ?></span>
                            <i class="fas <?= $gasto['icono'] ?> me-1" style="color: <?= $gasto['color'] ?>"></i>
                            <?= htmlspecialchars($gasto['descripcion'] ?: $gasto['categoria']) ?>
                            <small class="text-muted d-block ms-4"><?= formatDate($gasto['fecha']) ?></small>
                        </div>
                        <span class="text-danger fw-bold"><?= formatMoney($gasto['monto']) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="empty-state py-4">
                    <i class="fas fa-list"></i>
                    <p class="mb-0">Sin gastos registrados</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Indicadores Clave -->
<div class="row g-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Indicadores Clave</h5>
            </div>
            <div class="card-body">
                <div class="row g-4 text-center">
                    <div class="col-md-3">
                        <div class="border rounded p-3 h-100">
                            <i class="fas fa-calendar-day fa-2x text-primary mb-2"></i>
                            <h5 class="mb-1"><?= formatMoney($promedioDiario) ?></h5>
                            <small class="text-muted">Gasto Promedio Diario</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="border rounded p-3 h-100">
                            <i class="fas fa-receipt fa-2x text-warning mb-2"></i>
                            <h5 class="mb-1"><?= $gastos['cantidad'] ?></h5>
                            <small class="text-muted">Transacciones de Gasto</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="border rounded p-3 h-100">
                            <i class="fas fa-tags fa-2x text-info mb-2"></i>
                            <h5 class="mb-1"><?= $gastos['cantidad'] > 0 ? formatMoney($gastos['total'] / $gastos['cantidad']) : formatMoney(0) ?></h5>
                            <small class="text-muted">Gasto Promedio por Transacción</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="border rounded p-3 h-100">
                            <i class="fas fa-chart-pie fa-2x text-success mb-2"></i>
                            <h5 class="mb-1"><?= count($gastosPorCategoria) ?></h5>
                            <small class="text-muted">Categorías con Gastos</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Toggle selector de mes
    toggleMesSelect('<?= $periodo ?>');
    
    // Detectar tema para colores de gráficas
    const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    const textColor = isDark ? '#f1f5f9' : '#1e293b';
    const gridColor = isDark ? 'rgba(255,255,255,0.1)' : 'rgba(0,0,0,0.05)';
    
    // Gráfica de Evolución
    const evolucionCtx = document.getElementById('evolucionChart').getContext('2d');
    new Chart(evolucionCtx, {
        type: 'bar',
        data: {
            labels: <?= json_encode($mesesEvolucion) ?>,
            datasets: [{
                label: 'Ingresos',
                data: <?= json_encode($datosIngresos) ?>,
                backgroundColor: 'rgba(25, 135, 84, 0.8)',
                borderRadius: 4
            }, {
                label: 'Gastos',
                data: <?= json_encode($datosGastos) ?>,
                backgroundColor: 'rgba(220, 53, 69, 0.8)',
                borderRadius: 4
            }, {
                type: 'line',
                label: 'Balance',
                data: <?= json_encode($datosBalance) ?>,
                borderColor: '#0d6efd',
                backgroundColor: 'transparent',
                tension: 0.4,
                pointRadius: 3
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: true, position: 'top', labels: { color: textColor } }
            },
            scales: {
                y: { beginAtZero: true, ticks: { color: textColor }, grid: { color: gridColor } },
                x: { ticks: { color: textColor } }
            }
        }
    });
    
    <?php if (count($gastosPorCategoria) > 0): ?>
    App.createDoughnutChart('gastosCategChart',
        <?= json_encode(array_column($gastosPorCategoria, 'nombre')) ?>,
        <?= json_encode(array_map('floatval', array_column($gastosPorCategoria, 'total'))) ?>,
        <?= json_encode(array_column($gastosPorCategoria, 'color')) ?>
    );
    <?php endif; ?>
    
    <?php if (count($ingresosPorCategoria) > 0): ?>
    App.createDoughnutChart('ingresosCategChart',
        <?= json_encode(array_column($ingresosPorCategoria, 'nombre')) ?>,
        <?= json_encode(array_map('floatval', array_column($ingresosPorCategoria, 'total'))) ?>,
        <?= json_encode(array_column($ingresosPorCategoria, 'color')) ?>
    );
    <?php endif; ?>
    
    // Gastos por día
    const diasCtx = document.getElementById('diasSemanaChart').getContext('2d');
    new Chart(diasCtx, {
        type: 'bar',
        data: {
            labels: <?= json_encode($diasSemana) ?>,
            datasets: [{
                label: 'Gastos',
                data: <?= json_encode($datosGastosDia) ?>,
                backgroundColor: [
                    'rgba(220, 53, 69, 0.5)',
                    'rgba(25, 135, 84, 0.8)',
                    'rgba(25, 135, 84, 0.8)',
                    'rgba(25, 135, 84, 0.8)',
                    'rgba(25, 135, 84, 0.8)',
                    'rgba(255, 193, 7, 0.8)',
                    'rgba(255, 193, 7, 0.8)'
                ],
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { ticks: { color: textColor }, grid: { color: gridColor } },
                x: { ticks: { color: textColor } }
            }
        }
    });
});

function toggleMesSelect(periodo) {
    const mesSelect = document.getElementById('mesSelect');
    if (periodo === 'anio') {
        mesSelect.style.display = 'none';
    } else {
        mesSelect.style.display = '';
    }
}
</script>

<style>
@media print {
    .sidebar, .page-header-actions, form, .btn { display: none !important; }
    .main-content { margin-left: 0 !important; padding: 0 !important; }
    .card { break-inside: avoid; }
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
