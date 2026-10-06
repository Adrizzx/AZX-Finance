<?php
/**
 * AZX-Finance - API para verificar disponibilidad de email
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../config/database.php';

// Solo permitir POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

$email = trim($_POST['email'] ?? '');

if (empty($email)) {
    echo json_encode(['error' => 'Email requerido']);
    exit;
}

// Validar formato de email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['error' => 'Email inválido', 'available' => false]);
    exit;
}

try {
    $db = getDB();
    
    // Verificar si el email ya existe
    $stmt = $db->prepare("SELECT id FROM usuarios WHERE email = ?");
    $stmt->execute([$email]);
    
    $exists = $stmt->fetch() !== false;
    
    echo json_encode([
        'available' => !$exists,
        'email' => $email
    ]);
} catch (Exception $e) {
    echo json_encode(['error' => 'Error de servidor', 'available' => false]);
}
