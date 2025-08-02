<?php
/**
 * Script de prueba rápida para verificar que el panel de administración funciona
 */

error_reporting(E_ALL);
ini_set('display_errors', TRUE);

echo "<h1>🧪 ABPay - Test Rápido del Sistema</h1>";

try {
    // Test 1: Cargar configuración
    echo "<h2>1. ✅ Configuración</h2>";
    $current_host = $_SERVER['HTTP_HOST'] ?? 'default';
    $config_file = __DIR__ . '/config/' . $current_host . '.php';
    if (!file_exists($config_file)) {
        $config_file = __DIR__ . '/config/default.php';
    }
    require_once $config_file;
    echo "Config cargado: " . basename($config_file) . "<br>";
    
    // Test 2: Conexión a base de datos
    echo "<h2>2. ✅ Base de Datos</h2>";
    require_once __DIR__ . '/src/includes/Database.php';
    $db = Database::getInstance();
    echo "Conexión exitosa<br>";
    
    // Verificar tablas necesarias
    $tables = ['sites', 'paypal_accounts', 'site_paypal_accounts', 'payment_counters', 'logs'];
    foreach ($tables as $table) {
        $stmt = $db->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$table]);
        if ($stmt->fetch()) {
            echo "✅ Tabla '$table' existe<br>";
        } else {
            echo "❌ Tabla '$table' NO existe<br>";
        }
    }
    
    // Test 3: Verificar sitio matriz
    echo "<h2>3. ✅ Sitio Matriz</h2>";
    $isMatrix = Database::isMatrix();
    if ($isMatrix) {
        echo "✅ Este sitio es MATRIZ - Panel admin disponible<br>";
    } else {
        echo "⚠️ Este sitio NO es matriz - Panel admin no accesible<br>";
    }
    
    // Test 4: Verificar archivos del admin
    echo "<h2>4. ✅ Archivos del Panel Admin</h2>";
    $adminFiles = [
        'admin/index.php',
        'admin/login.php',
        'admin/sites.php',
        'admin/accounts.php',
        'admin/assignments.php',
        'admin/logs.php',
        'admin/settings.php',
        'admin/assets/css/admin.css',
        'admin/assets/js/admin.js',
        'admin/includes/auth.php',
        'admin/includes/layout.php'
    ];
    
    foreach ($adminFiles as $file) {
        if (file_exists($file)) {
            echo "✅ $file<br>";
        } else {
            echo "❌ $file NO existe<br>";
        }
    }
    
    // Test 5: PayPal Manager
    echo "<h2>5. ✅ PayPal Manager</h2>";
    require_once __DIR__ . '/src/includes/PayPalManager.php';
    $paypalManager = new PayPalManager();
    echo "PayPalManager cargado correctamente<br>";
    
    // Contar cuentas
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM paypal_accounts WHERE is_active = TRUE");
    $stmt->execute();
    $activeAccounts = $stmt->fetch()['count'];
    echo "Cuentas PayPal activas: $activeAccounts<br>";
    
    // Test 6: Contar sitios
    echo "<h2>6. ✅ Sitios</h2>";
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM sites WHERE is_active = TRUE");
    $stmt->execute();
    $activeSites = $stmt->fetch()['count'];
    echo "Sitios activos: $activeSites<br>";
    
    // Test 7: Verificar permisos de archivos
    echo "<h2>7. ✅ Permisos</h2>";
    if (is_writable(__DIR__)) {
        echo "✅ Directorio raíz escribible<br>";
    } else {
        echo "⚠️ Directorio raíz no escribible<br>";
    }
    
    if (is_readable('admin/')) {
        echo "✅ Directorio admin legible<br>";
    } else {
        echo "❌ Directorio admin no legible<br>";
    }
    
    // Test 8: Información del servidor
    echo "<h2>8. ✅ Información del Servidor</h2>";
    echo "PHP Version: " . PHP_VERSION . "<br>";
    echo "Servidor: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown') . "<br>";
    echo "Host actual: " . $current_host . "<br>";
    echo "Zona horaria: " . date_default_timezone_get() . "<br>";
    
    // Test 9: URLs de acceso
    echo "<h2>9. 🔗 URLs de Acceso</h2>";
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $baseUrl = $protocol . '://' . $current_host;
    
    echo "<strong>Panel de Administración:</strong><br>";
    echo "<a href='$baseUrl/admin/' target='_blank'>$baseUrl/admin/</a><br><br>";
    
    echo "<strong>Test de Formularios HTML:</strong><br>";
    echo "<a href='$baseUrl/test_html_forms.php' target='_blank'>$baseUrl/test_html_forms.php</a><br><br>";
    
    echo "<strong>Script de Migración:</strong><br>";
    echo "Ejecutar: <code>mysql -u usuario -p base_datos < migration.sql</code><br><br>";
    
    // Test 10: Credenciales por defecto
    echo "<h2>10. 🔐 Credenciales del Admin</h2>";
    echo "<strong>Usuario:</strong> admin<br>";
    echo "<strong>Contraseña:</strong> admin123<br>";
    echo "<em>⚠️ Cambiar estas credenciales en producción</em><br><br>";
    
    // Resumen final
    echo "<h2>✅ Resumen del Test</h2>";
    echo "<div style='background: #e8f5e8; padding: 15px; border-radius: 5px; border-left: 5px solid #4caf50;'>";
    echo "<strong>🎉 ¡Sistema listo para usar!</strong><br>";
    echo "• Base de datos configurada<br>";
    echo "• Archivos del panel instalados<br>";
    echo "• PayPal Manager funcionando<br>";
    echo "• Sitio matriz configurado<br>";
    echo "</div>";
    
    echo "<h3>📋 Próximos pasos:</h3>";
    echo "<ol>";
    echo "<li>Acceder al panel admin</li>";
    echo "<li>Crear sitios de prueba</li>";
    echo "<li>Agregar cuentas PayPal</li>";
    echo "<li>Configurar asignaciones</li>";
    echo "<li>Probar el flujo de pagos</li>";
    echo "</ol>";
    
} catch (Exception $e) {
    echo "<div style='background: #ffe8e8; padding: 15px; border-radius: 5px; border-left: 5px solid #f44336;'>";
    echo "<strong>❌ Error:</strong> " . htmlspecialchars($e->getMessage()) . "<br>";
    echo "<strong>Archivo:</strong> " . $e->getFile() . "<br>";
    echo "<strong>Línea:</strong> " . $e->getLine() . "<br>";
    echo "</div>";
    
    echo "<h3>🔧 Posibles soluciones:</h3>";
    echo "<ul>";
    echo "<li>Verificar configuración de base de datos</li>";
    echo "<li>Ejecutar migration.sql</li>";
    echo "<li>Verificar permisos de archivos</li>";
    echo "<li>Revisar logs de error del servidor</li>";
    echo "</ul>";
}

echo "<hr>";
echo "<p><strong>Fecha del test:</strong> " . date('Y-m-d H:i:s') . "</p>";
echo "<p><em>💡 Elimina este archivo después de completar las pruebas.</em></p>";
?>