-- ROBOKIT STORE v5
-- Tienda pública + pedidos online + seguimiento + reservas de stock.
-- RECOMENDADO: usar "php artisan migrate" y NO ejecutar este SQL.
-- Este archivo es solo una alternativa manual para MySQL/MariaDB modernos.

ALTER TABLE `producto`
  ADD COLUMN IF NOT EXISTS `stock_reservado` int unsigned NOT NULL DEFAULT 0 AFTER `Stock`;

ALTER TABLE `pedido`
  ADD COLUMN IF NOT EXISTS `canal` varchar(20) NOT NULL DEFAULT 'Mostrador',
  ADD COLUMN IF NOT EXISTS `tipo_entrega` varchar(30) NULL,
  ADD COLUMN IF NOT EXISTS `direccion_entrega` text NULL,
  ADD COLUMN IF NOT EXISTS `notas_cliente` text NULL,
  ADD COLUMN IF NOT EXISTS `codigo_seguimiento` varchar(40) NULL,
  ADD COLUMN IF NOT EXISTS `reserva_aplicada` tinyint(1) NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS `fecha_entregado` timestamp NULL DEFAULT NULL;

UPDATE `pedido` SET `canal`='Mostrador' WHERE `canal` IS NULL OR `canal`='';
UPDATE `pedido` SET `tipo_entrega`='Mostrador' WHERE `tipo_entrega` IS NULL;

-- Crea el índice único solo si todavía no existe.
SET @idx_exists := (
  SELECT COUNT(*)
  FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = 'pedido'
    AND index_name = 'pedido_codigo_seguimiento_unique'
);
SET @idx_sql := IF(
  @idx_exists = 0,
  'CREATE UNIQUE INDEX pedido_codigo_seguimiento_unique ON pedido (codigo_seguimiento)',
  'SELECT 1'
);
PREPARE stmt FROM @idx_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
