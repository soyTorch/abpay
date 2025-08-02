# 🎛️ Panel de Administración ABPay

Panel de administración completo y moderno para gestionar el sistema de pagos ABPay con formularios HTML de PayPal.

## ✨ Características

### 🔐 Sistema de Autenticación
- Login seguro con credenciales
- Sesiones persistentes
- Protección de rutas administrativas
- Solo accesible desde sitios matriz

### 📊 Dashboard Completo
- Estadísticas en tiempo real
- Gráficos de actividad de pagos
- Uso de cuentas PayPal en tiempo real
- Actividad reciente del sistema
- Auto-refresh cada 30 segundos

### 🌍 Gestión de Sitios
- ✅ Crear, editar y eliminar sitios
- ✅ Configurar sitios matriz y secundarios
- ✅ Gestión de prioridades
- ✅ Estados activo/inactivo
- ✅ Validaciones completas

### 💳 Gestión de Cuentas PayPal
- ✅ Agregar cuentas solo con email
- ✅ Configurar límites diarios personalizados
- ✅ Soporte multi-moneda (EUR, USD, GBP, etc.)
- ✅ Visualización de uso en tiempo real
- ✅ Estados activo/inactivo

### 🔗 Sistema de Asignaciones
- ✅ Vincular sitios con cuentas PayPal
- ✅ Múltiples cuentas por sitio (balanceo)
- ✅ Activar/desactivar asignaciones
- ✅ Vista consolidada de configuraciones

### 📈 Reportes y Logs
- ✅ Filtros avanzados por fecha, tipo, sitio
- ✅ Búsqueda en tiempo real
- ✅ Estadísticas de actividad
- ✅ Monitoreo de errores PHP
- ✅ Exportación de datos

### ⚙️ Configuración del Sistema
- ✅ Información del servidor y PHP
- ✅ Pruebas del sistema automatizadas
- ✅ Configuración de límites en masa
- ✅ Herramientas de mantenimiento
- ✅ Limpieza de logs antiguos
- ✅ Estadísticas de base de datos

## 🚀 Acceso al Panel

### URL de Acceso
```
https://tu-sitio-matriz.com/admin/
```

### Credenciales por Defecto
```
Usuario: admin
Contraseña: admin123
```

> ⚠️ **Importante**: Cambia las credenciales en producción editando el archivo `admin/includes/auth.php`

### Acceso por Token (Compatibilidad)
También funciona con el sistema de token anterior:
```
https://tu-sitio-matriz.com/admin/login.php?u=admin&token=eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJhZG1pbiI6ImRpZGFjIn0.uXqn5qDvcpUk2-DjuFOcmis6nNTDhjAudgqnmDpIuM8
```

## 📁 Estructura de Archivos

```
admin/
├── assets/
│   ├── css/
│   │   └── admin.css          # Estilos del panel
│   └── js/
│       └── admin.js           # JavaScript funcional
├── includes/
│   ├── auth.php               # Sistema de autenticación
│   └── layout.php             # Layout base y componentes
├── index.php                  # Dashboard principal
├── login.php                  # Página de login
├── logout.php                 # Cerrar sesión
├── sites.php                  # Gestión de sitios
├── accounts.php               # Gestión de cuentas PayPal
├── assignments.php            # Asignaciones sitios-cuentas
├── logs.php                   # Sistema de logs y reportes
├── settings.php               # Configuración del sistema
└── README.md                  # Esta documentación
```

## 🎨 Diseño y UX

### Características del Diseño
- 🎨 **Diseño moderno**: Interfaz limpia y profesional
- 📱 **Completamente responsive**: Funciona en todos los dispositivos
- 🌙 **Sidebar colapsible**: Navegación optimizada
- ⚡ **Carga rápida**: Sin dependencias pesadas
- 🎯 **UX intuitiva**: Fácil de usar y navegar

### Componentes Incluidos
- Cards informativos con estadísticas
- Tablas responsivas con acciones
- Modales para formularios
- Alertas y notificaciones
- Badges de estado
- Barras de progreso
- Botones con iconos
- Formularios validados

## 🔧 Configuración

### Cambiar Credenciales de Admin
Edita el archivo `admin/includes/auth.php`:

```php
private static $admin_users = [
    'admin' => [
        'password' => 'tu-nueva-contraseña',
        'token' => 'tu-nuevo-token',
        'name' => 'Tu Nombre',
        'permissions' => ['*']
    ]
];
```

### Configurar Auto-refresh
Los dashboards se actualizan automáticamente cada 30 segundos. Para cambiar este intervalo, edita los archivos correspondientes:

```javascript
// Cambiar de 30000ms (30s) a tu preferencia
setInterval(function() {
    location.reload();
}, 30000);
```

### Personalizar Estilos
Edita `admin/assets/css/admin.css` para personalizar:
- Colores del tema
- Tamaños de fuente
- Espaciados
- Animaciones

## 📊 Funcionalidades Avanzadas

### Dashboard Inteligente
- **Gráficos en tiempo real** con Chart.js
- **Barras de progreso** para uso de cuentas
- **Estadísticas consolidadas** de todo el sistema
- **Actividad reciente** con filtros automáticos

### Gestión Granular
- **Búsqueda en tiempo real** en todas las tablas
- **Filtros avanzados** por múltiples criterios
- **Validación en vivo** de formularios
- **Confirmaciones** para acciones críticas

### Monitoreo Completo
- **Logs estructurados** por tipo y fecha
- **Errores PHP** capturados automáticamente
- **Estadísticas de uso** por cuenta y sitio
- **Alertas visuales** para problemas

### Herramientas de Mantenimiento
- **Limpieza automática** de logs antiguos
- **Reinicio de contadores** por fecha
- **Pruebas del sistema** automatizadas
- **Información del servidor** detallada

## 🔐 Seguridad

### Medidas Implementadas
- ✅ Autenticación obligatoria para todas las páginas
- ✅ Protección CSRF en formularios
- ✅ Validación de inputs en cliente y servidor
- ✅ Escape de HTML para prevenir XSS
- ✅ Sesiones seguras con timeouts
- ✅ Acceso restringido solo a sitios matriz

### Recomendaciones
1. **Cambia las credenciales por defecto**
2. **Usa HTTPS en producción**
3. **Restringe acceso por IP si es posible**
4. **Actualiza regularmente las dependencias**
5. **Monitorea los logs de acceso**

## 🚀 Flujo de Trabajo Típico

### 1. Configuración Inicial
1. Acceder al panel de administración
2. Crear sitios en `Sitios`
3. Agregar cuentas PayPal en `Cuentas PayPal`
4. Asignar cuentas a sitios en `Asignaciones`

### 2. Monitoreo Diario
1. Revisar el `Dashboard` para estadísticas generales
2. Verificar uso de cuentas en tiempo real
3. Revisar `Logs` para actividad reciente
4. Ajustar límites si es necesario

### 3. Mantenimiento Semanal
1. Ejecutar `Pruebas del Sistema` en `Configuración`
2. Limpiar logs antiguos si es necesario
3. Revisar errores PHP en `Logs`
4. Actualizar límites de cuentas según necesidad

## 🆘 Solución de Problemas

### Problemas Comunes

**1. No puedo acceder al panel**
- Verifica que estés en un sitio matriz
- Comprueba las credenciales
- Revisa que la base de datos esté accesible

**2. Las estadísticas no se actualizan**
- Verifica la configuración de auto-refresh
- Comprueba los permisos de base de datos
- Revisa los logs de errores PHP

**3. Los formularios no funcionan**
- Verifica que JavaScript esté habilitado
- Comprueba los permisos de escritura
- Revisa la validación de campos

**4. Problemas de rendimiento**
- Limpia logs antiguos regularmente
- Verifica la configuración de PHP
- Optimiza las consultas de base de datos

## 📞 Soporte

Para soporte técnico o reportar problemas:
1. Revisa los logs en `Logs > Errores PHP`
2. Ejecuta las pruebas del sistema en `Configuración`
3. Verifica la información del servidor
4. Documenta el problema con capturas de pantalla

---

## 🎉 ¡Disfruta del Panel!

Este panel de administración está diseñado para ser:
- **Fácil de usar** para administradores
- **Potente** para desarrolladores
- **Escalable** para proyectos grandes
- **Mantenible** a largo plazo

¡Gestiona tus pagos PayPal con confianza! 💪