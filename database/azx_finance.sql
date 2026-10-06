-- =====================================================
-- AZX-FINANCE - BASE DE DATOS COMPLETA
-- Sistema de Gestión de Finanzas Personales
-- Con Sistema de Perfiles estilo Streaming
-- Fecha: 2026
-- =====================================================

-- Nota para hosting compartido (InfinityFree):
-- La base ya debe existir y estar seleccionada en phpMyAdmin.
-- No ejecutar CREATE DATABASE ni USE porque no hay privilegios.

-- =====================================================
-- TABLA: usuarios (Sistema de Perfiles)
-- =====================================================
CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,  -- Contraseña hasheada con password_hash()
    avatar VARCHAR(255) DEFAULT 'default.png',
    color_perfil VARCHAR(20) DEFAULT '#ff6b00',
    moneda_principal VARCHAR(10) DEFAULT 'USD',
    fecha_registro DATETIME DEFAULT CURRENT_TIMESTAMP,
    ultimo_acceso DATETIME DEFAULT NULL,
    activo TINYINT(1) DEFAULT 1,
    INDEX idx_ultimo_acceso (ultimo_acceso DESC)
) ENGINE=InnoDB;

-- Perfiles de ejemplo con contraseñas hasheadas (password: 1234)
-- El hash corresponde a password_hash('1234', PASSWORD_DEFAULT)
INSERT INTO usuarios (nombre, email, password, avatar, color_perfil, moneda_principal, ultimo_acceso) VALUES 
('Usuario 1', 'usuario1@azxfinance.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'default.png', '#ff6b00', 'USD', NOW()),
('Usuario 2', 'usuario2@azxfinance.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'default.png', '#6f42c1', 'USD', DATE_SUB(NOW(), INTERVAL 1 DAY)),
('Usuario 3', 'usuario3@azxfinance.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'default.png', '#17a2b8', 'USD', DATE_SUB(NOW(), INTERVAL 2 DAY));

-- =====================================================
-- TABLA: categorias_ingresos
-- =====================================================
CREATE TABLE IF NOT EXISTS categorias_ingresos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT DEFAULT NULL,
    nombre VARCHAR(100) NOT NULL,
    icono VARCHAR(50) DEFAULT 'fa-money-bill',
    color VARCHAR(20) DEFAULT '#28a745',
    activo TINYINT(1) DEFAULT 1,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Categorías de ingresos por defecto (usuario_id NULL = disponible para todos)
INSERT INTO categorias_ingresos (usuario_id, nombre, icono, color) VALUES 
(NULL, 'Salario', 'fa-briefcase', '#28a745'),
(NULL, 'Freelance', 'fa-laptop-code', '#17a2b8'),
(NULL, 'Inversiones', 'fa-chart-line', '#6f42c1'),
(NULL, 'Regalo', 'fa-gift', '#e83e8c'),
(NULL, 'Venta', 'fa-tags', '#fd7e14'),
(NULL, 'Reembolso', 'fa-undo', '#20c997'),
(NULL, 'Otro', 'fa-ellipsis-h', '#6c757d');

-- =====================================================
-- TABLA: categorias_gastos
-- =====================================================
CREATE TABLE IF NOT EXISTS categorias_gastos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT DEFAULT NULL,
    nombre VARCHAR(100) NOT NULL,
    icono VARCHAR(50) DEFAULT 'fa-shopping-cart',
    color VARCHAR(20) DEFAULT '#dc3545',
    limite_mensual DECIMAL(15,2) DEFAULT NULL,
    activo TINYINT(1) DEFAULT 1,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Categorías de gastos por defecto (usuario_id NULL = disponible para todos)
INSERT INTO categorias_gastos (usuario_id, nombre, icono, color, limite_mensual) VALUES 
(NULL, 'Alimentación', 'fa-utensils', '#dc3545', 300.00),
(NULL, 'Transporte', 'fa-car', '#fd7e14', 150.00),
(NULL, 'Vivienda', 'fa-home', '#6f42c1', 800.00),
(NULL, 'Servicios', 'fa-bolt', '#ffc107', 200.00),
(NULL, 'Salud', 'fa-heartbeat', '#e83e8c', 100.00),
(NULL, 'Entretenimiento', 'fa-gamepad', '#17a2b8', 100.00),
(NULL, 'Educación', 'fa-graduation-cap', '#28a745', 150.00),
(NULL, 'Ropa', 'fa-tshirt', '#6610f2', 100.00),
(NULL, 'Tecnología', 'fa-mobile-alt', '#20c997', 100.00),
(NULL, 'Suscripciones', 'fa-tv', '#007bff', 50.00),
(NULL, 'Restaurantes', 'fa-hamburger', '#ff6b6b', 80.00),
(NULL, 'Otro', 'fa-ellipsis-h', '#6c757d', NULL);

-- =====================================================
-- TABLA: ingresos
-- =====================================================
CREATE TABLE IF NOT EXISTS ingresos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    categoria_id INT NOT NULL,
    monto DECIMAL(15,2) NOT NULL,
    descripcion VARCHAR(255),
    fecha DATE NOT NULL,
    es_recurrente TINYINT(1) DEFAULT 0,
    frecuencia ENUM('diario', 'semanal', 'quincenal', 'mensual', 'anual') DEFAULT NULL,
    referencia_tipo VARCHAR(50) DEFAULT NULL,
    referencia_id INT DEFAULT NULL,
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (categoria_id) REFERENCES categorias_ingresos(id),
    INDEX idx_fecha (fecha),
    INDEX idx_usuario_fecha (usuario_id, fecha),
    INDEX idx_referencia (referencia_tipo, referencia_id)
) ENGINE=InnoDB;

-- =====================================================
-- TABLA: gastos
-- =====================================================
CREATE TABLE IF NOT EXISTS gastos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    categoria_id INT NOT NULL,
    monto DECIMAL(15,2) NOT NULL,
    descripcion VARCHAR(255),
    fecha DATE NOT NULL,
    etiquetas VARCHAR(255) DEFAULT NULL,
    es_recurrente TINYINT(1) DEFAULT 0,
    frecuencia ENUM('diario', 'semanal', 'quincenal', 'mensual', 'anual') DEFAULT NULL,
    referencia_tipo VARCHAR(50) DEFAULT NULL,
    referencia_id INT DEFAULT NULL,
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (categoria_id) REFERENCES categorias_gastos(id),
    INDEX idx_fecha (fecha),
    INDEX idx_usuario_fecha (usuario_id, fecha),
    INDEX idx_referencia (referencia_tipo, referencia_id)
) ENGINE=InnoDB;

-- =====================================================
-- TABLA: metas_ahorro
-- =====================================================
CREATE TABLE IF NOT EXISTS metas_ahorro (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    nombre VARCHAR(150) NOT NULL,
    descripcion TEXT,
    monto_objetivo DECIMAL(15,2) NOT NULL,
    monto_actual DECIMAL(15,2) DEFAULT 0.00,
    fecha_inicio DATE NOT NULL,
    fecha_limite DATE,
    icono VARCHAR(50) DEFAULT 'fa-bullseye',
    color VARCHAR(20) DEFAULT '#007bff',
    prioridad INT DEFAULT 0,
    estado ENUM('activa', 'completada', 'cancelada') DEFAULT 'activa',
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================================
-- TABLA: aportes_metas (historial de aportes a metas)
-- =====================================================
CREATE TABLE IF NOT EXISTS aportes_metas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    meta_id INT NOT NULL,
    monto DECIMAL(15,2) NOT NULL,
    nota VARCHAR(255),
    fecha DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (meta_id) REFERENCES metas_ahorro(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================================
-- TABLA: bolsillos_ahorro (cuentas de ahorro separadas)
-- =====================================================
CREATE TABLE IF NOT EXISTS bolsillos_ahorro (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    descripcion VARCHAR(255),
    saldo DECIMAL(15,2) DEFAULT 0.00,
    meta_monto DECIMAL(15,2) DEFAULT NULL,
    icono VARCHAR(50) DEFAULT 'fa-piggy-bank',
    color VARCHAR(20) DEFAULT '#28a745',
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    activo TINYINT(1) DEFAULT 1,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================================
-- TABLA: movimientos_bolsillos
-- =====================================================
CREATE TABLE IF NOT EXISTS movimientos_bolsillos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bolsillo_id INT NOT NULL,
    tipo ENUM('deposito', 'retiro', 'transferencia_entrada', 'transferencia_salida') NOT NULL,
    monto DECIMAL(15,2) NOT NULL,
    descripcion VARCHAR(255),
    bolsillo_destino_id INT DEFAULT NULL,
    fecha DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (bolsillo_id) REFERENCES bolsillos_ahorro(id) ON DELETE CASCADE,
    FOREIGN KEY (bolsillo_destino_id) REFERENCES bolsillos_ahorro(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- =====================================================
-- TABLA: presupuestos_mensuales
-- =====================================================
CREATE TABLE IF NOT EXISTS presupuestos_mensuales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    categoria_gasto_id INT NOT NULL,
    monto_presupuestado DECIMAL(15,2) NOT NULL,
    mes INT NOT NULL,
    anio INT NOT NULL,
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (categoria_gasto_id) REFERENCES categorias_gastos(id),
    UNIQUE KEY unique_presupuesto (usuario_id, categoria_gasto_id, mes, anio)
) ENGINE=InnoDB;

-- =====================================================
-- TABLA: tipos_inversion
-- =====================================================
CREATE TABLE IF NOT EXISTS tipos_inversion (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    icono VARCHAR(50) DEFAULT 'fa-chart-pie',
    color VARCHAR(20) DEFAULT '#6f42c1',
    riesgo ENUM('bajo', 'medio', 'alto', 'muy_alto') DEFAULT 'medio',
    descripcion TEXT
) ENGINE=InnoDB;

INSERT INTO tipos_inversion (nombre, icono, color, riesgo, descripcion) VALUES
('CDT', 'fa-university', '#28a745', 'bajo', 'Certificado de Depósito a Término'),
('Acciones', 'fa-chart-line', '#007bff', 'alto', 'Acciones de empresas'),
('ETF', 'fa-layer-group', '#17a2b8', 'medio', 'Fondos cotizados'),
('Criptomonedas', 'fa-bitcoin', '#ffc107', 'muy_alto', 'Bitcoin, Ethereum, etc.'),
('Fondos de Inversión', 'fa-chart-pie', '#6f42c1', 'medio', 'Fondos mutuos'),
('Finca Raíz', 'fa-building', '#fd7e14', 'medio', 'Bienes inmuebles'),
('Bonos', 'fa-file-contract', '#20c997', 'bajo', 'Bonos gubernamentales o corporativos'),
('Otro', 'fa-ellipsis-h', '#6c757d', 'medio', 'Otro tipo de inversión');

-- =====================================================
-- TABLA: inversiones
-- =====================================================
CREATE TABLE IF NOT EXISTS inversiones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    tipo_id INT NOT NULL,
    nombre VARCHAR(150) NOT NULL,
    descripcion TEXT,
    monto_invertido DECIMAL(15,2) NOT NULL,
    monto_actual DECIMAL(15,2) NOT NULL,
    tasa_interes_anual DECIMAL(5,2) DEFAULT NULL,
    fecha_inicio DATE NOT NULL,
    fecha_vencimiento DATE DEFAULT NULL,
    plataforma VARCHAR(100) DEFAULT NULL,
    estado ENUM('activa', 'vendida', 'vencida', 'pausada') DEFAULT 'activa',
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (tipo_id) REFERENCES tipos_inversion(id)
) ENGINE=InnoDB;

-- =====================================================
-- TABLA: deudas
-- =====================================================
CREATE TABLE IF NOT EXISTS deudas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    nombre VARCHAR(150) NOT NULL,
    descripcion TEXT,
    monto_total DECIMAL(15,2) NOT NULL,
    monto_pagado DECIMAL(15,2) DEFAULT 0,
    tasa_interes DECIMAL(5,2) DEFAULT 0,
    cuota_mensual DECIMAL(15,2) DEFAULT NULL,
    fecha_inicio DATE NOT NULL,
    fecha_vencimiento DATE DEFAULT NULL,
    acreedor VARCHAR(100) DEFAULT NULL,
    tipo ENUM('tarjeta_credito', 'prestamo_personal', 'hipoteca', 'vehiculo', 'educativo', 'otro') DEFAULT 'otro',
    estado ENUM('activa', 'pagada', 'atrasada', 'en_negociacion') DEFAULT 'activa',
    prioridad INT DEFAULT 0,
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================================
-- TABLA: pagos_deudas
-- =====================================================
CREATE TABLE IF NOT EXISTS pagos_deudas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    deuda_id INT NOT NULL,
    monto DECIMAL(15,2) NOT NULL,
    fecha DATE NOT NULL,
    nota VARCHAR(255),
    fecha_registro DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (deuda_id) REFERENCES deudas(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================================
-- TABLA: recordatorios
-- =====================================================
CREATE TABLE IF NOT EXISTS recordatorios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    titulo VARCHAR(150) NOT NULL,
    descripcion TEXT,
    monto DECIMAL(15,2) DEFAULT NULL,
    fecha_recordatorio DATE NOT NULL,
    hora_recordatorio TIME DEFAULT '09:00:00',
    tipo ENUM('pago', 'cobro', 'ahorro', 'inversion', 'otro') DEFAULT 'otro',
    repetir ENUM('nunca', 'diario', 'semanal', 'mensual', 'anual') DEFAULT 'nunca',
    completado TINYINT(1) DEFAULT 0,
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================================
-- TABLA: configuracion_usuario
-- =====================================================
CREATE TABLE IF NOT EXISTS configuracion_usuario (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL UNIQUE,
    tema ENUM('claro', 'oscuro', 'auto') DEFAULT 'claro',
    idioma VARCHAR(10) DEFAULT 'es',
    notificaciones_email TINYINT(1) DEFAULT 1,
    notificaciones_push TINYINT(1) DEFAULT 1,
    primer_dia_semana ENUM('domingo', 'lunes') DEFAULT 'lunes',
    formato_fecha VARCHAR(20) DEFAULT 'd/m/Y',
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================================
-- TABLA: historial_saldo (para gráficas de evolución)
-- =====================================================
CREATE TABLE IF NOT EXISTS historial_saldo (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    fecha DATE NOT NULL,
    saldo_total DECIMAL(15,2) NOT NULL,
    ingresos_dia DECIMAL(15,2) DEFAULT 0,
    gastos_dia DECIMAL(15,2) DEFAULT 0,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    UNIQUE KEY unique_usuario_fecha (usuario_id, fecha)
) ENGINE=InnoDB;

-- =====================================================
-- TABLA: monedas (para conversor)
-- =====================================================
CREATE TABLE IF NOT EXISTS monedas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(10) NOT NULL UNIQUE,
    nombre VARCHAR(100) NOT NULL,
    simbolo VARCHAR(10) NOT NULL,
    tasa_cambio_usd DECIMAL(15,6) NOT NULL DEFAULT 1,
    fecha_actualizacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    activa TINYINT(1) DEFAULT 1
) ENGINE=InnoDB;

INSERT INTO monedas (codigo, nombre, simbolo, tasa_cambio_usd) VALUES
('USD', 'Dólar Estadounidense', '$', 1.000000),
('EUR', 'Euro', '€', 0.920000),
('COP', 'Peso Colombiano', '$', 4150.000000),
('MXN', 'Peso Mexicano', '$', 17.150000),
('ARS', 'Peso Argentino', '$', 875.000000),
('BRL', 'Real Brasileño', 'R$', 4.970000),
('CLP', 'Peso Chileno', '$', 980.000000),
('PEN', 'Sol Peruano', 'S/', 3.720000),
('GBP', 'Libra Esterlina', '£', 0.790000),
('JPY', 'Yen Japonés', '¥', 149.500000);

-- =====================================================
-- VISTAS ÚTILES
-- =====================================================

-- En InfinityFree no hay privilegios para CREATE VIEW.
-- Estas vistas se omiten en despliegue gratuito compartido.

-- =====================================================
-- ÍNDICES ADICIONALES
-- =====================================================
CREATE INDEX idx_gastos_categoria ON gastos(categoria_id);
CREATE INDEX idx_ingresos_categoria ON ingresos(categoria_id);
CREATE INDEX idx_inversiones_tipo ON inversiones(tipo_id);
CREATE INDEX idx_deudas_estado ON deudas(estado);
CREATE INDEX idx_recordatorios_fecha ON recordatorios(fecha_recordatorio);

-- =====================================================
-- TABLA: prestamos (Dinero prestado a otras personas)
-- =====================================================
CREATE TABLE IF NOT EXISTS prestamos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    deudor_nombre VARCHAR(150) NOT NULL,
    deudor_telefono VARCHAR(20) DEFAULT NULL,
    deudor_email VARCHAR(150) DEFAULT NULL,
    monto_original DECIMAL(15,2) NOT NULL,
    monto_pendiente DECIMAL(15,2) NOT NULL,
    interes_porcentaje DECIMAL(5,2) DEFAULT 0,
    descripcion TEXT,
    fecha_prestamo DATE NOT NULL,
    fecha_vencimiento DATE DEFAULT NULL,
    estado ENUM('pendiente', 'parcial', 'pagado', 'vencido') DEFAULT 'pendiente',
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================================
-- TABLA: pagos_prestamos (Pagos/abonos recibidos)
-- =====================================================
CREATE TABLE IF NOT EXISTS pagos_prestamos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    prestamo_id INT NOT NULL,
    monto DECIMAL(15,2) NOT NULL,
    fecha_pago DATE NOT NULL,
    metodo_pago VARCHAR(50) DEFAULT 'efectivo',
    notas VARCHAR(255) DEFAULT NULL,
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (prestamo_id) REFERENCES prestamos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE INDEX idx_prestamos_estado ON prestamos(estado);
CREATE INDEX idx_prestamos_deudor ON prestamos(deudor_nombre);
CREATE INDEX idx_pagos_prestamo ON pagos_prestamos(prestamo_id);
