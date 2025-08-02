/**
 * JavaScript para el panel de administración ABPay
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize sidebar toggle
    initSidebarToggle();
    
    // Initialize alerts auto-close
    initAlerts();
    
    // Initialize tooltips
    initTooltips();
    
    // Initialize form validations
    initFormValidations();
    
    // Initialize modals
    initModals();
});

/**
 * Sidebar toggle functionality
 */
function initSidebarToggle() {
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.querySelector('.sidebar');
    
    if (sidebarToggle && sidebar) {
        // Load saved state
        const isCollapsed = localStorage.getItem('sidebar-collapsed') === 'true';
        if (isCollapsed) {
            sidebar.classList.add('collapsed');
        }
        
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('collapsed');
            const collapsed = sidebar.classList.contains('collapsed');
            localStorage.setItem('sidebar-collapsed', collapsed);
        });
        
        // Mobile sidebar toggle
        const overlay = document.createElement('div');
        overlay.className = 'sidebar-overlay';
        document.body.appendChild(overlay);
        
        function toggleMobileSidebar() {
            sidebar.classList.toggle('open');
            overlay.classList.toggle('active');
            document.body.classList.toggle('sidebar-open');
        }
        
        sidebarToggle.addEventListener('click', function() {
            if (window.innerWidth <= 768) {
                toggleMobileSidebar();
            }
        });
        
        overlay.addEventListener('click', toggleMobileSidebar);
    }
}

/**
 * Auto-close alerts after 5 seconds
 */
function initAlerts() {
    const alerts = document.querySelectorAll('.alert');
    
    alerts.forEach(alert => {
        // Auto close after 5 seconds
        setTimeout(() => {
            if (alert.parentNode) {
                fadeOut(alert);
            }
        }, 5000);
        
        // Manual close button
        const closeBtn = alert.querySelector('.alert-close');
        if (closeBtn) {
            closeBtn.addEventListener('click', () => {
                fadeOut(alert);
            });
        }
    });
}

/**
 * Fade out animation
 */
function fadeOut(element) {
    element.style.opacity = '0';
    element.style.transform = 'translateY(-10px)';
    element.style.transition = 'all 0.3s ease';
    
    setTimeout(() => {
        if (element.parentNode) {
            element.parentNode.removeChild(element);
        }
    }, 300);
}

/**
 * Initialize tooltips
 */
function initTooltips() {
    const elementsWithTooltip = document.querySelectorAll('[title]');
    
    elementsWithTooltip.forEach(element => {
        element.addEventListener('mouseenter', showTooltip);
        element.addEventListener('mouseleave', hideTooltip);
    });
}

function showTooltip(e) {
    const element = e.target;
    const text = element.getAttribute('title');
    
    if (!text) return;
    
    // Remove title to prevent browser tooltip
    element.setAttribute('data-original-title', text);
    element.removeAttribute('title');
    
    // Create tooltip
    const tooltip = document.createElement('div');
    tooltip.className = 'custom-tooltip';
    tooltip.textContent = text;
    document.body.appendChild(tooltip);
    
    // Position tooltip
    const rect = element.getBoundingClientRect();
    tooltip.style.left = rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2) + 'px';
    tooltip.style.top = rect.top - tooltip.offsetHeight - 10 + 'px';
    
    // Store reference
    element._tooltip = tooltip;
}

function hideTooltip(e) {
    const element = e.target;
    
    if (element._tooltip) {
        document.body.removeChild(element._tooltip);
        element._tooltip = null;
    }
    
    // Restore title
    const originalTitle = element.getAttribute('data-original-title');
    if (originalTitle) {
        element.setAttribute('title', originalTitle);
        element.removeAttribute('data-original-title');
    }
}

/**
 * Form validations
 */
function initFormValidations() {
    const forms = document.querySelectorAll('form[data-validate]');
    
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!validateForm(form)) {
                e.preventDefault();
                showAlert('error', 'Por favor, corrige los errores en el formulario.');
            }
        });
        
        // Real-time validation
        const inputs = form.querySelectorAll('input, select, textarea');
        inputs.forEach(input => {
            input.addEventListener('blur', () => validateField(input));
            input.addEventListener('input', () => clearFieldError(input));
        });
    });
}

function validateForm(form) {
    const inputs = form.querySelectorAll('input[required], select[required], textarea[required]');
    let isValid = true;
    
    inputs.forEach(input => {
        if (!validateField(input)) {
            isValid = false;
        }
    });
    
    return isValid;
}

function validateField(input) {
    const value = input.value.trim();
    let isValid = true;
    let errorMessage = '';
    
    // Required validation
    if (input.hasAttribute('required') && !value) {
        isValid = false;
        errorMessage = 'Este campo es requerido.';
    }
    
    // Email validation
    else if (input.type === 'email' && value) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(value)) {
            isValid = false;
            errorMessage = 'Ingresa un email válido.';
        }
    }
    
    // URL validation
    else if (input.type === 'url' && value) {
        const urlRegex = /^https?:\/\/.+\..+/;
        if (!urlRegex.test(value)) {
            isValid = false;
            errorMessage = 'Ingresa una URL válida.';
        }
    }
    
    // Number validation
    else if (input.type === 'number' && value) {
        const min = input.getAttribute('min');
        const max = input.getAttribute('max');
        
        if (min && parseFloat(value) < parseFloat(min)) {
            isValid = false;
            errorMessage = `El valor mínimo es ${min}.`;
        } else if (max && parseFloat(value) > parseFloat(max)) {
            isValid = false;
            errorMessage = `El valor máximo es ${max}.`;
        }
    }
    
    // Show/hide error
    if (isValid) {
        clearFieldError(input);
    } else {
        showFieldError(input, errorMessage);
    }
    
    return isValid;
}

function showFieldError(input, message) {
    clearFieldError(input);
    
    input.classList.add('is-invalid');
    
    const errorDiv = document.createElement('div');
    errorDiv.className = 'field-error';
    errorDiv.textContent = message;
    
    input.parentNode.appendChild(errorDiv);
}

function clearFieldError(input) {
    input.classList.remove('is-invalid');
    
    const existingError = input.parentNode.querySelector('.field-error');
    if (existingError) {
        existingError.remove();
    }
}

/**
 * Modal functionality
 */
function initModals() {
    // Open modal buttons
    document.addEventListener('click', function(e) {
        if (e.target.matches('[data-modal]')) {
            const modalId = e.target.getAttribute('data-modal');
            openModal(modalId);
        }
        
        // Close modal buttons
        if (e.target.matches('.modal-close, [data-modal-close]')) {
            closeModal(e.target.closest('.modal'));
        }
    });
    
    // Close modal on overlay click
    document.addEventListener('click', function(e) {
        if (e.target.matches('.modal-overlay')) {
            closeModal(e.target.closest('.modal'));
        }
    });
    
    // Close modal on ESC key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const openModal = document.querySelector('.modal.active');
            if (openModal) {
                closeModal(openModal);
            }
        }
    });
}

function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('active');
        document.body.classList.add('modal-open');
        
        // Focus first input
        const firstInput = modal.querySelector('input, select, textarea');
        if (firstInput) {
            setTimeout(() => firstInput.focus(), 100);
        }
    }
}

function closeModal(modal) {
    if (modal) {
        modal.classList.remove('active');
        document.body.classList.remove('modal-open');
    }
}

/**
 * AJAX utilities
 */
function ajaxRequest(url, options = {}) {
    const defaults = {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    };
    
    const config = { ...defaults, ...options };
    
    return fetch(url, config)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        });
}

/**
 * Show notification
 */
function showNotification(type, message, duration = 5000) {
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
        <div class="notification-content">
            <i class="fas fa-${getNotificationIcon(type)}"></i>
            <span>${message}</span>
        </div>
        <button class="notification-close">
            <i class="fas fa-times"></i>
        </button>
    `;
    
    document.body.appendChild(notification);
    
    // Auto remove
    setTimeout(() => {
        if (notification.parentNode) {
            notification.remove();
        }
    }, duration);
    
    // Manual close
    notification.querySelector('.notification-close').addEventListener('click', () => {
        notification.remove();
    });
}

function getNotificationIcon(type) {
    const icons = {
        success: 'check-circle',
        error: 'exclamation-triangle',
        warning: 'exclamation-circle',
        info: 'info-circle'
    };
    return icons[type] || icons.info;
}

/**
 * Confirmation dialog
 */
function confirmAction(message, callback) {
    const confirmed = confirm(message);
    if (confirmed && typeof callback === 'function') {
        callback();
    }
}

/**
 * Table utilities
 */
function initDataTables() {
    const tables = document.querySelectorAll('.data-table');
    
    tables.forEach(table => {
        // Add search functionality
        addTableSearch(table);
        
        // Add sorting
        addTableSorting(table);
        
        // Add row actions
        addTableActions(table);
    });
}

function addTableSearch(table) {
    const searchInput = table.parentNode.querySelector('.table-search');
    if (!searchInput) return;
    
    searchInput.addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase();
        const rows = table.querySelectorAll('tbody tr');
        
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(searchTerm) ? '' : 'none';
        });
    });
}

function addTableSorting(table) {
    const headers = table.querySelectorAll('th[data-sortable]');
    
    headers.forEach(header => {
        header.addEventListener('click', function() {
            const column = this.getAttribute('data-sortable');
            sortTable(table, column);
        });
    });
}

function sortTable(table, column) {
    // Implementation depends on specific needs
    console.log('Sorting table by column:', column);
}

function addTableActions(table) {
    // Add event listeners for action buttons
    table.addEventListener('click', function(e) {
        if (e.target.matches('.btn-delete')) {
            const row = e.target.closest('tr');
            const itemName = row.querySelector('td').textContent;
            
            confirmAction(
                `¿Estás seguro de que quieres eliminar "${itemName}"?`,
                () => deleteTableRow(row)
            );
        }
    });
}

function deleteTableRow(row) {
    // Animate row removal
    row.style.opacity = '0';
    row.style.transform = 'translateX(-100%)';
    row.style.transition = 'all 0.3s ease';
    
    setTimeout(() => {
        if (row.parentNode) {
            row.remove();
        }
    }, 300);
}

// Global utilities
window.AdminUtils = {
    showNotification,
    confirmAction,
    ajaxRequest,
    openModal,
    closeModal,
    validateForm
};