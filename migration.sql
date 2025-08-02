-- Migración para usar formularios HTML de PayPal
-- Ejecutar este script en tu base de datos existente

USE dripcenters_pagos;

-- Hacer el campo client_id opcional
ALTER TABLE paypal_accounts 
MODIFY COLUMN client_id VARCHAR(255) NULL 
COMMENT 'Opcional - Solo para compatibilidad con API antigua';

-- Agregar cuentas de ejemplo (opcional, puedes cambiar los emails)
INSERT IGNORE INTO paypal_accounts (email, daily_limit, currency) VALUES 
('example1@paypal.com', 4, 'EUR'),
('example2@paypal.com', 4, 'EUR'),
('example3@paypal.com', 4, 'EUR');

-- Verificar las cuentas existentes
SELECT 
    id,
    email,
    daily_limit,
    currency,
    client_id,
    is_active
FROM paypal_accounts
ORDER BY id;

-- Verificar asignaciones a sitios
SELECT 
    s.host as sitio,
    pa.email as cuenta_paypal,
    pa.daily_limit,
    pa.currency,
    spa.is_active as activa
FROM sites s
INNER JOIN site_paypal_accounts spa ON spa.site_id = s.id
INNER JOIN paypal_accounts pa ON pa.id = spa.paypal_account_id
WHERE s.is_active = TRUE AND spa.is_active = TRUE
ORDER BY s.host, pa.email;