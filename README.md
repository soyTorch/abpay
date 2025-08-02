# Payment Processing System

Sistema de procesamiento de pagos con PayPal para múltiples sitios con balanceo de carga y límites diarios.

## Características

- Gestión de múltiples sitios
- Balanceo de carga entre cuentas PayPal
- Sistema de límites diarios por cuenta
- Logging detallado de operaciones
- Integración con WooCommerce
- Soporte para sitio matriz y sitios secundarios
- Sistema de redirección inteligente

## Arquitectura del Sistema

### Concepto de Sitio Matriz

El sistema puede funcionar en dos modos:
- **Sitio Matriz**: Actúa como distribuidor central de pagos
- **Sitio Secundario**: Procesa pagos con sus cuentas PayPal asignadas

La diferencia entre ambos modos se configura con un solo parámetro en la configuración:
```php
define('IS_MATRIX', true); // Para sitio matriz
define('IS_MATRIX', false); // Para sitio secundario
```

### Flujo de Procesamiento de Pagos

1. **Inicio del Proceso**
   - Cliente hace clic en "Pagar" en WooCommerce
   - Se genera una URL codificada con el ID del pedido
   - Se redirige al cliente al sistema de pagos

2. **Verificación Inicial**
   - Se valida el ID del pedido
   - Se comprueba si el pedido ya está pagado
   - Se verifica el estado del sitio (matriz/secundario)

3. **Distribución de Pagos**
   - **En Sitio Matriz**:
     1. Busca sitios secundarios con cuentas disponibles
     2. Selecciona el sitio según prioridad y disponibilidad
     3. Redirige al cliente al sitio seleccionado
     4. Si no hay sitios disponibles, muestra página de límite

   - **En Sitio Secundario**:
     1. Busca una cuenta PayPal disponible
     2. Si no hay cuentas disponibles, redirige al sitio matriz
     3. Si hay cuenta disponible, muestra botón de pago

4. **Proceso de Pago**
   - Se muestra el botón de PayPal con la cuenta seleccionada
   - Cliente completa el pago
   - Se actualiza el estado del pedido en WooCommerce
   - Se incrementa el contador de pagos diario

### Sistema de Balanceo de Carga

#### Nivel 1: Entre Sitios
```sql
WITH site_availability AS (
    SELECT 
        s.id, s.host, s.priority,
        COUNT(DISTINCT CASE 
            WHEN COALESCE(pc.count, 0) < pa.daily_limit 
            THEN pa.id 
        END) as available_accounts
    FROM sites s
    -- ... resto de la consulta
)
```
- Prioriza sitios según configuración
- Considera número de cuentas disponibles
- Balancea carga entre sitios activos

#### Nivel 2: Entre Cuentas PayPal
```php
// En PayPalManager.php
public function getAvailableAccount(): ?array {
    // Selección aleatoria entre cuentas disponibles
    // Considera límites diarios
    // ... código de selección
}
```

### Sistema de Logging

El sistema mantiene un registro detallado de todas las operaciones:
- Intentos de pago
- Redirecciones entre sitios
- Selección de cuentas PayPal
- Éxitos y errores en pagos
- Información del cliente (IP, User Agent)
- Sitio que procesa cada operación

## Configuración

### 1. Configuración de Base de Datos

```sql
-- Crear las tablas necesarias
CREATE TABLE sites ( ... );
CREATE TABLE paypal_accounts ( ... );
CREATE TABLE site_paypal_accounts ( ... );
CREATE TABLE payment_counters ( ... );
CREATE TABLE logs ( ... );
```

### 2. Configuración de Sitios

Para cada sitio, crear un archivo en `config/`:
```php
// config/your-domain.php
define('SITE_HOST', 'your-domain.com');
define('SITE_NAME', 'Your Site Name');
define('IS_MATRIX', false);
define('DB_HOST', 'localhost');
// ... resto de configuración
```

### 3. Configuración de Cuentas PayPal

1. Insertar cuentas en la tabla `paypal_accounts`:
```sql
INSERT INTO paypal_accounts (
    client_id, email, daily_limit, currency
) VALUES (
    'your-client-id',
    'your-paypal-email',
    4, -- límite diario
    'EUR'
);
```

2. Asignar cuentas a sitios:
```sql
INSERT INTO site_paypal_accounts (
    site_id, paypal_account_id
) VALUES (
    1, -- ID del sitio
    1  -- ID de la cuenta PayPal
);
```

## Mantenimiento

### Monitoreo
- Revisar logs diariamente
- Verificar contadores de pagos
- Monitorear disponibilidad de cuentas

### Reseteo Diario
- Los contadores se resetean automáticamente cada día
- No se requiere mantenimiento manual

### Backups
- Hacer backup diario de la base de datos
- Especial atención a tablas de configuración y logs

## Troubleshooting

### Problemas Comunes

1. **Error de Redirección**
   - Verificar configuración IS_MATRIX
   - Comprobar disponibilidad de sitios

2. **Cuenta PayPal No Disponible**
   - Verificar límites diarios
   - Comprobar asignación de cuentas

3. **Errores de WooCommerce**
   - Verificar credenciales API
   - Comprobar conectividad

## Seguridad

- Todas las URLs contienen IDs codificados
- Validación estricta de parámetros
- Sistema de logging para auditoría
- Sanitización de datos de entrada
- Protección contra inyección SQL

## Licencia

Propietario - Todos los derechos reservados 