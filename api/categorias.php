<?php
/**
 * AZX-Finance - API para gestión de categorías
 * Permite agregar nuevas categorías personalizadas con validación de duplicados
 */

header('Content-Type: application/json');
session_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';

// Verificar sesión
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$userId = $_SESSION['user_id'];
$db = getDB();

// Solo permitir POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'add_categoria_ingreso':
        handleAddCategoriaIngreso($db, $userId);
        break;
    case 'add_categoria_gasto':
        handleAddCategoriaGasto($db, $userId);
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Acción no válida']);
}

/**
 * Agregar nueva categoría de ingreso
 */
function handleAddCategoriaIngreso($db, $userId) {
    $nombre = trim($_POST['nombre'] ?? '');
    $icono = trim($_POST['icono'] ?? 'fa-tag');
    $color = trim($_POST['color'] ?? '#28a745');
    
    // Validaciones
    if (empty($nombre)) {
        echo json_encode(['success' => false, 'message' => 'El nombre es obligatorio']);
        return;
    }
    
    if (strlen($nombre) < 2) {
        echo json_encode(['success' => false, 'message' => 'El nombre debe tener al menos 2 caracteres']);
        return;
    }
    
    if (strlen($nombre) > 100) {
        echo json_encode(['success' => false, 'message' => 'El nombre no puede exceder 100 caracteres']);
        return;
    }
    
    // Verificar duplicados (tanto en categorías globales como del usuario)
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM categorias_ingresos 
        WHERE LOWER(nombre) = LOWER(?) 
        AND (usuario_id IS NULL OR usuario_id = ?)
    ");
    $stmt->execute([$nombre, $userId]);
    $result = $stmt->fetch();
    
    if ($result['count'] > 0) {
        echo json_encode(['success' => false, 'message' => 'Ya existe una categoría con ese nombre']);
        return;
    }
    
    // Insertar categoría
    try {
        $stmt = $db->prepare("
            INSERT INTO categorias_ingresos (usuario_id, nombre, icono, color, activo)
            VALUES (?, ?, ?, ?, 1)
        ");
        $stmt->execute([$userId, $nombre, $icono, $color]);
        
        $newId = $db->lastInsertId();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Categoría creada correctamente',
            'categoria' => [
                'id' => $newId,
                'nombre' => $nombre,
                'icono' => $icono,
                'color' => $color
            ]
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error al crear la categoría']);
    }
}

/**
 * Agregar nueva categoría de gasto
 */
function handleAddCategoriaGasto($db, $userId) {
    $nombre = trim($_POST['nombre'] ?? '');
    $icono = trim($_POST['icono'] ?? 'fa-tag');
    $color = trim($_POST['color'] ?? '#dc3545');
    $limite = floatval($_POST['limite_mensual'] ?? 0);
    
    // Validaciones
    if (empty($nombre)) {
        echo json_encode(['success' => false, 'message' => 'El nombre es obligatorio']);
        return;
    }
    
    if (strlen($nombre) < 2) {
        echo json_encode(['success' => false, 'message' => 'El nombre debe tener al menos 2 caracteres']);
        return;
    }
    
    if (strlen($nombre) > 100) {
        echo json_encode(['success' => false, 'message' => 'El nombre no puede exceder 100 caracteres']);
        return;
    }
    
    if ($limite < 0) {
        echo json_encode(['success' => false, 'message' => 'El límite no puede ser negativo']);
        return;
    }
    
    // Verificar duplicados
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM categorias_gastos 
        WHERE LOWER(nombre) = LOWER(?) 
        AND (usuario_id IS NULL OR usuario_id = ?)
    ");
    $stmt->execute([$nombre, $userId]);
    $result = $stmt->fetch();
    
    if ($result['count'] > 0) {
        echo json_encode(['success' => false, 'message' => 'Ya existe una categoría con ese nombre']);
        return;
    }
    
    // Insertar categoría
    try {
        $stmt = $db->prepare("
            INSERT INTO categorias_gastos (usuario_id, nombre, icono, color, limite_mensual, activo)
            VALUES (?, ?, ?, ?, ?, 1)
        ");
        $stmt->execute([$userId, $nombre, $icono, $color, $limite > 0 ? $limite : null]);
        
        $newId = $db->lastInsertId();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Categoría creada correctamente',
            'categoria' => [
                'id' => $newId,
                'nombre' => $nombre,
                'icono' => $icono,
                'color' => $color,
                'limite_mensual' => $limite > 0 ? $limite : null
            ]
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error al crear la categoría']);
    }
}
