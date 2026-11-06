CREATE DATABASE IF NOT EXISTS motor_ahp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE motor_ahp;

CREATE TABLE IF NOT EXISTS evaluaciones (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    empresa VARCHAR(180) NOT NULL,
    proyecto VARCHAR(255) NOT NULL,
    tipo_dato VARCHAR(40) NOT NULL DEFAULT 'simulacion',
    estado VARCHAR(60) NOT NULL,
    entrada_json LONGTEXT NOT NULL,
    resultado_json LONGTEXT NOT NULL,
    creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_empresa (empresa),
    INDEX idx_creado_en (creado_en)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
