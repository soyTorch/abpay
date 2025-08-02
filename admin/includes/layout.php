<?php
/**
 * Layout base para el panel de administración
 */

require_once __DIR__ . '/auth.php';

function renderAdminHeader($title = 'Panel de Administración', $current_page = '') {
    $user = AdminAuth::getCurrentUser();
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo htmlspecialchars($title); ?> - ABPay Admin</title>
        <link rel="stylesheet" href="assets/css/admin.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    </head>
    <body>
        <div class="admin-wrapper">
            <!-- Sidebar -->
            <aside class="sidebar">
                <div class="sidebar-header">
                    <h2><i class="fas fa-credit-card"></i> ABPay</h2>
                </div>
                
                <nav class="sidebar-nav">
                    <ul class="nav-menu">
                        <li class="nav-item <?php echo $current_page === 'dashboard' ? 'active' : ''; ?>">
                            <a href="index.php" class="nav-link">
                                <i class="fas fa-tachometer-alt"></i>
                                <span>Dashboard</span>
                            </a>
                        </li>
                        
                        <li class="nav-item <?php echo $current_page === 'sites' ? 'active' : ''; ?>">
                            <a href="sites.php" class="nav-link">
                                <i class="fas fa-globe"></i>
                                <span>Sitios</span>
                            </a>
                        </li>
                        
                        <li class="nav-item <?php echo $current_page === 'accounts' ? 'active' : ''; ?>">
                            <a href="accounts.php" class="nav-link">
                                <i class="fab fa-paypal"></i>
                                <span>Cuentas PayPal</span>
                            </a>
                        </li>
                        
                        <li class="nav-item <?php echo $current_page === 'assignments' ? 'active' : ''; ?>">
                            <a href="assignments.php" class="nav-link">
                                <i class="fas fa-link"></i>
                                <span>Asignaciones</span>
                            </a>
                        </li>
                        
                        <li class="nav-item <?php echo $current_page === 'reports' ? 'active' : ''; ?>">
                            <a href="reports.php" class="nav-link">
                                <i class="fas fa-chart-bar"></i>
                                <span>Reportes</span>
                            </a>
                        </li>
                        
                        <li class="nav-item <?php echo $current_page === 'logs' ? 'active' : ''; ?>">
                            <a href="logs.php" class="nav-link">
                                <i class="fas fa-file-alt"></i>
                                <span>Logs</span>
                            </a>
                        </li>
                        
                        <li class="nav-item <?php echo $current_page === 'settings' ? 'active' : ''; ?>">
                            <a href="settings.php" class="nav-link">
                                <i class="fas fa-cog"></i>
                                <span>Configuración</span>
                            </a>
                        </li>
                    </ul>
                </nav>
                
                <div class="sidebar-footer">
                    <div class="user-info">
                        <div class="user-avatar">
                            <i class="fas fa-user"></i>
                        </div>
                        <div class="user-details">
                            <span class="user-name"><?php echo htmlspecialchars($user['name']); ?></span>
                            <span class="user-role">Administrador</span>
                        </div>
                    </div>
                    <a href="logout.php" class="logout-btn" title="Cerrar sesión">
                        <i class="fas fa-sign-out-alt"></i>
                    </a>
                </div>
            </aside>
            
            <!-- Main Content -->
            <main class="main-content">
                <header class="content-header">
                    <div class="header-left">
                        <button class="sidebar-toggle" id="sidebarToggle">
                            <i class="fas fa-bars"></i>
                        </button>
                        <h1 class="page-title"><?php echo htmlspecialchars($title); ?></h1>
                    </div>
                    
                    <div class="header-right">
                        <div class="header-stats">
                            <span class="stat-item">
                                <i class="fas fa-server"></i>
                                <?php echo $_SERVER['HTTP_HOST']; ?>
                            </span>
                            <span class="stat-item">
                                <i class="fas fa-clock"></i>
                                <?php echo date('H:i'); ?>
                            </span>
                        </div>
                    </div>
                </header>
                
                <div class="content-body">
    <?php
}

function renderAdminFooter() {
    ?>
                </div>
            </main>
        </div>
        
        <script src="assets/js/admin.js"></script>
    </body>
    </html>
    <?php
}

function showAlert($type, $message) {
    $icons = [
        'success' => 'fas fa-check-circle',
        'error' => 'fas fa-exclamation-triangle',
        'warning' => 'fas fa-exclamation-circle',
        'info' => 'fas fa-info-circle'
    ];
    
    $icon = $icons[$type] ?? $icons['info'];
    
    echo "<div class='alert alert-{$type}'>";
    echo "<i class='{$icon}'></i>";
    echo "<span>" . htmlspecialchars($message) . "</span>";
    echo "<button class='alert-close' onclick='this.parentElement.remove()'>";
    echo "<i class='fas fa-times'></i>";
    echo "</button>";
    echo "</div>";
}

function renderCard($title, $content, $actions = '') {
    echo "<div class='card'>";
    echo "<div class='card-header'>";
    echo "<h3 class='card-title'>" . htmlspecialchars($title) . "</h3>";
    if ($actions) {
        echo "<div class='card-actions'>" . $actions . "</div>";
    }
    echo "</div>";
    echo "<div class='card-body'>";
    echo $content;
    echo "</div>";
    echo "</div>";
}

function renderStatsCard($title, $value, $icon, $color = 'blue', $subtitle = '') {
    echo "<div class='stats-card stats-{$color}'>";
    echo "<div class='stats-icon'>";
    echo "<i class='{$icon}'></i>";
    echo "</div>";
    echo "<div class='stats-content'>";
    echo "<div class='stats-value'>" . htmlspecialchars($value) . "</div>";
    echo "<div class='stats-title'>" . htmlspecialchars($title) . "</div>";
    if ($subtitle) {
        echo "<div class='stats-subtitle'>" . htmlspecialchars($subtitle) . "</div>";
    }
    echo "</div>";
    echo "</div>";
}
?>