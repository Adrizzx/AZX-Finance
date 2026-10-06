<?php
/**
 * AZX-Finance - Gestión de Gastos
 */

// Cargar configuración y base de datos antes del header para poder hacer redirects
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';

$db = getDB();
$userId = getCurrentUserId();

// Eliminar gasto (antes del header para poder hacer redirect)
if (isset($_GET['delete'])) {
    $stmt = $db->prepare("DELETE FROM gastos WHERE id = ? AND usuario_id = ?");
    $stmt->execute([$_GET['delete'], $userId]);
    header('Location: gastos.php?success=deleted');
    exit;
}

$errors = [];

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add' || $_POST['action'] === 'edit') {
        // Validaciones del servidor
        $monto = floatval($_POST['monto'] ?? 0);
        $categoriaId = intval($_POST['categoria_id'] ?? 0);
        $fecha = $_POST['fecha'] ?? '';
        
        if ($monto <= 0) {
            $errors[] = 'El monto debe ser mayor a 0';
        }
        
        if ($categoriaId <= 0) {
            $errors[] = 'Debe seleccionar una categoría';
        }
        
        // Validación de fecha más robusta
        if (empty($fecha)) {
            $errors[] = 'La fecha es requerida';
        } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            $errors[] = 'El formato de fecha es inválido';
        } else {
            // Verificar que la fecha sea una fecha real válida
            $fechaParts = explode('-', $fecha);
            if (!checkdate((int)$fechaParts[1], (int)$fechaParts[2], (int)$fechaParts[0])) {
                $errors[] = 'La fecha no es válida';
            }
        }
        
        // Validación adicional para edición
        if ($_POST['action'] === 'edit') {
            $gastoId = intval($_POST['id'] ?? 0);
            if ($gastoId <= 0) {
                $errors[] = 'ID de gasto inválido';
            } else {
                // Verificar que el gasto existe y pertenece al usuario
                $stmt = $db->prepare("SELECT id FROM gastos WHERE id = ? AND usuario_id = ?");
                $stmt->execute([$gastoId, $userId]);
                if (!$stmt->fetch()) {
                    $errors[] = 'El gasto no existe o no tienes permiso para editarlo';
                }
            }
        }
        
        // Si hay errores, no procesar
        if (!empty($errors)) {
            // Los errores se mostrarán en la página
        } else {
            if ($_POST['action'] === 'add') {
                $stmt = $db->prepare("
                    INSERT INTO gastos (usuario_id, categoria_id, monto, descripcion, fecha, es_recurrente, frecuencia, etiquetas)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $userId,
                    $categoriaId,
                    $monto,
                    trim($_POST['descripcion'] ?? ''),
                    $fecha,
                    isset($_POST['es_recurrente']) ? 1 : 0,
                    $_POST['frecuencia'] ?? null,
                    trim($_POST['etiquetas'] ?? '') ?: null
                ]);
                header('Location: gastos.php?success=added');
                exit;
            }
            
            if ($_POST['action'] === 'edit') {
                $gastoId = intval($_POST['id']);
                $stmt = $db->prepare("
                    UPDATE gastos 
                    SET categoria_id = ?, monto = ?, descripcion = ?, fecha = ?, 
                        es_recurrente = ?, frecuencia = ?, etiquetas = ?
                    WHERE id = ? AND usuario_id = ?
                ");
                $result = $stmt->execute([
                    $categoriaId,
                    $monto,
                    trim($_POST['descripcion'] ?? ''),
                    $fecha,
                    isset($_POST['es_recurrente']) ? 1 : 0,
                    $_POST['frecuencia'] ?? null,
                    trim($_POST['etiquetas'] ?? '') ?: null,
                    $gastoId,
                    $userId
                ]);
                
                if ($result && $stmt->rowCount() >= 0) {
                    header('Location: gastos.php?success=updated');
                    exit;
                } else {
                    $errors[] = 'Error al actualizar el gasto';
                }
            }
        }
    }
}

// Ahora sí incluimos el header (después de posibles redirects)
$pageTitle = 'Gastos';
require_once __DIR__ . '/../includes/header.php';

$currentMonth = date('m');
$currentYear = date('Y');

// Obtener categorías con límites (NULL = predeterminadas para todos, o propias del usuario)
$stmt = $db->prepare("SELECT * FROM categorias_gastos WHERE usuario_id IS NULL OR usuario_id = ? ORDER BY nombre");
$stmt->execute([$userId]);
$categorias = $stmt->fetchAll();

// Filtros
$filtroMes = $_GET['mes'] ?? $currentMonth;
$filtroAnio = $_GET['anio'] ?? $currentYear;
$filtroCategoria = $_GET['categoria'] ?? '';

// Construir consulta con filtros
$sql = "
    SELECT g.*, cg.nombre as categoria_nombre, cg.icono, cg.color, cg.limite_mensual
    FROM gastos g
    JOIN categorias_gastos cg ON g.categoria_id = cg.id
    WHERE g.usuario_id = ?
";
$params = [$userId];

if ($filtroMes) {
    $sql .= " AND MONTH(g.fecha) = ?";
    $params[] = $filtroMes;
}
if ($filtroAnio) {
    $sql .= " AND YEAR(g.fecha) = ?";
    $params[] = $filtroAnio;
}
if ($filtroCategoria) {
    $sql .= " AND g.categoria_id = ?";
    $params[] = $filtroCategoria;
}

$sql .= " ORDER BY g.fecha DESC, g.id DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$gastos = $stmt->fetchAll();

// Total del mes filtrado
$stmt = $db->prepare("
    SELECT COALESCE(SUM(monto), 0) as total 
    FROM gastos 
    WHERE usuario_id = ? AND MONTH(fecha) = ? AND YEAR(fecha) = ?
");
$stmt->execute([$userId, $filtroMes, $filtroAnio]);
$totalMes = $stmt->fetch()['total'];

// Gastos por categoría del mes con límites
$stmt = $db->prepare("
    SELECT cg.id, cg.nombre, cg.color, cg.limite_mensual, COALESCE(SUM(g.monto), 0) as total
    FROM categorias_gastos cg
    LEFT JOIN gastos g ON cg.id = g.categoria_id 
        AND g.usuario_id = ? 
        AND MONTH(g.fecha) = ? 
        AND YEAR(g.fecha) = ?
    WHERE cg.usuario_id = ? OR cg.usuario_id IS NULL
    GROUP BY cg.id
    HAVING total > 0
    ORDER BY total DESC
");
$stmt->execute([$userId, $filtroMes, $filtroAnio, $userId]);
$gastosPorCategoria = $stmt->fetchAll();

// Calcular alertas de límites
$categoriasConAlerta = [];
foreach ($gastosPorCategoria as $cat) {
    if ($cat['limite_mensual'] && $cat['total'] >= $cat['limite_mensual'] * 0.8) {
        $cat['porcentaje'] = round(($cat['total'] / $cat['limite_mensual']) * 100);
        $categoriasConAlerta[] = $cat;
    }
}

// Promedio diario
$diasTranscurridos = date('j');
$promedioDiario = $diasTranscurridos > 0 ? $totalMes / $diasTranscurridos : 0;

// Obtener gasto para editar
$gastoEditar = null;
if (isset($_GET['edit'])) {
    $stmt = $db->prepare("SELECT * FROM gastos WHERE id = ? AND usuario_id = ?");
    $stmt->execute([$_GET['edit'], $userId]);
    $gastoEditar = $stmt->fetch();
}

$showModal = isset($_GET['add']) || $gastoEditar;
?>

<!-- Page Header -->
<div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-3">
    <div>
        <h1><i class="fas fa-arrow-up text-danger me-2"></i>Gastos</h1>
        <p>Controla y categoriza tus gastos</p>
    </div>
    <div class="page-header-actions">
        <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#gastoModal">
            <i class="fas fa-plus"></i> Nuevo Gasto
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
        case 'added': echo 'Gasto registrado correctamente.'; break;
        case 'updated': echo 'Gasto actualizado correctamente.'; break;
        case 'deleted': echo 'Gasto eliminado correctamente.'; break;
    }
    ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Alertas de límites -->
<?php foreach ($categoriasConAlerta as $alerta): ?>
<div class="alert-finance <?= $alerta['porcentaje'] >= 100 ? 'bg-danger bg-opacity-10' : 'warning' ?> mb-3">
    <div class="alert-icon" style="background: <?= $alerta['porcentaje'] >= 100 ? 'var(--danger)' : 'var(--warning)' ?>">
        <i class="fas <?= $alerta['porcentaje'] >= 100 ? 'fa-exclamation-circle' : 'fa-exclamation-triangle' ?>"></i>
    </div>
    <div class="alert-content">
        <strong><?= htmlspecialchars($alerta['nombre']) ?>:</strong>
        <?php if ($alerta['porcentaje'] >= 100): ?>
            ¡Has superado el límite! (<?= formatMoney($alerta['total']) ?> de <?= formatMoney($alerta['limite_mensual']) ?>)
        <?php else: ?>
            Estás al <?= $alerta['porcentaje'] ?>% del límite (<?= formatMoney($alerta['total']) ?> de <?= formatMoney($alerta['limite_mensual']) ?>)
        <?php endif; ?>
    </div>
</div>
<?php endforeach; ?>

<!-- Stats Cards -->
<div class="row g-4 mb-4">
    <div class="col-lg-3 col-md-6">
        <div class="card stat-card">
            <div class="stat-icon bg-danger-soft">
                <i class="fas fa-shopping-cart"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Total del Mes</div>
                <div class="stat-value text-danger"><?= formatMoney($totalMes) ?></div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="card stat-card">
            <div class="stat-icon bg-primary-soft">
                <i class="fas fa-receipt"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Transacciones</div>
                <div class="stat-value"><?= count($gastos) ?></div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="card stat-card">
            <div class="stat-icon bg-warning-soft">
                <i class="fas fa-calculator"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Promedio Diario</div>
                <div class="stat-value"><?= formatMoney($promedioDiario) ?></div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="card stat-card">
            <div class="stat-icon bg-info-soft">
                <i class="fas fa-chart-pie"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Categorías Activas</div>
                <div class="stat-value"><?= count($gastosPorCategoria) ?></div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Lista de Gastos -->
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <h5 class="card-title mb-0">Listado de Gastos</h5>
                    
                    <!-- Filtros -->
                    <form class="d-flex gap-2 flex-wrap" method="GET">
                        <select name="mes" class="form-select form-select-sm" style="width: auto;">
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?= $m ?>" <?= $filtroMes == $m ? 'selected' : '' ?>>
                                <?= getMonthName($m) ?>
                            </option>
                            <?php endfor; ?>
                        </select>
                        <select name="anio" class="form-select form-select-sm" style="width: 80px;">
                            <?php for ($a = date('Y'); $a >= date('Y') - 5; $a--): ?>
                            <option value="<?= $a ?>" <?= $filtroAnio == $a ? 'selected' : '' ?>><?= $a ?></option>
                            <?php endfor; ?>
                        </select>
                        <select name="categoria" class="form-select form-select-sm" style="width: auto;">
                            <option value="">Todas las categorías</option>
                            <?php foreach ($categorias as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= $filtroCategoria == $cat['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['nombre']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-sm btn-primary">Filtrar</button>
                    </form>
                </div>
            </div>
            <div class="card-body p-0">
                <?php if (count($gastos) > 0): ?>
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Categoría</th>
                                <th>Descripción</th>
                                <th>Monto</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($gastos as $gasto): ?>
                            <tr>
                                <td><?= formatDate($gasto['fecha']) ?></td>
                                <td>
                                    <span class="badge" style="background: <?= $gasto['color'] ?>20; color: <?= $gasto['color'] ?>">
                                        <i class="fas <?= $gasto['icono'] ?> me-1"></i>
                                        <?= htmlspecialchars($gasto['categoria_nombre']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?= htmlspecialchars($gasto['descripcion'] ?: '-') ?>
                                    <?php if ($gasto['es_recurrente']): ?>
                                    <span class="badge bg-info ms-1" title="Recurrente: <?= $gasto['frecuencia'] ?>">
                                        <i class="fas fa-sync-alt"></i>
                                    </span>
                                    <?php endif; ?>
                                    <?php if ($gasto['etiquetas']): ?>
                                    <br><small class="text-muted"><?= htmlspecialchars($gasto['etiquetas']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td class="fw-bold text-danger"><?= formatMoney($gasto['monto']) ?></td>
                                <td>
                                    <a href="?edit=<?= $gasto['id'] ?>" class="btn btn-sm btn-soft-primary" title="Editar">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <button class="btn btn-sm btn-soft-danger" 
                                            data-delete="?delete=<?= $gasto['id'] ?>" 
                                            data-name="este gasto"
                                            title="Eliminar">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <h4>No hay gastos</h4>
                    <p>No se encontraron gastos para el período seleccionado.</p>
                    <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#gastoModal">
                        <i class="fas fa-plus"></i> Registrar Gasto
                    </button>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Gráfica por Categoría -->
    <div class="col-lg-4">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Por Categoría</h5>
            </div>
            <div class="card-body">
                <?php if (count($gastosPorCategoria) > 0): ?>
                <div class="chart-container" style="height: 220px;">
                    <canvas id="gastosCategoriaChart"></canvas>
                </div>
                <div class="mt-4">
                    <?php foreach (array_slice($gastosPorCategoria, 0, 5) as $cat): ?>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="category-label">
                                <span class="d-inline-block rounded-circle me-2" style="width: 10px; height: 10px; background: <?= $cat['color'] ?>"></span>
                                <?= htmlspecialchars($cat['nombre']) ?>
                            </span>
                            <strong class="category-amount"><?= formatMoney($cat['total']) ?></strong>
                        </div>
                        <?php if ($cat['limite_mensual']): ?>
                        <div class="progress" style="height: 4px;">
                            <div class="progress-bar <?= $cat['total'] >= $cat['limite_mensual'] ? 'bg-danger' : '' ?>" 
                                 style="width: <?= min(100, ($cat['total'] / $cat['limite_mensual']) * 100) ?>%; background: <?= $cat['color'] ?>"></div>
                        </div>
                        <small class="limit-text"><?= round(($cat['total'] / $cat['limite_mensual']) * 100) ?>% del límite</small>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="empty-state py-4">
                    <i class="fas fa-chart-pie"></i>
                    <p class="mb-0">Sin datos para mostrar</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal Nuevo/Editar Gasto -->
<div class="modal fade <?= $showModal ? 'show' : '' ?>" id="gastoModal" tabindex="-1" <?= $showModal ? 'style="display: block;"' : '' ?>>
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="<?= $gastoEditar ? 'edit' : 'add' ?>">
                <?php if ($gastoEditar): ?>
                <input type="hidden" name="id" value="<?= $gastoEditar['id'] ?>">
                <?php endif; ?>
                
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-arrow-up text-danger me-2"></i>
                        <?= $gastoEditar ? 'Editar Gasto' : 'Nuevo Gasto' ?>
                    </h5>
                    <a href="gastos.php" class="btn-close"></a>
                </div>
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Monto</label>
                        <div class="input-group">
                            <span class="input-group-text"><?= APP_CURRENCY_SYMBOL ?></span>
                            <input type="number" class="form-control" name="monto" step="0.01" required
                                   value="<?= $gastoEditar['monto'] ?? '' ?>"
                                   placeholder="0.00">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label d-flex justify-content-between align-items-center">
                            <span>Categoría</span>
                            <button type="button" class="btn btn-link btn-sm p-0" onclick="toggleNuevaCategoriaGasto()">
                                <i class="fas fa-plus"></i> Nueva categoría
                            </button>
                        </label>
                        <select class="form-select" name="categoria_id" id="categoriaGastoSelect" required>
                            <option value="">Seleccionar categoría</option>
                            <?php foreach ($categorias as $cat): ?>
                            <option value="<?= $cat['id'] ?>" 
                                    <?= ($gastoEditar['categoria_id'] ?? '') == $cat['id'] ? 'selected' : '' ?>
                                    data-limite="<?= $cat['limite_mensual'] ?>">
                                <?= htmlspecialchars($cat['nombre']) ?>
                                <?= $cat['limite_mensual'] ? '(Límite: ' . formatMoney($cat['limite_mensual']) . ')' : '' ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        
                        <!-- Formulario para nueva categoría -->
                        <div id="nuevaCategoriaGastoForm" class="mt-2 p-3 border rounded bg-light" style="display: none;">
                            <div class="row g-2">
                                <div class="col-6">
                                    <input type="text" class="form-control form-control-sm" id="nuevaCategoriaGastoNombre" 
                                           placeholder="Nombre" maxlength="100">
                                </div>
                                <div class="col-3">
                                    <input type="number" class="form-control form-control-sm" id="nuevaCategoriaGastoLimite" 
                                           placeholder="Límite $" min="0" step="0.01">
                                </div>
                                <div class="col-3">
                                    <input type="color" class="form-control form-control-sm form-control-color w-100" 
                                           id="nuevaCategoriaGastoColor" value="#dc3545">
                                </div>
                            </div>
                            <div class="d-flex gap-2 mt-2">
                                <button type="button" class="btn btn-danger btn-sm flex-grow-1" onclick="guardarCategoriaGasto()">
                                    <i class="fas fa-check"></i> Guardar
                                </button>
                                <button type="button" class="btn btn-secondary btn-sm" onclick="toggleNuevaCategoriaGasto()">
                                    Cancelar
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Fecha</label>
                        <input type="date" class="form-control" name="fecha" required
                               value="<?= $gastoEditar['fecha'] ?? date('Y-m-d') ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Descripción</label>
                        <input type="text" class="form-control" name="descripcion"
                               value="<?= htmlspecialchars($gastoEditar['descripcion'] ?? '') ?>"
                               placeholder="Ej: Compra en supermercado">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Etiquetas</label>
                        <input type="text" class="form-control" name="etiquetas"
                               value="<?= htmlspecialchars($gastoEditar['etiquetas'] ?? '') ?>"
                               placeholder="Ej: necesario, trabajo, personal">
                        <small class="text-muted">Separa las etiquetas con comas</small>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" name="es_recurrente" id="esRecurrenteGasto"
                                   <?= ($gastoEditar['es_recurrente'] ?? 0) ? 'checked' : '' ?>
                                   onchange="document.getElementById('frecuenciaGastoGroup').style.display = this.checked ? 'block' : 'none'">
                            <label class="form-check-label" for="esRecurrenteGasto">
                                Es un gasto recurrente (Ej: Netflix, arriendo)
                            </label>
                        </div>
                    </div>
                    
                    <div class="mb-3" id="frecuenciaGastoGroup" style="display: <?= ($gastoEditar['es_recurrente'] ?? 0) ? 'block' : 'none' ?>">
                        <label class="form-label">Frecuencia</label>
                        <select class="form-select" name="frecuencia">
                            <option value="mensual" <?= ($gastoEditar['frecuencia'] ?? '') == 'mensual' ? 'selected' : '' ?>>Mensual</option>
                            <option value="quincenal" <?= ($gastoEditar['frecuencia'] ?? '') == 'quincenal' ? 'selected' : '' ?>>Quincenal</option>
                            <option value="semanal" <?= ($gastoEditar['frecuencia'] ?? '') == 'semanal' ? 'selected' : '' ?>>Semanal</option>
                            <option value="anual" <?= ($gastoEditar['frecuencia'] ?? '') == 'anual' ? 'selected' : '' ?>>Anual</option>
                        </select>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <a href="gastos.php" class="btn btn-secondary">Cancelar</a>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-save me-1"></i>
                        <?= $gastoEditar ? 'Actualizar' : 'Guardar' ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php if ($showModal): ?><div class="modal-backdrop fade show"></div><?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    <?php if (count($gastosPorCategoria) > 0): ?>
    App.createDoughnutChart('gastosCategoriaChart',
        <?= json_encode(array_column($gastosPorCategoria, 'nombre')) ?>,
        <?= json_encode(array_map('floatval', array_column($gastosPorCategoria, 'total'))) ?>,
        <?= json_encode(array_column($gastosPorCategoria, 'color')) ?>
    );
    <?php endif; ?>
    
    <?php if (isset($_GET['add']) && !$gastoEditar): ?>
    new bootstrap.Modal(document.getElementById('gastoModal')).show();
    <?php endif; ?>
});

// Funciones para agregar categorías de gasto
function toggleNuevaCategoriaGasto() {
    const form = document.getElementById('nuevaCategoriaGastoForm');
    form.style.display = form.style.display === 'none' ? 'block' : 'none';
    if (form.style.display === 'block') {
        document.getElementById('nuevaCategoriaGastoNombre').focus();
    }
}

function guardarCategoriaGasto() {
    const nombre = document.getElementById('nuevaCategoriaGastoNombre').value.trim();
    const color = document.getElementById('nuevaCategoriaGastoColor').value;
    const limite = document.getElementById('nuevaCategoriaGastoLimite').value;
    
    if (!nombre) {
        Swal.fire({ icon: 'warning', title: 'Campo requerido', text: 'Ingresa un nombre para la categoría' });
        return;
    }
    
    if (nombre.length < 2) {
        Swal.fire({ icon: 'warning', title: 'Nombre muy corto', text: 'El nombre debe tener al menos 2 caracteres' });
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'add_categoria_gasto');
    formData.append('nombre', nombre);
    formData.append('color', color);
    formData.append('limite_mensual', limite || 0);
    
    fetch('<?= BASE_URL ?>api/categorias.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Agregar la nueva categoría al select
            const select = document.getElementById('categoriaGastoSelect');
            const option = document.createElement('option');
            option.value = data.categoria.id;
            option.textContent = data.categoria.nombre + (data.categoria.limite_mensual ? ' (Límite: $' + data.categoria.limite_mensual + ')' : '');
            option.selected = true;
            select.appendChild(option);
            
            // Limpiar y ocultar formulario
            document.getElementById('nuevaCategoriaGastoNombre').value = '';
            document.getElementById('nuevaCategoriaGastoLimite').value = '';
            toggleNuevaCategoriaGasto();
            
            Swal.fire({ icon: 'success', title: 'Categoría creada', text: data.message, timer: 2000, showConfirmButton: false });
        } else {
            Swal.fire({ icon: 'error', title: 'Error', text: data.message });
        }
    })
    .catch(error => {
        Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo crear la categoría' });
    });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
