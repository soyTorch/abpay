<?php
error_reporting(E_ALL);
ini_set('display_errors', TRUE);
ini_set('log_errors', TRUE);
date_default_timezone_set('Europe/Madrid');
header('Content-Type: text/html; charset=utf-8');

try {

// Dependencies and config
$current_host = $_SERVER['HTTP_HOST'] ?? 'default';
$config_file = __DIR__ . '/config/' . $current_host . '.php';
if (!file_exists($config_file)) {
    $config_file = __DIR__ . '/config/default.php';
}
require_once $config_file;
require_once __DIR__ . '/src/includes/Database.php';
require_once __DIR__ . '/src/includes/Logger.php';
require_once __DIR__ . '/src/includes/PayPalManager.php';
require_once __DIR__ . '/src/includes/WooCommerceAPI.php';

// Initialize database and services
$logger = new Logger('system');
$logger->registerErrorHandler();
$paypalManager = new PayPalManager();
$woocommerce = new WooCommerceAPI();

$logger->log("Configuración cargada - Archivo: " . $config_file, true);


// MAIN SCRIPT 
$logger->log("Página de pago accedida - Iniciando procesamiento", true);
$logger->log(sprintf(
    "Información de la solicitud - IP: %s, User Agent: %s, Referer: %s",
    $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
    $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
    $_SERVER['HTTP_REFERER'] ?? 'Direct'
), true);

// Validación del ID del pedido
$logger->log("Verificando parámetro ID del pedido", true);
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $logger->logPaymentError('N/A', sprintf(
        "ID de pedido no proporcionado. Parámetros recibidos: %s",
        json_encode($_GET)
    ));
    $logger->log("Redirigiendo a página principal por ID no válido", true);
    header("Location: " . Database::getConfig('site')['host']);
    exit;
}

$encoded_id = $_GET['id'];
$order_id = base64_decode($encoded_id);

$logger->log(sprintf(
    "Decodificando ID del pedido - Encoded: %s, Decoded: %s",
    $encoded_id,
    $order_id
), true);

if (!$order_id || !is_numeric($order_id)) {
    $logger->logPaymentError($encoded_id, sprintf(
        "ID de pedido inválido o corrupto. Valor decodificado: %s",
        $order_id
    ));
    $logger->log("Redirigiendo a página principal por ID corrupto", true);
    header("Location: " . Database::getConfig('site')['host']);
    exit;
}

$logger->log(sprintf("Obteniendo información del pedido %s de WooCommerce", $order_id), true);

$order = $woocommerce->getOrder($order_id);
if (!$order) {
    $logger->logPaymentError($order_id, sprintf(
        "Error al obtener el pedido de WooCommerce. Detalles de la solicitud: %s",
        json_encode(['server' => $_SERVER, 'get' => $_GET])
    ));
    $logger->log("Redirigiendo a página principal por error en WooCommerce", true);
    header("Location: " . Database::getConfig('site')['host']);
    exit;
}

$logger->log(sprintf(
    "Información del pedido obtenida - ID: %s, Estado: %s",
    $order_id,
    $order['status']
), true);

// Verificar si el pedido ya está pagado
if (in_array($order['status'], ['processing', 'completed'])) {
    $logger->log(sprintf(
        "Intento de acceso a pedido ya pagado - ID: %s, Estado: %s, Fecha de pago: %s",
        $order_id,
        $order['status'],
        $order['date_paid'] ?? 'No disponible'
    ), true);
    require __DIR__ . '/templates/payment/already-paid.php';
    exit;
}

$totalAmount = $order['total'] ?? 0;
$currency = $order['currency'] ?? 'EUR';
$logger->log(sprintf(
    "Detalles del pedido - ID: %s, Total: %s %s, Cliente: %s",
    $order_id,
    $totalAmount,
    $currency,
    $order['billing']['email'] ?? 'No disponible'
), true);

// Gestión de sitios y redirección
$logger->log(sprintf(
    "Verificando disponibilidad de sitios - Es matriz: %s",
    Database::isMatrix() ? 'Sí' : 'No'
), true);

// Primero intentar obtener una cuenta en el sitio actual
$logger->log("Buscando cuenta PayPal disponible en el sitio actual", true);
$paypalAccount = $paypalManager->getAvailableAccount();

if (!$paypalAccount) {
    $logger->log("No hay cuentas disponibles en el sitio actual, buscando globalmente", true);
    
    // Si no hay cuenta disponible, buscar la mejor combinación sitio/cuenta globalmente
    $globalAccount = $paypalManager->getGloballyLeastUsedAccount();
    
    if ($globalAccount) {
        $currentHost = $_SERVER['HTTP_HOST'];
        $targetHost = $globalAccount['site_host'];
        
        // Si el mejor sitio no es el actual, redireccionar
        if ($currentHost !== $targetHost) {
            $current_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
            $current_domain = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
            $redirect_url = str_replace($current_domain, "https://" . $targetHost, $current_url);
            
            $logger->log(sprintf(
                "Redirigiendo al sitio óptimo - De: %s a: %s, Cuenta: %s (Uso: %d/%d), URL: %s",
                $currentHost,
                $targetHost,
                $globalAccount['account']['email'],
                $globalAccount['current_site_usage'],
                $globalAccount['account']['daily_limit'],
                $redirect_url
            ), true);
            
            header("Location: " . $redirect_url);
            exit;
        } else {
            // El sitio actual es el mejor, usar la cuenta encontrada
            $paypalAccount = [
                'index' => $globalAccount['account_id'] - 1,
                'account' => $globalAccount['account'],
                'current_count' => $globalAccount['current_site_usage']
            ];
            
            $logger->log(sprintf(
                "Usando cuenta globalmente óptima en sitio actual - Email: %s, Uso: %d/%d",
                $globalAccount['account']['email'],
                $globalAccount['current_site_usage'],
                $globalAccount['account']['daily_limit']
            ), true);
        }
    } else {
        // No hay cuentas disponibles en ningún sitio
        $logger->log("No hay cuentas PayPal disponibles en ningún sitio - Mostrando página de límite", true);
        require __DIR__ . '/templates/payment/limit.php';
        exit;
    }
}

if ($paypalAccount) {
    $logger->log(sprintf(
        "Cuenta PayPal final seleccionada - Email: %s, Pagos hoy: %d/%d",
        $paypalAccount['account']['email'],
        $paypalAccount['current_count'],
        $paypalAccount['account']['daily_limit']
    ), true);
} else {
    $logger->log("Error: No se pudo seleccionar ninguna cuenta PayPal", true);
}

// Generar reporte de distribución para monitoreo
$paypalManager->getPaymentDistributionReport();

// Log payment attempt
$logger->logPaymentAttempt($order_id, $paypalAccount);
$logger->log("Cargando plantilla de botón de pago", true);

// Load template
require __DIR__ . '/templates/payment/button.php';


} catch (Exception $e) {
    echo $e;
}