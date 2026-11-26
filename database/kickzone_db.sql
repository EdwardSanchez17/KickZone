-- ============================================================================
-- BASE DE DATOS: KICKZONE - CENTRO DEPORTIVO INTEGRAL
-- Proyecto Final Desarrollo Web - Universidad Tecnológica de Panamá
-- Equipo: David Rios, Edward Sanchez, Edgardo Cordoba, Jean Llanten
-- ============================================================================

DROP DATABASE IF EXISTS kickzone_db;
CREATE DATABASE kickzone_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE kickzone_db;

-- 1. TABLA DE ROLES
CREATE TABLE roles (
    id_rol INT AUTO_INCREMENT PRIMARY KEY,
    nombre_rol VARCHAR(30) NOT NULL UNIQUE,
    descripcion VARCHAR(150) NOT NULL
) ENGINE=InnoDB;

-- 2. TABLA DE USUARIOS (Con soporte para bloqueo por fuerza bruta y estados)
CREATE TABLE usuarios (
    id_usuario INT AUTO_INCREMENT PRIMARY KEY,
    id_rol INT NOT NULL,
    nombre VARCHAR(80) NOT NULL,
    apellido VARCHAR(80) NOT NULL,
    correo VARCHAR(120) NOT NULL UNIQUE,
    telefono VARCHAR(20) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    estado ENUM('ACTIVO', 'INACTIVO') NOT NULL DEFAULT 'ACTIVO',
    intentos_fallidos INT NOT NULL DEFAULT 0,
    bloqueado_hasta DATETIME NULL DEFAULT NULL,
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_usuario_rol FOREIGN KEY (id_rol) REFERENCES roles(id_rol) ON UPDATE CASCADE,
    INDEX idx_usuario_correo (correo),
    INDEX idx_usuario_rol (id_rol)
) ENGINE=InnoDB;

-- 3. TABLA DE DEPORTES / DISCIPLINAS
CREATE TABLE deportes (
    id_deporte INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(60) NOT NULL UNIQUE,
    descripcion VARCHAR(200) NOT NULL,
    icono VARCHAR(50) DEFAULT 'fa-futbol'
) ENGINE=InnoDB;

-- 4. TABLA DE CANCHAS (Con tarifa diurna y nocturna)
CREATE TABLE canchas (
    id_cancha INT AUTO_INCREMENT PRIMARY KEY,
    id_deporte INT NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    descripcion TEXT NOT NULL,
    superficie VARCHAR(60) NOT NULL,
    capacidad_jugadores INT NOT NULL,
    tarifa_diurna DECIMAL(8,2) NOT NULL,
    tarifa_nocturna DECIMAL(8,2) NOT NULL,
    hora_inicio_nocturna TIME NOT NULL DEFAULT '18:00:00',
    imagen_url VARCHAR(255) DEFAULT 'assets/img/cancha_default.jpg',
    estado ENUM('DISPONIBLE', 'MANTENIMIENTO', 'INACTIVA') NOT NULL DEFAULT 'DISPONIBLE',
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_cancha_deporte FOREIGN KEY (id_deporte) REFERENCES deportes(id_deporte) ON UPDATE CASCADE,
    INDEX idx_cancha_deporte (id_deporte),
    INDEX idx_cancha_estado (estado),
    INDEX idx_cancha_nombre (nombre)
) ENGINE=InnoDB;

-- 5. TABLA DE IMPLEMENTOS DEPORTIVOS EN ALQUILER
CREATE TABLE implementos (
    id_implemento INT AUTO_INCREMENT PRIMARY KEY,
    id_deporte INT NULL,
    nombre VARCHAR(100) NOT NULL,
    descripcion VARCHAR(200) NOT NULL,
    precio_alquiler DECIMAL(8,2) NOT NULL,
    stock_total INT NOT NULL DEFAULT 10,
    estado ENUM('ACTIVO', 'INACTIVO') NOT NULL DEFAULT 'ACTIVO',
    CONSTRAINT chk_implemento_stock CHECK (stock_total >= 0),
    CONSTRAINT fk_implemento_deporte FOREIGN KEY (id_deporte) REFERENCES deportes(id_deporte) ON DELETE SET NULL,
    INDEX idx_implemento_estado (estado),
    INDEX idx_implemento_nombre (nombre)
) ENGINE=InnoDB;

-- 6. TABLA DE RESERVAS
CREATE TABLE reservas (
    id_reserva INT AUTO_INCREMENT PRIMARY KEY,
    codigo_reserva VARCHAR(20) NOT NULL UNIQUE,
    id_cliente INT NOT NULL,
    id_cancha INT NOT NULL,
    fecha_reserva DATE NOT NULL,
    hora_inicio TIME NOT NULL,
    hora_fin TIME NOT NULL,
    es_tarifa_nocturna TINYINT(1) NOT NULL DEFAULT 0,
    subtotal_cancha DECIMAL(8,2) NOT NULL,
    subtotal_implementos DECIMAL(8,2) NOT NULL DEFAULT 0.00,
    total_pagar DECIMAL(8,2) NOT NULL,
    notas_cliente VARCHAR(255) NULL,
    estado ENUM('PENDIENTE', 'CONFIRMADA', 'COMPLETADA', 'CANCELADA') NOT NULL DEFAULT 'PENDIENTE',
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT chk_reserva_horario CHECK (hora_fin > hora_inicio),
    CONSTRAINT chk_reserva_total CHECK (total_pagar >= 0),
    CONSTRAINT fk_reserva_cliente FOREIGN KEY (id_cliente) REFERENCES usuarios(id_usuario) ON UPDATE CASCADE,
    CONSTRAINT fk_reserva_cancha FOREIGN KEY (id_cancha) REFERENCES canchas(id_cancha) ON UPDATE CASCADE,
    INDEX idx_reserva_fecha_cancha (id_cancha, fecha_reserva, estado),
    INDEX idx_reserva_cliente (id_cliente),
    INDEX idx_reserva_fecha_estado (fecha_reserva, estado)
) ENGINE=InnoDB;

-- 7. TABLA DETALLE DE IMPLEMENTOS ALQUILADOS POR RESERVA
CREATE TABLE reserva_implementos (
    id_detalle INT AUTO_INCREMENT PRIMARY KEY,
    id_reserva INT NOT NULL,
    id_implemento INT NOT NULL,
    cantidad INT NOT NULL DEFAULT 1,
    precio_unitario DECIMAL(8,2) NOT NULL,
    subtotal DECIMAL(8,2) NOT NULL,
    CONSTRAINT chk_detalle_cantidad CHECK (cantidad > 0),
    CONSTRAINT fk_det_reserva FOREIGN KEY (id_reserva) REFERENCES reservas(id_reserva) ON DELETE CASCADE,
    CONSTRAINT fk_det_implemento FOREIGN KEY (id_implemento) REFERENCES implementos(id_implemento) ON UPDATE CASCADE
) ENGINE=InnoDB;

-- 8. TABLA DE PAGOS
CREATE TABLE pagos (
    id_pago INT AUTO_INCREMENT PRIMARY KEY,
    id_reserva INT NOT NULL,
    metodo_pago ENUM('YAPPY', 'EFECTIVO_CANCHA', 'TRANSFERENCIA', 'TARJETA') NOT NULL,
    referencia_pago VARCHAR(80) NULL,
    monto DECIMAL(8,2) NOT NULL,
    estado_pago ENUM('PENDIENTE', 'VERIFICADO', 'REEMBOLSADO') NOT NULL DEFAULT 'PENDIENTE',
    registrado_por INT NULL,
    fecha_pago TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_pago_reserva FOREIGN KEY (id_reserva) REFERENCES reservas(id_reserva) ON DELETE CASCADE,
    CONSTRAINT fk_pago_usuario FOREIGN KEY (registrado_por) REFERENCES usuarios(id_usuario) ON DELETE SET NULL,
    INDEX idx_pago_estado (estado_pago)
) ENGINE=InnoDB;

-- 9. TABLA DE RESEÑAS DE CLIENTES
CREATE TABLE resenas (
    id_resena INT AUTO_INCREMENT PRIMARY KEY,
    id_reserva INT NOT NULL UNIQUE,
    id_cliente INT NOT NULL,
    id_cancha INT NOT NULL,
    calificacion TINYINT NOT NULL CHECK (calificacion BETWEEN 1 AND 5),
    comentario TEXT NOT NULL,
    fecha_resena TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_resena_reserva FOREIGN KEY (id_reserva) REFERENCES reservas(id_reserva) ON DELETE CASCADE,
    CONSTRAINT fk_resena_cliente FOREIGN KEY (id_cliente) REFERENCES usuarios(id_usuario) ON DELETE CASCADE,
    CONSTRAINT fk_resena_cancha FOREIGN KEY (id_cancha) REFERENCES canchas(id_cancha) ON DELETE CASCADE,
    INDEX idx_resena_cancha (id_cancha)
) ENGINE=InnoDB;

-- ============================================================================
-- DATOS DE PRUEBA REALISTAS (KICKZONE PANAMÁ)
-- ============================================================================

INSERT INTO roles (id_rol, nombre_rol, descripcion) VALUES
(1, 'ADMIN', 'Administrador general del centro deportivo con acceso total y reportes.'),
(2, 'ENCARGADO', 'Operador de sede encargado de confirmar reservas, pagos y estado de canchas.'),
(3, 'CLIENTE', 'Usuario final que consulta disponibilidad, reserva canchas y alquila implementos.');

-- Contraseña de prueba para TODAS las cuentas iniciales: KickZone2026*
-- (Generada con password_hash('KickZone2026*', PASSWORD_DEFAULT))
INSERT INTO usuarios (id_rol, nombre, apellido, correo, telefono, password_hash) VALUES
(1, 'David', 'Rios', 'admin@kickzone.pa', '6000-1001', '$2y$10$/oNSI3.dkQvj5G9gARkTRu/MSl6Lv55FBwFGYcvCpxN83/7wQJd46'),
(2, 'Edgardo', 'Cordoba', 'encargado@kickzone.pa', '6000-1002', '$2y$10$/oNSI3.dkQvj5G9gARkTRu/MSl6Lv55FBwFGYcvCpxN83/7wQJd46'),
(3, 'Edward', 'Sanchez', 'edward@correo.pa', '6000-1003', '$2y$10$/oNSI3.dkQvj5G9gARkTRu/MSl6Lv55FBwFGYcvCpxN83/7wQJd46'),
(3, 'Jean', 'Llanten', 'jean@correo.pa', '6000-1004', '$2y$10$/oNSI3.dkQvj5G9gARkTRu/MSl6Lv55FBwFGYcvCpxN83/7wQJd46'),
(3, 'Ana', 'Pérez', 'ana.perez@correo.pa', '6000-1005', '$2y$10$/oNSI3.dkQvj5G9gARkTRu/MSl6Lv55FBwFGYcvCpxN83/7wQJd46'),
(3, 'Carlos', 'Mendoza', 'carlos.mendoza@correo.pa', '6000-1006', '$2y$10$/oNSI3.dkQvj5G9gARkTRu/MSl6Lv55FBwFGYcvCpxN83/7wQJd46'),
(3, 'Lucía', 'Herrera', 'lucia.herrera@correo.pa', '6000-1007', '$2y$10$/oNSI3.dkQvj5G9gARkTRu/MSl6Lv55FBwFGYcvCpxN83/7wQJd46');

INSERT INTO deportes (id_deporte, nombre, descripcion) VALUES
(1, 'Fútbol 5', 'Canchas rápidas de grama sintética FIFA Quality para partidos 5 vs 5.'),
(2, 'Fútbol 7', 'Canchas amplias al aire libre con iluminación LED profesional para 7 vs 7.'),
(3, 'Pádel', 'Pistas panorámicas de cristal templado y césped fibrilado.'),
(4, 'Baloncesto', 'Cancha techada de tabloncillo sintético con aros profesionales.');

INSERT INTO canchas (id_deporte, nombre, descripcion, superficie, capacidad_jugadores, tarifa_diurna, tarifa_nocturna, imagen_url) VALUES
(1, 'Arena KickZone 1 (Techada)', 'Cancha Fútbol 5 techada con grama sintética de última generación y bancas techadas.', 'Grama Sintética', 10, 35.00, 45.00, 'assets/img/futbol5_1.jpg'),
(1, 'Arena KickZone 2 (VIP)', 'Cancha Fútbol 5 con marcador electrónico, iluminación LED anti-deslumbramiento y zona de hidratación.', 'Grama Sintética', 10, 40.00, 50.00, 'assets/img/futbol5_2.jpg'),
(2, 'Estadio Norte Fútbol 7', 'Cancha reglamentaria Fútbol 7 ideal para ligas empresariales y torneos nocturnos.', 'Grama Sintética Pro', 14, 55.00, 70.00, 'assets/img/futbol7_1.jpg'),
(3, 'Pista Pádel Panorámica A', 'Pista de pádel con paredes de vidrio templado de 12mm y reflectores LED.', 'Césped Azul Pádel', 4, 24.00, 32.00, 'assets/img/padel_1.jpg'),
(3, 'Pista Pádel Panorámica B', 'Pista de pádel climatizada lateralmente con superficie de alto rebote.', 'Césped Azul Pádel', 4, 24.00, 32.00, 'assets/img/padel_2.jpg'),
(4, 'Duela Central Baloncesto', 'Cancha techada de baloncesto con tableros de acrílico templado y reloj de 24 segundos.', 'Tabloncillo Sintético', 10, 30.00, 38.00, 'assets/img/basket_1.jpg');

INSERT INTO implementos (id_deporte, nombre, descripcion, precio_alquiler, stock_total) VALUES
(1, 'Balón Profesional Fútbol #5', 'Balón cosido térmicamente de alta presión.', 3.50, 15),
(1, 'Juego de 10 Petos Numerados', 'Petos lavados y desinfectados (5 neón, 5 azul marino).', 4.00, 12),
(1, 'Par de Guantes de Portero', 'Guantes con palma de látex de agarre profesional.', 3.00, 8),
(3, 'Set de 2 Palas de Pádel + Tubo Pelotas', 'Palas de fibra de carbono y 3 pelotas presurizadas.', 8.00, 10),
(4, 'Balón Oficial Baloncesto #7', 'Balón de cuero compuesto para cancha cubierta.', 3.50, 10);

-- ----------------------------------------------------------------------------
-- RESERVAS DE PRUEBA (fechas relativas a hoy para que la agenda siempre tenga datos)
-- Tarifa nocturna desde las 18:00. total_pagar = subtotal_cancha + subtotal_implementos
-- ----------------------------------------------------------------------------
INSERT INTO reservas (id_reserva, codigo_reserva, id_cliente, id_cancha, fecha_reserva, hora_inicio, hora_fin,
                      es_tarifa_nocturna, subtotal_cancha, subtotal_implementos, total_pagar, notas_cliente, estado) VALUES
(1, 'KZ-2026-0001', 3, 1, DATE_SUB(CURDATE(), INTERVAL 10 DAY), '19:00:00', '20:00:00', 1, 45.00, 7.50, 52.50, 'Partido de la liga de la oficina', 'COMPLETADA'),
(2, 'KZ-2026-0002', 4, 4, DATE_SUB(CURDATE(), INTERVAL 7 DAY),  '10:00:00', '11:00:00', 0, 24.00, 8.00, 32.00, NULL, 'COMPLETADA'),
(3, 'KZ-2026-0003', 5, 3, DATE_SUB(CURDATE(), INTERVAL 5 DAY),  '20:00:00', '21:00:00', 1, 70.00, 0.00, 70.00, 'Cumpleaños, llegamos 10 min antes', 'COMPLETADA'),
(4, 'KZ-2026-0004', 6, 6, DATE_SUB(CURDATE(), INTERVAL 3 DAY),  '17:00:00', '18:00:00', 0, 30.00, 3.50, 33.50, NULL, 'CANCELADA'),
(5, 'KZ-2026-0005', 3, 2, CURDATE(), '18:00:00', '19:00:00', 1, 50.00, 4.00, 54.00, NULL, 'CONFIRMADA'),
(6, 'KZ-2026-0006', 7, 1, CURDATE(), '16:00:00', '17:00:00', 0, 35.00, 0.00, 35.00, 'Primera vez en KickZone', 'PENDIENTE'),
(7, 'KZ-2026-0007', 5, 5, CURDATE(), '19:00:00', '20:00:00', 1, 32.00, 8.00, 40.00, NULL, 'PENDIENTE'),
(8, 'KZ-2026-0008', 4, 3, DATE_ADD(CURDATE(), INTERVAL 2 DAY), '21:00:00', '22:00:00', 1, 70.00, 6.50, 76.50, 'Necesitamos guantes de portero', 'CONFIRMADA'),
(9, 'KZ-2026-0009', 6, 6, DATE_ADD(CURDATE(), INTERVAL 4 DAY), '09:00:00', '10:00:00', 0, 30.00, 0.00, 30.00, NULL, 'PENDIENTE');

INSERT INTO reserva_implementos (id_reserva, id_implemento, cantidad, precio_unitario, subtotal) VALUES
(1, 1, 1, 3.50, 3.50),
(1, 2, 1, 4.00, 4.00),
(2, 4, 1, 8.00, 8.00),
(4, 5, 1, 3.50, 3.50),
(5, 2, 1, 4.00, 4.00),
(7, 4, 1, 8.00, 8.00),
(8, 1, 1, 3.50, 3.50),
(8, 3, 1, 3.00, 3.00);

INSERT INTO pagos (id_reserva, metodo_pago, referencia_pago, monto, estado_pago, registrado_por) VALUES
(1, 'YAPPY', 'YP-884512', 52.50, 'VERIFICADO', 2),
(2, 'EFECTIVO_CANCHA', NULL, 32.00, 'VERIFICADO', 2),
(3, 'TARJETA', 'VISA-****4821', 70.00, 'VERIFICADO', 2),
(4, 'TRANSFERENCIA', 'BG-TR-55120', 33.50, 'REEMBOLSADO', 1),
(5, 'YAPPY', 'YP-901233', 54.00, 'VERIFICADO', 2),
(6, 'TRANSFERENCIA', 'BG-TR-56001', 35.00, 'PENDIENTE', NULL),
(8, 'YAPPY', 'YP-902877', 76.50, 'PENDIENTE', NULL);

-- Reseñas: solo sobre reservas COMPLETADAS (regla del módulo de reseñas verificadas)
INSERT INTO resenas (id_reserva, id_cliente, id_cancha, calificacion, comentario) VALUES
(1, 3, 1, 5, 'Grama en excelente estado y la iluminación nocturna es muy buena.'),
(2, 4, 4, 4, 'Pista muy limpia. Las palas alquiladas estaban en buen estado.'),
(3, 5, 3, 5, 'Perfecta para torneos, los camerinos impecables.');

