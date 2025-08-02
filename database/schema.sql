-- Crear la base de datos si no existe
CREATE DATABASE IF NOT EXISTS dripcenters_pagos;
USE dripcenters_pagos;

-- Tabla de sitios
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

-- Tabla de configuración
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

-- Tabla de cuentas PayPal
CREATE TABLE IF NOT EXISTS paypal_accounts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    client_id VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    daily_limit INT NOT NULL DEFAULT 4,
    currency VARCHAR(3) NOT NULL DEFAULT 'EUR',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabla de asignación de cuentas PayPal a sitios
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

-- Tabla de contadores de pagos diarios
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

-- Tabla de logs
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

-- Tabla de errores PHP
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

-- Insertar sitios iniciales
INSERT INTO sites (host, name, is_matrix, priority) VALUES 
('kja.vynkapay.com', 'VynkaPay Matrix', TRUE, 0),
('hardmode.net', 'Hard Mode Payments', FALSE, 1);

-- Insertar configuración compartida de WooCommerce
INSERT INTO config (`key`, value, is_shared) VALUES 
('woocommerce', '{
    "api_url": "https://dripcenters.net/wp-json/wc/v3",
    "consumer_key": "ck_16d575abcd8385ce2a32431d934d71f5126c6a49",
    "consumer_secret": "cs_702e6bc7476a235ee3863836f3435659f92fb31a"
}', TRUE);

-- Insertar cuenta PayPal de hardmode.net
INSERT INTO paypal_accounts (client_id, email, daily_limit, currency) VALUES 
('AbsoCIm2dhvT5fE9qexyAJIsL7xsjENdw0-E19VChP5yLXHFCXQxn8rhXGO6bA5f7keb1I0BEGQngc2y', '694094253@qq.com', 4, 'EUR');

-- Asignar cuenta PayPal a hardmode.net
INSERT INTO site_paypal_accounts (site_id, paypal_account_id) 
SELECT 
    (SELECT id FROM sites WHERE host = 'hardmode.net'),
    (SELECT id FROM paypal_accounts WHERE email = '694094253@qq.com'); 