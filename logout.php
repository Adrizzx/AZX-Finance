<?php
/**
 * AZX-Finance - Cerrar Sesión
 */
require_once __DIR__ . '/config/config.php';

// Destruir la sesión
session_destroy();

// Redirigir al login
header('Location: login.php');
exit;
