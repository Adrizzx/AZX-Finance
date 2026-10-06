<?php
/**
 * AZX-Finance - Presupuesto Mensual
 */

// Cargar configuración y base de datos antes del header para poder hacer redirects
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';

$db = getDB();
$userId = getCurrentUserId();
$mesActual = isset($_GET['mes']) ? (int)$_GET['mes'] : (int)date('m');
$anioActual = isset($_GET['anio']) ? (int)$_GET['anio'] : (int)date('Y');

// Procesar formulario de presupuesto (antes del header para poder hacer redirect)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'guardar_presupuesto') {
        // Primero eliminar todos los presupuestos del mes actual para este usuario
        $stmt = $db->prepare("DELETE FROM presupuestos_mensuales WHERE usuario_id = ? AND mes = ? AND anio = ?");
        $stmt->execute([$userId, $_POST['mes'], $_POST['anio']]);
        
        // Guardar solo las categorías activas con su monto
        $categoriasActivas = isset($_POST['categoria_activa']) ? $_POST['categoria_activa'] : [];
        foreach ($_POST['presupuesto'] as $categoriaId => $monto) {
            // Solo guardar si está activa
            if (in_array($categoriaId, $categoriasActivas)) {
                $montoFinal = $monto > 0 ? $monto : 0;
                $stmt = $db->prepare("
                    INSERT INTO presupuestos_mensuales (usuario_id, categoria_gasto_id, monto_presupuestado, mes, anio)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([$userId, $categoriaId, $montoFinal, $_POST['mes'], $_POST['anio']]);
            }
        }
        header('Location: presupuesto.php?mes=' . $_POST['mes'] . '&anio=' . $_POST['anio'] . '&success=saved');
        exit;
    }
    
    if ($_POST['action'] === 'crear_categoria') {
        $nombre = trim($_POST['nombre_categoria']);
        $color = $_POST['color_categoria'] ?? '#6c757d';
        $icono = $_POST['icono_categoria'] ?? 'fa-tag';
        
        if (!empty($nombre)) {
            $stmt = $db->prepare("
                INSERT INTO categorias_gastos (usuario_id, nombre, icono, color, limite_mensual)
                VALUES (?, ?, ?, ?, NULL)
            ");
            $stmt->execute([$userId, $nombre, $icono, $color]);
        }
        header('Location: presupuesto.php?mes=' . $mesActual . '&anio=' . $anioActual . '&editar=1&success=category_created');
        exit;
    }
    
    if ($_POST['action'] === 'eliminar_categoria') {
        $categoriaId = (int)$_POST['categoria_id'];
        $esSistema = isset($_POST['es_sistema']) && $_POST['es_sistema'] === '1';
        
        if ($esSistema) {
            // Para categorías del sistema, crear una copia inactiva para este usuario
            // Primero obtener los datos de la categoría original
            $stmt = $db->prepare("SELECT nombre, icono, color, limite_mensual FROM categorias_gastos WHERE id = ?");
            $stmt->execute([$categoriaId]);
            $catOriginal = $stmt->fetch();
            
            if ($catOriginal) {
                // Verificar si ya existe una copia para este usuario
                $stmt = $db->prepare("SELECT id FROM categorias_gastos WHERE usuario_id = ? AND nombre = ?");
                $stmt->execute([$userId, $catOriginal['nombre']]);
                $copiaExistente = $stmt->fetch();
                
                if ($copiaExistente) {
                    // Actualizar la copia existente a inactiva
                    $stmt = $db->prepare("UPDATE categorias_gastos SET activo = 0 WHERE id = ?");
                    $stmt->execute([$copiaExistente['id']]);
                } else {
                    // Crear una copia inactiva para el usuario
                    $stmt = $db->prepare("
                        INSERT INTO categorias_gastos (usuario_id, nombre, icono, color, limite_mensual, activo)
                        VALUES (?, ?, ?, ?, ?, 0)
                    ");
                    $stmt->execute([$userId, $catOriginal['nombre'], $catOriginal['icono'], $catOriginal['color'], $catOriginal['limite_mensual']]);
                }
            }
        } else {
            // Para categorías personalizadas, eliminar directamente
            $stmt = $db->prepare("DELETE FROM categorias_gastos WHERE id = ? AND usuario_id = ?");
            $stmt->execute([$categoriaId, $userId]);
        }
        
        // También eliminar cualquier presupuesto asociado
        $stmt = $db->prepare("DELETE FROM presupuestos_mensuales WHERE usuario_id = ? AND categoria_gasto_id = ?");
        $stmt->execute([$userId, $categoriaId]);
        
        header('Location: presupuesto.php?mes=' . $mesActual . '&anio=' . $anioActual . '&editar=1&success=category_deleted');
        exit;
    }
    
    if ($_POST['action'] === 'restaurar_categoria') {
        $nombre = trim($_POST['nombre_categoria']);
        // Eliminar la copia inactiva para que vuelva a aparecer la del sistema
        $stmt = $db->prepare("DELETE FROM categorias_gastos WHERE usuario_id = ? AND nombre = ? AND activo = 0");
        $stmt->execute([$userId, $nombre]);
        header('Location: presupuesto.php?mes=' . $mesActual . '&anio=' . $anioActual . '&editar=1&success=category_restored');
        exit;
    }
    
    if ($_POST['action'] === 'restaurar_todas') {
        // Eliminar todas las copias inactivas del usuario
        $stmt = $db->prepare("DELETE FROM categorias_gastos WHERE usuario_id = ? AND activo = 0");
        $stmt->execute([$userId]);
        header('Location: presupuesto.php?mes=' . $mesActual . '&anio=' . $anioActual . '&editar=1&success=categories_restored');
        exit;
    }
    
    if ($_POST['action'] === 'copiar_anterior') {
        $mesAnterior = $mesActual - 1;
        $anioAnterior = $anioActual;
        if ($mesAnterior < 1) {
            $mesAnterior = 12;
            $anioAnterior--;
        }
        
        $stmt = $db->prepare("
            SELECT categoria_gasto_id, monto_presupuestado 
            FROM presupuestos_mensuales 
            WHERE usuario_id = ? AND mes = ? AND anio = ?
        ");
        $stmt->execute([$userId, $mesAnterior, $anioAnterior]);
        $presupuestosAnteriores = $stmt->fetchAll();
        
        foreach ($presupuestosAnteriores as $p) {
            $stmt = $db->prepare("
                INSERT INTO presupuestos_mensuales (usuario_id, categoria_gasto_id, monto_presupuestado, mes, anio)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE monto_presupuestado = ?
            ");
            $stmt->execute([$userId, $p['categoria_gasto_id'], $p['monto_presupuestado'], $mesActual, $anioActual, $p['monto_presupuestado']]);
        }
        
        header('Location: presupuesto.php?mes=' . $mesActual . '&anio=' . $anioActual . '&success=copied');
        exit;
    }
}

// Ahora sí incluimos el header (después de posibles redirects)
$pageTitle = 'Presupuesto';
require_once __DIR__ . '/../includes/header.php';

// Verificar si es la primera vez (no hay presupuestos para este mes)
$stmt = $db->prepare("SELECT COUNT(*) as total FROM presupuestos_mensuales WHERE usuario_id = ? AND mes = ? AND anio = ?");
$stmt->execute([$userId, $mesActual, $anioActual]);
$esPrimeraVez = $stmt->fetch()['total'] == 0;

// Obtener nombres de categorías que el usuario ha ocultado (copias inactivas)
$stmt = $db->prepare("
    SELECT nombre FROM categorias_gastos WHERE usuario_id = ? AND activo = 0
");
$stmt->execute([$userId]);
$categoriasOcultas = array_column($stmt->fetchAll(), 'nombre');

// Obtener categorías de gastos con presupuesto y gastos reales
$placeholders = count($categoriasOcultas) > 0 ? implode(',', array_fill(0, count($categoriasOcultas), '?')) : "'__NONE__'";

$sql = "
    SELECT 
        cg.id, cg.nombre, cg.icono, cg.color, cg.limite_mensual, cg.usuario_id as categoria_usuario_id,
        COALESCE(pm.monto_presupuestado, 0) as presupuestado,
        pm.id as presupuesto_id,
        COALESCE(SUM(g.monto), 0) as gastado
    FROM categorias_gastos cg
    LEFT JOIN presupuestos_mensuales pm ON cg.id = pm.categoria_gasto_id 
        AND pm.usuario_id = ? AND pm.mes = ? AND pm.anio = ?
    LEFT JOIN gastos g ON cg.id = g.categoria_id 
        AND g.usuario_id = ? AND MONTH(g.fecha) = ? AND YEAR(g.fecha) = ?
    WHERE (cg.usuario_id = ? OR cg.usuario_id IS NULL) 
        AND cg.activo = 1
        AND cg.nombre NOT IN ($placeholders)
    GROUP BY cg.id
    ORDER BY cg.nombre ASC
";

$params = [$userId, $mesActual, $anioActual, $userId, $mesActual, $anioActual, $userId];
if (count($categoriasOcultas) > 0) {
    $params = array_merge($params, $categoriasOcultas);
}
$stmt = $db->prepare($sql);
$stmt->execute($params);
$categorias = $stmt->fetchAll();

// Determinar si cada categoría está activa en el presupuesto
foreach ($categorias as &$cat) {
    // Si es primera vez, activar todas por defecto con su límite mensual
    if ($esPrimeraVez) {
        $cat['activa_presupuesto'] = true;
        $cat['presupuestado'] = $cat['limite_mensual'] ?? 0;
    } else {
        // Si tiene presupuesto_id significa que está activa
        $cat['activa_presupuesto'] = !is_null($cat['presupuesto_id']);
    }
    $cat['es_personalizada'] = !is_null($cat['categoria_usuario_id']);
}
unset($cat);

// Filtrar solo categorías activas para totales
$categoriasActivas = array_filter($categorias, fn($c) => $c['activa_presupuesto']);

// Calcular totales solo de categorías activas
$totalPresupuestado = array_sum(array_column($categoriasActivas, 'presupuestado'));
$totalGastado = array_sum(array_column($categoriasActivas, 'gastado'));
$diferencia = $totalPresupuestado - $totalGastado;
$porcentajeUsado = $totalPresupuestado > 0 ? round(($totalGastado / $totalPresupuestado) * 100) : 0;

// Categorías con alerta (más del 80%) - solo de las activas
$categoriasAlerta = array_filter($categoriasActivas, function($c) {
    return $c['presupuestado'] > 0 && ($c['gastado'] / $c['presupuestado']) >= 0.8;
});

// Obtener ingresos del mes para comparar
$stmt = $db->prepare("
    SELECT COALESCE(SUM(monto), 0) as total 
    FROM ingresos 
    WHERE usuario_id = ? AND MONTH(fecha) = ? AND YEAR(fecha) = ?
");
$stmt->execute([$userId, $mesActual, $anioActual]);
$ingresosMes = $stmt->fetch()['total'];

$showModal = isset($_GET['editar']);
?>

<!-- Page Header -->
<div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-3">
    <div>
        <h1><i class="fas fa-calculator text-primary me-2"></i>Presupuesto Mensual</h1>
        <p>Planifica y controla tus gastos de <?= getMonthName($mesActual) ?> <?= $anioActual ?></p>
    </div>
    <div class="page-header-actions d-flex gap-2 flex-wrap">
        <form method="POST" class="d-inline">
            <input type="hidden" name="action" value="copiar_anterior">
            <button type="submit" class="btn btn-soft-primary" title="Copiar presupuesto del mes anterior">
                <i class="fas fa-copy"></i> Copiar Anterior
            </button>
        </form>
        <a href="?mes=<?= $mesActual ?>&anio=<?= $anioActual ?>&editar=1" class="btn btn-primary">
            <i class="fas fa-edit"></i> Editar Presupuesto
        </a>
    </div>
</div>

<?php if (isset($_GET['success'])): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <?php
    switch ($_GET['success']) {
        case 'saved': echo 'Presupuesto guardado correctamente.'; break;
        case 'copied': echo 'Presupuesto copiado del mes anterior.'; break;
    }
    ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Selector de Mes -->
<div class="card mb-4">
    <div class="card-body">
        <form class="d-flex gap-3 align-items-center flex-wrap" method="GET">
            <div class="d-flex align-items-center gap-2">
                <a href="?mes=<?= $mesActual == 1 ? 12 : $mesActual - 1 ?>&anio=<?= $mesActual == 1 ? $anioActual - 1 : $anioActual ?>" 
                   class="btn btn-soft-primary"><i class="fas fa-chevron-left"></i></a>
                
                <select name="mes" class="form-select" style="width: auto;">
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                    <option value="<?= $m ?>" <?= $mesActual == $m ? 'selected' : '' ?>><?= getMonthName($m) ?></option>
                    <?php endfor; ?>
                </select>
                
                <select name="anio" class="form-select" style="min-width: 110px;">
                    <?php for ($a = date('Y'); $a >= date('Y') - 3; $a--): ?>
                    <option value="<?= $a ?>" <?= $anioActual == $a ? 'selected' : '' ?>><?= $a ?></option>
                    <?php endfor; ?>
                </select>
                
                <a href="?mes=<?= $mesActual == 12 ? 1 : $mesActual + 1 ?>&anio=<?= $mesActual == 12 ? $anioActual + 1 : $anioActual ?>" 
                   class="btn btn-soft-primary"><i class="fas fa-chevron-right"></i></a>
            </div>
            <button type="submit" class="btn btn-primary">Ver</button>
        </form>
    </div>
</div>

<!-- Resumen -->
<div class="row g-4 mb-4">
    <div class="col-lg-3 col-md-6">
        <div class="card stat-card">
            <div class="stat-icon bg-primary-soft">
                <i class="fas fa-clipboard-list"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Presupuestado</div>
                <div class="stat-value"><?= formatMoney($totalPresupuestado) ?></div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="card stat-card">
            <div class="stat-icon bg-danger-soft">
                <i class="fas fa-shopping-cart"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Gastado</div>
                <div class="stat-value text-danger"><?= formatMoney($totalGastado) ?></div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="card stat-card">
            <div class="stat-icon <?= $diferencia >= 0 ? 'bg-success-soft' : 'bg-warning-soft' ?>">
                <i class="fas fa-<?= $diferencia >= 0 ? 'check-circle' : 'exclamation-triangle' ?>"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label"><?= $diferencia >= 0 ? 'Disponible' : 'Excedido' ?></div>
                <div class="stat-value <?= $diferencia >= 0 ? 'text-success' : 'text-warning' ?>">
                    <?= formatMoney(abs($diferencia)) ?>
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
                <div class="stat-label">Usado</div>
                <div class="stat-value"><?= $porcentajeUsado ?>%</div>
            </div>
        </div>
    </div>
</div>

<!-- Comparación con Ingresos -->
<div class="card mb-4">
    <div class="card-body">
        <div class="row align-items-center">
            <div class="col-md-4 text-center border-end">
                <small class="text-muted d-block">Ingresos del Mes</small>
                <h4 class="text-success mb-0"><?= formatMoney($ingresosMes) ?></h4>
            </div>
            <div class="col-md-4 text-center border-end">
                <small class="text-muted d-block">Gastos Reales</small>
                <h4 class="text-danger mb-0"><?= formatMoney($totalGastado) ?></h4>
            </div>
            <div class="col-md-4 text-center">
                <small class="text-muted d-block">Balance Real</small>
                <h4 class="<?= ($ingresosMes - $totalGastado) >= 0 ? 'text-success' : 'text-danger' ?> mb-0">
                    <?= formatMoney($ingresosMes - $totalGastado) ?>
                </h4>
            </div>
        </div>
    </div>
</div>

<!-- Alertas -->
<?php if (count($categoriasAlerta) > 0): ?>
<div class="alert alert-warning mb-4">
    <h6 class="alert-heading"><i class="fas fa-exclamation-triangle me-2"></i>Alertas de Presupuesto</h6>
    <ul class="mb-0">
        <?php foreach ($categoriasAlerta as $cat): 
            $porcentaje = round(($cat['gastado'] / $cat['presupuestado']) * 100);
        ?>
        <li>
            <strong><?= htmlspecialchars($cat['nombre']) ?>:</strong>
            <?php if ($porcentaje >= 100): ?>
                ¡Presupuesto excedido! (<?= $porcentaje ?>%)
            <?php else: ?>
                Al <?= $porcentaje ?>% del límite
            <?php endif; ?>
        </li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<div class="row g-4">
    <!-- Tabla de Presupuesto -->
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Detalle por Categoría</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-container">
                    <table class="table mb-0">
                        <thead>
                            <tr>
                                <th>Categoría</th>
                                <th>Presupuestado</th>
                                <th>Gastado</th>
                                <th>Disponible</th>
                                <th style="width: 200px;">Progreso</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categorias as $cat): 
                                // Solo mostrar categorías activas en el presupuesto
                                if (!$cat['activa_presupuesto']) continue;
                                
                                $disponible = $cat['presupuestado'] - $cat['gastado'];
                                $porcentaje = $cat['presupuestado'] > 0 
                                    ? min(100, round(($cat['gastado'] / $cat['presupuestado']) * 100)) 
                                    : 0;
                                $excedido = $porcentaje >= 100;
                            ?>
                            <tr>
                                <td>
                                    <span class="d-inline-block rounded-circle me-2" 
                                          style="width: 12px; height: 12px; background: <?= $cat['color'] ?>"></span>
                                    <i class="fas <?= $cat['icono'] ?> me-1" style="color: <?= $cat['color'] ?>"></i>
                                    <?= htmlspecialchars($cat['nombre']) ?>
                                </td>
                                <td><?= formatMoney($cat['presupuestado']) ?></td>
                                <td class="<?= $cat['gastado'] > 0 ? 'text-danger' : '' ?>">
                                    <?= formatMoney($cat['gastado']) ?>
                                </td>
                                <td class="<?= $disponible >= 0 ? 'text-success' : 'text-danger' ?> fw-bold">
                                    <?= formatMoney($disponible) ?>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="progress flex-grow-1" style="height: 8px;">
                                            <div class="progress-bar <?= $excedido ? 'bg-danger' : ($porcentaje >= 80 ? 'bg-warning' : 'bg-success') ?>" 
                                                 style="width: <?= $porcentaje ?>%"></div>
                                        </div>
                                        <span class="small <?= $excedido ? 'text-danger' : '' ?>"><?= $porcentaje ?>%</span>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-light">
                            <tr class="fw-bold">
                                <td>TOTAL</td>
                                <td><?= formatMoney($totalPresupuestado) ?></td>
                                <td class="text-danger"><?= formatMoney($totalGastado) ?></td>
                                <td class="<?= $diferencia >= 0 ? 'text-success' : 'text-danger' ?>">
                                    <?= formatMoney($diferencia) ?>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="progress flex-grow-1" style="height: 8px;">
                                            <div class="progress-bar <?= $porcentajeUsado >= 100 ? 'bg-danger' : '' ?>" 
                                                 style="width: <?= min(100, $porcentajeUsado) ?>%"></div>
                                        </div>
                                        <span class="small"><?= $porcentajeUsado ?>%</span>
                                    </div>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Gráfica -->
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="card-title mb-0">Distribución de Gastos</h5>
            </div>
            <div class="card-body">
                <div class="chart-container" style="height: 300px;">
                    <canvas id="presupuestoChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Editar Presupuesto -->
<?php if ($showModal): ?>
<div class="modal fade show" id="editarPresupuestoModal" tabindex="-1" style="display: block;">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form method="POST" id="formPresupuesto">
                <input type="hidden" name="action" value="guardar_presupuesto">
                <input type="hidden" name="mes" value="<?= $mesActual ?>">
                <input type="hidden" name="anio" value="<?= $anioActual ?>">
                
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-edit text-primary me-2"></i>
                        Editar Presupuesto - <?= getMonthName($mesActual) ?> <?= $anioActual ?>
                    </h5>
                    <a href="presupuesto.php?mes=<?= $mesActual ?>&anio=<?= $anioActual ?>" class="btn-close"></a>
                </div>
                
                <div class="modal-body" style="max-height: 60vh; overflow-y: auto;">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <p class="text-muted mb-0">Activa las categorías en las que planeas gastar.</p>
                        <div class="btn-group btn-group-sm">
                            <button type="button" class="btn btn-outline-success" id="btnActivarTodas" title="Activar todas">
                                <i class="fas fa-check-double me-1"></i> Todas
                            </button>
                            <button type="button" class="btn btn-outline-secondary" id="btnDesactivarTodas" title="Desactivar todas">
                                <i class="fas fa-times me-1"></i> Ninguna
                            </button>
                        </div>
                    </div>
                    
                    <?php if (isset($_GET['success']) && $_GET['success'] === 'category_created'): ?>
                    <div class="alert alert-success alert-dismissible fade show py-2">
                        <i class="fas fa-check-circle me-1"></i> Categoría creada exitosamente.
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (isset($_GET['success']) && $_GET['success'] === 'category_deleted'): ?>
                    <div class="alert alert-info alert-dismissible fade show py-2">
                        <i class="fas fa-trash me-1"></i> Categoría eliminada/oculta.
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (isset($_GET['success']) && ($_GET['success'] === 'category_restored' || $_GET['success'] === 'categories_restored')): ?>
                    <div class="alert alert-success alert-dismissible fade show py-2">
                        <i class="fas fa-undo me-1"></i> Categoría(s) restaurada(s) exitosamente.
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>
                    
                    <div class="list-group mb-4">
                        <?php foreach ($categorias as $cat): ?>
                        <div class="list-group-item d-flex align-items-center gap-3 categoria-item <?= $cat['activa_presupuesto'] ? '' : 'categoria-inactiva' ?>">
                            <!-- Switch para activar/desactivar -->
                            <div class="form-check form-switch mb-0">
                                <input class="form-check-input categoria-switch" type="checkbox" 
                                       id="switch_<?= $cat['id'] ?>" 
                                       name="categoria_activa[]" 
                                       value="<?= $cat['id'] ?>"
                                       <?= $cat['activa_presupuesto'] ? 'checked' : '' ?>
                                       data-categoria-id="<?= $cat['id'] ?>">
                            </div>
                            
                            <!-- Icono y nombre -->
                            <div class="d-flex align-items-center" style="min-width: 140px;">
                                <i class="fas <?= $cat['icono'] ?> me-2" style="color: <?= $cat['color'] ?>; width: 20px;"></i>
                                <span class="categoria-nombre"><?= htmlspecialchars($cat['nombre']) ?></span>
                            </div>
                            
                            <!-- Input de monto -->
                            <div class="input-group input-group-sm flex-grow-1">
                                <span class="input-group-text"><?= APP_CURRENCY_SYMBOL ?></span>
                                <input type="number" class="form-control monto-input" 
                                       name="presupuesto[<?= $cat['id'] ?>]" 
                                       id="monto_<?= $cat['id'] ?>"
                                       step="0.01" 
                                       value="<?= $cat['presupuestado'] ?>" 
                                       placeholder="0.00"
                                       <?= $cat['activa_presupuesto'] ? '' : 'disabled' ?>>
                            </div>
                            
                            <!-- Botón eliminar -->
                            <button type="button" class="btn btn-sm btn-outline-danger btn-eliminar-categoria" 
                                    data-categoria-id="<?= $cat['id'] ?>"
                                    data-categoria-nombre="<?= htmlspecialchars($cat['nombre']) ?>"
                                    data-es-sistema="<?= $cat['es_personalizada'] ? '0' : '1' ?>"
                                    title="Eliminar del presupuesto">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Sección crear nueva categoría -->
                    <div class="card bg-light">
                        <div class="card-header py-2">
                            <h6 class="mb-0"><i class="fas fa-plus-circle text-success me-2"></i>Crear Nueva Categoría</h6>
                        </div>
                        <div class="card-body py-3">
                            <div class="row g-2 align-items-end">
                                <div class="col-md-4">
                                    <label class="form-label small">Nombre</label>
                                    <input type="text" class="form-control form-control-sm" id="nueva_categoria_nombre" placeholder="Ej: Mascotas">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small">Icono</label>
                                    <select class="form-select form-select-sm" id="nueva_categoria_icono">
                                        <option value="fa-tag">🏷️ Etiqueta</option>
                                        <option value="fa-paw">🐾 Mascotas</option>
                                        <option value="fa-gift">🎁 Regalos</option>
                                        <option value="fa-plane">✈️ Viajes</option>
                                        <option value="fa-baby">👶 Bebé</option>
                                        <option value="fa-tools">🔧 Herramientas</option>
                                        <option value="fa-dumbbell">💪 Gimnasio</option>
                                        <option value="fa-coffee">☕ Café</option>
                                        <option value="fa-book">📚 Libros</option>
                                        <option value="fa-music">🎵 Música</option>
                                        <option value="fa-camera">📷 Fotografía</option>
                                        <option value="fa-bicycle">🚴 Ciclismo</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small">Color</label>
                                    <input type="color" class="form-control form-control-sm form-control-color" id="nueva_categoria_color" value="#6c757d">
                                </div>
                                <div class="col-md-3">
                                    <button type="button" class="btn btn-success btn-sm w-100" id="btnCrearCategoria">
                                        <i class="fas fa-plus me-1"></i> Crear
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Sección categorías ocultas -->
                    <?php if (count($categoriasOcultas) > 0): ?>
                    <div class="card bg-light mt-3">
                        <div class="card-header py-2 d-flex justify-content-between align-items-center">
                            <h6 class="mb-0"><i class="fas fa-eye-slash text-secondary me-2"></i>Categorías Ocultas (<?= count($categoriasOcultas) ?>)</h6>
                            <button type="button" class="btn btn-sm btn-outline-primary" id="btnRestaurarTodas">
                                <i class="fas fa-undo me-1"></i> Restaurar Todas
                            </button>
                        </div>
                        <div class="card-body py-2">
                            <div class="d-flex flex-wrap gap-2">
                                <?php foreach ($categoriasOcultas as $nombreOculta): ?>
                                <span class="badge bg-secondary d-flex align-items-center gap-1">
                                    <?= htmlspecialchars($nombreOculta) ?>
                                    <button type="button" class="btn btn-link btn-sm p-0 text-white btn-restaurar-categoria" 
                                            data-nombre="<?= htmlspecialchars($nombreOculta) ?>"
                                            title="Restaurar">
                                        <i class="fas fa-undo"></i>
                                    </button>
                                </span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="modal-footer">
                    <div class="me-auto">
                        <small class="text-muted">
                            <i class="fas fa-info-circle me-1"></i>
                            Las categorías desactivadas no se incluirán en tu presupuesto.
                        </small>
                    </div>
                    <a href="presupuesto.php?mes=<?= $mesActual ?>&anio=<?= $anioActual ?>" class="btn btn-secondary">Cancelar</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i> Guardar Presupuesto
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<div class="modal-backdrop fade show"></div>

<!-- Form oculto para eliminar categoría -->
<form method="POST" id="formEliminarCategoria" style="display: none;">
    <input type="hidden" name="action" value="eliminar_categoria">
    <input type="hidden" name="categoria_id" id="eliminar_categoria_id">
    <input type="hidden" name="es_sistema" id="eliminar_es_sistema">
</form>

<!-- Form oculto para crear categoría -->
<form method="POST" id="formCrearCategoria" style="display: none;">
    <input type="hidden" name="action" value="crear_categoria">
    <input type="hidden" name="nombre_categoria" id="crear_nombre_categoria">
    <input type="hidden" name="icono_categoria" id="crear_icono_categoria">
    <input type="hidden" name="color_categoria" id="crear_color_categoria">
</form>

<!-- Form oculto para restaurar categoría -->
<form method="POST" id="formRestaurarCategoria" style="display: none;">
    <input type="hidden" name="action" value="restaurar_categoria">
    <input type="hidden" name="nombre_categoria" id="restaurar_nombre_categoria">
</form>

<!-- Form oculto para restaurar todas las categorías -->
<form method="POST" id="formRestaurarTodas" style="display: none;">
    <input type="hidden" name="action" value="restaurar_todas">
</form>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    <?php 
    $categoriasConGasto = array_filter($categorias, fn($c) => $c['gastado'] > 0 && $c['activa_presupuesto']);
    if (count($categoriasConGasto) > 0):
    ?>
    App.createDoughnutChart('presupuestoChart',
        <?= json_encode(array_column($categoriasConGasto, 'nombre')) ?>,
        <?= json_encode(array_map('floatval', array_column($categoriasConGasto, 'gastado'))) ?>,
        <?= json_encode(array_column($categoriasConGasto, 'color')) ?>
    );
    <?php endif; ?>
    
    // Función para actualizar estado visual de categoría
    function actualizarEstadoCategoria(switchEl, activar) {
        const categoriaId = switchEl.dataset.categoriaId;
        const montoInput = document.getElementById('monto_' + categoriaId);
        const categoriaItem = switchEl.closest('.categoria-item');
        
        switchEl.checked = activar;
        if (activar) {
            montoInput.disabled = false;
            categoriaItem.classList.remove('categoria-inactiva');
        } else {
            montoInput.disabled = true;
            categoriaItem.classList.add('categoria-inactiva');
        }
    }
    
    // Manejar switches de categorías
    document.querySelectorAll('.categoria-switch').forEach(function(switchEl) {
        switchEl.addEventListener('change', function() {
            actualizarEstadoCategoria(this, this.checked);
        });
    });
    
    // Activar todas las categorías
    const btnActivarTodas = document.getElementById('btnActivarTodas');
    if (btnActivarTodas) {
        btnActivarTodas.addEventListener('click', function() {
            document.querySelectorAll('.categoria-switch').forEach(function(switchEl) {
                actualizarEstadoCategoria(switchEl, true);
            });
        });
    }
    
    // Desactivar todas las categorías
    const btnDesactivarTodas = document.getElementById('btnDesactivarTodas');
    if (btnDesactivarTodas) {
        btnDesactivarTodas.addEventListener('click', function() {
            document.querySelectorAll('.categoria-switch').forEach(function(switchEl) {
                actualizarEstadoCategoria(switchEl, false);
            });
        });
    }
    
    // Crear nueva categoría
    const btnCrear = document.getElementById('btnCrearCategoria');
    if (btnCrear) {
        btnCrear.addEventListener('click', function() {
            const nombre = document.getElementById('nueva_categoria_nombre').value.trim();
            const icono = document.getElementById('nueva_categoria_icono').value;
            const color = document.getElementById('nueva_categoria_color').value;
            
            if (!nombre) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Campo requerido',
                    text: 'Ingresa un nombre para la categoría',
                    confirmButtonColor: '#6366f1'
                });
                return;
            }
            
            document.getElementById('crear_nombre_categoria').value = nombre;
            document.getElementById('crear_icono_categoria').value = icono;
            document.getElementById('crear_color_categoria').value = color;
            document.getElementById('formCrearCategoria').submit();
        });
    }
    
    // Eliminar categoría
    document.querySelectorAll('.btn-eliminar-categoria').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const categoriaId = this.dataset.categoriaId;
            const categoriaNombre = this.dataset.categoriaNombre;
            const esSistema = this.dataset.esSistema === '1';
            
            let mensaje, titulo;
            if (esSistema) {
                titulo = '¿Ocultar categoría?';
                mensaje = `La categoría <strong>${categoriaNombre}</strong> se ocultará de tu presupuesto.<br><small class="text-muted">Podrás restaurarla creándola de nuevo.</small>`;
            } else {
                titulo = '¿Eliminar categoría?';
                mensaje = `Se eliminará permanentemente la categoría <strong>${categoriaNombre}</strong>.<br><small class="text-muted">Los gastos asociados se mantendrán.</small>`;
            }
            
            Swal.fire({
                title: titulo,
                html: mensaje,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: esSistema ? 'Sí, ocultar' : 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('eliminar_categoria_id').value = categoriaId;
                    document.getElementById('eliminar_es_sistema').value = esSistema ? '1' : '0';
                    document.getElementById('formEliminarCategoria').submit();
                }
            });
        });
    });
    
    // Restaurar categoría individual
    document.querySelectorAll('.btn-restaurar-categoria').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const nombre = this.dataset.nombre;
            document.getElementById('restaurar_nombre_categoria').value = nombre;
            document.getElementById('formRestaurarCategoria').submit();
        });
    });
    
    // Restaurar todas las categorías
    const btnRestaurarTodas = document.getElementById('btnRestaurarTodas');
    if (btnRestaurarTodas) {
        btnRestaurarTodas.addEventListener('click', function() {
            Swal.fire({
                title: '¿Restaurar todas las categorías?',
                text: 'Se restaurarán todas las categorías ocultas.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#6366f1',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Sí, restaurar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('formRestaurarTodas').submit();
                }
            });
        });
    }
});
</script>

<style>
.categoria-item {
    transition: all 0.3s ease;
}
.categoria-inactiva {
    opacity: 0.5;
    background-color: var(--bg-tertiary) !important;
}
.categoria-inactiva .categoria-nombre {
    text-decoration: line-through;
}
.form-check-input:checked {
    background-color: var(--primary);
    border-color: var(--primary);
}
.list-group-item {
    border-color: var(--border-color);
    background-color: var(--bg-secondary);
}
[data-theme="dark"] .card.bg-light {
    background-color: var(--bg-tertiary) !important;
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
