-- =====================================================================
--  Миграция: корзина и покупка фильмов
--  Добавляет:
--    * цену тайтла (0 = доступен только по подписке);
--    * тип заказа (подписка / покупка фильмов);
--    * таблицу корзины, позиции заказа и купленные фильмы.
--  Применяется один раз: mysql illuminos < database/migrations/2026_11_cart.sql
-- =====================================================================

SET NAMES utf8mb4;

-- 1. Цена фильма для покупки навсегда
ALTER TABLE titles
    ADD COLUMN price DECIMAL(8,2) NOT NULL DEFAULT 0.00 COMMENT 'Цена покупки, ₽ (0 — только подписка)'
    AFTER rating;

-- 2. Тип заказа и необязательный тариф (заказ фильмов тариф не содержит)
ALTER TABLE payments
    ADD COLUMN kind ENUM('subscription','films') NOT NULL DEFAULT 'subscription' AFTER order_number;

ALTER TABLE payments
    MODIFY COLUMN tariff_id INT UNSIGNED NULL;

-- 3. Корзина пользователя
CREATE TABLE IF NOT EXISTS cart_items (
    id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id   INT UNSIGNED NOT NULL,
    title_id  INT UNSIGNED NOT NULL,
    quantity  TINYINT UNSIGNED NOT NULL DEFAULT 1,
    added_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_cart_user_title (user_id, title_id),
    CONSTRAINT fk_cart_user  FOREIGN KEY (user_id)  REFERENCES users (id)  ON DELETE CASCADE,
    CONSTRAINT fk_cart_title FOREIGN KEY (title_id) REFERENCES titles (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Корзина: фильмы, которые пользователь собирается купить';

-- 4. Позиции заказа (фиксируют название и цену на момент покупки)
CREATE TABLE IF NOT EXISTS order_items (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    payment_id INT UNSIGNED NOT NULL,
    title_id   INT UNSIGNED DEFAULT NULL,
    title_name VARCHAR(200) NOT NULL,
    price      DECIMAL(8,2) NOT NULL,
    quantity   TINYINT UNSIGNED NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    KEY idx_order_items_payment (payment_id),
    CONSTRAINT fk_oi_payment FOREIGN KEY (payment_id) REFERENCES payments (id) ON DELETE CASCADE,
    CONSTRAINT fk_oi_title   FOREIGN KEY (title_id)   REFERENCES titles (id)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Состав заказа: купленные фильмы';

-- 5. Купленные фильмы — дают доступ к просмотру без подписки
CREATE TABLE IF NOT EXISTS purchases (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id      INT UNSIGNED NOT NULL,
    title_id     INT UNSIGNED NOT NULL,
    payment_id   INT UNSIGNED DEFAULT NULL,
    purchased_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_purchase_user_title (user_id, title_id),
    CONSTRAINT fk_pur_user    FOREIGN KEY (user_id)    REFERENCES users (id)    ON DELETE CASCADE,
    CONSTRAINT fk_pur_title   FOREIGN KEY (title_id)   REFERENCES titles (id)   ON DELETE CASCADE,
    CONSTRAINT fk_pur_payment FOREIGN KEY (payment_id) REFERENCES payments (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Фильмы, купленные навсегда';

-- 6. Цены для части каталога (остальные — только по подписке)
UPDATE titles SET price = 399.00 WHERE slug = 'avatar';
UPDATE titles SET price = 449.00 WHERE slug = 'proekt-konec-sveta';
UPDATE titles SET price = 349.00 WHERE slug = 'interstellar';
UPDATE titles SET price = 299.00 WHERE slug = 'pobeg-iz-shoushenka';
UPDATE titles SET price = 299.00 WHERE slug = 'na-zapadnom-fronte';
UPDATE titles SET price = 349.00 WHERE slug = 'piraty-karibskogo-morya';
UPDATE titles SET price = 249.00 WHERE slug = 'wall-e';
UPDATE titles SET price = 249.00 WHERE slug = 'vechnoe-siyanie';
UPDATE titles SET price = 199.00 WHERE slug = '500-dney-leta';
UPDATE titles SET price = 199.00 WHERE slug = 'most-v-terabitiyu';
UPDATE titles SET price = 199.00 WHERE slug = 'znakomtes-dzho-blek';
UPDATE titles SET price = 249.00 WHERE slug = 'levsha';
