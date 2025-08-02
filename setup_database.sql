-- Script de configuración específico para tu instalación
-- Ejecutar con: mysql -u dripcenters_pagos -p dripcenters_pagos < setup_database.sql

USE dripcenters_pagos;

-- Crear tablas si no existen
CREATE TABLE IF NOT EXISTS sites (
    id INT PRIMARY KEY AUTO_INCREMENT,
    host VARCHAR(255) NOT NULL UNIQUE,
    name VARCHAR(255),
    is_matrix BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    priority INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS paypal_accounts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    client_id VARCHAR(255) NULL COMMENT 'Opcional - Solo para compatibilidad con API antigua',
    email VARCHAR(255) NOT NULL,
    daily_limit INT NOT NULL DEFAULT 4,
    currency VARCHAR(3) NOT NULL DEFAULT 'EUR',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS site_paypal_accounts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    site_id INT NOT NULL,
    paypal_account_id INT NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (site_id) REFERENCES sites(id),
    FOREIGN KEY (paypal_account_id) REFERENCES paypal_accounts(id),
    UNIQUE KEY unique_site_account (site_id, paypal_account_id)
);

CREATE TABLE IF NOT EXISTS payment_counters (
    id INT PRIMARY KEY AUTO_INCREMENT,
    site_id INT NOT NULL,
    paypal_account_id INT NOT NULL,
    date DATE NOT NULL,
    count INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (site_id) REFERENCES sites(id),
    FOREIGN KEY (paypal_account_id) REFERENCES paypal_accounts(id),
    UNIQUE KEY unique_counter (site_id, paypal_account_id, date)
);

CREATE TABLE IF NOT EXISTS logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    site_id INT,
    type VARCHAR(50) NOT NULL,
    message TEXT NOT NULL,
    ip VARCHAR(45),
    user_agent TEXT,
    referer TEXT,
    request_uri TEXT,
    method VARCHAR(10),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (site_id) REFERENCES sites(id),
    INDEX idx_type (type),
    INDEX idx_created_at (created_at)
);

CREATE TABLE IF NOT EXISTS php_errors (
    id INT PRIMARY KEY AUTO_INCREMENT,
    site_id INT,
    error_type VARCHAR(50) NOT NULL,
    error_message TEXT NOT NULL,
    error_file VARCHAR(255) NOT NULL,
    error_line INT NOT NULL,
    error_trace TEXT,
    request_uri TEXT,
    request_method VARCHAR(10),
    request_params TEXT,
    ip VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (site_id) REFERENCES sites(id),
    INDEX idx_error_type (error_type),
    INDEX idx_created_at (created_at),
    INDEX idx_error_file (error_file)
);

CREATE TABLE IF NOT EXISTS config (
    id INT PRIMARY KEY AUTO_INCREMENT,
    site_id INT,
    `key` VARCHAR(255) NOT NULL,
    value JSON NOT NULL,
    is_shared BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (site_id) REFERENCES sites(id),
    UNIQUE KEY unique_config (site_id, `key`)
);

-- Insertar o actualizar tu sitio como matriz
INSERT INTO sites (host, name, is_matrix, priority, is_active) 
VALUES ('kja.vynkapay.com', 'VynkaPay Matrix', TRUE, 0, TRUE)
ON DUPLICATE KEY UPDATE 
    is_matrix = TRUE, 
    is_active = TRUE, 
    name = 'VynkaPay Matrix',
    updated_at = CURRENT_TIMESTAMP;

-- Insertar configuración de WooCommerce si no existe
INSERT IGNORE INTO config (`key`, value, is_shared) VALUES 
('woocommerce', '{
    "api_url": "https://dripcenters.net/wp-json/wc/v3",
    "consumer_key": "ck_16d575abcd8385ce2a32431d934d71f5126c6a49",
    "consumer_secret": "cs_702e6bc7476a235ee3863836f3435659f92fb31a"
}', TRUE);

-- Crear cuenta PayPal de ejemplo si no existe
INSERT IGNORE INTO paypal_accounts (email, daily_limit, currency) VALUES 
('example@paypal.com', 4, 'EUR');

-- Asignar cuenta a tu sitio si no existe
INSERT IGNORE INTO site_paypal_accounts (site_id, paypal_account_id) 
SELECT 
    (SELECT id FROM sites WHERE host = 'kja.vynkapay.com' LIMIT 1),
    (SELECT id FROM paypal_accounts WHERE email = 'example@paypal.com' LIMIT 1);

-- Mostrar resultados
SELECT 'SITIOS CONFIGURADOS:' as info;
SELECT id, host, name, is_matrix, is_active FROM sites;

SELECT 'CUENTAS PAYPAL:' as info;
SELECT id, email, daily_limit, currency, is_active FROM paypal_accounts;

SELECT 'ASIGNACIONES:' as info;
SELECT 
    s.host,
    pa.email,
    spa.is_active
FROM site_paypal_accounts spa
JOIN sites s ON spa.site_id = s.id
JOIN paypal_accounts pa ON spa.paypal_account_id = pa.id;