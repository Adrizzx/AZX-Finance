<?php
/**
 * AZX-Finance - Gestión de Ingresos
 */

// Cargar configuración y base de datos antes del header para poder hacer redirects
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';

$db = getDB();
$userId = getCurrentUserId();

// Eliminar ingreso (antes del header para poder hacer redirect)
if (isset($_GET['delete'])) {
    $stmt = $db->prepare("DELETE FROM ingresos WHERE id = ? AND usuario_id = ?");
    $stmt->execute([$_GET['delete'], $userId]);
    header('Location: ingresos.php?success=deleted');
    exit;
}

$errors = [];

// Procesar formulario de nuevo ingreso
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
            $ingresoId = intval($_POST['id'] ?? 0);
            if ($ingresoId <= 0) {
                $errors[] = 'ID de ingreso inválido';
            } else {
                // Verificar que el ingreso existe y pertenece al usuario
                $stmt = $db->prepare("SELECT id FROM ingresos WHERE id = ? AND usuario_id = ?");
                $stmt->execute([$ingresoId, $userId]);
                if (!$stmt->fetch()) {
                    $errors[] = 'El ingreso no existe o no tienes permiso para editarlo';
                }
            }
        }
        
        // Si hay errores, no procesar
        if (!empty($errors)) {
            // Los errores se mostrarán en la página
        } else {
            if ($_POST['action'] === 'add') {
                $stmt = $db->prepare("
                    INSERT INTO ingresos (usuario_id, categoria_id, monto, descripcion, fecha, es_recurrente, frecuencia)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $userId,
                    $categoriaId,
                    $monto,
                    trim($_POST['descripcion'] ?? ''),
                    $fecha,
                    isset($_POST['es_recurrente']) ? 1 : 0,
                    $_POST['frecuencia'] ?? null
                ]);
                header('Location: ingresos.php?success=added');
                exit;
            }
            
            if ($_POST['action'] === 'edit') {
                $ingresoId = intval($_POST['id']);
                $stmt = $db->prepare("
                    UPDATE ingresos 
                    SET categoria_id = ?, monto = ?, descripcion = ?, fecha = ?, 
                        es_recurrente = ?, frecuencia = ?
                    WHERE id = ? AND usuario_id = ?
                ");
                $result = $stmt->execute([
                    $categoriaId,
                    $monto,
                    trim($_POST['descripcion'] ?? ''),
                    $fecha,
                    isset($_POST['es_recurrente']) ? 1 : 0,
                    $_POST['frecuencia'] ?? null,
                    $ingresoId,
                    $userId
                ]);
                
                if ($result && $stmt->rowCount() >= 0) {
                    header('Location: ingresos.php?success=updated');
                    exit;
                } else {
                    $errors[] = 'Error al actualizar el ingreso';
                }
            }
        }
    }
}

// Ahora sí incluimos el header (después de posibles redirects)
$pageTitle = 'Ingresos';
require_once __DIR__ . '/../includes/header.php';

$currentMonth = date('m');
$currentYear = date('Y');

// Obtener categorías (NULL = predeterminadas para todos, o propias del usuario)
$stmt = $db->prepare("SELECT * FROM categorias_ingresos WHERE usuario_id IS NULL OR usuario_id = ? ORDER BY nombre");
$stmt->execute([$userId]);
$categorias = $stmt->fetchAll();

// Filtros
$filtroMes = $_GET['mes'] ?? $currentMonth;
$filtroAnio = $_GET['anio'] ?? $currentYear;
$filtroCategoria = $_GET['categoria'] ?? '';

// Construir consulta con filtros
$sql = "
    SELECT i.*, ci.nombre as categoria_nombre, ci.icono, ci.color
    FROM ingresos i
    JOIN categorias_ingresos ci ON i.categoria_id = ci.id
    WHERE i.usuario_id = ?
";
$params = [$userId];

if ($filtroMes) {
    $sql .= " AND MONTH(i.fecha) = ?";
    $params[] = $filtroMes;
}
if ($filtroAnio) {
    $sql .= " AND YEAR(i.fecha) = ?";
    $params[] = $filtroAnio;
}
if ($filtroCategoria) {
    $sql .= " AND i.categoria_id = ?";
    $params[] = $filtroCategoria;
}

$sql .= " ORDER BY i.fecha DESC, i.id DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$ingresos = $stmt->fetchAll();

// Total del mes filtrado
$stmt = $db->prepare("
    SELECT COALESCE(SUM(monto), 0) as total 
    FROM ingresos 
    WHERE usuario_id = ? AND MONTH(fecha) = ? AND YEAR(fecha) = ?
");
$stmt->execute([$userId, $filtroMes, $filtroAnio]);
$totalMes = $stmt->fetch()['total'];

// Ingresos por categoría del mes
$stmt = $db->prepare("
    SELECT ci.nombre, ci.color, SUM(i.monto) as total
    FROM ingresos i
    JOIN categorias_ingresos ci ON i.categoria_id = ci.id
    WHERE i.usuario_id = ? AND MONTH(i.fecha) = ? AND YEAR(i.fecha) = ?
    GROUP BY ci.id
    ORDER BY total DESC
");
$stmt->execute([$userId, $filtroMes, $filtroAnio]);
$ingresosPorCategoria = $stmt->fetchAll();

// Obtener ingreso para editar
$ingresoEditar = null;
if (isset($_GET['edit'])) {
    $stmt = $db->prepare("SELECT * FROM ingresos WHERE id = ? AND usuario_id = ?");
    $stmt->execute([$_GET['edit'], $userId]);
    $ingresoEditar = $stmt->fetch();
}

$showModal = isset($_GET['add']) || $ingresoEditar;
?>

<!-- Page Header -->
<div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-3">
    <div>
        <h1><i class="fas fa-arrow-down text-success me-2"></i>Ingresos</h1>
        <p>Gestiona tus fuentes de ingresos</p>
    </div>
    <div class="page-header-actions">
        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#ingresoModal">
            <i class="fas fa-plus"></i> Nuevo Ingreso
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
        case 'added': echo 'Ingreso registrado correctamente.'; break;
        case 'updated': echo 'Ingreso actualizado correctamente.'; break;
        case 'deleted': echo 'Ingreso eliminado correctamente.'; break;
    }
    ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Stats Cards -->
<div class="row g-4 mb-4">
    <div class="col-lg-4 col-md-6">
        <div class="card stat-card">
            <div class="stat-icon bg-success-soft">
                <i class="fas fa-money-bill-wave"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Total del Mes</div>
                <div class="stat-value text-success"><?= formatMoney($totalMes) ?></div>
            </div>
        </div>
    </div>
    <div class="col-lg-4 col-md-6">
        <div class="card stat-card">
            <div class="stat-icon bg-primary-soft">
                <i class="fas fa-list"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Transacciones</div>
                <div class="stat-value"><?= count($ingresos) ?></div>
            </div>
        </div>
    </div>
    <div class="col-lg-4 col-md-6">
        <div class="card stat-card">
            <div class="stat-icon bg-info-soft">
                <i class="fas fa-chart-pie"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Categorías Activas</div>
                <div class="stat-value"><?= count($ingresosPorCategoria) ?></div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Lista de Ingresos -->
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <h5 class="card-title mb-0">Listado de Ingresos</h5>
                    
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
                <?php if (count($ingresos) > 0): ?>
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
                            <?php foreach ($ingresos as $ingreso): ?>
                            <tr>
                                <td><?= formatDate($ingreso['fecha']) ?></td>
                                <td>
                                    <span class="badge" style="background: <?= $ingreso['color'] ?>20; color: <?= $ingreso['color'] ?>">
                                        <i class="fas <?= $ingreso['icono'] ?> me-1"></i>
                                        <?= htmlspecialchars($ingreso['categoria_nombre']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?= htmlspecialchars($ingreso['descripcion'] ?: '-') ?>
                                    <?php if ($ingreso['es_recurrente']): ?>
                                    <span class="badge bg-info ms-1" title="Recurrente: <?= $ingreso['frecuencia'] ?>">
                                        <i class="fas fa-sync-alt"></i>
                                    </span>
                                    <?php endif; ?>
                                </td>
                                <td class="fw-bold text-success"><?= formatMoney($ingreso['monto']) ?></td>
                                <td>
                                    <a href="?edit=<?= $ingreso['id'] ?>" class="btn btn-sm btn-soft-primary" title="Editar">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <button class="btn btn-sm btn-soft-danger" 
                                            data-delete="?delete=<?= $ingreso['id'] ?>" 
                                            data-name="este ingreso"
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
                    <h4>No hay ingresos</h4>
                    <p>No se encontraron ingresos para el período seleccionado.</p>
                    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#ingresoModal">
                        <i class="fas fa-plus"></i> Registrar Ingreso
                    </button>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Gráfica por Categoría -->
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Por Categoría</h5>
            </div>
            <div class="card-body">
                <?php if (count($ingresosPorCategoria) > 0): ?>
                <div class="chart-container" style="height: 250px;">
                    <canvas id="ingresosCategoriaChart"></canvas>
                </div>
                <div class="mt-4">
                    <?php foreach ($ingresosPorCategoria as $cat): ?>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="category-label">
                            <span class="d-inline-block rounded-circle me-2" style="width: 10px; height: 10px; background: <?= $cat['color'] ?>"></span>
                            <?= htmlspecialchars($cat['nombre']) ?>
                        </span>
                        <strong class="category-amount"><?= formatMoney($cat['total']) ?></strong>
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

<!-- Modal Nuevo/Editar Ingreso -->
<div class="modal fade <?= $showModal ? 'show' : '' ?>" id="ingresoModal" tabindex="-1" <?= $showModal ? 'style="display: block;"' : '' ?>>
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="<?= $ingresoEditar ? 'edit' : 'add' ?>">
                <?php if ($ingresoEditar): ?>
                <input type="hidden" name="id" value="<?= $ingresoEditar['id'] ?>">
                <?php endif; ?>
                
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-arrow-down text-success me-2"></i>
                        <?= $ingresoEditar ? 'Editar Ingreso' : 'Nuevo Ingreso' ?>
                    </h5>
                    <a href="ingresos.php" class="btn-close"></a>
                </div>
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Monto</label>
                        <div class="input-group">
                            <span class="input-group-text"><?= APP_CURRENCY_SYMBOL ?></span>
                            <input type="number" class="form-control" name="monto" step="0.01" required
                                   value="<?= $ingresoEditar['monto'] ?? '' ?>"
                                   placeholder="0.00">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label d-flex justify-content-between align-items-center">
                            <span>Categoría</span>
                            <button type="button" class="btn btn-link btn-sm p-0" onclick="toggleNuevaCategoriaIngreso()">
                                <i class="fas fa-plus"></i> Nueva categoría
                            </button>
                        </label>
                        <select class="form-select" name="categoria_id" id="categoriaIngresoSelect" required>
                            <option value="">Seleccionar categoría</option>
                            <?php foreach ($categorias as $cat): ?>
                            <option value="<?= $cat['id'] ?>" 
                                    <?= ($ingresoEditar['categoria_id'] ?? '') == $cat['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['nombre']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        
                        <!-- Formulario para nueva categoría -->
                        <div id="nuevaCategoriaIngresoForm" class="mt-2 p-3 border rounded bg-light" style="display: none;">
                            <div class="row g-2">
                                <div class="col-8">
                                    <input type="text" class="form-control form-control-sm" id="nuevaCategoriaIngresoNombre" 
                                           placeholder="Nombre de la categoría" maxlength="100">
                                </div>
                                <div class="col-4">
                                    <input type="color" class="form-control form-control-sm form-control-color w-100" 
                                           id="nuevaCategoriaIngresoColor" value="#28a745">
                                </div>
                            </div>
                            <div class="d-flex gap-2 mt-2">
                                <button type="button" class="btn btn-success btn-sm flex-grow-1" onclick="guardarCategoriaIngreso()">
                                    <i class="fas fa-check"></i> Guardar
                                </button>
                                <button type="button" class="btn btn-secondary btn-sm" onclick="toggleNuevaCategoriaIngreso()">
                                    Cancelar
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Fecha</label>
                        <input type="date" class="form-control" name="fecha" required
                               value="<?= $ingresoEditar['fecha'] ?? date('Y-m-d') ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Descripción</label>
                        <input type="text" class="form-control" name="descripcion"
                               value="<?= htmlspecialchars($ingresoEditar['descripcion'] ?? '') ?>"
                               placeholder="Ej: Salario de febrero">
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" name="es_recurrente" id="esRecurrente"
                                   <?= ($ingresoEditar['es_recurrente'] ?? 0) ? 'checked' : '' ?>
                                   onchange="document.getElementById('frecuenciaGroup').style.display = this.checked ? 'block' : 'none'">
                            <label class="form-check-label" for="esRecurrente">
                                Es un ingreso recurrente
                            </label>
                        </div>
                    </div>
                    
                    <div class="mb-3" id="frecuenciaGroup" style="display: <?= ($ingresoEditar['es_recurrente'] ?? 0) ? 'block' : 'none' ?>">
                        <label class="form-label">Frecuencia</label>
                        <select class="form-select" name="frecuencia">
                            <option value="mensual" <?= ($ingresoEditar['frecuencia'] ?? '') == 'mensual' ? 'selected' : '' ?>>Mensual</option>
                            <option value="quincenal" <?= ($ingresoEditar['frecuencia'] ?? '') == 'quincenal' ? 'selected' : '' ?>>Quincenal</option>
                            <option value="semanal" <?= ($ingresoEditar['frecuencia'] ?? '') == 'semanal' ? 'selected' : '' ?>>Semanal</option>
                            <option value="anual" <?= ($ingresoEditar['frecuencia'] ?? '') == 'anual' ? 'selected' : '' ?>>Anual</option>
                        </select>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <a href="ingresos.php" class="btn btn-secondary">Cancelar</a>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save me-1"></i>
                        <?= $ingresoEditar ? 'Actualizar' : 'Guardar' ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php if ($showModal): ?><div class="modal-backdrop fade show"></div><?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    <?php if (count($ingresosPorCategoria) > 0): ?>
    App.createDoughnutChart('ingresosCategoriaChart',
        <?= json_encode(array_column($ingresosPorCategoria, 'nombre')) ?>,
        <?= json_encode(array_map('floatval', array_column($ingresosPorCategoria, 'total'))) ?>,
        <?= json_encode(array_column($ingresosPorCategoria, 'color')) ?>
    );
    <?php endif; ?>
    
    // Inicializar modal si hay parámetro add
    <?php if (isset($_GET['add']) && !$ingresoEditar): ?>
    new bootstrap.Modal(document.getElementById('ingresoModal')).show();
    <?php endif; ?>
});

// Funciones para agregar categorías
function toggleNuevaCategoriaIngreso() {
    const form = document.getElementById('nuevaCategoriaIngresoForm');
    form.style.display = form.style.display === 'none' ? 'block' : 'none';
    if (form.style.display === 'block') {
        document.getElementById('nuevaCategoriaIngresoNombre').focus();
    }
}

function guardarCategoriaIngreso() {
    const nombre = document.getElementById('nuevaCategoriaIngresoNombre').value.trim();
    const color = document.getElementById('nuevaCategoriaIngresoColor').value;
    
    if (!nombre) {
        Swal.fire({ icon: 'warning', title: 'Campo requerido', text: 'Ingresa un nombre para la categoría' });
        return;
    }
    
    if (nombre.length < 2) {
        Swal.fire({ icon: 'warning', title: 'Nombre muy corto', text: 'El nombre debe tener al menos 2 caracteres' });
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'add_categoria_ingreso');
    formData.append('nombre', nombre);
    formData.append('color', color);
    
    fetch('<?= BASE_URL ?>api/categorias.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Agregar la nueva categoría al select
            const select = document.getElementById('categoriaIngresoSelect');
            const option = document.createElement('option');
            option.value = data.categoria.id;
            option.textContent = data.categoria.nombre;
            option.selected = true;
            select.appendChild(option);
            
            // Limpiar y ocultar formulario
            document.getElementById('nuevaCategoriaIngresoNombre').value = '';
            toggleNuevaCategoriaIngreso();
            
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
