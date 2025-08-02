<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/../src/includes/Database.php';

// Verificar autenticación y sitio matriz
checkMatrixSite();
AdminAuth::requireAuth();

$db = Database::getInstance();

// Filtros
$date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-7 days'));
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$log_type = $_GET['log_type'] ?? '';
$site_id = $_GET['site_id'] ?? '';
$search = $_GET['search'] ?? '';
$limit = (int)($_GET['limit'] ?? 50);

// Obtener logs con filtros
$whereConditions = ["l.created_at >= ? AND l.created_at <= ?"];
$params = [$date_from . ' 00:00:00', $date_to . ' 23:59:59'];

if ($log_type) {
    $whereConditions[] = "l.type = ?";
    $params[] = $log_type;
}

if ($site_id) {
    $whereConditions[] = "l.site_id = ?";
    $params[] = $site_id;
}

if ($search) {
    $whereConditions[] = "l.message LIKE ?";
    $params[] = '%' . $search . '%';
}

$stmt = $db->prepare("
    SELECT 
        l.*,
        s.host as site_host,
        s.name as site_name
    FROM logs l
    LEFT JOIN sites s ON l.site_id = s.id
    WHERE " . implode(' AND ', $whereConditions) . "
    ORDER BY l.created_at DESC
    LIMIT ?
");
$params[] = $limit;
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Obtener sitios para filtro
$stmt = $db->prepare("SELECT id, host, name FROM sites WHERE is_active = TRUE ORDER BY host");
$stmt->execute();
$sites = $stmt->fetchAll();

// Estadísticas de logs
$stmt = $db->prepare("
    SELECT 
        l.type,
        COUNT(*) as count,
        COUNT(CASE WHEN l.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 END) as count_24h
    FROM logs l
    WHERE l.created_at >= ? AND l.created_at <= ?
    GROUP BY l.type
    ORDER BY count DESC
");
$stmt->execute([$date_from . ' 00:00:00', $date_to . ' 23:59:59']);
$logStats = $stmt->fetchAll();

// Obtener errores PHP recientes
$stmt = $db->prepare("
    SELECT 
        pe.*,
        s.host as site_host
    FROM php_errors pe
    LEFT JOIN sites s ON pe.site_id = s.id
    WHERE pe.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ORDER BY pe.created_at DESC
    LIMIT 20
");
$stmt->execute();
$phpErrors = $stmt->fetchAll();

renderAdminHeader('Logs y Reportes', 'logs');
?>

<!-- Filtros -->
<div class="card" style="margin-bottom: 1.5rem;">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fas fa-filter"></i>
            Filtros de Búsqueda
        </h3>
        <div class="card-actions">
            <button class="btn btn-secondary btn-sm" onclick="clearFilters()">
                <i class="fas fa-times"></i>
                Limpiar Filtros
            </button>
        </div>
    </div>
    <div class="card-body">
        <form method="GET" class="filter-form">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1rem;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label for="date_from" class="form-label">Fecha Desde</label>
                    <input type="date" id="date_from" name="date_from" class="form-control" 
                           value="<?php echo htmlspecialchars($date_from); ?>">
                </div>
                
                <div class="form-group" style="margin-bottom: 0;">
                    <label for="date_to" class="form-label">Fecha Hasta</label>
                    <input type="date" id="date_to" name="date_to" class="form-control" 
                           value="<?php echo htmlspecialchars($date_to); ?>">
                </div>
                
                <div class="form-group" style="margin-bottom: 0;">
                    <label for="log_type" class="form-label">Tipo de Log</label>
                    <select id="log_type" name="log_type" class="form-control">
                        <option value="">Todos los tipos</option>
                        <option value="payment_success" <?php echo $log_type === 'payment_success' ? 'selected' : ''; ?>>Pagos Exitosos</option>
                        <option value="payment_error" <?php echo $log_type === 'payment_error' ? 'selected' : ''; ?>>Errores de Pago</option>
                        <option value="payment_attempt" <?php echo $log_type === 'payment_attempt' ? 'selected' : ''; ?>>Intentos de Pago</option>
                        <option value="system" <?php echo $log_type === 'system' ? 'selected' : ''; ?>>Sistema</option>
                        <option value="admin" <?php echo $log_type === 'admin' ? 'selected' : ''; ?>>Administración</option>
                    </select>
                </div>
                
                <div class="form-group" style="margin-bottom: 0;">
                    <label for="site_id" class="form-label">Sitio</label>
                    <select id="site_id" name="site_id" class="form-control">
                        <option value="">Todos los sitios</option>
                        <?php foreach ($sites as $site): ?>
                            <option value="<?php echo $site['id']; ?>" 
                                    <?php echo $site_id == $site['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($site['host']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr auto auto; gap: 1rem; align-items: end;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label for="search" class="form-label">Buscar en Mensajes</label>
                    <input type="text" id="search" name="search" class="form-control" 
                           placeholder="Buscar texto en logs..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                
                <div class="form-group" style="margin-bottom: 0;">
                    <label for="limit" class="form-label">Límite</label>
                    <select id="limit" name="limit" class="form-control">
                        <option value="50" <?php echo $limit == 50 ? 'selected' : ''; ?>>50</option>
                        <option value="100" <?php echo $limit == 100 ? 'selected' : ''; ?>>100</option>
                        <option value="200" <?php echo $limit == 200 ? 'selected' : ''; ?>>200</option>
                        <option value="500" <?php echo $limit == 500 ? 'selected' : ''; ?>>500</option>
                    </select>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search"></i>
                    Buscar
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Estadísticas de Logs -->
<?php if (!empty($logStats)): ?>
<div class="card" style="margin-bottom: 1.5rem;">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fas fa-chart-pie"></i>
            Estadísticas del Período (<?php echo $date_from; ?> - <?php echo $date_to; ?>)
        </h3>
    </div>
    <div class="card-body">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
            <?php foreach ($logStats as $stat): ?>
                <div class="stats-card">
                    <div class="stats-content">
                        <div class="stats-value"><?php echo number_format($stat['count']); ?></div>
                        <div class="stats-title"><?php echo ucfirst(str_replace('_', ' ', $stat['type'])); ?></div>
                        <div class="stats-subtitle"><?php echo $stat['count_24h']; ?> en las últimas 24h</div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Errores PHP Recientes -->
<?php if (!empty($phpErrors)): ?>
<div class="card" style="margin-bottom: 1.5rem;">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fas fa-exclamation-triangle"></i>
            Errores PHP Recientes (Últimas 24h)
        </h3>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Tiempo</th>
                        <th>Tipo</th>
                        <th>Mensaje</th>
                        <th>Archivo</th>
                        <th>Línea</th>
                        <th>Sitio</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_slice($phpErrors, 0, 10) as $error): ?>
                        <tr>
                            <td style="font-size: 0.75rem; color: var(--text-secondary);">
                                <?php echo date('H:i:s', strtotime($error['created_at'])); ?>
                            </td>
                            <td>
                                <span class="badge badge-danger">
                                    <?php echo htmlspecialchars($error['error_type']); ?>
                                </span>
                            </td>
                            <td style="font-size: 0.875rem; max-width: 300px;">
                                <?php echo htmlspecialchars(substr($error['error_message'], 0, 100)) . (strlen($error['error_message']) > 100 ? '...' : ''); ?>
                            </td>
                            <td style="font-size: 0.75rem; color: var(--text-secondary);">
                                <?php echo htmlspecialchars(basename($error['error_file'])); ?>
                            </td>
                            <td style="font-size: 0.75rem;">
                                <?php echo $error['error_line']; ?>
                            </td>
                            <td style="font-size: 0.75rem; color: var(--text-secondary);">
                                <?php echo htmlspecialchars($error['site_host'] ?? 'N/A'); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Logs -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fas fa-file-alt"></i>
            Logs del Sistema
            <?php if (count($logs) >= $limit): ?>
                <span class="badge badge-warning">(Mostrando primeros <?php echo $limit; ?>)</span>
            <?php endif; ?>
        </h3>
        <div class="card-actions">
            <button class="btn btn-secondary btn-sm" onclick="location.reload()">
                <i class="fas fa-sync-alt"></i>
                Actualizar
            </button>
        </div>
    </div>
    <div class="card-body">
        <?php if (empty($logs)): ?>
            <div style="text-align: center; padding: 2rem;">
                <i class="fas fa-file-alt" style="font-size: 3rem; color: var(--text-light); margin-bottom: 1rem;"></i>
                <h4>No se encontraron logs</h4>
                <p style="color: var(--text-secondary);">Ajusta los filtros para ver más resultados.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Tiempo</th>
                            <th>Tipo</th>
                            <th>Mensaje</th>
                            <th>Sitio</th>
                            <th>IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td style="font-size: 0.75rem; color: var(--text-secondary); white-space: nowrap;">
                                    <?php echo date('d/m/Y H:i:s', strtotime($log['created_at'])); ?>
                                </td>
                                <td>
                                    <?php
                                    $badgeClass = 'badge-secondary';
                                    $icon = 'fas fa-info';
                                    
                                    switch ($log['type']) {
                                        case 'payment_success':
                                            $badgeClass = 'badge-success';
                                            $icon = 'fas fa-check';
                                            break;
                                        case 'payment_error':
                                            $badgeClass = 'badge-danger';
                                            $icon = 'fas fa-exclamation-triangle';
                                            break;
                                        case 'payment_attempt':
                                            $badgeClass = 'badge-info';
                                            $icon = 'fas fa-credit-card';
                                            break;
                                        case 'system':
                                            $badgeClass = 'badge-warning';
                                            $icon = 'fas fa-cog';
                                            break;
                                        case 'admin':
                                            $badgeClass = 'badge-purple';
                                            $icon = 'fas fa-user-shield';
                                            break;
                                    }
                                    ?>
                                    <span class="badge <?php echo $badgeClass; ?>" title="<?php echo ucfirst(str_replace('_', ' ', $log['type'])); ?>">
                                        <i class="<?php echo $icon; ?>"></i>
                                        <?php echo ucfirst(str_replace('_', ' ', $log['type'])); ?>
                                    </span>
                                </td>
                                <td style="font-size: 0.875rem; max-width: 400px; word-break: break-word;">
                                    <?php echo htmlspecialchars($log['message']); ?>
                                </td>
                                <td style="font-size: 0.75rem; color: var(--text-secondary);">
                                    <?php if ($log['site_host']): ?>
                                        <strong><?php echo htmlspecialchars($log['site_host']); ?></strong>
                                        <br>
                                        <small><?php echo htmlspecialchars($log['site_name'] ?? ''); ?></small>
                                    <?php else: ?>
                                        <span style="color: var(--text-light);">Sistema</span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size: 0.75rem; color: var(--text-secondary);">
                                    <?php echo htmlspecialchars($log['ip'] ?? 'N/A'); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function clearFilters() {
    const form = document.querySelector('.filter-form');
    form.reset();
    
    // Set default dates
    document.getElementById('date_from').value = '<?php echo date('Y-m-d', strtotime('-7 days')); ?>';
    document.getElementById('date_to').value = '<?php echo date('Y-m-d'); ?>';
    document.getElementById('limit').value = '50';
    
    form.submit();
}

// Auto-refresh every 30 seconds
setInterval(function() {
    location.reload();
}, 30000);

// Real-time search
let searchTimeout;
document.getElementById('search').addEventListener('input', function() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        document.querySelector('.filter-form').submit();
    }, 1000);
});
</script>

<style>
.badge-purple {
    background: rgba(124, 58, 237, 0.1);
    color: #7c3aed;
}

.filter-form {
    background: var(--bg-secondary);
    padding: 1rem;
    border-radius: var(--border-radius);
    border: 1px solid var(--border-color);
}
</style>

<?php renderAdminFooter(); ?>