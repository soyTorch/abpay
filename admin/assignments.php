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

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'create') {
            $site_id = (int)($_POST['site_id'] ?? 0);
            $paypal_account_id = (int)($_POST['paypal_account_id'] ?? 0);
            
            if (!$site_id || !$paypal_account_id) {
                throw new Exception('Debes seleccionar un sitio y una cuenta PayPal.');
            }
            
            // Verificar que no exista la asignación
            $stmt = $db->prepare("
                SELECT id FROM site_paypal_accounts 
                WHERE site_id = ? AND paypal_account_id = ?
            ");
            $stmt->execute([$site_id, $paypal_account_id]);
            if ($stmt->fetch()) {
                throw new Exception('Esta asignación ya existe.');
            }
            
            $stmt = $db->prepare("
                INSERT INTO site_paypal_accounts (site_id, paypal_account_id, is_active) 
                VALUES (?, ?, TRUE)
            ");
            $stmt->execute([$site_id, $paypal_account_id]);
            
            $message = "Asignación creada exitosamente.";
            $messageType = 'success';
            
        } elseif ($action === 'toggle') {
            $id = (int)($_POST['id'] ?? 0);
            $is_active = (int)($_POST['is_active'] ?? 0);
            
            $stmt = $db->prepare("
                UPDATE site_paypal_accounts 
                SET is_active = ?, updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $stmt->execute([$is_active, $id]);
            
            $message = "Estado de asignación actualizado exitosamente.";
            $messageType = 'success';
            
        } elseif ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            
            $stmt = $db->prepare("DELETE FROM site_paypal_accounts WHERE id = ?");
            $stmt->execute([$id]);
            
            $message = "Asignación eliminada exitosamente.";
            $messageType = 'success';
        }
        
    } catch (Exception $e) {
        $message = $e->getMessage();
        $messageType = 'error';
    }
}

// Obtener asignaciones con información completa
$stmt = $db->prepare("
    SELECT 
        spa.id,
        spa.is_active,
        spa.created_at,
        s.id as site_id,
        s.host as site_host,
        s.name as site_name,
        s.is_matrix as site_is_matrix,
        s.is_active as site_is_active,
        pa.id as paypal_account_id,
        pa.email as paypal_email,
        pa.daily_limit as paypal_daily_limit,
        pa.currency as paypal_currency,
        pa.is_active as paypal_is_active,
        COALESCE(pc.count, 0) as today_usage
    FROM site_paypal_accounts spa
    INNER JOIN sites s ON spa.site_id = s.id
    INNER JOIN paypal_accounts pa ON spa.paypal_account_id = pa.id
    LEFT JOIN payment_counters pc ON pa.id = pc.paypal_account_id AND pc.date = CURRENT_DATE()
    ORDER BY s.host, pa.email
");
$stmt->execute();
$assignments = $stmt->fetchAll();

// Obtener sitios disponibles
$stmt = $db->prepare("
    SELECT id, host, name, is_matrix, is_active 
    FROM sites 
    WHERE is_active = TRUE 
    ORDER BY host
");
$stmt->execute();
$sites = $stmt->fetchAll();

// Obtener cuentas PayPal disponibles
$stmt = $db->prepare("
    SELECT id, email, daily_limit, currency, is_active 
    FROM paypal_accounts 
    WHERE is_active = TRUE 
    ORDER BY email
");
$stmt->execute();
$accounts = $stmt->fetchAll();

// Estadísticas
$stmt = $db->prepare("
    SELECT 
        COUNT(*) as total_assignments,
        SUM(CASE WHEN spa.is_active = TRUE THEN 1 ELSE 0 END) as active_assignments,
        COUNT(DISTINCT spa.site_id) as sites_with_accounts,
        COUNT(DISTINCT spa.paypal_account_id) as accounts_with_sites
    FROM site_paypal_accounts spa
    INNER JOIN sites s ON spa.site_id = s.id AND s.is_active = TRUE
    INNER JOIN paypal_accounts pa ON spa.paypal_account_id = pa.id AND pa.is_active = TRUE
");
$stmt->execute();
$stats = $stmt->fetch();

renderAdminHeader('Asignaciones Sitios-Cuentas', 'assignments');
?>

<?php if ($message): ?>
    <?php showAlert($messageType, $message); ?>
<?php endif; ?>

<!-- Estadísticas -->
<div class="stats-grid" style="margin-bottom: 2rem;">
    <?php 
    renderStatsCard('Total Asignaciones', $stats['total_assignments'], 'fas fa-link', 'blue');
    renderStatsCard('Asignaciones Activas', $stats['active_assignments'], 'fas fa-check-circle', 'green');
    renderStatsCard('Sitios Configurados', $stats['sites_with_accounts'], 'fas fa-globe', 'purple');
    renderStatsCard('Cuentas Asignadas', $stats['accounts_with_sites'], 'fab fa-paypal', 'yellow');
    ?>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fas fa-link"></i>
            Asignaciones Sitio → Cuenta PayPal
        </h3>
        <div class="card-actions">
            <button class="btn btn-primary" data-modal="createAssignmentModal">
                <i class="fas fa-plus"></i>
                Nueva Asignación
            </button>
        </div>
    </div>
    <div class="card-body">
        <?php if (empty($assignments)): ?>
            <div style="text-align: center; padding: 2rem;">
                <i class="fas fa-link" style="font-size: 3rem; color: var(--text-light); margin-bottom: 1rem;"></i>
                <h4>No hay asignaciones configuradas</h4>
                <p style="color: var(--text-secondary);">Asigna cuentas PayPal a tus sitios para comenzar a procesar pagos.</p>
                <button class="btn btn-primary" data-modal="createAssignmentModal">
                    <i class="fas fa-plus"></i>
                    Crear Primera Asignación
                </button>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Sitio</th>
                            <th>Cuenta PayPal</th>
                            <th>Límite Diario</th>
                            <th>Uso Hoy</th>
                            <th>Progreso</th>
                            <th>Moneda</th>
                            <th>Estado</th>
                            <th>Creado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($assignments as $assignment): ?>
                            <tr>
                                <td>
                                    <div>
                                        <strong><?php echo htmlspecialchars($assignment['site_host']); ?></strong>
                                        <br>
                                        <small style="color: var(--text-secondary);">
                                            <?php echo htmlspecialchars($assignment['site_name']); ?>
                                        </small>
                                    </div>
                                    <?php if ($assignment['site_is_matrix']): ?>
                                        <span class="badge badge-warning">
                                            <i class="fas fa-crown"></i>
                                            Matriz
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($assignment['paypal_email']); ?></strong>
                                </td>
                                <td>
                                    <span class="badge badge-info">
                                        <?php echo $assignment['paypal_daily_limit']; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge <?php echo $assignment['today_usage'] > 0 ? 'badge-success' : 'badge-secondary'; ?>">
                                        <?php echo $assignment['today_usage']; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php 
                                    $usage_percent = ($assignment['paypal_daily_limit'] > 0) 
                                        ? round(($assignment['today_usage'] / $assignment['paypal_daily_limit']) * 100, 1) 
                                        : 0;
                                    ?>
                                    <div style="display: flex; align-items: center; gap: 0.5rem; min-width: 100px;">
                                        <div style="flex: 1; background: var(--bg-tertiary); height: 8px; border-radius: 4px; overflow: hidden;">
                                            <div style="
                                                height: 100%; 
                                                width: <?php echo min($usage_percent, 100); ?>%; 
                                                background: <?php 
                                                    if ($usage_percent >= 90) echo 'var(--error-color)';
                                                    elseif ($usage_percent >= 70) echo 'var(--warning-color)';
                                                    else echo 'var(--success-color)';
                                                ?>;
                                                transition: width 0.3s ease;
                                            "></div>
                                        </div>
                                        <span style="font-size: 0.75rem; color: var(--text-secondary); min-width: 35px;">
                                            <?php echo $usage_percent; ?>%
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge badge-secondary">
                                        <?php echo htmlspecialchars($assignment['paypal_currency']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="display: flex; flex-direction: column; gap: 0.25rem;">
                                        <?php if ($assignment['is_active']): ?>
                                            <span class="badge badge-success">
                                                <i class="fas fa-check"></i>
                                                Activo
                                            </span>
                                        <?php else: ?>
                                            <span class="badge badge-danger">
                                                <i class="fas fa-times"></i>
                                                Inactivo
                                            </span>
                                        <?php endif; ?>
                                        
                                        <?php if (!$assignment['site_is_active']): ?>
                                            <span class="badge badge-warning" title="El sitio está inactivo">
                                                <i class="fas fa-exclamation-triangle"></i>
                                                Sitio Inactivo
                                            </span>
                                        <?php endif; ?>
                                        
                                        <?php if (!$assignment['paypal_is_active']): ?>
                                            <span class="badge badge-warning" title="La cuenta PayPal está inactiva">
                                                <i class="fas fa-exclamation-triangle"></i>
                                                Cuenta Inactiva
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td style="font-size: 0.75rem; color: var(--text-secondary);">
                                    <?php echo date('d/m/Y', strtotime($assignment['created_at'])); ?>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 0.5rem;">
                                        <button class="btn btn-<?php echo $assignment['is_active'] ? 'warning' : 'success'; ?> btn-sm" 
                                                onclick="toggleAssignment(<?php echo $assignment['id']; ?>, <?php echo $assignment['is_active'] ? 0 : 1; ?>)"
                                                title="<?php echo $assignment['is_active'] ? 'Desactivar' : 'Activar'; ?>">
                                            <i class="fas fa-<?php echo $assignment['is_active'] ? 'pause' : 'play'; ?>"></i>
                                        </button>
                                        <button class="btn btn-danger btn-sm" 
                                                onclick="deleteAssignment(<?php echo $assignment['id']; ?>, '<?php echo htmlspecialchars($assignment['site_host']); ?>', '<?php echo htmlspecialchars($assignment['paypal_email']); ?>')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Crear Asignación -->
<div id="createAssignmentModal" class="modal">
    <div class="modal-overlay"></div>
    <div class="modal-content">
        <div class="modal-header">
            <h3>Crear Nueva Asignación</h3>
            <button class="modal-close">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form method="POST" data-validate>
            <input type="hidden" name="action" value="create">
            <div class="modal-body">
                <div class="form-group">
                    <label for="site_id" class="form-label">Sitio *</label>
                    <select id="site_id" name="site_id" class="form-control" required>
                        <option value="">Selecciona un sitio</option>
                        <?php foreach ($sites as $site): ?>
                            <option value="<?php echo $site['id']; ?>">
                                <?php echo htmlspecialchars($site['host']); ?> 
                                - <?php echo htmlspecialchars($site['name']); ?>
                                <?php if ($site['is_matrix']): ?> (Matriz)<?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="paypal_account_id" class="form-label">Cuenta PayPal *</label>
                    <select id="paypal_account_id" name="paypal_account_id" class="form-control" required>
                        <option value="">Selecciona una cuenta</option>
                        <?php foreach ($accounts as $account): ?>
                            <option value="<?php echo $account['id']; ?>">
                                <?php echo htmlspecialchars($account['email']); ?>
                                (Límite: <?php echo $account['daily_limit']; ?>, 
                                 Moneda: <?php echo $account['currency']; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div style="background: var(--bg-secondary); padding: 1rem; border-radius: var(--border-radius); margin-top: 1rem;">
                    <h4 style="font-size: 0.875rem; margin-bottom: 0.5rem;">
                        <i class="fas fa-info-circle"></i>
                        Información
                    </h4>
                    <p style="font-size: 0.75rem; color: var(--text-secondary); margin: 0;">
                        Las asignaciones permiten que un sitio use una cuenta PayPal específica para procesar pagos. 
                        Un sitio puede tener múltiples cuentas asignadas para balanceo de carga.
                    </p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-modal-close>Cancelar</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i>
                    Crear Asignación
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleAssignment(id, newState) {
    const action = newState ? 'activar' : 'desactivar';
    
    AdminUtils.confirmAction(
        `¿Estás seguro de que quieres ${action} esta asignación?`,
        function() {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="toggle">
                <input type="hidden" name="id" value="${id}">
                <input type="hidden" name="is_active" value="${newState}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    );
}

function deleteAssignment(id, siteHost, paypalEmail) {
    AdminUtils.confirmAction(
        `¿Estás seguro de que quieres eliminar la asignación?\n\nSitio: ${siteHost}\nCuenta: ${paypalEmail}\n\nEsta acción no se puede deshacer.`,
        function() {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="${id}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    );
}

// Actualizar datos en tiempo real
setInterval(function() {
    // Solo actualizar si no hay modales abiertos
    if (!document.querySelector('.modal.active')) {
        location.reload();
    }
}, 30000);
</script>

<?php renderAdminFooter(); ?>