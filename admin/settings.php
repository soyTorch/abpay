<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/../src/includes/Database.php';

// Verificar autenticación y sitio matriz
checkMatrixSite();
AdminAuth::requireAuth();

$db = Database::getInstance();
$message = '';
$messageType = '';

// Procesar configuraciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'update_limits') {
            // Actualizar límites globales
            foreach ($_POST['account_limits'] ?? [] as $accountId => $limit) {
                $limit = (int)$limit;
                if ($limit > 0) {
                    $stmt = $db->prepare("
                        UPDATE paypal_accounts 
                        SET daily_limit = ?, updated_at = CURRENT_TIMESTAMP 
                        WHERE id = ?
                    ");
                    $stmt->execute([$limit, $accountId]);
                }
            }
            
            $message = "Límites actualizados exitosamente.";
            $messageType = 'success';
            
        } elseif ($action === 'reset_counters') {
            $date = $_POST['reset_date'] ?? date('Y-m-d');
            
            $stmt = $db->prepare("DELETE FROM payment_counters WHERE date = ?");
            $stmt->execute([$date]);
            
            $message = "Contadores reiniciados para la fecha: " . $date;
            $messageType = 'success';
            
        } elseif ($action === 'cleanup_logs') {
            $days = (int)($_POST['cleanup_days'] ?? 30);
            
            $stmt = $db->prepare("
                DELETE FROM logs 
                WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
            ");
            $stmt->execute([$days]);
            $deletedLogs = $stmt->rowCount();
            
            $stmt = $db->prepare("
                DELETE FROM php_errors 
                WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
            ");
            $stmt->execute([$days]);
            $deletedErrors = $stmt->rowCount();
            
            $message = "Limpieza completada. Eliminados: {$deletedLogs} logs y {$deletedErrors} errores.";
            $messageType = 'success';
            
        } elseif ($action === 'test_system') {
            // Probar componentes del sistema
            $tests = [];
            
            // Test base de datos
            try {
                $stmt = $db->prepare("SELECT 1");
                $stmt->execute();
                $tests['database'] = ['status' => 'success', 'message' => 'Conexión exitosa'];
            } catch (Exception $e) {
                $tests['database'] = ['status' => 'error', 'message' => $e->getMessage()];
            }
            
            // Test cuentas PayPal
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM paypal_accounts WHERE is_active = TRUE");
            $stmt->execute();
            $activeAccounts = $stmt->fetch()['count'];
            
            if ($activeAccounts > 0) {
                $tests['paypal'] = ['status' => 'success', 'message' => "{$activeAccounts} cuentas activas"];
            } else {
                $tests['paypal'] = ['status' => 'warning', 'message' => 'No hay cuentas PayPal activas'];
            }
            
            // Test sitios
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM sites WHERE is_active = TRUE");
            $stmt->execute();
            $activeSites = $stmt->fetch()['count'];
            
            if ($activeSites > 0) {
                $tests['sites'] = ['status' => 'success', 'message' => "{$activeSites} sitios activos"];
            } else {
                $tests['sites'] = ['status' => 'warning', 'message' => 'No hay sitios activos'];
            }
            
            // Test asignaciones
            $stmt = $db->prepare("
                SELECT COUNT(*) as count 
                FROM site_paypal_accounts spa
                INNER JOIN sites s ON spa.site_id = s.id AND s.is_active = TRUE
                INNER JOIN paypal_accounts pa ON spa.paypal_account_id = pa.id AND pa.is_active = TRUE
                WHERE spa.is_active = TRUE
            ");
            $stmt->execute();
            $activeAssignments = $stmt->fetch()['count'];
            
            if ($activeAssignments > 0) {
                $tests['assignments'] = ['status' => 'success', 'message' => "{$activeAssignments} asignaciones activas"];
            } else {
                $tests['assignments'] = ['status' => 'error', 'message' => 'No hay asignaciones activas'];
            }
            
            $message = "Pruebas del sistema completadas.";
            $messageType = 'info';
        }
        
    } catch (Exception $e) {
        $message = $e->getMessage();
        $messageType = 'error';
    }
}

// Obtener información del sistema
$systemInfo = [
    'php_version' => PHP_VERSION,
    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
    'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown',
    'server_time' => date('Y-m-d H:i:s'),
    'timezone' => date_default_timezone_get(),
    'memory_limit' => ini_get('memory_limit'),
    'max_execution_time' => ini_get('max_execution_time'),
    'upload_max_filesize' => ini_get('upload_max_filesize'),
    'post_max_size' => ini_get('post_max_size')
];

// Obtener estadísticas del sistema
$stmt = $db->prepare("
    SELECT 
        (SELECT COUNT(*) FROM sites) as total_sites,
        (SELECT COUNT(*) FROM sites WHERE is_active = TRUE) as active_sites,
        (SELECT COUNT(*) FROM paypal_accounts) as total_accounts,
        (SELECT COUNT(*) FROM paypal_accounts WHERE is_active = TRUE) as active_accounts,
        (SELECT COUNT(*) FROM site_paypal_accounts WHERE is_active = TRUE) as active_assignments,
        (SELECT COUNT(*) FROM logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as logs_30d,
        (SELECT COUNT(*) FROM payment_counters WHERE date >= DATE_SUB(CURRENT_DATE(), INTERVAL 7 DAY)) as payments_7d
");
$stmt->execute();
$systemStats = $stmt->fetch();

// Obtener cuentas para configurar límites
$stmt = $db->prepare("
    SELECT 
        pa.*,
        COUNT(spa.id) as assigned_sites,
        COALESCE(pc.count, 0) as today_usage
    FROM paypal_accounts pa
    LEFT JOIN site_paypal_accounts spa ON pa.id = spa.paypal_account_id AND spa.is_active = TRUE
    LEFT JOIN payment_counters pc ON pa.id = pc.paypal_account_id AND pc.date = CURRENT_DATE()
    WHERE pa.is_active = TRUE
    GROUP BY pa.id
    ORDER BY pa.email
");
$stmt->execute();
$accounts = $stmt->fetchAll();

// Información de uso de base de datos
try {
    $stmt = $db->prepare("
        SELECT 
            table_name,
            table_rows,
            ROUND(((data_length + index_length) / 1024 / 1024), 2) AS 'size_mb'
        FROM information_schema.tables 
        WHERE table_schema = DATABASE()
        ORDER BY (data_length + index_length) DESC
    ");
    $stmt->execute();
    $tableStats = $stmt->fetchAll();
} catch (Exception $e) {
    $tableStats = [];
}

renderAdminHeader('Configuración del Sistema', 'settings');
?>

<?php if ($message): ?>
    <?php showAlert($messageType, $message); ?>
<?php endif; ?>

<!-- Información del Sistema -->
<div class="card" style="margin-bottom: 1.5rem;">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fas fa-server"></i>
            Información del Sistema
        </h3>
        <div class="card-actions">
            <form method="POST" style="display: inline;">
                <input type="hidden" name="action" value="test_system">
                <button type="submit" class="btn btn-info btn-sm">
                    <i class="fas fa-check-circle"></i>
                    Probar Sistema
                </button>
            </form>
        </div>
    </div>
    <div class="card-body">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem;">
            <!-- Información del Servidor -->
            <div>
                <h4 style="font-size: 1rem; margin-bottom: 1rem; color: var(--primary-color);">
                    <i class="fas fa-server"></i>
                    Servidor
                </h4>
                <div style="background: var(--bg-secondary); padding: 1rem; border-radius: var(--border-radius);">
                    <div style="margin-bottom: 0.5rem;">
                        <strong>PHP:</strong> <?php echo $systemInfo['php_version']; ?>
                    </div>
                    <div style="margin-bottom: 0.5rem;">
                        <strong>Software:</strong> <?php echo htmlspecialchars($systemInfo['server_software']); ?>
                    </div>
                    <div style="margin-bottom: 0.5rem;">
                        <strong>Zona Horaria:</strong> <?php echo $systemInfo['timezone']; ?>
                    </div>
                    <div>
                        <strong>Hora del Servidor:</strong> <?php echo $systemInfo['server_time']; ?>
                    </div>
                </div>
            </div>
            
            <!-- Configuración PHP -->
            <div>
                <h4 style="font-size: 1rem; margin-bottom: 1rem; color: var(--primary-color);">
                    <i class="fas fa-code"></i>
                    Configuración PHP
                </h4>
                <div style="background: var(--bg-secondary); padding: 1rem; border-radius: var(--border-radius);">
                    <div style="margin-bottom: 0.5rem;">
                        <strong>Memory Limit:</strong> <?php echo $systemInfo['memory_limit']; ?>
                    </div>
                    <div style="margin-bottom: 0.5rem;">
                        <strong>Max Execution:</strong> <?php echo $systemInfo['max_execution_time']; ?>s
                    </div>
                    <div style="margin-bottom: 0.5rem;">
                        <strong>Upload Max:</strong> <?php echo $systemInfo['upload_max_filesize']; ?>
                    </div>
                    <div>
                        <strong>Post Max:</strong> <?php echo $systemInfo['post_max_size']; ?>
                    </div>
                </div>
            </div>
            
            <!-- Estadísticas del Sistema -->
            <div>
                <h4 style="font-size: 1rem; margin-bottom: 1rem; color: var(--primary-color);">
                    <i class="fas fa-chart-bar"></i>
                    Estadísticas
                </h4>
                <div style="background: var(--bg-secondary); padding: 1rem; border-radius: var(--border-radius);">
                    <div style="margin-bottom: 0.5rem;">
                        <strong>Sitios:</strong> <?php echo $systemStats['active_sites']; ?>/<?php echo $systemStats['total_sites']; ?> activos
                    </div>
                    <div style="margin-bottom: 0.5rem;">
                        <strong>Cuentas:</strong> <?php echo $systemStats['active_accounts']; ?>/<?php echo $systemStats['total_accounts']; ?> activas
                    </div>
                    <div style="margin-bottom: 0.5rem;">
                        <strong>Asignaciones:</strong> <?php echo $systemStats['active_assignments']; ?> activas
                    </div>
                    <div>
                        <strong>Logs (30d):</strong> <?php echo number_format($systemStats['logs_30d']); ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Resultados de pruebas -->
        <?php if (isset($tests)): ?>
            <div style="margin-top: 1.5rem;">
                <h4 style="font-size: 1rem; margin-bottom: 1rem; color: var(--primary-color);">
                    <i class="fas fa-check-circle"></i>
                    Resultados de Pruebas
                </h4>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                    <?php foreach ($tests as $component => $result): ?>
                        <div style="
                            background: var(--bg-secondary); 
                            padding: 1rem; 
                            border-radius: var(--border-radius);
                            border-left: 4px solid <?php 
                                echo $result['status'] === 'success' ? 'var(--success-color)' : 
                                    ($result['status'] === 'warning' ? 'var(--warning-color)' : 'var(--error-color)'); 
                            ?>;
                        ">
                            <div style="font-weight: 600; margin-bottom: 0.5rem;">
                                <?php echo ucfirst($component); ?>
                            </div>
                            <div style="font-size: 0.875rem; color: var(--text-secondary);">
                                <?php echo htmlspecialchars($result['message']); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Configuración de Límites -->
<div class="card" style="margin-bottom: 1.5rem;">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fas fa-chart-line"></i>
            Configuración de Límites Diarios
        </h3>
    </div>
    <div class="card-body">
        <?php if (empty($accounts)): ?>
            <p style="color: var(--text-secondary);">No hay cuentas PayPal activas para configurar.</p>
        <?php else: ?>
            <form method="POST">
                <input type="hidden" name="action" value="update_limits">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Cuenta PayPal</th>
                                <th>Límite Actual</th>
                                <th>Uso Hoy</th>
                                <th>Sitios Asignados</th>
                                <th>Nuevo Límite</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($accounts as $account): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($account['email']); ?></strong>
                                    </td>
                                    <td>
                                        <span class="badge badge-info">
                                            <?php echo $account['daily_limit']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $account['today_usage'] > 0 ? 'badge-success' : 'badge-secondary'; ?>">
                                            <?php echo $account['today_usage']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge badge-info">
                                            <?php echo $account['assigned_sites']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <input type="number" 
                                               name="account_limits[<?php echo $account['id']; ?>]" 
                                               value="<?php echo $account['daily_limit']; ?>"
                                               class="form-control" 
                                               min="1" 
                                               max="1000" 
                                               style="width: 100px;">
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div style="margin-top: 1rem;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        Actualizar Límites
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<!-- Herramientas de Mantenimiento -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 1.5rem;">
    <!-- Reiniciar Contadores -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-redo"></i>
                Reiniciar Contadores
            </h3>
        </div>
        <div class="card-body">
            <p style="color: var(--text-secondary); margin-bottom: 1rem;">
                Reinicia los contadores de pagos para una fecha específica. 
                Útil para corregir errores o hacer pruebas.
            </p>
            <form method="POST" onsubmit="return confirm('¿Estás seguro de reiniciar los contadores?')">
                <input type="hidden" name="action" value="reset_counters">
                <div class="form-group">
                    <label for="reset_date" class="form-label">Fecha</label>
                    <input type="date" id="reset_date" name="reset_date" 
                           class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <button type="submit" class="btn btn-warning">
                    <i class="fas fa-redo"></i>
                    Reiniciar Contadores
                </button>
            </form>
        </div>
    </div>
    
    <!-- Limpiar Logs -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-broom"></i>
                Limpiar Logs Antiguos
            </h3>
        </div>
        <div class="card-body">
            <p style="color: var(--text-secondary); margin-bottom: 1rem;">
                Elimina logs y errores anteriores a la cantidad de días especificada 
                para liberar espacio en la base de datos.
            </p>
            <form method="POST" onsubmit="return confirm('¿Estás seguro de eliminar los logs antiguos?')">
                <input type="hidden" name="action" value="cleanup_logs">
                <div class="form-group">
                    <label for="cleanup_days" class="form-label">Mantener últimos</label>
                    <select id="cleanup_days" name="cleanup_days" class="form-control">
                        <option value="7">7 días</option>
                        <option value="30" selected>30 días</option>
                        <option value="60">60 días</option>
                        <option value="90">90 días</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-warning">
                    <i class="fas fa-broom"></i>
                    Limpiar Logs
                </button>
            </form>
        </div>
    </div>
</div>

<!-- Uso de Base de Datos -->
<?php if (!empty($tableStats)): ?>
<div class="card" style="margin-top: 1.5rem;">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fas fa-database"></i>
            Uso de Base de Datos
        </h3>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Tabla</th>
                        <th>Registros</th>
                        <th>Tamaño (MB)</th>
                        <th>Uso</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $totalSize = array_sum(array_column($tableStats, 'size_mb'));
                    foreach ($tableStats as $table): 
                        $percentage = $totalSize > 0 ? ($table['size_mb'] / $totalSize * 100) : 0;
                    ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($table['table_name']); ?></strong></td>
                            <td><?php echo number_format($table['table_rows']); ?></td>
                            <td><?php echo number_format($table['size_mb'], 2); ?></td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 0.5rem;">
                                    <div style="flex: 1; background: var(--bg-tertiary); height: 8px; border-radius: 4px; overflow: hidden;">
                                        <div style="
                                            height: 100%; 
                                            width: <?php echo $percentage; ?>%; 
                                            background: var(--primary-color);
                                            transition: width 0.3s ease;
                                        "></div>
                                    </div>
                                    <span style="font-size: 0.75rem; color: var(--text-secondary);">
                                        <?php echo round($percentage, 1); ?>%
                                    </span>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div style="margin-top: 1rem; font-size: 0.875rem; color: var(--text-secondary);">
            <strong>Tamaño total:</strong> <?php echo number_format($totalSize, 2); ?> MB
        </div>
    </div>
</div>
<?php endif; ?>

<?php renderAdminFooter(); ?>