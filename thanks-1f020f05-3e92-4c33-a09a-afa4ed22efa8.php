<?php
error_reporting(E_ALL);
ini_set('display_errors', TRUE);
ini_set('log_errors', TRUE);
date_default_timezone_set('Europe/Madrid');
header('Content-Type: text/html; charset=utf-8');

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

// MAIN SCRIPT 
try {
    $logger->log("Iniciando procesamiento de pago", true);

    // Verificar parámetros obligatorios
    $logger->log("Verificando parámetros de entrada", true);
    if (!isset($_GET['id']) || empty($_GET['id'])) {
        $logger->logPaymentError('N/A', 'Parámetro ID no proporcionado o vacío');
        die("Datos de pago no válidos.");
    }
    if (!isset($_GET['account'])) {
        $logger->logPaymentError('N/A', 'Parámetro account no proporcionado');
        die("Datos de pago no válidos.");
    }

    $orderId = base64_decode($_GET['id']);
    $accountIndex = (int)$_GET['account'];
    $accountId = isset($_GET['account_id']) ? (int)$_GET['account_id'] : null;

    $logger->log(sprintf(
        "Parámetros validados - OrderID: %s, AccountIndex: %d, AccountID: %s, Sitio actual: %s",
        $orderId,
        $accountIndex,
        $accountId ?? 'null',
        $_SERVER['HTTP_HOST']
    ), true);

    // Si tenemos account_id, intentar encontrar el índice correcto para este sitio
    if ($accountId) {
        $logger->log(sprintf("Buscando índice local para Account ID %d en sitio actual", $accountId), true);
        
        // Buscar el índice correcto para este account_id en el sitio actual
        $stmt = Database::getInstance()->prepare("
            SELECT pa.id 
            FROM paypal_accounts pa
            INNER JOIN site_paypal_accounts spa ON spa.paypal_account_id = pa.id
            WHERE pa.id = ? AND spa.site_id = ? AND spa.is_active = TRUE AND pa.is_active = TRUE
        ");
        $stmt->execute([$accountId, Database::getCurrentSiteId()]);
        $result = $stmt->fetch();
        
        if ($result) {
            $accountIndex = $accountId - 1; // Convertir ID a índice
            $logger->log(sprintf("Account ID %d encontrado en sitio actual, usando índice %d", $accountId, $accountIndex), true);
        } else {
            $logger->log(sprintf("Account ID %d NO encontrado en sitio actual, usando índice original %d", $accountId, $accountIndex), true);
        }
    }

    // Obtener información de la cuenta PayPal
    $logger->log(sprintf("Obteniendo información de cuenta PayPal para índice %d", $accountIndex), true);
    $paypalAccount = $paypalManager->getAccountInfo($accountIndex);
    if (!$paypalAccount) {
        $logger->logPaymentError($orderId, sprintf(
            "Cuenta PayPal no encontrada para el índice %d en sitio %s. ID de cuenta buscado: %d. Detalles de la solicitud: %s",
            $accountIndex,
            $_SERVER['HTTP_HOST'],
            $accountIndex + 1,
            json_encode($_SERVER)
        ));
        die("Error en el procesamiento del pago");
    }

    $logger->log(sprintf(
        "Cuenta encontrada - Procesando pedido ID: %s con cuenta PayPal: %s (Account ID: %d, Index: %d) - Límite diario: %d",
        $orderId,
        $paypalAccount['email'],
        $accountIndex + 1,
        $accountIndex,
        $paypalAccount['daily_limit']
    ), true);

    // Comprobar si el pedido ya ha sido procesado previamente
    $logger->log(sprintf("Obteniendo información del pedido %s de WooCommerce", $orderId), true);
    $orderData = $woocommerce->getOrder($orderId);
    if (!$orderData) {
        $logger->logPaymentError($orderId, sprintf(
            "Error al obtener el pedido de WooCommerce. Detalles: %s",
            json_encode(['account' => $paypalAccount, 'request' => $_SERVER])
        ), $paypalAccount);
        die("Error al obtener el pedido");
    }

    $orderAlreadyProcessed = isset($orderData['status']) && $orderData['status'] === 'processing';
    $logger->log(sprintf(
        "Estado del pedido %s: %s (Procesado previamente: %s)",
        $orderId,
        $orderData['status'] ?? 'unknown',
        $orderAlreadyProcessed ? 'Sí' : 'No'
    ), true);

    if (!$orderAlreadyProcessed) {
        $logger->log("Preparando metadatos del pago", true);
        // Preparar metadatos del pago
        $metadata = [
            '_paypal_account_email' => $paypalAccount['email'],
            '_paypal_account_currency' => $paypalAccount['currency'],
            '_paypal_payment_date' => date('Y-m-d H:i:s'),
            '_paypal_payment_ip' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
            '_paypal_payment_user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            '_paypal_payment_referer' => $_SERVER['HTTP_REFERER'] ?? 'Direct',
            '_paypal_payment_method' => 'PayPal HTML Form',
            '_paypal_daily_payment_count' => $paypalManager->getDailyPaymentCount($accountIndex)
        ];
        
        $logger->log(sprintf(
            "Actualizando estado del pedido %s a 'processing' con metadatos: %s",
            $orderId,
            json_encode($metadata)
        ), true);
        
        // Actualizar el pedido en WooCommerce
        if (!$woocommerce->updateOrderStatus($orderId, 'processing', $metadata)) {
            $logger->logPaymentError($orderId, sprintf(
                "Error al actualizar el estado del pedido. Metadatos: %s",
                json_encode($metadata)
            ), $paypalAccount);
            die("Hubo un problema actualizando el pedido.");
        }

        $logger->log(sprintf("Estado del pedido %s actualizado exitosamente", $orderId), true);

        // Crear nota privada con información del pago
        $noteContent = sprintf(
            "Pago procesado con PayPal\n" .
            "- Cuenta: %s\n" .
            "- Fecha: %s\n" .
            "- IP: %s\n" .
            "- Pagos hoy: %d/%d\n" .
            "- Moneda: %s",
            $paypalAccount['email'],
            $metadata['_paypal_payment_date'],
            $metadata['_paypal_payment_ip'],
            $metadata['_paypal_daily_payment_count'],
            $paypalAccount['daily_limit'],
            $paypalAccount['currency']
        );
        $woocommerce->addOrderNote($orderId, $noteContent);

        $logger->log("Incrementando contador de pagos para la cuenta PayPal", true);
        $paypalManager->incrementPaymentCount($accountIndex);

        // Registrar el éxito del pago
        $logger->logPaymentSuccess($orderId, $paypalAccount);
        $logger->log("Proceso de pago completado exitosamente", true);
    } else {
        $logger->log(
            sprintf(
                "Pedido %s ya procesado anteriormente (estado: %s), mostrando página de agradecimiento",
                $orderId,
                $orderData['status']
            ),
            true
        );
    }

    // Load template
    $logger->log("Cargando plantilla de agradecimiento", true);
    require __DIR__ . '/templates/payment/thanks.php';

} catch (Exception $e) {
    $logger->log(sprintf(
        "Error crítico en el procesamiento: %s\nStack trace: %s",
        $e->getMessage(),
        $e->getTraceAsString()
    ), true);
    error_log($e->getMessage());
}
?>