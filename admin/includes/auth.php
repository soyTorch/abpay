<?php
/**
 * Sistema de autenticación simple para el panel de administración
 */

session_start();

class AdminAuth {
    private static $admin_users = [
        'admin' => [
            'password' => 'admin123', // Cambiar en producción
            'token' => 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJhZG1pbiI6ImRpZGFjIn0.uXqn5qDvcpUk2-DjuFOcmis6nNTDhjAudgqnmDpIuM8',
            'name' => 'Administrador',
            'permissions' => ['*'] // Todos los permisos
        ]
    ];

    public static function login($username, $password) {
        if (isset(self::$admin_users[$username]) && 
            self::$admin_users[$username]['password'] === $password) {
            
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_user'] = $username;
            $_SESSION['admin_name'] = self::$admin_users[$username]['name'];
            $_SESSION['admin_permissions'] = self::$admin_users[$username]['permissions'];
            $_SESSION['admin_token'] = self::$admin_users[$username]['token'];
            
            return true;
        }
        return false;
    }

    public static function logout() {
        session_destroy();
        header('Location: login.php');
        exit;
    }

    public static function isLoggedIn() {
        return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
    }

    public static function requireAuth() {
        if (!self::isLoggedIn()) {
            header('Location: login.php');
            exit;
        }
    }

    public static function getCurrentUser() {
        if (!self::isLoggedIn()) {
            return null;
        }
        
        return [
            'username' => $_SESSION['admin_user'],
            'name' => $_SESSION['admin_name'],
            'permissions' => $_SESSION['admin_permissions'],
            'token' => $_SESSION['admin_token']
        ];
    }

    public static function hasPermission($permission) {
        if (!self::isLoggedIn()) {
            return false;
        }
        
        $permissions = $_SESSION['admin_permissions'];
        return in_array('*', $permissions) || in_array($permission, $permissions);
    }

    public static function checkTokenAuth() {
        // Compatibilidad con el sistema de token URL anterior
        if (isset($_GET['u'], $_GET['token']) && 
            $_GET['u'] === 'admin' && 
            $_GET['token'] === self::$admin_users['admin']['token']) {
            
            self::login('admin', self::$admin_users['admin']['password']);
            return true;
        }
        
        return self::isLoggedIn();
    }
}

// Verificar si solo se está accediendo por URL del sitio matriz
function checkMatrixSite() {
    require_once __DIR__ . '/../../src/includes/Database.php';
    $isMatrix = Database::isMatrix();
    
    if (!$isMatrix) {
        http_response_code(404);
        echo '<h1>404 Not Found</h1>';
        exit;
    }
}
?>