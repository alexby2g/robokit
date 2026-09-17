-- ROBOKIT v5.1 - alternativa manual a la migración Laravel.
-- IMPORTANTE: usa ESTE SQL O `php artisan migrate`, nunca ambos.
-- Está pensado para aplicarse UNA sola vez sobre una base que ya tiene ROBOKIT v5.

ALTER TABLE users
  ADD COLUMN role VARCHAR(20) NOT NULL DEFAULT 'cliente' AFTER password,
  ADD COLUMN usuario_id INT UNSIGNED NULL AFTER role,
  ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER usuario_id,
  ADD UNIQUE KEY uq_users_usuario_id (usuario_id);

ALTER TABLE usuario
  ADD COLUMN Email VARCHAR(190) NULL AFTER Telefono,
  ADD UNIQUE KEY uq_usuario_email (Email);

ALTER TABLE producto
  ADD COLUMN slug VARCHAR(180) NULL AFTER Nombre,
  ADD COLUMN precio_anterior DECIMAL(10,2) NULL AFTER Precio,
  ADD COLUMN estado_publicacion VARCHAR(20) NOT NULL DEFAULT 'Publicado' AFTER Descripcion,
  ADD COLUMN destacado TINYINT(1) NOT NULL DEFAULT 0 AFTER estado_publicacion,
  ADD COLUMN orden_catalogo INT NOT NULL DEFAULT 0 AFTER destacado;

UPDATE producto SET slug = CONCAT('producto-', id) WHERE slug IS NULL OR slug = '';
ALTER TABLE producto ADD UNIQUE KEY uq_producto_slug (slug);

ALTER TABLE imagenes
  ADD COLUMN es_principal TINYINT(1) NOT NULL DEFAULT 0 AFTER id_producto,
  ADD COLUMN orden INT NOT NULL DEFAULT 0 AFTER es_principal;

UPDATE imagenes i
JOIN (
  SELECT id_producto, MIN(id) AS principal_id
  FROM imagenes
  WHERE id_producto IS NOT NULL
  GROUP BY id_producto
) p ON p.id_producto = i.id_producto
SET i.es_principal = IF(i.id = p.principal_id, 1, 0);

CREATE TABLE catalogo_config (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre_tienda VARCHAR(120) NOT NULL DEFAULT 'ROBOKIT STORE',
  subtitulo VARCHAR(180) NOT NULL DEFAULT 'Robótica, kits y componentes',
  hero_titulo VARCHAR(220) NOT NULL DEFAULT 'Robótica lista para tu próximo proyecto.',
  hero_texto TEXT NULL,
  whatsapp VARCHAR(30) NULL,
  mostrar_stock TINYINT(1) NOT NULL DEFAULT 1,
  delivery_habilitado TINYINT(1) NOT NULL DEFAULT 1,
  recojo_habilitado TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NULL DEFAULT NULL,
  updated_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO catalogo_config
(id, nombre_tienda, subtitulo, hero_titulo, hero_texto, mostrar_stock, delivery_habilitado, recojo_habilitado, created_at, updated_at)
VALUES
(1, 'ROBOKIT STORE', 'Robótica, kits y componentes', 'Robótica lista para tu próximo proyecto.', 'Explora kits, placas, sensores y componentes. Haz tu pedido y sigue el estado desde la web.', 1, 1, 1, NOW(), NOW());
