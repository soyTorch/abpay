# 🧪 Guía de Testing - Panel de Administración ABPay

## 📋 Prerrequisitos

### 1. Configuración de Base de Datos
Asegúrate de tener la base de datos configurada:

```bash
# Si no has ejecutado la migración, hazlo ahora:
mysql -u tu_usuario -p tu_base_datos < migration.sql

# O ejecuta el esquema completo:
mysql -u tu_usuario -p tu_base_datos < database/schema.sql
```

### 2. Verificar Configuración
Asegúrate de que tu sitio esté configurado como matriz en la base de datos:

```sql
UPDATE sites SET is_matrix = TRUE WHERE host = 'tu-dominio.com';
```

## 🚀 Pasos de Testing

### **PASO 1: Acceso Inicial**

#### A. Acceder al Panel
```
URL: https://tu-dominio.com/admin/
```

#### B. Credenciales por Defecto
```
Usuario: admin
Contraseña: admin123
```

#### C. Verificaciones Iniciales
- ✅ La página de login se carga correctamente
- ✅ Puedes iniciar sesión
- ✅ Te redirige al dashboard
- ✅ El sidebar se muestra correctamente

---

### **PASO 2: Probar Dashboard**

#### A. Verificar Estadísticas
- ✅ Se muestran las 4 cards de estadísticas
- ✅ Los números son correctos (sitios, cuentas, pagos)
- ✅ El gráfico de pagos se carga

#### B. Funcionalidades del Dashboard
- ✅ Auto-refresh funciona (espera 30 segundos)
- ✅ Las barras de progreso se muestran
- ✅ La actividad reciente aparece

---

### **PASO 3: Gestión de Sitios**

#### A. Crear Nuevo Sitio
1. Ir a **Sitios** en el sidebar
2. Clic en **"Nuevo Sitio"**
3. Llenar el formulario:
   - Host: `test.example.com`
   - Nombre: `Sitio de Prueba`
   - Prioridad: `1`
4. Clic en **"Crear Sitio"**

#### B. Verificaciones
- ✅ El sitio aparece en la tabla
- ✅ Se puede editar el sitio
- ✅ Se puede cambiar el estado (activo/inactivo)
- ✅ No se puede eliminar el sitio actual

#### C. Editar Sitio
1. Clic en el botón de editar (icono lápiz)
2. Cambiar el nombre
3. Guardar cambios
4. ✅ Los cambios se reflejan en la tabla

---

### **PASO 4: Gestión de Cuentas PayPal**

#### A. Crear Nueva Cuenta
1. Ir a **Cuentas PayPal**
2. Clic en **"Nueva Cuenta"**
3. Llenar:
   - Email: `test@paypal.com`
   - Límite Diario: `10`
   - Moneda: `EUR`
4. Crear cuenta

#### B. Verificaciones
- ✅ La cuenta aparece en la tabla
- ✅ Se muestra el progreso de uso (0%)
- ✅ Se puede editar la cuenta
- ✅ Se puede cambiar el límite diario

#### C. Crear Más Cuentas de Prueba
```
test2@paypal.com - Límite: 5 - EUR
test3@paypal.com - Límite: 8 - USD
```

---

### **PASO 5: Sistema de Asignaciones**

#### A. Crear Asignaciones
1. Ir a **Asignaciones**
2. Clic en **"Nueva Asignación"**
3. Seleccionar:
   - Sitio: `Sitio de Prueba`
   - Cuenta: `test@paypal.com`
4. Crear asignación

#### B. Verificaciones
- ✅ La asignación aparece en la tabla
- ✅ Se muestra la información completa
- ✅ Se puede activar/desactivar
- ✅ Se puede eliminar

#### C. Crear Múltiples Asignaciones
- Asigna las 3 cuentas de prueba al mismo sitio
- Verifica que todas aparecen correctamente

---

### **PASO 6: Logs y Reportes**

#### A. Verificar Logs Existentes
1. Ir a **Logs**
2. Verificar que aparecen logs del sistema
3. Probar filtros:
   - Por fecha
   - Por tipo de log
   - Búsqueda de texto

#### B. Funcionalidades de Filtrado
- ✅ Filtro por fecha funciona
- ✅ Filtro por tipo funciona
- ✅ Búsqueda en tiempo real funciona
- ✅ Se pueden limpiar filtros

#### C. Errores PHP
- ✅ Se muestran errores recientes (si los hay)
- ✅ La información es completa y útil

---

### **PASO 7: Configuración del Sistema**

#### A. Probar Sistema
1. Ir a **Configuración**
2. Clic en **"Probar Sistema"**
3. Verificar resultados:
   - ✅ Base de datos: ✅ Conexión exitosa
   - ✅ PayPal: ✅ X cuentas activas
   - ✅ Sitios: ✅ X sitios activos
   - ✅ Asignaciones: ✅ X asignaciones activas

#### B. Información del Sistema
- ✅ Se muestra información del servidor
- ✅ Configuración PHP es correcta
- ✅ Estadísticas del sistema son precisas

#### C. Herramientas de Mantenimiento
- ✅ Configuración de límites funciona
- ✅ Reinicio de contadores funciona
- ✅ Limpieza de logs funciona

---

### **PASO 8: Testing de Formularios HTML**

#### A. Usar el Script de Prueba
```
URL: https://tu-dominio.com/test_html_forms.php
```

#### B. Verificaciones
- ✅ Se muestra la cuenta PayPal seleccionada
- ✅ El formulario HTML se genera correctamente
- ✅ Los datos son correctos

#### C. Probar Flujo de Pago (Opcional)
1. Crear un pedido de prueba en WooCommerce
2. Ir a la página de pago
3. Verificar que se muestra el formulario HTML
4. ✅ El botón de PayPal se ve correctamente

---

## 🧪 Testing Avanzado

### **PASO 9: Testing de Responsive Design**

#### A. Probar en Mobile
1. Abrir herramientas de desarrollador (F12)
2. Cambiar a vista móvil
3. Verificar:
   - ✅ Sidebar se colapsa correctamente
   - ✅ Tablas son responsivas
   - ✅ Modales se adaptan al tamaño

#### B. Probar en Tablet
- ✅ Layout se adapta correctamente
- ✅ Navegación funciona bien

### **PASO 10: Testing de Seguridad**

#### A. Acceso Sin Autenticación
1. Abrir ventana incógnito
2. Intentar acceder a `admin/index.php` directamente
3. ✅ Te redirige a login

#### B. Acceso desde Sitio No-Matriz
1. Si tienes otro dominio configurado como no-matriz
2. Intentar acceder al admin
3. ✅ Debe mostrar 404

### **PASO 11: Testing de Performance**

#### A. Tiempos de Carga
- ✅ Dashboard carga en < 2 segundos
- ✅ Páginas de gestión cargan rápido
- ✅ Auto-refresh no afecta UX

#### B. Uso de Memoria
- ✅ Sin leaks de memoria en JavaScript
- ✅ Consumo de CPU bajo

---

## 🔧 Debugging y Solución de Problemas

### Si algo no funciona:

#### 1. Verificar Logs de Error
```bash
# Logs del servidor web
tail -f /var/log/apache2/error.log
# o
tail -f /var/log/nginx/error.log

# Logs de PHP
tail -f /var/log/php/error.log
```

#### 2. Verificar en el Panel
- Ir a **Logs > Errores PHP**
- Revisar errores recientes

#### 3. Verificar Base de Datos
```sql
-- Verificar que existen las tablas
SHOW TABLES;

-- Verificar sitio matriz
SELECT * FROM sites WHERE is_matrix = TRUE;

-- Verificar cuentas
SELECT * FROM paypal_accounts WHERE is_active = TRUE;
```

#### 4. Verificar Permisos
```bash
# Permisos de archivos
chmod 755 admin/
chmod 644 admin/*.php
chmod 644 admin/assets/css/*.css
chmod 644 admin/assets/js/*.js
```

---

## ✅ Checklist Final de Testing

### Funcionalidades Básicas
- [ ] Login funciona correctamente
- [ ] Dashboard muestra estadísticas reales
- [ ] Sidebar navegación funciona
- [ ] Auto-refresh funciona

### CRUD Operaciones
- [ ] Crear sitios ✅
- [ ] Editar sitios ✅
- [ ] Eliminar sitios ✅
- [ ] Crear cuentas PayPal ✅
- [ ] Editar cuentas PayPal ✅
- [ ] Eliminar cuentas PayPal ✅
- [ ] Crear asignaciones ✅
- [ ] Editar asignaciones ✅
- [ ] Eliminar asignaciones ✅

### Reportes y Logs
- [ ] Filtros funcionan ✅
- [ ] Búsqueda en tiempo real ✅
- [ ] Exportación de datos ✅
- [ ] Estadísticas precisas ✅

### Configuración
- [ ] Pruebas del sistema ✅
- [ ] Herramientas de mantenimiento ✅
- [ ] Información del servidor ✅

### Seguridad
- [ ] Autenticación obligatoria ✅
- [ ] Protección de rutas ✅
- [ ] Validación de inputs ✅
- [ ] Escape de HTML ✅

### UX/UI
- [ ] Diseño responsive ✅
- [ ] Navegación intuitiva ✅
- [ ] Modales funcionan ✅
- [ ] Alertas/notificaciones ✅

---

## 🎉 ¡Panel Listo!

Si todos los tests pasan, ¡tu panel de administración está completamente funcional y listo para producción!

### Próximos Pasos
1. **Cambiar credenciales** de admin en producción
2. **Configurar HTTPS** si no está ya
3. **Hacer backup** de la base de datos
4. **Monitorear logs** regularmente
5. **Mantener actualizado** el sistema

¡Disfruta de tu nuevo panel de administración! 🚀