-- =====================================================================
--  Illuminos — схема базы данных онлайн-кинотеатра
--  СУБД: MySQL 5.7+ / 8.0, MariaDB 10.6, кодировка utf8mb4
--  Импорт: phpMyAdmin → база `illuminos_timur` → вкладка «Импорт»
--  Полный дамп со всеми данными: database/db.sql
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- 1. Пользователи. Три роли: admin (администратор), subscriber
--    (подписчик с активной подпиской), user (обычный клиент).
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS users;
CREATE TABLE users (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    last_name     VARCHAR(50)  NOT NULL              COMMENT 'Фамилия',
    first_name    VARCHAR(50)  NOT NULL              COMMENT 'Имя',
    middle_name   VARCHAR(50)  DEFAULT NULL          COMMENT 'Отчество',
    login         VARCHAR(32)  NOT NULL              COMMENT 'Логин для входа',
    email         VARCHAR(120) NOT NULL              COMMENT 'Электронная почта',
    password_hash VARCHAR(255) NOT NULL              COMMENT 'Хеш пароля (password_hash)',
    role          ENUM('user','subscriber','admin') NOT NULL DEFAULT 'user',
    avatar        VARCHAR(255) DEFAULT NULL          COMMENT 'Файл аватара в uploads/avatars',
    status        ENUM('active','blocked') NOT NULL DEFAULT 'active',
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_login (login),
    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Зарегистрированные пользователи сервиса';

-- ---------------------------------------------------------------------
-- 2. Жанры (категории контента)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS genres;
CREATE TABLE genres (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name        VARCHAR(60)  NOT NULL COMMENT 'Название жанра',
    slug        VARCHAR(60)  NOT NULL COMMENT 'Латиница для URL',
    icon        VARCHAR(120) DEFAULT NULL COMMENT 'SVG-иконка категории',
    description VARCHAR(255) DEFAULT NULL,
    sort_order  TINYINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uq_genres_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Жанры: боевик, драма, комедия и т.д.';

-- ---------------------------------------------------------------------
-- 3. Тайтлы — фильмы и сериалы в одном справочнике
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS titles;
CREATE TABLE titles (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    type           ENUM('movie','series') NOT NULL DEFAULT 'movie',
    title          VARCHAR(200) NOT NULL COMMENT 'Название на русском',
    original_title VARCHAR(200) DEFAULT NULL,
    slug           VARCHAR(200) NOT NULL COMMENT 'Адрес страницы /title.php?slug=',
    description    TEXT         DEFAULT NULL COMMENT 'Описание (синопсис)',
    year           SMALLINT UNSIGNED DEFAULT NULL COMMENT 'Год начала',
    year_end       SMALLINT UNSIGNED DEFAULT NULL COMMENT 'Год окончания (для сериалов)',
    country        VARCHAR(120) DEFAULT NULL,
    duration       SMALLINT UNSIGNED DEFAULT NULL COMMENT 'Минуты: фильм целиком / эпизод',
    age_limit      VARCHAR(5)   DEFAULT NULL COMMENT 'Возрастной рейтинг: 0+, 6+ … 18+',
    rating         DECIMAL(3,1) NOT NULL DEFAULT 0.0 COMMENT 'Рейтинг Кинопоиска',
    price          DECIMAL(8,2) NOT NULL DEFAULT 0.00 COMMENT 'Цена покупки навсегда, ₽ (0 — только подписка)',
    poster         VARCHAR(255) DEFAULT NULL COMMENT 'Постер в assets/img/posters',
    backdrop       VARCHAR(255) DEFAULT NULL COMMENT 'Кадр для hero-баннера',
    logo           VARCHAR(255) DEFAULT NULL COMMENT 'PNG-логотип названия',
    trailer        VARCHAR(255) DEFAULT NULL COMMENT 'Видеофайл трейлера',
    accent_color   VARCHAR(7)   NOT NULL DEFAULT '#FF6B35' COMMENT 'Акцент цвета тайтла',
    badge          VARCHAR(40)  DEFAULT NULL COMMENT 'Плашка: Новое, Топ-10 …',
    director       VARCHAR(200) DEFAULT NULL,
    cast_list      TEXT         DEFAULT NULL COMMENT 'Актёры через запятую',
    is_new         TINYINT(1)   NOT NULL DEFAULT 0,
    is_popular     TINYINT(1)   NOT NULL DEFAULT 0,
    views          INT UNSIGNED NOT NULL DEFAULT 0,
    created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_titles_slug (slug),
    KEY idx_titles_type (type),
    KEY idx_titles_new (is_new),
    KEY idx_titles_popular (is_popular)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Каталог фильмов и сериалов';

-- ---------------------------------------------------------------------
-- 4. Связь «тайтл — жанр» (многие ко многим)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS title_genres;
CREATE TABLE title_genres (
    title_id INT UNSIGNED NOT NULL,
    genre_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (title_id, genre_id),
    KEY idx_tg_genre (genre_id),
    CONSTRAINT fk_tg_title FOREIGN KEY (title_id) REFERENCES titles (id) ON DELETE CASCADE,
    CONSTRAINT fk_tg_genre FOREIGN KEY (genre_id) REFERENCES genres (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 5. Сезоны и серии (только для сериалов)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS seasons;
CREATE TABLE seasons (
    id       INT UNSIGNED NOT NULL AUTO_INCREMENT,
    title_id INT UNSIGNED NOT NULL,
    number   TINYINT UNSIGNED NOT NULL COMMENT 'Номер сезона',
    name     VARCHAR(150) DEFAULT NULL,
    year     SMALLINT UNSIGNED DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_season_number (title_id, number),
    CONSTRAINT fk_seasons_title FOREIGN KEY (title_id) REFERENCES titles (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS episodes;
CREATE TABLE episodes (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    season_id   INT UNSIGNED NOT NULL,
    number      SMALLINT UNSIGNED NOT NULL COMMENT 'Номер серии в сезоне',
    name        VARCHAR(200) NOT NULL,
    description VARCHAR(500) DEFAULT NULL,
    duration    SMALLINT UNSIGNED DEFAULT NULL,
    released_at DATE DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_episode_number (season_id, number),
    CONSTRAINT fk_episodes_season FOREIGN KEY (season_id) REFERENCES seasons (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 6. Избранное пользователя
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS favorites;
CREATE TABLE favorites (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id    INT UNSIGNED NOT NULL,
    title_id   INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_fav_user_title (user_id, title_id),
    CONSTRAINT fk_fav_user  FOREIGN KEY (user_id)  REFERENCES users (id)  ON DELETE CASCADE,
    CONSTRAINT fk_fav_title FOREIGN KEY (title_id) REFERENCES titles (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 7. История просмотра
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS watch_history;
CREATE TABLE watch_history (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id    INT UNSIGNED NOT NULL,
    title_id   INT UNSIGNED NOT NULL,
    episode_id INT UNSIGNED DEFAULT NULL,
    progress   TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Просмотрено, %',
    watched_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_history_user (user_id, watched_at),
    CONSTRAINT fk_hist_user  FOREIGN KEY (user_id)  REFERENCES users (id)  ON DELETE CASCADE,
    CONSTRAINT fk_hist_title FOREIGN KEY (title_id) REFERENCES titles (id) ON DELETE CASCADE,
    CONSTRAINT fk_hist_ep    FOREIGN KEY (episode_id) REFERENCES episodes (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 8. Оценки и отзывы (одна оценка от пользователя на тайтл)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS reviews;
CREATE TABLE reviews (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id    INT UNSIGNED NOT NULL,
    title_id   INT UNSIGNED NOT NULL,
    score      TINYINT UNSIGNED NOT NULL COMMENT 'Оценка 1–10',
    text       VARCHAR(1000) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_review_user_title (user_id, title_id),
    CONSTRAINT fk_rev_user  FOREIGN KEY (user_id)  REFERENCES users (id)  ON DELETE CASCADE,
    CONSTRAINT fk_rev_title FOREIGN KEY (title_id) REFERENCES titles (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 9. Тарифы подписки
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS tariffs;
CREATE TABLE tariffs (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code        VARCHAR(30)  NOT NULL COMMENT 'Машинное имя: month, year …',
    name        VARCHAR(80)  NOT NULL,
    price       DECIMAL(8,2) NOT NULL COMMENT 'Цена со скидкой, ₽',
    old_price   DECIMAL(8,2) DEFAULT NULL COMMENT 'Цена без скидки, ₽',
    period_days SMALLINT UNSIGNED NOT NULL COMMENT 'Срок действия, дней',
    quality     VARCHAR(20)  NOT NULL DEFAULT 'Full HD',
    devices     TINYINT UNSIGNED NOT NULL DEFAULT 1,
    features    TEXT DEFAULT NULL COMMENT 'Возможности тарифа, по одному пункту в строке',
    is_popular  TINYINT(1) NOT NULL DEFAULT 0,
    is_active   TINYINT(1) NOT NULL DEFAULT 1,
    sort_order  TINYINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uq_tariff_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 10. Промокоды
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS promocodes;
CREATE TABLE promocodes (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code             VARCHAR(30) NOT NULL,
    discount_percent TINYINT UNSIGNED NOT NULL COMMENT 'Скидка, %',
    valid_until      DATE DEFAULT NULL,
    usage_limit      SMALLINT UNSIGNED NOT NULL DEFAULT 100,
    used_count       SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    is_active        TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY uq_promo_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 11. Платежи (заказы). Именно эту таблицу администратор видит
--     в разделе «Платежи» и меняет статусы заказов.
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS payments;
CREATE TABLE payments (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_number VARCHAR(20) NOT NULL COMMENT 'Номер заказа IL-2026-00001',
    kind         ENUM('subscription','films') NOT NULL DEFAULT 'subscription' COMMENT 'Тип заказа',
    user_id      INT UNSIGNED NOT NULL,
    tariff_id    INT UNSIGNED DEFAULT NULL COMMENT 'NULL для заказов фильмов',
    amount       DECIMAL(8,2) NOT NULL COMMENT 'Сумма до скидки',
    discount     DECIMAL(8,2) NOT NULL DEFAULT 0 COMMENT 'Размер скидки, ₽',
    total        DECIMAL(8,2) NOT NULL COMMENT 'Итого к оплате',
    method       ENUM('card','sbp','wallet') NOT NULL DEFAULT 'card',
    status       ENUM('pending','paid','cancelled','refunded') NOT NULL DEFAULT 'pending',
    promo_code   VARCHAR(30) DEFAULT NULL,
    card_mask    VARCHAR(19) DEFAULT NULL COMMENT 'Маска карты **** 1234',
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    paid_at      DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_payment_order (order_number),
    KEY idx_payments_user (user_id),
    CONSTRAINT fk_pay_user   FOREIGN KEY (user_id)   REFERENCES users (id)   ON DELETE CASCADE,
    CONSTRAINT fk_pay_tariff FOREIGN KEY (tariff_id) REFERENCES tariffs (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Заказы на подписку и история платежей';

-- ---------------------------------------------------------------------
-- 12. Подписки пользователей
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS subscriptions;
CREATE TABLE subscriptions (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id    INT UNSIGNED NOT NULL,
    tariff_id  INT UNSIGNED NOT NULL,
    payment_id INT UNSIGNED DEFAULT NULL,
    starts_at  DATE NOT NULL,
    ends_at    DATE NOT NULL,
    status     ENUM('active','expired','cancelled') NOT NULL DEFAULT 'active',
    PRIMARY KEY (id),
    KEY idx_subs_user (user_id, status),
    CONSTRAINT fk_sub_user    FOREIGN KEY (user_id)    REFERENCES users (id)    ON DELETE CASCADE,
    CONSTRAINT fk_sub_tariff  FOREIGN KEY (tariff_id)  REFERENCES tariffs (id),
    CONSTRAINT fk_sub_payment FOREIGN KEY (payment_id) REFERENCES payments (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 13. Корзина: фильмы, которые пользователь собирается купить
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS cart_items;
CREATE TABLE cart_items (
    id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id   INT UNSIGNED NOT NULL,
    title_id  INT UNSIGNED NOT NULL,
    quantity  TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Количество лицензий',
    added_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_cart_user_title (user_id, title_id),
    CONSTRAINT fk_cart_user  FOREIGN KEY (user_id)  REFERENCES users (id)  ON DELETE CASCADE,
    CONSTRAINT fk_cart_title FOREIGN KEY (title_id) REFERENCES titles (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Корзина покупки фильмов';

-- ---------------------------------------------------------------------
-- 14. Позиции заказа: название и цена фиксируются на момент покупки
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS order_items;
CREATE TABLE order_items (
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
  COMMENT='Состав заказа с фильмами';

-- ---------------------------------------------------------------------
-- 15. Купленные фильмы: доступ к просмотру без подписки
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS purchases;
CREATE TABLE purchases (
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
  COMMENT='Купленные навсегда фильмы';

SET FOREIGN_KEY_CHECKS = 1;