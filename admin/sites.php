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
            $host = trim($_POST['host'] ?? '');
            $name = trim($_POST['name'] ?? '');
            $is_matrix = isset($_POST['is_matrix']) ? 1 : 0;
            $priority = (int)($_POST['priority'] ?? 0);
            
            if (empty($host) || empty($name)) {
                throw new Exception('Host y nombre son requeridos.');
            }
            
            // Validar formato del host
            if (!preg_match('/^[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $host)) {
                throw new Exception('El formato del host no es válido.');
            }
            
            $stmt = $db->prepare("
                INSERT INTO sites (host, name, is_matrix, priority, is_active) 
                VALUES (?, ?, ?, ?, TRUE)
            ");
            $stmt->execute([$host, $name, $is_matrix, $priority]);
            
            $message = "Sitio creado exitosamente.";
            $messageType = 'success';
            
        } elseif ($action === 'update') {
            $id = (int)($_POST['id'] ?? 0);
            $host = trim($_POST['host'] ?? '');
            $name = trim($_POST['name'] ?? '');
            $is_matrix = isset($_POST['is_matrix']) ? 1 : 0;
            $priority = (int)($_POST['priority'] ?? 0);
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            if (empty($host) || empty($name)) {
                throw new Exception('Host y nombre son requeridos.');
            }
            
            $stmt = $db->prepare("
                UPDATE sites 
                SET host = ?, name = ?, is_matrix = ?, priority = ?, is_active = ?, updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $stmt->execute([$host, $name, $is_matrix, $priority, $is_active, $id]);
            
            $message = "Sitio actualizado exitosamente.";
            $messageType = 'success';
            
        } elseif ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            
            // Verificar que no sea el sitio actual
            $stmt = $db->prepare("SELECT host FROM sites WHERE id = ?");
            $stmt->execute([$id]);
            $site = $stmt->fetch();
            
            if ($site && $site['host'] === $_SERVER['HTTP_HOST']) {
                throw new Exception('No puedes eliminar el sitio actual.');
            }
            
            // Verificar si tiene cuentas asignadas
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM site_paypal_accounts WHERE site_id = ?");
            $stmt->execute([$id]);
            $hasAccounts = $stmt->fetch()['count'] > 0;
            
            if ($hasAccounts) {
                throw new Exception('No puedes eliminar un sitio que tiene cuentas PayPal asignadas.');
            }
            
            $stmt = $db->prepare("DELETE FROM sites WHERE id = ?");
            $stmt->execute([$id]);
            
            $message = "Sitio eliminado exitosamente.";
            $messageType = 'success';
        }
        
    } catch (Exception $e) {
        $message = $e->getMessage();
        $messageType = 'error';
    }
}

// Obtener lista de sitios
$stmt = $db->prepare("
    SELECT 
        s.*,
        COUNT(spa.id) as assigned_accounts
    FROM sites s
    LEFT JOIN site_paypal_accounts spa ON s.id = spa.site_id AND spa.is_active = TRUE
    GROUP BY s.id
    ORDER BY s.priority ASC, s.created_at DESC
");
$stmt->execute();
$sites = $stmt->fetchAll();

renderAdminHeader('Gestión de Sitios', 'sites');
?>

<?php if ($message): ?>
    <?php showAlert($messageType, $message); ?>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fas fa-globe"></i>
            Sitios Registrados
        </h3>
        <div class="card-actions">
            <button class="btn btn-primary" data-modal="createSiteModal">
                <i class="fas fa-plus"></i>
                Nuevo Sitio
            </button>
        </div>
    </div>
    <div class="card-body">
        <?php if (empty($sites)): ?>
            <div style="text-align: center; padding: 2rem;">
                <i class="fas fa-globe" style="font-size: 3rem; color: var(--text-light); margin-bottom: 1rem;"></i>
                <h4>No hay sitios registrados</h4>
                <p style="color: var(--text-secondary);">Crea tu primer sitio para comenzar.</p>
                <button class="btn btn-primary" data-modal="createSiteModal">
                    <i class="fas fa-plus"></i>
                    Crear Primer Sitio
                </button>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Host</th>
                            <th>Nombre</th>
                            <th>Tipo</th>
                            <th>Prioridad</th>
                            <th>Cuentas</th>
                            <th>Estado</th>
                            <th>Creado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sites as $site): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($site['host']); ?></strong>
                                    <?php if ($site['host'] === $_SERVER['HTTP_HOST']): ?>
                                        <span class="badge badge-info">
                                            <i class="fas fa-star"></i>
                                            Actual
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($site['name']); ?></td>
                                <td>
                                    <?php if ($site['is_matrix']): ?>
                                        <span class="badge badge-warning">
                                            <i class="fas fa-crown"></i>
                                            Matriz
                                        </span>
                                    <?php else: ?>
                                        <span class="badge badge-secondary">
                                            <i class="fas fa-sitemap"></i>
                                            Secundario
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge badge-info"><?php echo $site['priority']; ?></span>
                                </td>
                                <td>
                                    <span class="badge badge-secondary">
                                        <i class="fab fa-paypal"></i>
                                        <?php echo $site['assigned_accounts']; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($site['is_active']): ?>
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
                                    <?php echo date('d/m/Y', strtotime($site['created_at'])); ?>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 0.5rem;">
                                        <button class="btn btn-secondary btn-sm" onclick="editSite(<?php echo htmlspecialchars(json_encode($site)); ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <?php if ($site['host'] !== $_SERVER['HTTP_HOST']): ?>
                                            <button class="btn btn-danger btn-sm" onclick="deleteSite(<?php echo $site['id']; ?>, '<?php echo htmlspecialchars($site['name']); ?>')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        <?php endif; ?>
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

<!-- Modal Crear Sitio -->
<div id="createSiteModal" class="modal">
    <div class="modal-overlay"></div>
    <div class="modal-content">
        <div class="modal-header">
            <h3>Crear Nuevo Sitio</h3>
            <button class="modal-close">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form method="POST" data-validate>
            <input type="hidden" name="action" value="create">
            <div class="modal-body">
                <div class="form-group">
                    <label for="host" class="form-label">Host *</label>
                    <input type="text" id="host" name="host" class="form-control" 
                           placeholder="ejemplo.com" required>
                    <small style="color: var(--text-secondary);">
                        Dominio completo sin http:// ni www
                    </small>
                </div>
                
                <div class="form-group">
                    <label for="name" class="form-label">Nombre *</label>
                    <input type="text" id="name" name="name" class="form-control" 
                           placeholder="Nombre descriptivo del sitio" required>
                </div>
                
                <div class="form-group">
                    <label for="priority" class="form-label">Prioridad</label>
                    <input type="number" id="priority" name="priority" class="form-control" 
                           value="0" min="0" max="100">
                    <small style="color: var(--text-secondary);">
                        Mayor prioridad = menor número (0 = máxima prioridad)
                    </small>
                </div>
                
                <div class="form-group">
                    <label class="form-label">
                        <input type="checkbox" name="is_matrix" style="margin-right: 0.5rem;">
                        Sitio Matriz
                    </label>
                    <small style="color: var(--text-secondary); display: block; margin-top: 0.25rem;">
                        Los sitios matriz pueden distribuir pagos a otros sitios
                    </small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-modal-close>Cancelar</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i>
                    Crear Sitio
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Editar Sitio -->
<div id="editSiteModal" class="modal">
    <div class="modal-overlay"></div>
    <div class="modal-content">
        <div class="modal-header">
            <h3>Editar Sitio</h3>
            <button class="modal-close">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form method="POST" data-validate>
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="edit_id">
            <div class="modal-body">
                <div class="form-group">
                    <label for="edit_host" class="form-label">Host *</label>
                    <input type="text" id="edit_host" name="host" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_name" class="form-label">Nombre *</label>
                    <input type="text" id="edit_name" name="name" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_priority" class="form-label">Prioridad</label>
                    <input type="number" id="edit_priority" name="priority" class="form-control" 
                           min="0" max="100">
                </div>
                
                <div class="form-group">
                    <label class="form-label">
                        <input type="checkbox" name="is_matrix" id="edit_is_matrix" style="margin-right: 0.5rem;">
                        Sitio Matriz
                    </label>
                </div>
                
                <div class="form-group">
                    <label class="form-label">
                        <input type="checkbox" name="is_active" id="edit_is_active" style="margin-right: 0.5rem;">
                        Sitio Activo
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

<style>
/* Modal Styles */
.modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 9999;
    display: none;
    align-items: center;
    justify-content: center;
}

.modal.active {
    display: flex;
}

.modal-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(4px);
}

.modal-content {
    background: white;
    border-radius: var(--border-radius-lg);
    box-shadow: var(--shadow-lg);
    width: 90%;
    max-width: 500px;
    max-height: 90vh;
    overflow: hidden;
    position: relative;
    z-index: 1;
}

.modal-header {
    padding: 1.5rem;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: var(--bg-secondary);
}

.modal-header h3 {
    margin: 0;
    font-size: 1.125rem;
    font-weight: 600;
}

.modal-close {
    background: none;
    border: none;
    font-size: 1.25rem;
    cursor: pointer;
    color: var(--text-secondary);
    padding: 0.5rem;
    border-radius: 50%;
    transition: var(--transition);
}

.modal-close:hover {
    background: var(--bg-tertiary);
    color: var(--text-primary);
}

.modal-body {
    padding: 1.5rem;
    max-height: 60vh;
    overflow-y: auto;
}

.modal-footer {
    padding: 1.5rem;
    border-top: 1px solid var(--border-color);
    display: flex;
    gap: 1rem;
    justify-content: flex-end;
    background: var(--bg-secondary);
}

.form-control.is-invalid {
    border-color: var(--error-color);
    box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1);
}

.field-error {
    color: var(--error-color);
    font-size: 0.75rem;
    margin-top: 0.25rem;
}
</style>

<script>
function editSite(site) {
    document.getElementById('edit_id').value = site.id;
    document.getElementById('edit_host').value = site.host;
    document.getElementById('edit_name').value = site.name;
    document.getElementById('edit_priority').value = site.priority;
    document.getElementById('edit_is_matrix').checked = site.is_matrix == 1;
    document.getElementById('edit_is_active').checked = site.is_active == 1;
    
    AdminUtils.openModal('editSiteModal');
}

function deleteSite(id, name) {
    AdminUtils.confirmAction(
        `¿Estás seguro de que quieres eliminar el sitio "${name}"?\n\nEsta acción no se puede deshacer.`,
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
</script>

<?php renderAdminFooter(); ?>