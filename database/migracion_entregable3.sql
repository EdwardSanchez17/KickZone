-- ============================================================================
-- KICKZONE - Migración para el Entregable 3
-- Aplica sobre una base kickzone_db ya creada con el script del Entregable 2,
-- SIN borrar datos. Ejecutar UNA sola vez (compatible con MySQL 8 y MariaDB).
-- Para una instalación nueva basta con importar kickzone_db.sql
-- ============================================================================
USE kickzone_db;

-- Fecha de la última modificación de cada reserva (trazabilidad de cambios de estado)
ALTER TABLE reservas
    ADD COLUMN fecha_actualizacion TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER fecha_creacion;

-- Índice para la agenda y los filtros del encargado (por fecha y estado)
CREATE INDEX idx_reserva_fecha_estado ON reservas (fecha_reserva, estado);

-- Reglas de integridad del motor de reservas
ALTER TABLE reservas ADD CONSTRAINT chk_reserva_horario CHECK (hora_fin > hora_inicio);
ALTER TABLE reservas ADD CONSTRAINT chk_reserva_total CHECK (total_pagar >= 0);
ALTER TABLE reserva_implementos ADD CONSTRAINT chk_detalle_cantidad CHECK (cantidad > 0);
ALTER TABLE implementos ADD CONSTRAINT chk_implemento_stock CHECK (stock_total >= 0);
