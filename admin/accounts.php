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
            $email = trim($_POST['email'] ?? '');
            $daily_limit = (int)($_POST['daily_limit'] ?? 4);
            $currency = trim($_POST['currency'] ?? 'EUR');
            
            if (empty($email)) {
                throw new Exception('El email es requerido.');
            }
            
            // Validar formato del email
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('El formato del email no es válido.');
            }
            
            // Verificar que no exista
            $stmt = $db->prepare("SELECT id FROM paypal_accounts WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                throw new Exception('Ya existe una cuenta con este email.');
            }
            
            $stmt = $db->prepare("
                INSERT INTO paypal_accounts (email, daily_limit, currency, is_active) 
                VALUES (?, ?, ?, TRUE)
            ");
            $stmt->execute([$email, $daily_limit, $currency]);
            
            $message = "Cuenta PayPal creada exitosamente.";
            $messageType = 'success';
            
        } elseif ($action === 'update') {
            $id = (int)($_POST['id'] ?? 0);
            $email = trim($_POST['email'] ?? '');
            $daily_limit = (int)($_POST['daily_limit'] ?? 4);
            $currency = trim($_POST['currency'] ?? 'EUR');
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            if (empty($email)) {
                throw new Exception('El email es requerido.');
            }
            
            // Verificar que no exista otro con el mismo email
            $stmt = $db->prepare("SELECT id FROM paypal_accounts WHERE email = ? AND id != ?");
            $stmt->execute([$email, $id]);
            if ($stmt->fetch()) {
                throw new Exception('Ya existe otra cuenta con este email.');
            }
            
            $stmt = $db->prepare("
                UPDATE paypal_accounts 
                SET email = ?, daily_limit = ?, currency = ?, is_active = ?, updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $stmt->execute([$email, $daily_limit, $currency, $is_active, $id]);
            
            $message = "Cuenta PayPal actualizada exitosamente.";
            $messageType = 'success';
            
        } elseif ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            
            // Verificar si está asignada a sitios
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM site_paypal_accounts WHERE paypal_account_id = ?");
            $stmt->execute([$id]);
            $isAssigned = $stmt->fetch()['count'] > 0;
            
            if ($isAssigned) {
                throw new Exception('No puedes eliminar una cuenta que está asignada a sitios.');
            }
            
            $stmt = $db->prepare("DELETE FROM paypal_accounts WHERE id = ?");
            $stmt->execute([$id]);
            
            $message = "Cuenta PayPal eliminada exitosamente.";
            $messageType = 'success';
        }
        
    } catch (Exception $e) {
        $message = $e->getMessage();
        $messageType = 'error';
    }
}

// Obtener lista de cuentas con estadísticas
$stmt = $db->prepare("
    SELECT 
        pa.*,
        COUNT(spa.id) as assigned_sites,
        COALESCE(pc.count, 0) as today_usage,
        ROUND((COALESCE(pc.count, 0) / pa.daily_limit) * 100, 1) as usage_percent
    FROM paypal_accounts pa
    LEFT JOIN site_paypal_accounts spa ON pa.id = spa.paypal_account_id AND spa.is_active = TRUE
    LEFT JOIN payment_counters pc ON pa.id = pc.paypal_account_id AND pc.date = CURRENT_DATE()
    GROUP BY pa.id
    ORDER BY pa.created_at DESC
");
$stmt->execute();
$accounts = $stmt->fetchAll();

// Obtener estadísticas generales
$stmt = $db->prepare("
    SELECT 
        COUNT(*) as total_accounts,
        SUM(CASE WHEN is_active = TRUE THEN 1 ELSE 0 END) as active_accounts,
        SUM(daily_limit) as total_daily_limit,
        COALESCE(SUM(pc.count), 0) as total_today_usage
    FROM paypal_accounts pa
    LEFT JOIN payment_counters pc ON pa.id = pc.paypal_account_id AND pc.date = CURRENT_DATE()
");
$stmt->execute();
$accountStats = $stmt->fetch();

renderAdminHeader('Gestión de Cuentas PayPal', 'accounts');
?>

<?php if ($message): ?>
    <?php showAlert($messageType, $message); ?>
<?php endif; ?>

<!-- Estadísticas -->
<div class="stats-grid" style="margin-bottom: 2rem;">
    <?php 
    renderStatsCard('Total Cuentas', $accountStats['total_accounts'], 'fab fa-paypal', 'blue');
    renderStatsCard('Cuentas Activas', $accountStats['active_accounts'], 'fas fa-check-circle', 'green');
    renderStatsCard('Límite Diario Total', $accountStats['total_daily_limit'], 'fas fa-chart-line', 'purple');
    renderStatsCard('Uso Hoy', $accountStats['total_today_usage'], 'fas fa-credit-card', 'yellow');
    ?>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fab fa-paypal"></i>
            Cuentas PayPal
        </h3>
        <div class="card-actions">
            <button class="btn btn-primary" data-modal="createAccountModal">
                <i class="fas fa-plus"></i>
                Nueva Cuenta
            </button>
        </div>
    </div>
    <div class="card-body">
        <?php if (empty($accounts)): ?>
            <div style="text-align: center; padding: 2rem;">
                <i class="fab fa-paypal" style="font-size: 3rem; color: var(--text-light); margin-bottom: 1rem;"></i>
                <h4>No hay cuentas PayPal registradas</h4>
                <p style="color: var(--text-secondary);">Agrega tu primera cuenta PayPal para comenzar.</p>
                <button class="btn btn-primary" data-modal="createAccountModal">
                    <i class="fas fa-plus"></i>
                    Crear Primera Cuenta
                </button>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Email</th>
                            <th>Límite Diario</th>
                            <th>Uso Hoy</th>
                            <th>Progreso</th>
                            <th>Moneda</th>
                            <th>Sitios</th>
                            <th>Estado</th>
                            <th>Creado</th>
                            <th>Acciones</th>
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
                                    <div style="display: flex; align-items: center; gap: 0.5rem; min-width: 100px;">
                                        <div style="flex: 1; background: var(--bg-tertiary); height: 8px; border-radius: 4px; overflow: hidden;">
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
                                        <span style="font-size: 0.75rem; color: var(--text-secondary); min-width: 35px;">
                                            <?php echo $account['usage_percent']; ?>%
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge badge-secondary">
                                        <?php echo htmlspecialchars($account['currency']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge badge-info">
                                        <i class="fas fa-globe"></i>
                                        <?php echo $account['assigned_sites']; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($account['is_active']): ?>
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
                                </td>
                                <td style="font-size: 0.75rem; color: var(--text-secondary);">
                                    <?php echo date('d/m/Y', strtotime($account['created_at'])); ?>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 0.5rem;">
                                        <button class="btn btn-secondary btn-sm" onclick="editAccount(<?php echo htmlspecialchars(json_encode($account)); ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-danger btn-sm" onclick="deleteAccount(<?php echo $account['id']; ?>, '<?php echo htmlspecialchars($account['email']); ?>')">
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

<!-- Modal Crear Cuenta -->
<div id="createAccountModal" class="modal">
    <div class="modal-overlay"></div>
    <div class="modal-content">
        <div class="modal-header">
            <h3>Crear Nueva Cuenta PayPal</h3>
            <button class="modal-close">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form method="POST" data-validate>
            <input type="hidden" name="action" value="create">
            <div class="modal-body">
                <div class="form-group">
                    <label for="email" class="form-label">Email PayPal *</label>
                    <input type="email" id="email" name="email" class="form-control" 
                           placeholder="ejemplo@paypal.com" required>
                    <small style="color: var(--text-secondary);">
                        El email asociado a tu cuenta de PayPal Business
                    </small>
                </div>
                
                <div class="form-group">
                    <label for="daily_limit" class="form-label">Límite Diario *</label>
                    <input type="number" id="daily_limit" name="daily_limit" class="form-control" 
                           value="4" min="1" max="1000" required>
                    <small style="color: var(--text-secondary);">
                        Número máximo de pagos por día para esta cuenta
                    </small>
                </div>
                
                <div class="form-group">
                    <label for="currency" class="form-label">Moneda *</label>
                    <select id="currency" name="currency" class="form-control" required>
                        <option value="EUR">EUR - Euro</option>
                        <option value="USD">USD - Dólar Americano</option>
                        <option value="GBP">GBP - Libra Esterlina</option>
                        <option value="CAD">CAD - Dólar Canadiense</option>
                        <option value="AUD">AUD - Dólar Australiano</option>
                        <option value="JPY">JPY - Yen Japonés</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-modal-close>Cancelar</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i>
                    Crear Cuenta
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Editar Cuenta -->
<div id="editAccountModal" class="modal">
    <div class="modal-overlay"></div>
    <div class="modal-content">
        <div class="modal-header">
            <h3>Editar Cuenta PayPal</h3>
            <button class="modal-close">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form method="POST" data-validate>
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="edit_id">
            <div class="modal-body">
                <div class="form-group">
                    <label for="edit_email" class="form-label">Email PayPal *</label>
                    <input type="email" id="edit_email" name="email" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_daily_limit" class="form-label">Límite Diario *</label>
                    <input type="number" id="edit_daily_limit" name="daily_limit" class="form-control" 
                           min="1" max="1000" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_currency" class="form-label">Moneda *</label>
                    <select id="edit_currency" name="currency" class="form-control" required>
                        <option value="EUR">EUR - Euro</option>
                        <option value="USD">USD - Dólar Americano</option>
                        <option value="GBP">GBP - Libra Esterlina</option>
                        <option value="CAD">CAD - Dólar Canadiense</option>
                        <option value="AUD">AUD - Dólar Australiano</option>
                        <option value="JPY">JPY - Yen Japonés</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">
                        <input type="checkbox" name="is_active" id="edit_is_active" style="margin-right: 0.5rem;">
                        Cuenta Activa
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-modal-close>Cancelar</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i>
                    Guardar Cambios
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function editAccount(account) {
    document.getElementById('edit_id').value = account.id;
    document.getElementById('edit_email').value = account.email;
    document.getElementById('edit_daily_limit').value = account.daily_limit;
    document.getElementById('edit_currency').value = account.currency;
    document.getElementById('edit_is_active').checked = account.is_active == 1;
    
    AdminUtils.openModal('editAccountModal');
}

function deleteAccount(id, email) {
    AdminUtils.confirmAction(
        `¿Estás seguro de que quieres eliminar la cuenta "${email}"?\n\nEsta acción no se puede deshacer.`,
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

// Auto-refresh usage data every 30 seconds
setInterval(function() {
    location.reload();
}, 30000);
</script>

<?php renderAdminFooter(); ?>