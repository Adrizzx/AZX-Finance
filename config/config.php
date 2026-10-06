<?php
/**
 * AZX-Finance - Configuración General
 */

// Zona horaria
date_default_timezone_set('America/Bogota');

// URL base del proyecto
define('BASE_URL', 'http://localhost/AZX-Finance/');

// Rutas del sistema
define('ROOT_PATH', dirname(__DIR__) . '/');
define('INCLUDES_PATH', ROOT_PATH . 'includes/');
define('ASSETS_PATH', ROOT_PATH . 'assets/');
define('UPLOADS_PATH', ROOT_PATH . 'uploads/');

// Configuración de la aplicación
define('APP_NAME', 'AZX-Finance');
define('APP_VERSION', '1.0.0');
define('APP_CURRENCY', 'USD');
define('APP_CURRENCY_SYMBOL', '$');
define('MAX_PROFILES_DISPLAY', 5);

// Obtener ID de usuario de la sesión
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

// Verificar si hay sesión activa
function isLoggedIn() {
    return isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0;
}

// Requerir login
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . 'login.php');
        exit;
    }
}

// Constante para compatibilidad (se sobreescribe con sesión)
define('DEFAULT_USER_ID', 1);

// Configuración de sesiones
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Incluir conexión a base de datos
require_once __DIR__ . '/database.php';

// Función para formatear moneda
function formatMoney($amount, $symbol = APP_CURRENCY_SYMBOL) {
    return $symbol . number_format($amount, 2, '.', ',');
}

// Función para formatear fecha
function formatDate($date, $format = 'd/m/Y') {
    return date($format, strtotime($date));
}

// Función para respuestas JSON
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// Función para sanitizar entrada
function sanitize($input) {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

// Función para validar fecha
function isValidDate($date, $format = 'Y-m-d') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}

// Función para obtener meses en español
function getMonthName($monthNumber) {
    $months = [
        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
        5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
        9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
    ];
    return $months[$monthNumber] ?? '';
}

// Colores para gráficas
define('CHART_COLORS', [
    '#007bff', '#28a745', '#dc3545', '#ffc107', '#17a2b8',
    '#6f42c1', '#e83e8c', '#fd7e14', '#20c997', '#6c757d'
]);
?>
