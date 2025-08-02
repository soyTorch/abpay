<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/../src/includes/Database.php';
require_once __DIR__ . '/../src/includes/PayPalManager.php';
require_once __DIR__ . '/../src/includes/Logger.php';

// Verificar autenticación y sitio matriz
checkMatrixSite();
AdminAuth::requireAuth();

// Obtener datos para el dashboard
$db = Database::getInstance();
$paypalManager = new PayPalManager();

try {
    // Estadísticas generales
    $stats = [];
    
    // Total de sitios activos
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM sites WHERE is_active = TRUE");
    $stmt->execute();
    $stats['total_sites'] = $stmt->fetch()['count'];
    
    // Total de cuentas PayPal activas
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM paypal_accounts WHERE is_active = TRUE");
    $stmt->execute();
    $stats['total_accounts'] = $stmt->fetch()['count'];
    
    // Pagos de hoy
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(count), 0) as total 
        FROM payment_counters 
        WHERE date = CURRENT_DATE()
    ");
    $stmt->execute();
    $stats['payments_today'] = $stmt->fetch()['total'];
    
    // Pagos esta semana
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(count), 0) as total 
        FROM payment_counters 
        WHERE date >= DATE_SUB(CURRENT_DATE(), INTERVAL 7 DAY)
    ");
    $stmt->execute();
    $stats['payments_week'] = $stmt->fetch()['total'];
    
    // Uso actual de cuentas
    $accountUsage = [];
    $stmt = $db->prepare("
        SELECT 
            pa.email,
            pa.daily_limit,
            COALESCE(pc.count, 0) as today_count,
            ROUND((COALESCE(pc.count, 0) / pa.daily_limit) * 100, 1) as usage_percent
        FROM paypal_accounts pa
        LEFT JOIN payment_counters pc ON pa.id = pc.paypal_account_id AND pc.date = CURRENT_DATE()
        WHERE pa.is_active = TRUE
        ORDER BY usage_percent DESC
    ");
    $stmt->execute();
    $accountUsage = $stmt->fetchAll();
    
    // Actividad reciente
    $recentActivity = [];
    $stmt = $db->prepare("
        SELECT 
            l.message,
            l.created_at,
            l.type,
            s.host as site_host
        FROM logs l
        LEFT JOIN sites s ON l.site_id = s.id
        WHERE l.type IN ('payment_success', 'payment_error', 'payment_attempt')
        ORDER BY l.created_at DESC
        LIMIT 10
    ");
    $stmt->execute();
    $recentActivity = $stmt->fetchAll();
    
    // Datos para gráfico de pagos (últimos 7 días)
    $chartData = [];
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-{$i} days"));
        $stmt = $db->prepare("
            SELECT COALESCE(SUM(count), 0) as total 
            FROM payment_counters 
            WHERE date = ?
        ");
        $stmt->execute([$date]);
        $total = $stmt->fetch()['total'];
        
        $chartData[] = [
            'date' => $date,
            'total' => $total
        ];
    }
    
} catch (Exception $e) {
    $error = "Error al cargar datos del dashboard: " . $e->getMessage();
}

renderAdminHeader('Dashboard', 'dashboard');
?>

<?php if (isset($error)): ?>
    <div class="alert alert-error">
        <i class="fas fa-exclamation-triangle"></i>
        <?php echo htmlspecialchars($error); ?>
    </div>
<?php endif; ?>

<!-- Statistics Cards -->
<div class="stats-grid">
    <?php 
    renderStatsCard('Sitios Activos', $stats['total_sites'], 'fas fa-globe', 'blue');
    renderStatsCard('Cuentas PayPal', $stats['total_accounts'], 'fab fa-paypal', 'yellow');
    renderStatsCard('Pagos Hoy', $stats['payments_today'], 'fas fa-credit-card', 'green');
    renderStatsCard('Pagos Esta Semana', $stats['payments_week'], 'fas fa-chart-line', 'purple', 'Últimos 7 días');
    ?>
</div>

<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem; margin-bottom: 2rem;">
    <!-- Gráfico de Pagos -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-chart-area"></i>
                Actividad de Pagos (Últimos 7 días)
            </h3>
        </div>
        <div class="card-body">
            <canvas id="paymentsChart" height="300"></canvas>
        </div>
    </div>
    
    <!-- Uso de Cuentas -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-percentage"></i>
                Uso de Cuentas Hoy
            </h3>
        </div>
        <div class="card-body">
            <?php if (empty($accountUsage)): ?>
                <p class="text-secondary">No hay cuentas PayPal configuradas.</p>
            <?php else: ?>
                <div style="space-y: 1rem;">
                    <?php foreach ($accountUsage as $account): ?>
                        <div style="margin-bottom: 1rem;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                                <span style="font-size: 0.875rem; font-weight: 500;">
                                    <?php echo htmlspecialchars($account['email']); ?>
                                </span>
                                <span style="font-size: 0.75rem; color: var(--text-secondary);">
                                    <?php echo $account['today_count']; ?>/<?php echo $account['daily_limit']; ?>
                                </span>
                            </div>
                            <div style="background: var(--bg-tertiary); height: 8px; border-radius: 4px; overflow: hidden;">
                                <div style="
                                    height: 100%; 
                                    width: <?php echo min($account['usage_percent'], 100); ?>%; 
                                    background: <?php 
                                        if ($account['usage_percent'] >= 90) echo 'var(--error-color)';
                                        elseif ($account['usage_percent'] >= 70) echo 'var(--warning-color)';
                                        else echo 'var(--success-color)';
                                    ?>;
                                    transition: width 0.3s ease;
                                "></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Actividad Reciente -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fas fa-clock"></i>
            Actividad Reciente
        </h3>
        <div class="card-actions">
            <a href="logs.php" class="btn btn-secondary btn-sm">
                <i class="fas fa-external-link-alt"></i>
                Ver Todos los Logs
            </a>
        </div>
    </div>
    <div class="card-body">
        <?php if (empty($recentActivity)): ?>
            <p class="text-secondary">No hay actividad reciente.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Tiempo</th>
                            <th>Tipo</th>
                            <th>Mensaje</th>
                            <th>Sitio</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentActivity as $activity): ?>
                            <tr>
                                <td style="font-size: 0.75rem; color: var(--text-secondary);">
                                    <?php echo date('H:i:s', strtotime($activity['created_at'])); ?>
                                </td>
                                <td>
                                    <?php
                                    $badgeClass = 'badge-secondary';
                                    $icon = 'fas fa-info';
                                    
                                    if ($activity['type'] === 'payment_success') {
                                        $badgeClass = 'badge-success';
                                        $icon = 'fas fa-check';
                                    } elseif ($activity['type'] === 'payment_error') {
                                        $badgeClass = 'badge-danger';
                                        $icon = 'fas fa-exclamation-triangle';
                                    } elseif ($activity['type'] === 'payment_attempt') {
                                        $badgeClass = 'badge-info';
                                        $icon = 'fas fa-credit-card';
                                    }
                                    ?>
                                    <span class="badge <?php echo $badgeClass; ?>">
                                        <i class="<?php echo $icon; ?>"></i>
                                        <?php echo ucfirst(str_replace('_', ' ', $activity['type'])); ?>
                                    </span>
                                </td>
                                <td style="font-size: 0.875rem;">
                                    <?php echo htmlspecialchars(substr($activity['message'], 0, 80)) . (strlen($activity['message']) > 80 ? '...' : ''); ?>
                                </td>
                                <td style="font-size: 0.75rem; color: var(--text-secondary);">
                                    <?php echo htmlspecialchars($activity['site_host'] ?? 'N/A'); ?>
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
// Gráfico de pagos
const ctx = document.getElementById('paymentsChart').getContext('2d');
const chartData = <?php echo json_encode($chartData); ?>;

const chart = new Chart(ctx, {
    type: 'line',
    data: {
        labels: chartData.map(item => {
            const date = new Date(item.date);
            return date.toLocaleDateString('es-ES', { month: 'short', day: 'numeric' });
        }),
        datasets: [{
            label: 'Pagos',
            data: chartData.map(item => item.total),
            borderColor: '#4f46e5',
            backgroundColor: 'rgba(79, 70, 229, 0.1)',
            borderWidth: 3,
            fill: true,
            tension: 0.4,
            pointBackgroundColor: '#4f46e5',
            pointBorderColor: '#ffffff',
            pointBorderWidth: 3,
            pointRadius: 6,
            pointHoverRadius: 8
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            }
        },
        scales: {
            x: {
                grid: {
                    display: false
                },
                border: {
                    display: false
                }
            },
            y: {
                beginAtZero: true,
                grid: {
                    color: 'rgba(0, 0, 0, 0.05)'
                },
                border: {
                    display: false
                },
                ticks: {
                    precision: 0
                }
            }
        },
        elements: {
            point: {
                hoverBackgroundColor: '#4f46e5'
            }
        }
    }
});

// Auto-refresh cada 30 segundos
setInterval(function() {
    location.reload();
}, 30000);
</script>

<?php renderAdminFooter(); ?>