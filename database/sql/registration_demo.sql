-- MySQL 8 / InnoDB。仅在隔离的开发数据库中执行；不包含删除表操作。
-- 若 DB_PREFIX 非空，请在执行前为表名加上相同前缀。
CREATE TABLE demo_registration_users (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    email VARCHAR(254) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY demo_registration_users_email_unique (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
