<?php
/**
 * AZX-Finance - Bolsillos de Ahorro
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

// Obtener o crear categoría de gastos "Ahorro Bolsillos" (para depósitos)
$stmt = $db->prepare("SELECT id FROM categorias_gastos WHERE nombre = 'Ahorro Bolsillos' AND (usuario_id = ? OR usuario_id IS NULL) LIMIT 1");
$stmt->execute([$userId]);
$catGasto = $stmt->fetch();

if (!$catGasto) {
    $stmt = $db->prepare("INSERT INTO categorias_gastos (nombre, icono, color, usuario_id) VALUES ('Ahorro Bolsillos', 'fa-piggy-bank', '#28a745', ?)");
    $stmt->execute([$userId]);
    $categoriaGastoId = $db->lastInsertId();
} else {
    $categoriaGastoId = $catGasto['id'];
}

// Obtener o crear categoría de ingresos "Retiro Bolsillos" (para retiros)
$stmt = $db->prepare("SELECT id FROM categorias_ingresos WHERE nombre = 'Retiro Bolsillos' AND (usuario_id = ? OR usuario_id IS NULL) LIMIT 1");
$stmt->execute([$userId]);
$catIngreso = $stmt->fetch();

if (!$catIngreso) {
    $stmt = $db->prepare("INSERT INTO categorias_ingresos (nombre, icono, color, usuario_id) VALUES ('Retiro Bolsillos', 'fa-piggy-bank', '#17a2b8', ?)");
    $stmt->execute([$userId]);
    $categoriaIngresoId = $db->lastInsertId();
} else {
    $categoriaIngresoId = $catIngreso['id'];
}

// Procesar formulario (ANTES del header para poder hacer redirect)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_bolsillo') {
        $stmt = $db->prepare("
            INSERT INTO bolsillos_ahorro (usuario_id, nombre, descripcion, icono, color)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $userId,
            $_POST['nombre'],
            $_POST['descripcion'],
            $_POST['icono'],
            $_POST['color']
        ]);
        header('Location: ahorros.php?success=added');
        exit;
    }
    
    if ($_POST['action'] === 'edit_bolsillo') {
        $stmt = $db->prepare("
            UPDATE bolsillos_ahorro 
            SET nombre = ?, descripcion = ?, icono = ?, color = ?
            WHERE id = ? AND usuario_id = ?
        ");
        $stmt->execute([
            $_POST['nombre'],
            $_POST['descripcion'],
            $_POST['icono'],
            $_POST['color'],
            $_POST['id'],
            $userId
        ]);
        header('Location: ahorros.php?success=updated');
        exit;
    }
    
    if ($_POST['action'] === 'movimiento') {
        $tipo = $_POST['tipo_movimiento'];
        $bolsilloId = $_POST['bolsillo_id'];
        $monto = floatval($_POST['monto']);
        
        // Obtener nombre del bolsillo
        $stmt = $db->prepare("SELECT nombre FROM bolsillos_ahorro WHERE id = ?");
        $stmt->execute([$bolsilloId]);
        $bolsilloNombre = $stmt->fetchColumn();
        
        // Verificar saldo antes de depositar
        if ($tipo === 'deposito') {
            $saldoActual = calcularSaldoDisponible($db, $userId);
            if ($monto > $saldoActual) {
                $errors[] = 'No tienes suficiente saldo disponible. Tu saldo actual es ' . formatMoney($saldoActual);
            }
        }
        
        if (empty($errors)) {
            // Registrar movimiento
            $stmt = $db->prepare("
                INSERT INTO movimientos_bolsillos (bolsillo_id, tipo, monto, descripcion, bolsillo_destino_id, fecha)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $bolsilloId,
                $tipo,
                $monto,
                $_POST['descripcion'],
                $tipo === 'transferencia' ? $_POST['bolsillo_destino_id'] : null,
                $_POST['fecha']
            ]);
            $movimientoId = $db->lastInsertId();
            
            // Actualizar saldo y registrar gasto/ingreso
            if ($tipo === 'deposito') {
                $stmt = $db->prepare("UPDATE bolsillos_ahorro SET saldo = saldo + ? WHERE id = ?");
                $stmt->execute([$monto, $bolsilloId]);
                
                // Registrar como GASTO (descuenta del saldo)
                $stmt = $db->prepare("
                    INSERT INTO gastos (usuario_id, categoria_id, monto, descripcion, fecha, referencia_tipo, referencia_id)
                    VALUES (?, ?, ?, ?, ?, 'movimiento_bolsillo', ?)
                ");
                $stmt->execute([
                    $userId,
                    $categoriaGastoId,
                    $monto,
                    'Depósito a bolsillo: ' . $bolsilloNombre,
                    $_POST['fecha'],
                    $movimientoId
                ]);
            } elseif ($tipo === 'retiro') {
                $stmt = $db->prepare("UPDATE bolsillos_ahorro SET saldo = saldo - ? WHERE id = ?");
                $stmt->execute([$monto, $bolsilloId]);
                
                // Registrar como INGRESO (suma al saldo)
                $stmt = $db->prepare("
                    INSERT INTO ingresos (usuario_id, categoria_id, monto, descripcion, fecha, referencia_tipo, referencia_id)
                    VALUES (?, ?, ?, ?, ?, 'movimiento_bolsillo', ?)
                ");
                $stmt->execute([
                    $userId,
                    $categoriaIngresoId,
                    $monto,
                    'Retiro de bolsillo: ' . $bolsilloNombre,
                    $_POST['fecha'],
                    $movimientoId
                ]);
            } elseif ($tipo === 'transferencia') {
                // Transferencia entre bolsillos no afecta el saldo general
                $stmt = $db->prepare("UPDATE bolsillos_ahorro SET saldo = saldo - ? WHERE id = ?");
                $stmt->execute([$monto, $bolsilloId]);
                $stmt = $db->prepare("UPDATE bolsillos_ahorro SET saldo = saldo + ? WHERE id = ?");
                $stmt->execute([$monto, $_POST['bolsillo_destino_id']]);
            }
            
            header('Location: ahorros.php?success=movimiento');
            exit;
        }
    }
}

// Eliminar bolsillo
if (isset($_GET['delete'])) {
    // Primero eliminar los gastos/ingresos asociados a los movimientos de este bolsillo
    $stmt = $db->prepare("
        DELETE g FROM gastos g
        INNER JOIN movimientos_bolsillos m ON g.referencia_id = m.id AND g.referencia_tipo = 'movimiento_bolsillo'
        WHERE m.bolsillo_id = ?
    ");
    $stmt->execute([$_GET['delete']]);
    
    $stmt = $db->prepare("
        DELETE i FROM ingresos i
        INNER JOIN movimientos_bolsillos m ON i.referencia_id = m.id AND i.referencia_tipo = 'movimiento_bolsillo'
        WHERE m.bolsillo_id = ?
    ");
    $stmt->execute([$_GET['delete']]);
    
    // Eliminar movimientos
    $stmt = $db->prepare("DELETE FROM movimientos_bolsillos WHERE bolsillo_id = ?");
    $stmt->execute([$_GET['delete']]);
    
    // Eliminar el bolsillo
    $stmt = $db->prepare("DELETE FROM bolsillos_ahorro WHERE id = ? AND usuario_id = ?");
    $stmt->execute([$_GET['delete'], $userId]);
    header('Location: ahorros.php?success=deleted');
    exit;
}

// Ahora sí incluir el header (ya no habrá redirects después de este punto)
$pageTitle = 'Bolsillos de Ahorro';
require_once __DIR__ . '/../includes/header.php';

// Obtener bolsillos
$stmt = $db->prepare("
    SELECT * FROM bolsillos_ahorro 
    WHERE usuario_id = ? AND activo = 1
    ORDER BY nombre
");
$stmt->execute([$userId]);
$bolsillos = $stmt->fetchAll();

// Total ahorrado
$totalAhorrado = array_sum(array_column($bolsillos, 'saldo'));

// Calcular saldo disponible
$saldoDisponible = calcularSaldoDisponible($db, $userId);

// Obtener últimos movimientos
$stmt = $db->prepare("
    SELECT m.*, b.nombre as bolsillo_nombre, b.color,
           bd.nombre as bolsillo_destino_nombre
    FROM movimientos_bolsillos m
    JOIN bolsillos_ahorro b ON m.bolsillo_id = b.id
    LEFT JOIN bolsillos_ahorro bd ON m.bolsillo_destino_id = bd.id
    WHERE b.usuario_id = ?
    ORDER BY m.fecha DESC, m.id DESC
    LIMIT 15
");
$stmt->execute([$userId]);
$movimientos = $stmt->fetchAll();

// Obtener bolsillo para editar
$bolsilloEditar = null;
if (isset($_GET['edit'])) {
    $stmt = $db->prepare("SELECT * FROM bolsillos_ahorro WHERE id = ? AND usuario_id = ?");
    $stmt->execute([$_GET['edit'], $userId]);
    $bolsilloEditar = $stmt->fetch();
}

$showModal = isset($_GET['add']) || $bolsilloEditar;
$showMovModal = isset($_GET['mov']);

// Iconos disponibles
$iconos = [
    'fa-piggy-bank' => 'Alcancía',
    'fa-medkit' => 'Emergencias',
    'fa-plane' => 'Viajes',
    'fa-chart-line' => 'Inversión',
    'fa-home' => 'Casa',
    'fa-car' => 'Vehículo',
    'fa-graduation-cap' => 'Educación',
    'fa-gift' => 'Regalos',
    'fa-heart' => 'Personal',
    'fa-briefcase' => 'Negocios'
];
?>

<!-- Page Header -->
<div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-3">
    <div>
        <h1><i class="fas fa-piggy-bank text-success me-2"></i>Bolsillos de Ahorro</h1>
        <p>Organiza tu dinero en diferentes fondos</p>
    </div>
    <div class="page-header-actions d-flex gap-2">
        <a href="?mov=1" class="btn btn-success">
            <i class="fas fa-exchange-alt"></i> Movimiento
        </a>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#bolsilloModal">
            <i class="fas fa-plus"></i> Nuevo Bolsillo
        </button>
    </div>
</div>

<?php if (isset($_GET['success'])): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <?php
    switch ($_GET['success']) {
        case 'added': echo 'Bolsillo creado correctamente.'; break;
        case 'updated': echo 'Bolsillo actualizado correctamente.'; break;
        case 'deleted': echo 'Bolsillo eliminado correctamente.'; break;
        case 'movimiento': echo 'Movimiento registrado correctamente.'; break;
    }
    ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <strong><i class="fas fa-exclamation-triangle me-2"></i>Error:</strong>
    <ul class="mb-0 mt-2">
        <?php foreach ($errors as $error): ?>
        <li><?= htmlspecialchars($error) ?></li>
        <?php endforeach; ?>
    </ul>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Alerta informativa -->
<div class="alert alert-info alert-dismissible fade show mb-4" role="alert">
    <i class="fas fa-info-circle me-2"></i>
    <strong>Tu saldo disponible: <?= formatMoney($saldoDisponible) ?></strong> — 
    Tienes <strong class="text-success"><?= formatMoney($totalAhorrado) ?></strong> guardados en bolsillos.
    Al depositar, el dinero se descuenta de tu saldo. Al retirar, regresa a tu cuenta.
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>

<!-- Total Ahorrado -->
<div class="row g-4 mb-4">
    <div class="col-12">
        <div class="card card-gradient-success border-0 shadow">
            <div class="card-body text-center py-4">
                <h6 class="mb-2 text-white">Total Ahorrado en Todos los Bolsillos</h6>
                <h1 class="display-4 fw-bold mb-0 text-white"><?= formatMoney($totalAhorrado) ?></h1>
                <p class="mb-0 mt-2 text-white-50"><?= count($bolsillos) ?> bolsillos activos</p>
            </div>
        </div>
    </div>
</div>

<!-- Lista de Bolsillos -->
<div class="row g-4 mb-4">
    <?php foreach ($bolsillos as $bolsillo): ?>
    <div class="col-lg-3 col-md-6">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div class="rounded-circle d-flex align-items-center justify-content-center" 
                         style="width: 50px; height: 50px; background: <?= $bolsillo['color'] ?>20; color: <?= $bolsillo['color'] ?>">
                        <i class="fas <?= $bolsillo['icono'] ?> fa-lg"></i>
                    </div>
                    <div class="dropdown">
                        <button class="btn btn-sm btn-soft-primary" data-bs-toggle="dropdown">
                            <i class="fas fa-ellipsis-v"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li>
                                <a class="dropdown-item" href="?mov=1&bolsillo=<?= $bolsillo['id'] ?>">
                                    <i class="fas fa-plus-circle me-2 text-success"></i>Depositar
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="?mov=1&bolsillo=<?= $bolsillo['id'] ?>&tipo=retiro">
                                    <i class="fas fa-minus-circle me-2 text-danger"></i>Retirar
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item" href="?edit=<?= $bolsillo['id'] ?>">
                                    <i class="fas fa-edit me-2"></i>Editar
                                </a>
                            </li>
                            <li>
                                <button class="dropdown-item text-danger" 
                                        data-delete="?delete=<?= $bolsillo['id'] ?>" 
                                        data-name="este bolsillo">
                                    <i class="fas fa-trash me-2"></i>Eliminar
                                </button>
                            </li>
                        </ul>
                    </div>
                </div>
                
                <h5 class="mb-1"><?= htmlspecialchars($bolsillo['nombre']) ?></h5>
                <?php if ($bolsillo['descripcion']): ?>
                <p class="text-muted small mb-3"><?= htmlspecialchars($bolsillo['descripcion']) ?></p>
                <?php endif; ?>
                
                <h3 class="mb-0" style="color: <?= $bolsillo['color'] ?>"><?= formatMoney($bolsillo['saldo']) ?></h3>
            </div>
            <div class="card-footer bg-transparent">
                <div class="d-flex gap-2">
                    <a href="?mov=1&bolsillo=<?= $bolsillo['id'] ?>" class="btn btn-sm btn-soft-success flex-grow-1">
                        <i class="fas fa-plus"></i> Depositar
                    </a>
                    <a href="?mov=1&bolsillo=<?= $bolsillo['id'] ?>&tipo=retiro" class="btn btn-sm btn-soft-danger flex-grow-1">
                        <i class="fas fa-minus"></i> Retirar
                    </a>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    
    <!-- Card para agregar nuevo -->
    <div class="col-lg-3 col-md-6">
        <div class="card h-100 border-dashed" style="border: 2px dashed #dee2e6;">
            <div class="card-body d-flex flex-column align-items-center justify-content-center text-center">
                <div class="rounded-circle bg-light d-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px;">
                    <i class="fas fa-plus fa-lg text-muted"></i>
                </div>
                <h6 class="text-muted mb-3">Crear nuevo bolsillo</h6>
                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#bolsilloModal">
                    <i class="fas fa-plus me-1"></i> Agregar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Últimos Movimientos -->
<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">
            <i class="fas fa-history me-2"></i>Últimos Movimientos
        </h5>
    </div>
    <div class="card-body p-0">
        <?php if (count($movimientos) > 0): ?>
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Bolsillo</th>
                        <th>Tipo</th>
                        <th>Descripción</th>
                        <th>Monto</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($movimientos as $mov): ?>
                    <tr>
                        <td><?= formatDate($mov['fecha']) ?></td>
                        <td>
                            <span class="badge" style="background: <?= $mov['color'] ?>20; color: <?= $mov['color'] ?>">
                                <?= htmlspecialchars($mov['bolsillo_nombre']) ?>
                            </span>
                            <?php if ($mov['tipo'] === 'transferencia' && $mov['bolsillo_destino_nombre']): ?>
                            <i class="fas fa-arrow-right mx-1"></i>
                            <span class="badge bg-secondary"><?= htmlspecialchars($mov['bolsillo_destino_nombre']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            $tipoIcon = $mov['tipo'] === 'deposito' ? 'fa-arrow-down text-success' : 
                                       ($mov['tipo'] === 'retiro' ? 'fa-arrow-up text-danger' : 'fa-exchange-alt text-info');
                            ?>
                            <i class="fas <?= $tipoIcon ?>"></i>
                            <?= ucfirst($mov['tipo']) ?>
                        </td>
                        <td><?= htmlspecialchars($mov['descripcion'] ?: '-') ?></td>
                        <td class="fw-bold <?= $mov['tipo'] === 'deposito' ? 'text-success' : ($mov['tipo'] === 'retiro' ? 'text-danger' : 'text-info') ?>">
                            <?= $mov['tipo'] === 'deposito' ? '+' : '-' ?><?= formatMoney($mov['monto']) ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="empty-state py-5">
            <i class="fas fa-exchange-alt"></i>
            <h4>No hay movimientos</h4>
            <p>Realiza tu primer depósito o retiro</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Nuevo/Editar Bolsillo -->
<div class="modal fade <?= $showModal ? 'show' : '' ?>" id="bolsilloModal" tabindex="-1" <?= $showModal ? 'style="display: block;"' : '' ?>>
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="<?= $bolsilloEditar ? 'edit_bolsillo' : 'add_bolsillo' ?>">
                <?php if ($bolsilloEditar): ?>
                <input type="hidden" name="id" value="<?= $bolsilloEditar['id'] ?>">
                <?php endif; ?>
                
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-piggy-bank text-success me-2"></i>
                        <?= $bolsilloEditar ? 'Editar Bolsillo' : 'Nuevo Bolsillo' ?>
                    </h5>
                    <a href="ahorros.php" class="btn-close"></a>
                </div>
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nombre *</label>
                        <input type="text" class="form-control" name="nombre" required
                               value="<?= htmlspecialchars($bolsilloEditar['nombre'] ?? '') ?>"
                               placeholder="Ej: Fondo de Emergencia">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Descripción</label>
                        <input type="text" class="form-control" name="descripcion"
                               value="<?= htmlspecialchars($bolsilloEditar['descripcion'] ?? '') ?>"
                               placeholder="¿Para qué es este fondo?">
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Icono</label>
                            <select class="form-select" name="icono">
                                <?php foreach ($iconos as $clase => $nombre): ?>
                                <option value="<?= $clase ?>" <?= ($bolsilloEditar['icono'] ?? 'fa-piggy-bank') == $clase ? 'selected' : '' ?>>
                                    <?= $nombre ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Color</label>
                            <input type="color" class="form-control form-control-color w-100" name="color"
                                   value="<?= $bolsilloEditar['color'] ?? '#28a745' ?>">
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <a href="ahorros.php" class="btn btn-secondary">Cancelar</a>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save me-1"></i>
                        <?= $bolsilloEditar ? 'Actualizar' : 'Crear Bolsillo' ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php if ($showModal): ?><div class="modal-backdrop fade show"></div><?php endif; ?>

<!-- Modal Movimiento -->
<?php if ($showMovModal): ?>
<div class="modal fade show" id="movimientoModal" tabindex="-1" style="display: block;">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="movimiento">
                
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-exchange-alt text-primary me-2"></i>
                        Nuevo Movimiento
                    </h5>
                    <a href="ahorros.php" class="btn-close"></a>
                </div>
                
                <div class="modal-body">
                    <!-- Saldo disponible -->
                    <div class="alert alert-<?= $saldoDisponible > 0 ? 'info' : 'warning' ?> py-2 mb-3" id="alertaSaldo">
                        <i class="fas fa-wallet me-2"></i>
                        <strong>Saldo disponible para depositar:</strong> <?= formatMoney($saldoDisponible) ?>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Tipo de Movimiento</label>
                        <select class="form-select" name="tipo_movimiento" id="tipoMovimiento" onchange="toggleTransferencia()">
                            <option value="deposito" <?= ($_GET['tipo'] ?? '') !== 'retiro' ? 'selected' : '' ?>>Depósito</option>
                            <option value="retiro" <?= ($_GET['tipo'] ?? '') === 'retiro' ? 'selected' : '' ?>>Retiro</option>
                            <option value="transferencia">Transferencia entre bolsillos</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Bolsillo Origen *</label>
                        <select class="form-select" name="bolsillo_id" required>
                            <?php foreach ($bolsillos as $b): ?>
                            <option value="<?= $b['id'] ?>" <?= ($_GET['bolsillo'] ?? '') == $b['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($b['nombre']) ?> (<?= formatMoney($b['saldo']) ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3" id="bolsilloDestinoGroup" style="display: none;">
                        <label class="form-label">Bolsillo Destino *</label>
                        <select class="form-select" name="bolsillo_destino_id">
                            <?php foreach ($bolsillos as $b): ?>
                            <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Monto</label>
                        <div class="input-group">
                            <span class="input-group-text"><?= APP_CURRENCY_SYMBOL ?></span>
                            <input type="number" class="form-control" name="monto" step="0.01" required
                                   placeholder="100.00">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Fecha *</label>
                        <input type="date" class="form-control" name="fecha" required value="<?= date('Y-m-d') ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Descripción</label>
                        <input type="text" class="form-control" name="descripcion"
                               placeholder="Ej: Ahorro del mes">
                    </div>
                </div>
                
                <div class="modal-footer">
                    <a href="ahorros.php" class="btn btn-secondary">Cancelar</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-check me-1"></i> Registrar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<div class="modal-backdrop fade show"></div>

<script>
function toggleTransferencia() {
    const tipo = document.getElementById('tipoMovimiento').value;
    document.getElementById('bolsilloDestinoGroup').style.display = tipo === 'transferencia' ? 'block' : 'none';
    // Mostrar alerta de saldo solo para depósitos
    const alertaSaldo = document.getElementById('alertaSaldo');
    if (alertaSaldo) {
        alertaSaldo.style.display = tipo === 'deposito' ? 'block' : 'none';
    }
}
// Ejecutar al cargar para estado inicial
document.addEventListener('DOMContentLoaded', toggleTransferencia);
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
