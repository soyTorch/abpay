# Migración a Formularios HTML de PayPal

## ✅ Cambios Completados

Hemos refactorizado exitosamente el sistema de pagos para usar formularios HTML simples de PayPal en lugar de la API. Esto simplifica enormemente el sistema y elimina la necesidad de client_id.

## 🔄 Qué ha cambiado

### 1. **Sistema de Cuentas PayPal**
- **Antes**: Requería `client_id` y `email` para usar la API de PayPal
- **Ahora**: Solo requiere `email` para usar formularios HTML básicos

### 2. **Formulario de Pago**
- **Antes**: Botón JavaScript dinámico con API de PayPal
- **Ahora**: Formulario HTML simple que redirige a PayPal

```html
<form action="https://www.paypal.com/cgi-bin/webscr" method="post" target="_blank">
  <input type="hidden" name="cmd" value="_xclick">
  <input type="hidden" name="business" value="tu-email@paypal.com">
  <input type="hidden" name="item_name" value="Pedido #123">
  <input type="hidden" name="amount" value="25.00">
  <input type="hidden" name="currency_code" value="EUR">
  <input type="submit" value="Pagar con PayPal" class="paypal-button">
</form>
```

### 3. **Flujo de Pago**
- **Antes**: API → Captura → Redirect a thanks.php
- **Ahora**: Formulario → PayPal → Redirect directo a thanks.php

## 🚀 Cómo usar el nuevo sistema

### Agregar nuevas cuentas PayPal
Solo necesitas el email de PayPal:

```sql
-- Agregar nueva cuenta
INSERT INTO paypal_accounts (email, daily_limit, currency) VALUES 
('nuevo-email@paypal.com', 10, 'EUR');

-- Asignar cuenta a un sitio
INSERT INTO site_paypal_accounts (site_id, paypal_account_id) 
SELECT 
    (SELECT id FROM sites WHERE host = 'tu-sitio.com'),
    (SELECT id FROM paypal_accounts WHERE email = 'nuevo-email@paypal.com');
```

### Migrar cuentas existentes
Las cuentas existentes seguirán funcionando. El campo `client_id` ahora es opcional y se mantiene solo por compatibilidad.

## 🔧 Configuración requerida en PayPal

Para que funcionen los formularios HTML, asegúrate de que tu cuenta PayPal tenga configurado:

1. **URL de retorno**: `https://tu-sitio.com/thanks-1f020f05-3e92-4c33-a09a-afa4ed22efa8.php`
2. **Auto Return**: Activado
3. **PDT (Payment Data Transfer)**: Opcional, pero recomendado para mayor seguridad

## ✨ Ventajas del nuevo sistema

1. **Simplicidad**: No necesitas API keys ni client_id
2. **Menos errores**: Formularios HTML son más estables que la API
3. **Mejor compatibilidad**: Funciona en todos los navegadores sin JavaScript
4. **Fácil mantenimiento**: Menos dependencias externas
5. **Configuración mínima**: Solo necesitas el email de PayPal

## 📝 Mantenimiento del código

### Archivos modificados:
- `src/includes/PayPalManager.php` - Eliminadas referencias a client_id
- `templates/payment/button.php` - Reemplazado con formulario HTML
- `assets/css/payment.css` - Agregados estilos para el botón
- `thanks-1f020f05-3e92-4c33-a09a-afa4ed22efa8.php` - Actualizado metadata
- `database/schema.sql` - client_id ahora opcional

### El sistema mantiene:
- ✅ Balanceo de carga entre cuentas
- ✅ Límites diarios por cuenta
- ✅ Logging completo
- ✅ Distribución entre sitios
- ✅ Contadores de uso
- ✅ Integración con WooCommerce

## 🎯 Próximos pasos

1. Ejecuta la migración de base de datos si es necesario
2. Actualiza las URLs de retorno en tu cuenta PayPal
3. Prueba el flujo de pago completo
4. Opcionalmente, limpia los client_id antiguos de la base de datos

¡El sistema está listo para usar! 🎉