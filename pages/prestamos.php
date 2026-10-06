<?php
/**
 * AZX-Finance - Gestión de Préstamos
 * Dinero que prestas a otras personas
 */

// Cargar configuración y base de datos antes del header para poder hacer redirects
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';

$db = getDB();
$userId = getCurrentUserId();

// Obtener o crear categoría de gastos para préstamos
$stmt = $db->prepare("SELECT id FROM categorias_gastos WHERE nombre = 'Préstamos Otorgados' AND (usuario_id = ? OR usuario_id IS NULL) LIMIT 1");
$stmt->execute([$userId]);
$catGastoPrestamo = $stmt->fetch();
if (!$catGastoPrestamo) {
    $db->prepare("INSERT INTO categorias_gastos (usuario_id, nombre, icono, color, limite_mensual) VALUES (?, 'Préstamos Otorgados', 'fa-hand-holding-usd', '#6f42c1', NULL)")->execute([$userId]);
    $catGastoPrestamoId = $db->lastInsertId();
} else {
    $catGastoPrestamoId = $catGastoPrestamo['id'];
}

// Obtener o crear categoría de ingresos para devoluciones
$stmt = $db->prepare("SELECT id FROM categorias_ingresos WHERE nombre = 'Devolución Préstamos' AND (usuario_id = ? OR usuario_id IS NULL) LIMIT 1");
$stmt->execute([$userId]);
$catIngresoPrestamo = $stmt->fetch();
if (!$catIngresoPrestamo) {
    $db->prepare("INSERT INTO categorias_ingresos (usuario_id, nombre, icono, color) VALUES (?, 'Devolución Préstamos', 'fa-hand-holding-usd', '#28a745')")->execute([$userId]);
    $catIngresoPrestamoId = $db->lastInsertId();
} else {
    $catIngresoPrestamoId = $catIngresoPrestamo['id'];
}

// Calcular saldo disponible del usuario
function calcularSaldoDisponible($db, $userId) {
    $stmt = $db->prepare("SELECT COALESCE(SUM(monto), 0) as total FROM ingresos WHERE usuario_id = ?");
    $stmt->execute([$userId]);
    $totalIngresos = $stmt->fetch()['total'];
    
    $stmt = $db->prepare("SELECT COALESCE(SUM(monto), 0) as total FROM gastos WHERE usuario_id = ?");
    $stmt->execute([$userId]);
    $totalGastos = $stmt->fetch()['total'];
    
    return $totalIngresos - $totalGastos;
}

// Eliminar préstamo
if (isset($_GET['delete'])) {
    $prestamoIdEliminar = intval($_GET['delete']);
    $dineroFueDevuelto = isset($_GET['devuelto']) && $_GET['devuelto'] === '1';

    // Obtener el préstamo para saber el monto
    $stmt = $db->prepare("SELECT * FROM prestamos WHERE id = ? AND usuario_id = ?");
    $stmt->execute([$prestamoIdEliminar, $userId]);
    $prestamoEliminar = $stmt->fetch();
    
    if ($prestamoEliminar) {
        try {
            $db->beginTransaction();

            // Eliminar pagos e ingresos asociados al historial del préstamo
            $descripcionDevolucion = "Pago de préstamo: " . $prestamoEliminar['deudor_nombre'];
            $stmt = $db->prepare("DELETE FROM ingresos WHERE usuario_id = ? AND categoria_id = ? AND descripcion LIKE ?");
            $stmt->execute([$userId, $catIngresoPrestamoId, $descripcionDevolucion . '%']);

            $stmt = $db->prepare("DELETE FROM pagos_prestamos WHERE prestamo_id = ?");
            $stmt->execute([$prestamoIdEliminar]);

            if ($dineroFueDevuelto) {
                // Si fue devuelto, se registra el regreso del dinero como ingreso
                $descripcionIngresoCierre = "Devolución por cierre de préstamo: " . $prestamoEliminar['deudor_nombre'];
                $stmt = $db->prepare("
                    INSERT INTO ingresos (usuario_id, categoria_id, monto, descripcion, fecha, es_recurrente)
                    VALUES (?, ?, ?, ?, ?, 0)
                ");
                $stmt->execute([
                    $userId,
                    $catIngresoPrestamoId,
                    $prestamoEliminar['monto_original'],
                    $descripcionIngresoCierre,
                    date('Y-m-d')
                ]);
            }

            // Si no fue devuelto, el gasto original se mantiene (dinero perdido)

            // Eliminar el préstamo
            $stmt = $db->prepare("DELETE FROM prestamos WHERE id = ? AND usuario_id = ?");
            $stmt->execute([$prestamoIdEliminar, $userId]);

            $db->commit();
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            header('Location: prestamos.php?error=delete_failed');
            exit;
        }
    }

    $successDelete = $dineroFueDevuelto ? 'deleted_devuelto' : 'deleted_perdido';
    header('Location: prestamos.php?success=' . $successDelete);
    exit;
}

// Eliminar pago
if (isset($_GET['delete_pago'])) {
    // Obtener info del pago para actualizar el préstamo
    $stmt = $db->prepare("
        SELECT pp.*, p.monto_pendiente, p.deudor_nombre, p.monto_original
        FROM pagos_prestamos pp 
        JOIN prestamos p ON pp.prestamo_id = p.id 
        WHERE pp.id = ? AND p.usuario_id = ?
    ");
    $stmt->execute([$_GET['delete_pago'], $userId]);
    $pago = $stmt->fetch();
    
    if ($pago) {
        // Devolver el monto al pendiente
        $nuevoMontoPendiente = $pago['monto_pendiente'] + $pago['monto'];
        $nuevoEstado = $nuevoMontoPendiente >= $pago['monto_original'] ? 'pendiente' : 'parcial';
        $stmt = $db->prepare("UPDATE prestamos SET monto_pendiente = ?, estado = ? WHERE id = ?");
        $stmt->execute([$nuevoMontoPendiente, $nuevoEstado, $pago['prestamo_id']]);
        
        // Eliminar el ingreso asociado a este pago
        $descripcionIngreso = "Pago de préstamo: " . $pago['deudor_nombre'];
        $stmt = $db->prepare("DELETE FROM ingresos WHERE usuario_id = ? AND categoria_id = ? AND monto = ? AND descripcion LIKE ? LIMIT 1");
        $stmt->execute([$userId, $catIngresoPrestamoId, $pago['monto'], $descripcionIngreso . '%']);
        
        // Eliminar el pago
        $stmt = $db->prepare("DELETE FROM pagos_prestamos WHERE id = ?");
        $stmt->execute([$_GET['delete_pago']]);
    }
    header('Location: prestamos.php?success=pago_deleted');
    exit;
}

$errors = [];

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    // Agregar nuevo préstamo
    if ($_POST['action'] === 'add') {
        $deudorNombre = trim($_POST['deudor_nombre'] ?? '');
        $monto = floatval($_POST['monto'] ?? 0);
        $fechaPrestamo = $_POST['fecha_prestamo'] ?? '';
        
        if (empty($deudorNombre)) {
            $errors[] = 'El nombre del deudor es requerido';
        }
        if ($monto <= 0) {
            $errors[] = 'El monto debe ser mayor a 0';
        }
        if (empty($fechaPrestamo)) {
            $errors[] = 'La fecha del préstamo es requerida';
        }
        
        // Verificar saldo disponible
        $saldoDisponible = calcularSaldoDisponible($db, $userId);
        if ($monto > $saldoDisponible) {
            $errors[] = 'No tienes suficiente saldo disponible. Tu saldo actual es ' . APP_CURRENCY_SYMBOL . number_format($saldoDisponible, 2);
        }
        
        if (empty($errors)) {
            // Registrar el préstamo
            $stmt = $db->prepare("
                INSERT INTO prestamos (usuario_id, deudor_nombre, deudor_telefono, deudor_email, 
                    monto_original, monto_pendiente, interes_porcentaje, descripcion, 
                    fecha_prestamo, fecha_vencimiento, estado)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pendiente')
            ");
            $stmt->execute([
                $userId,
                $deudorNombre,
                trim($_POST['deudor_telefono'] ?? '') ?: null,
                trim($_POST['deudor_email'] ?? '') ?: null,
                $monto,
                $monto, // monto_pendiente igual al original al inicio
                floatval($_POST['interes_porcentaje'] ?? 0),
                trim($_POST['descripcion'] ?? '') ?: null,
                $fechaPrestamo,
                !empty($_POST['fecha_vencimiento']) ? $_POST['fecha_vencimiento'] : null
            ]);
            
            // REGISTRAR GASTO: El dinero sale de tu cuenta
            $descripcionGasto = "Préstamo a: " . $deudorNombre;
            if (!empty($_POST['descripcion'])) {
                $descripcionGasto .= " - " . trim($_POST['descripcion']);
            }
            $stmt = $db->prepare("
                INSERT INTO gastos (usuario_id, categoria_id, monto, descripcion, fecha, es_recurrente)
                VALUES (?, ?, ?, ?, ?, 0)
            ");
            $stmt->execute([$userId, $catGastoPrestamoId, $monto, $descripcionGasto, $fechaPrestamo]);
            
            header('Location: prestamos.php?success=added');
            exit;
        }
    }
    
    // Editar préstamo
    if ($_POST['action'] === 'edit') {
        $prestamoId = intval($_POST['prestamo_id'] ?? 0);
        $deudorNombre = trim($_POST['deudor_nombre'] ?? '');
        $nuevaFechaVencimiento = !empty($_POST['fecha_vencimiento']) ? $_POST['fecha_vencimiento'] : null;
        
        if (empty($deudorNombre)) {
            $errors[] = 'El nombre del deudor es requerido';
        }
        
        // Validar que la nueva fecha no sea anterior a hoy
        if ($nuevaFechaVencimiento && $nuevaFechaVencimiento < date('Y-m-d')) {
            $errors[] = 'La fecha de vencimiento no puede ser anterior a hoy';
        }
        
        if (empty($errors)) {
            // Obtener el préstamo actual para determinar el nuevo estado
            $stmt = $db->prepare("SELECT * FROM prestamos WHERE id = ? AND usuario_id = ?");
            $stmt->execute([$prestamoId, $userId]);
            $prestamoActual = $stmt->fetch();
            
            if ($prestamoActual) {
                // Determinar el nuevo estado basado en el monto pendiente y la fecha
                $nuevoEstado = $prestamoActual['estado'];
                
                // Solo cambiar estado si no está pagado
                if ($prestamoActual['estado'] !== 'pagado') {
                    if ($prestamoActual['monto_pendiente'] <= 0) {
                        $nuevoEstado = 'pagado';
                    } elseif ($prestamoActual['monto_pendiente'] < $prestamoActual['monto_original']) {
                        $nuevoEstado = 'parcial';
                    } elseif ($nuevaFechaVencimiento && $nuevaFechaVencimiento < date('Y-m-d')) {
                        $nuevoEstado = 'vencido';
                    } else {
                        // Si la fecha se extiende y estaba vencido, volver a pendiente o parcial
                        if ($prestamoActual['monto_pendiente'] >= $prestamoActual['monto_original']) {
                            $nuevoEstado = 'pendiente';
                        } else {
                            $nuevoEstado = 'parcial';
                        }
                    }
                }
                
                $stmt = $db->prepare("
                    UPDATE prestamos 
                    SET deudor_nombre = ?, deudor_telefono = ?, deudor_email = ?, 
                        descripcion = ?, fecha_vencimiento = ?, estado = ?
                    WHERE id = ? AND usuario_id = ?
                ");
                $stmt->execute([
                    $deudorNombre,
                    trim($_POST['deudor_telefono'] ?? '') ?: null,
                    trim($_POST['deudor_email'] ?? '') ?: null,
                    trim($_POST['descripcion'] ?? '') ?: null,
                    $nuevaFechaVencimiento,
                    $nuevoEstado,
                    $prestamoId,
                    $userId
                ]);
            }
            header('Location: prestamos.php?success=updated');
            exit;
        }
    }
    
    // Registrar pago/abono
    if ($_POST['action'] === 'registrar_pago') {
        $prestamoId = intval($_POST['prestamo_id'] ?? 0);
        $montoPago = floatval($_POST['monto_pago'] ?? 0);
        $fechaPago = $_POST['fecha_pago'] ?? date('Y-m-d');
        
        if ($montoPago <= 0) {
            $errors[] = 'El monto del pago debe ser mayor a 0';
        }
        
        // Verificar que el préstamo existe y el monto no excede lo pendiente
        $stmt = $db->prepare("SELECT * FROM prestamos WHERE id = ? AND usuario_id = ?");
        $stmt->execute([$prestamoId, $userId]);
        $prestamo = $stmt->fetch();
        
        if (!$prestamo) {
            $errors[] = 'Préstamo no encontrado';
        } elseif ($montoPago > $prestamo['monto_pendiente']) {
            $errors[] = 'El monto del pago no puede ser mayor al saldo pendiente';
        }
        
        if (empty($errors)) {
            // Registrar el pago
            $stmt = $db->prepare("
                INSERT INTO pagos_prestamos (prestamo_id, monto, fecha_pago, metodo_pago, notas)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $prestamoId,
                $montoPago,
                $fechaPago,
                $_POST['metodo_pago'] ?? 'efectivo',
                trim($_POST['notas_pago'] ?? '') ?: null
            ]);
            
            // Actualizar el préstamo
            $nuevoMontoPendiente = $prestamo['monto_pendiente'] - $montoPago;
            $nuevoEstado = $nuevoMontoPendiente <= 0 ? 'pagado' : ($nuevoMontoPendiente < $prestamo['monto_original'] ? 'parcial' : 'pendiente');
            
            $stmt = $db->prepare("UPDATE prestamos SET monto_pendiente = ?, estado = ? WHERE id = ?");
            $stmt->execute([$nuevoMontoPendiente, $nuevoEstado, $prestamoId]);
            
            // REGISTRAR INGRESO: El dinero regresa a tu cuenta
            $descripcionIngreso = "Pago de préstamo: " . $prestamo['deudor_nombre'];
            if (!empty($_POST['notas_pago'])) {
                $descripcionIngreso .= " - " . trim($_POST['notas_pago']);
            }
            $stmt = $db->prepare("
                INSERT INTO ingresos (usuario_id, categoria_id, monto, descripcion, fecha, es_recurrente)
                VALUES (?, ?, ?, ?, ?, 0)
            ");
            $stmt->execute([$userId, $catIngresoPrestamoId, $montoPago, $descripcionIngreso, $fechaPago]);
            
            header('Location: prestamos.php?success=pago_registrado');
            exit;
        }
    }
    
    // Marcar como pagado completo
    if ($_POST['action'] === 'marcar_pagado') {
        $prestamoId = intval($_POST['prestamo_id'] ?? 0);
        
        $stmt = $db->prepare("SELECT * FROM prestamos WHERE id = ? AND usuario_id = ?");
        $stmt->execute([$prestamoId, $userId]);
        $prestamo = $stmt->fetch();
        
        if ($prestamo && $prestamo['monto_pendiente'] > 0) {
            // Registrar el pago final
            $stmt = $db->prepare("
                INSERT INTO pagos_prestamos (prestamo_id, monto, fecha_pago, metodo_pago, notas)
                VALUES (?, ?, ?, 'efectivo', 'Pago completo')
            ");
            $stmt->execute([$prestamoId, $prestamo['monto_pendiente'], date('Y-m-d')]);
            
            // REGISTRAR INGRESO: El dinero regresa a tu cuenta
            $descripcionIngreso = "Pago de préstamo: " . $prestamo['deudor_nombre'] . " - Pago completo";
            $stmt = $db->prepare("
                INSERT INTO ingresos (usuario_id, categoria_id, monto, descripcion, fecha, es_recurrente)
                VALUES (?, ?, ?, ?, ?, 0)
            ");
            $stmt->execute([$userId, $catIngresoPrestamoId, $prestamo['monto_pendiente'], $descripcionIngreso, date('Y-m-d')]);
            
            // Marcar como pagado
            $stmt = $db->prepare("UPDATE prestamos SET monto_pendiente = 0, estado = 'pagado' WHERE id = ?");
            $stmt->execute([$prestamoId]);
        }
        
        header('Location: prestamos.php?success=pagado_completo');
        exit;
    }
}

// Ahora sí incluimos el header
$pageTitle = 'Préstamos';
require_once __DIR__ . '/../includes/header.php';

// Filtros
$filtroEstado = $_GET['estado'] ?? 'todos';

// Obtener préstamos
$sql = "SELECT * FROM prestamos WHERE usuario_id = ?";
$params = [$userId];

if ($filtroEstado !== 'todos') {
    $sql .= " AND estado = ?";
    $params[] = $filtroEstado;
}
$sql .= " ORDER BY CASE estado WHEN 'pendiente' THEN 1 WHEN 'parcial' THEN 2 WHEN 'vencido' THEN 3 ELSE 4 END, fecha_prestamo DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$prestamos = $stmt->fetchAll();

// Calcular totales
$stmt = $db->prepare("
    SELECT 
        SUM(monto_original) as total_prestado,
        SUM(monto_pendiente) as total_pendiente,
        SUM(monto_original - monto_pendiente) as total_recuperado,
        COUNT(*) as total_prestamos,
        SUM(CASE WHEN estado IN ('pendiente', 'parcial') THEN 1 ELSE 0 END) as prestamos_activos
    FROM prestamos WHERE usuario_id = ?
");
$stmt->execute([$userId]);
$totales = $stmt->fetch();

// Calcular saldo disponible para prestar
$saldoDisponible = calcularSaldoDisponible($db, $userId);

// Actualizar préstamos vencidos
$db->prepare("
    UPDATE prestamos 
    SET estado = 'vencido' 
    WHERE usuario_id = ? AND estado IN ('pendiente', 'parcial') 
    AND fecha_vencimiento IS NOT NULL AND fecha_vencimiento < CURDATE()
")->execute([$userId]);

// Préstamo para editar
$prestamoEditar = null;
if (isset($_GET['edit'])) {
    $stmt = $db->prepare("SELECT * FROM prestamos WHERE id = ? AND usuario_id = ?");
    $stmt->execute([$_GET['edit'], $userId]);
    $prestamoEditar = $stmt->fetch();
}

// Préstamo para ver detalle/pagos
$prestamoDetalle = null;
$pagosDetalle = [];
if (isset($_GET['detalle'])) {
    $stmt = $db->prepare("SELECT * FROM prestamos WHERE id = ? AND usuario_id = ?");
    $stmt->execute([$_GET['detalle'], $userId]);
    $prestamoDetalle = $stmt->fetch();
    
    if ($prestamoDetalle) {
        $stmt = $db->prepare("SELECT * FROM pagos_prestamos WHERE prestamo_id = ? ORDER BY fecha_pago DESC");
        $stmt->execute([$prestamoDetalle['id']]);
        $pagosDetalle = $stmt->fetchAll();
    }
}

// Función para color de estado
function getEstadoClass($estado) {
    return match($estado) {
        'pendiente' => 'warning',
        'parcial' => 'info',
        'pagado' => 'success',
        'vencido' => 'danger',
        default => 'secondary'
    };
}

function getEstadoTexto($estado) {
    return match($estado) {
        'pendiente' => 'Pendiente',
        'parcial' => 'Pago Parcial',
        'pagado' => 'Pagado',
        'vencido' => 'Vencido',
        default => $estado
    };
}
?>

<!-- Page Header -->
<div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-3">
    <div>
        <h1><i class="fas fa-hand-holding-usd text-primary me-2"></i>Préstamos</h1>
        <p class="text-muted">Gestiona el dinero que has prestado a otras personas</p>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalPrestamo">
        <i class="fas fa-plus me-1"></i> Nuevo Préstamo
    </button>
</div>

<?php if (isset($_GET['success'])): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <?php
    switch ($_GET['success']) {
        case 'added': echo 'Préstamo registrado correctamente.'; break;
        case 'updated': echo 'Préstamo actualizado.'; break;
        case 'deleted': echo 'Préstamo eliminado.'; break;
        case 'deleted_perdido': echo 'Préstamo eliminado como dinero perdido. No se regresó el dinero.'; break;
        case 'deleted_devuelto': echo 'Préstamo eliminado y dinero devuelto correctamente a ingresos.'; break;
        case 'pago_registrado': echo '¡Pago registrado! El dinero ha sido recuperado.'; break;
        case 'pago_deleted': echo 'Pago eliminado.'; break;
        case 'pagado_completo': echo '¡Préstamo marcado como pagado completamente!'; break;
    }
    ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <ul class="mb-0">
        <?php foreach ($errors as $error): ?>
        <li><?= htmlspecialchars($error) ?></li>
        <?php endforeach; ?>
    </ul>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if (isset($_GET['error']) && $_GET['error'] === 'delete_failed'): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    Ocurrió un error al eliminar el préstamo. Inténtalo nuevamente.
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Resumen -->
<div class="row g-4 mb-4">
    <div class="col-lg-3 col-md-6">
        <div class="card stat-card">
            <div class="stat-icon bg-secondary-soft">
                <i class="fas fa-wallet"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Saldo Disponible</div>
                <div class="stat-value <?= $saldoDisponible >= 0 ? 'text-primary' : 'text-danger' ?>"><?= formatMoney($saldoDisponible) ?></div>
                <small class="text-muted">Para prestar</small>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="card stat-card">
            <div class="stat-icon bg-warning-soft">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Por Cobrar</div>
                <div class="stat-value text-warning"><?= formatMoney($totales['total_pendiente'] ?? 0) ?></div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="card stat-card">
            <div class="stat-icon bg-success-soft">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Recuperado</div>
                <div class="stat-value text-success"><?= formatMoney($totales['total_recuperado'] ?? 0) ?></div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="card stat-card">
            <div class="stat-icon bg-info-soft">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Préstamos Activos</div>
                <div class="stat-value"><?= $totales['prestamos_activos'] ?? 0 ?></div>
            </div>
        </div>
    </div>
</div>

<!-- Alerta informativa -->
<div class="alert alert-info alert-dismissible fade show mb-4" role="alert">
    <i class="fas fa-info-circle me-2"></i>
    <strong>¿Cómo funciona?</strong> Al registrar un préstamo, el dinero se descuenta de tu saldo. Cuando te devuelvan el dinero, registra el pago y el dinero regresará a tu cuenta.
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>

<!-- Filtros -->
<div class="card mb-4">
    <div class="card-body py-2">
        <div class="d-flex gap-2 flex-wrap">
            <a href="?estado=todos" class="btn btn-sm <?= $filtroEstado === 'todos' ? 'btn-primary' : 'btn-outline-primary' ?>">
                Todos
            </a>
            <a href="?estado=pendiente" class="btn btn-sm <?= $filtroEstado === 'pendiente' ? 'btn-warning' : 'btn-outline-warning' ?>">
                <i class="fas fa-clock me-1"></i> Pendientes
            </a>
            <a href="?estado=parcial" class="btn btn-sm <?= $filtroEstado === 'parcial' ? 'btn-info' : 'btn-outline-info' ?>">
                <i class="fas fa-hourglass-half me-1"></i> Pago Parcial
            </a>
            <a href="?estado=vencido" class="btn btn-sm <?= $filtroEstado === 'vencido' ? 'btn-danger' : 'btn-outline-danger' ?>">
                <i class="fas fa-exclamation-triangle me-1"></i> Vencidos
            </a>
            <a href="?estado=pagado" class="btn btn-sm <?= $filtroEstado === 'pagado' ? 'btn-success' : 'btn-outline-success' ?>">
                <i class="fas fa-check me-1"></i> Pagados
            </a>
        </div>
    </div>
</div>

<!-- Lista de Préstamos -->
<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">Mis Préstamos</h5>
    </div>
    <div class="card-body p-0">
        <?php if (empty($prestamos)): ?>
        <div class="text-center py-5">
            <i class="fas fa-hand-holding-usd fa-4x text-muted mb-3"></i>
            <h5>No tienes préstamos registrados</h5>
            <p class="text-muted">Registra el dinero que prestas para llevar un control</p>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalPrestamo">
                <i class="fas fa-plus me-1"></i> Registrar Préstamo
            </button>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Persona</th>
                        <th>Monto Original</th>
                        <th>Pendiente</th>
                        <th>Fecha Préstamo</th>
                        <th>Vencimiento</th>
                        <th>Estado</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($prestamos as $p): ?>
                    <tr>
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="avatar-sm bg-primary-soft rounded-circle me-2 d-flex align-items-center justify-content-center">
                                    <i class="fas fa-user text-primary"></i>
                                </div>
                                <div>
                                    <strong><?= htmlspecialchars($p['deudor_nombre']) ?></strong>
                                    <?php if ($p['deudor_telefono']): ?>
                                    <br><small class="text-muted"><i class="fas fa-phone me-1"></i><?= htmlspecialchars($p['deudor_telefono']) ?></small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td><?= formatMoney($p['monto_original']) ?></td>
                        <td class="<?= $p['monto_pendiente'] > 0 ? 'text-warning fw-bold' : 'text-success' ?>">
                            <?= formatMoney($p['monto_pendiente']) ?>
                        </td>
                        <td><?= formatDate($p['fecha_prestamo']) ?></td>
                        <td>
                            <?php if ($p['fecha_vencimiento']): ?>
                                <?php 
                                $vencimiento = new DateTime($p['fecha_vencimiento']);
                                $hoy = new DateTime();
                                $diff = $hoy->diff($vencimiento);
                                $vencido = $hoy > $vencimiento;
                                ?>
                                <span class="<?= $vencido ? 'text-danger' : '' ?>">
                                    <?= formatDate($p['fecha_vencimiento']) ?>
                                    <?php if (!$vencido && $p['estado'] !== 'pagado'): ?>
                                    <br><small class="text-muted">(<?= $diff->days ?> días)</small>
                                    <?php endif; ?>
                                </span>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge bg-<?= getEstadoClass($p['estado']) ?>">
                                <?= getEstadoTexto($p['estado']) ?>
                            </span>
                        </td>
                        <td class="text-end">
                            <div class="btn-group btn-group-sm">
                                <a href="?detalle=<?= $p['id'] ?>" class="btn btn-outline-primary" title="Ver detalle">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <?php if ($p['estado'] !== 'pagado'): ?>
                                <button type="button" class="btn btn-outline-success btn-registrar-pago" 
                                        data-prestamo-id="<?= $p['id'] ?>"
                                        data-deudor="<?= htmlspecialchars($p['deudor_nombre']) ?>"
                                        data-pendiente="<?= $p['monto_pendiente'] ?>"
                                        title="Registrar pago">
                                    <i class="fas fa-money-bill-wave"></i>
                                </button>
                                <?php endif; ?>
                                <a href="?edit=<?= $p['id'] ?>" class="btn btn-outline-secondary" title="Editar">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <button type="button" class="btn btn-outline-danger btn-eliminar" 
                                        data-id="<?= $p['id'] ?>" 
                                        data-nombre="<?= htmlspecialchars($p['deudor_nombre']) ?>"
                                        title="Eliminar">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Nuevo/Editar Préstamo -->
<div class="modal fade" id="modalPrestamo" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="<?= $prestamoEditar ? 'edit' : 'add' ?>">
                <?php if ($prestamoEditar): ?>
                <input type="hidden" name="prestamo_id" value="<?= $prestamoEditar['id'] ?>">
                <?php endif; ?>
                
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-hand-holding-usd text-primary me-2"></i>
                        <?= $prestamoEditar ? 'Editar Préstamo' : 'Nuevo Préstamo' ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nombre de la persona <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="deudor_nombre" required
                               value="<?= htmlspecialchars($prestamoEditar['deudor_nombre'] ?? '') ?>"
                               placeholder="¿A quién le prestaste?">
                    </div>
                    
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Teléfono</label>
                            <input type="tel" class="form-control" name="deudor_telefono"
                                   value="<?= htmlspecialchars($prestamoEditar['deudor_telefono'] ?? '') ?>"
                                   placeholder="Opcional">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="deudor_email"
                                   value="<?= htmlspecialchars($prestamoEditar['deudor_email'] ?? '') ?>"
                                   placeholder="Opcional">
                        </div>
                    </div>
                    
                    <?php if (!$prestamoEditar): ?>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Monto Prestado <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><?= APP_CURRENCY_SYMBOL ?></span>
                                <input type="number" class="form-control" name="monto" step="0.01" min="0.01" required
                                       placeholder="0.00">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Interés (%)</label>
                            <div class="input-group">
                                <input type="number" class="form-control" name="interes_porcentaje" step="0.01" min="0" value="0">
                                <span class="input-group-text">%</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Fecha del Préstamo <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="fecha_prestamo" required
                                   value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Fecha de Vencimiento</label>
                            <input type="date" class="form-control" name="fecha_vencimiento"
                                   placeholder="Opcional">
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="mb-3">
                        <label class="form-label">Fecha de Vencimiento</label>
                        <input type="date" class="form-control" name="fecha_vencimiento"
                               value="<?= $prestamoEditar['fecha_vencimiento'] ?? '' ?>">
                    </div>
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <label class="form-label">Descripción / Motivo</label>
                        <textarea class="form-control" name="descripcion" rows="2"
                                  placeholder="¿Para qué fue el préstamo?"><?= htmlspecialchars($prestamoEditar['descripcion'] ?? '') ?></textarea>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i> <?= $prestamoEditar ? 'Actualizar' : 'Guardar' ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Registrar Pago -->
<div class="modal fade" id="modalPago" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="registrar_pago">
                <input type="hidden" name="prestamo_id" id="pago_prestamo_id">
                
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-money-bill-wave text-success me-2"></i>
                        Registrar Pago
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <div class="alert alert-info py-2">
                        <strong id="pago_deudor_nombre"></strong> te debe: 
                        <span class="fw-bold" id="pago_monto_pendiente"></span>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Monto del Pago <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><?= APP_CURRENCY_SYMBOL ?></span>
                            <input type="number" class="form-control" name="monto_pago" id="monto_pago" 
                                   step="0.01" min="0.01" required>
                            <button type="button" class="btn btn-outline-secondary" id="btnPagoCompleto">
                                Todo
                            </button>
                        </div>
                    </div>
                    
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Fecha del Pago</label>
                            <input type="date" class="form-control" name="fecha_pago" value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Método de Pago</label>
                            <select class="form-select" name="metodo_pago">
                                <option value="efectivo">Efectivo</option>
                                <option value="transferencia">Transferencia</option>
                                <option value="tarjeta">Tarjeta</option>
                                <option value="otro">Otro</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Notas</label>
                        <input type="text" class="form-control" name="notas_pago" placeholder="Opcional">
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-check me-1"></i> Registrar Pago
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Detalle de Préstamo -->
<?php if ($prestamoDetalle): ?>
<div class="modal fade show" id="modalDetalle" tabindex="-1" style="display: block;">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-eye text-primary me-2"></i>
                    Detalle del Préstamo
                </h5>
                <a href="prestamos.php" class="btn-close"></a>
            </div>
            
            <div class="modal-body">
                <!-- Info del préstamo -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <h6 class="text-muted mb-1">Persona</h6>
                        <h4><?= htmlspecialchars($prestamoDetalle['deudor_nombre']) ?></h4>
                        <?php if ($prestamoDetalle['deudor_telefono']): ?>
                        <p class="mb-1"><i class="fas fa-phone me-2"></i><?= htmlspecialchars($prestamoDetalle['deudor_telefono']) ?></p>
                        <?php endif; ?>
                        <?php if ($prestamoDetalle['deudor_email']): ?>
                        <p class="mb-1"><i class="fas fa-envelope me-2"></i><?= htmlspecialchars($prestamoDetalle['deudor_email']) ?></p>
                        <?php endif; ?>
                        <?php if ($prestamoDetalle['descripcion']): ?>
                        <p class="text-muted mt-2"><em><?= htmlspecialchars($prestamoDetalle['descripcion']) ?></em></p>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <div class="row g-3">
                            <div class="col-6">
                                <small class="text-muted">Monto Original</small>
                                <h5><?= formatMoney($prestamoDetalle['monto_original']) ?></h5>
                            </div>
                            <div class="col-6">
                                <small class="text-muted">Pendiente</small>
                                <h5 class="<?= $prestamoDetalle['monto_pendiente'] > 0 ? 'text-warning' : 'text-success' ?>">
                                    <?= formatMoney($prestamoDetalle['monto_pendiente']) ?>
                                </h5>
                            </div>
                            <div class="col-6">
                                <small class="text-muted">Fecha Préstamo</small>
                                <p class="mb-0"><?= formatDate($prestamoDetalle['fecha_prestamo']) ?></p>
                            </div>
                            <div class="col-6">
                                <small class="text-muted">Estado</small>
                                <p class="mb-0">
                                    <span class="badge bg-<?= getEstadoClass($prestamoDetalle['estado']) ?>">
                                        <?= getEstadoTexto($prestamoDetalle['estado']) ?>
                                    </span>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Progreso -->
                <?php 
                $porcentajePagado = $prestamoDetalle['monto_original'] > 0 
                    ? (($prestamoDetalle['monto_original'] - $prestamoDetalle['monto_pendiente']) / $prestamoDetalle['monto_original']) * 100 
                    : 0;
                ?>
                <div class="mb-4">
                    <div class="d-flex justify-content-between mb-1">
                        <span>Progreso de pago</span>
                        <span><?= round($porcentajePagado) ?>%</span>
                    </div>
                    <div class="progress" style="height: 10px;">
                        <div class="progress-bar bg-success" style="width: <?= $porcentajePagado ?>%"></div>
                    </div>
                </div>
                
                <!-- Historial de pagos -->
                <h6 class="mb-3"><i class="fas fa-history me-2"></i>Historial de Pagos</h6>
                <?php if (empty($pagosDetalle)): ?>
                <div class="text-center py-3 text-muted">
                    <i class="fas fa-inbox fa-2x mb-2"></i>
                    <p>No hay pagos registrados aún</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Monto</th>
                                <th>Método</th>
                                <th>Notas</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pagosDetalle as $pago): ?>
                            <tr>
                                <td><?= formatDate($pago['fecha_pago']) ?></td>
                                <td class="text-success fw-bold"><?= formatMoney($pago['monto']) ?></td>
                                <td><span class="badge bg-secondary"><?= ucfirst($pago['metodo_pago']) ?></span></td>
                                <td><?= htmlspecialchars($pago['notas'] ?? '-') ?></td>
                                <td>
                                    <a href="?delete_pago=<?= $pago['id'] ?>" class="btn btn-sm btn-outline-danger"
                                       onclick="return confirm('¿Eliminar este pago? El monto volverá a estar pendiente.')">
                                        <i class="fas fa-times"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="modal-footer">
                <?php if ($prestamoDetalle['estado'] !== 'pagado'): ?>
                <button type="button" class="btn btn-success btn-registrar-pago" 
                        data-prestamo-id="<?= $prestamoDetalle['id'] ?>"
                        data-deudor="<?= htmlspecialchars($prestamoDetalle['deudor_nombre']) ?>"
                        data-pendiente="<?= $prestamoDetalle['monto_pendiente'] ?>">
                    <i class="fas fa-money-bill-wave me-1"></i> Registrar Pago
                </button>
                <?php endif; ?>
                <a href="prestamos.php" class="btn btn-secondary">Cerrar</a>
            </div>
        </div>
    </div>
</div>
<div class="modal-backdrop fade show"></div>
<?php endif; ?>

<?php if ($prestamoEditar): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    new bootstrap.Modal(document.getElementById('modalPrestamo')).show();
});
</script>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Registrar pago
    let montoPendienteActual = 0;
    
    document.querySelectorAll('.btn-registrar-pago').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const prestamoId = this.dataset.prestamoId;
            const deudor = this.dataset.deudor;
            const pendiente = parseFloat(this.dataset.pendiente);
            
            montoPendienteActual = pendiente;
            
            document.getElementById('pago_prestamo_id').value = prestamoId;
            document.getElementById('pago_deudor_nombre').textContent = deudor;
            document.getElementById('pago_monto_pendiente').textContent = '<?= APP_CURRENCY_SYMBOL ?>' + pendiente.toFixed(2);
            document.getElementById('monto_pago').max = pendiente;
            document.getElementById('monto_pago').value = '';
            
            new bootstrap.Modal(document.getElementById('modalPago')).show();
        });
    });
    
    // Botón pago completo
    document.getElementById('btnPagoCompleto')?.addEventListener('click', function() {
        document.getElementById('monto_pago').value = montoPendienteActual.toFixed(2);
    });
    
    // Eliminar préstamo
    document.querySelectorAll('.btn-eliminar').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const id = this.dataset.id;
            const nombre = this.dataset.nombre;
            
            Swal.fire({
                title: '¿Eliminar préstamo?',
                html: `Se eliminará el préstamo a <strong>${nombre}</strong> y todo su historial de pagos.<br><br>Selecciona qué pasó con ese dinero:`,
                icon: 'warning',
                showCancelButton: true,
                showDenyButton: true,
                confirmButtonColor: '#198754',
                denyButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Fue devuelto',
                denyButtonText: 'No fue devuelto',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '?delete=' + id + '&devuelto=1';
                } else if (result.isDenied) {
                    window.location.href = '?delete=' + id + '&devuelto=0';
                }
            });
        });
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
