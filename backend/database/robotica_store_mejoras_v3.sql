-- ROBOKIT STORE / Sistema de Robotica - mejora de flujo v3
-- Ejecutar SOLO si no se usará: php artisan migrate

CREATE TABLE IF NOT EXISTS `compra` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `proveedor` varchar(160) NOT NULL,
  `nro_documento` varchar(80) NULL,
  `Total` decimal(12,2) NOT NULL DEFAULT 0,
  `Fecha` date NULL,
  `observacion` text NULL,
  `created_at` timestamp NULL,
  `updated_at` timestamp NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `compra_producto` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_compra` bigint unsigned NOT NULL,
  `id_producto` int NOT NULL,
  `cantidad` int unsigned NOT NULL,
  `costo_unitario` decimal(12,2) NOT NULL,
  `subtotal` decimal(12,2) NOT NULL,
  `created_at` timestamp NULL,
  `updated_at` timestamp NULL,
  PRIMARY KEY (`id`),
  KEY `idx_compra_producto_compra` (`id_compra`),
  KEY `idx_compra_producto_producto` (`id_producto`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Las siguientes columnas son necesarias para historial de precio y reversión de stock.
-- En MySQL 8+, IF NOT EXISTS es soportado para ADD COLUMN en versiones recientes.
-- Si tu versión no lo soporta, usa la migración Laravel incluida.
ALTER TABLE `pedido_producto` ADD COLUMN IF NOT EXISTS `precio_unitario` decimal(12,2) NULL;
ALTER TABLE `pedido_producto` ADD COLUMN IF NOT EXISTS `subtotal` decimal(12,2) NULL;
ALTER TABLE `pedido` ADD COLUMN IF NOT EXISTS `stock_aplicado` tinyint(1) NOT NULL DEFAULT 0;

UPDATE `pedido` SET `stock_aplicado` = 1 WHERE LOWER(`Estado`) <> 'cancelado';
