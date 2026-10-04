CREATE TABLE sms_channels (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    provider VARCHAR(32) NOT NULL,
    credentials_ciphertext TEXT NOT NULL,
    options JSON NOT NULL,
    enabled TINYINT UNSIGNED NOT NULL DEFAULT 0,
    version BIGINT UNSIGNED NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE sms_templates (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    channel_id BIGINT UNSIGNED NOT NULL,
    scene VARCHAR(64) NOT NULL,
    template_id VARCHAR(128) NOT NULL,
    sign_name VARCHAR(128) NOT NULL,
    parameter_mapping JSON NOT NULL,
    enabled TINYINT UNSIGNED NOT NULL DEFAULT 0,
    version BIGINT UNSIGNED NOT NULL DEFAULT 1,
    UNIQUE KEY uk_channel_scene (channel_id, scene),
    CONSTRAINT fk_sms_template_channel FOREIGN KEY (channel_id)
        REFERENCES sms_channels (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE sms_routes (
    scene VARCHAR(64) PRIMARY KEY,
    channel_id BIGINT UNSIGNED NOT NULL,
    enabled TINYINT UNSIGNED NOT NULL DEFAULT 0,
    version BIGINT UNSIGNED NOT NULL DEFAULT 1,
    CONSTRAINT fk_sms_route_template FOREIGN KEY (channel_id, scene)
        REFERENCES sms_templates (channel_id, scene)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
