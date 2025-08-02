<?php
// Debug endpoint to check payment distribution
error_reporting(E_ALL);
ini_set('display_errors', TRUE);
date_default_timezone_set('Europe/Madrid');
header('Content-Type: application/json; charset=utf-8');

try {
    // Load configuration
    $current_host = $_SERVER['HTTP_HOST'] ?? 'default';
    $config_file = __DIR__ . '/config/' . $current_host . '.php';
    if (!file_exists($config_file)) {
        $config_file = __DIR__ . '/config/default.php';
    }
    require_once $config_file;
    require_once __DIR__ . '/src/includes/Database.php';
    require_once __DIR__ . '/src/includes/Logger.php';
    require_once __DIR__ . '/src/includes/PayPalManager.php';

    $paypalManager = new PayPalManager();
    
    // Get current site info
    $currentSiteId = Database::getCurrentSiteId();
    $isMatrix = Database::isMatrix();
    
    // Get distribution report
    $distribution = $paypalManager->getPaymentDistributionReport();
    
    // Get accounts available for current site
    $currentSiteAccount = $paypalManager->getAvailableAccount();
    
    // Get best site recommendation
    $bestSite = Database::getBestSiteForPayment();
    
    // Get globally best account
    $globalAccount = $paypalManager->getGloballyLeastUsedAccount();
    
    // Get accounts specifically for current site
    $db = Database::getInstance();
    $stmt = $db->prepare("
        SELECT 
            pa.id as account_id,
            (pa.id - 1) as account_index,
            pa.email as account_email,
            pa.daily_limit,
            COALESCE(pc.count, 0) as current_usage
        FROM paypal_accounts pa
        INNER JOIN site_paypal_accounts spa ON 
            spa.paypal_account_id = pa.id AND 
            spa.site_id = ? AND
            spa.is_active = TRUE
        LEFT JOIN payment_counters pc ON 
            pc.paypal_account_id = pa.id AND 
            pc.site_id = ? AND
            pc.date = CURRENT_DATE()
        WHERE pa.is_active = TRUE
        ORDER BY current_usage ASC
    ");
    $stmt->execute([$currentSiteId, $currentSiteId]);
    $currentSiteAccounts = $stmt->fetchAll();
    
    $response = [
        'timestamp' => date('Y-m-d H:i:s'),
        'current_host' => $current_host,
        'current_site_id' => $currentSiteId,
        'is_matrix' => $isMatrix,
        'current_site_accounts' => $currentSiteAccounts,
        'current_site_available_account' => $currentSiteAccount,
        'distribution' => $distribution,
        'recommended_site' => $bestSite,
        'recommended_global_account' => $globalAccount,
        'analysis' => [
            'current_site_has_accounts' => count($currentSiteAccounts) > 0,
            'global_recommendation_matches_current_site' => $globalAccount && $globalAccount['site_host'] === $current_host,
            'should_redirect' => $globalAccount && $globalAccount['site_host'] !== $current_host
        ],
        'summary' => [
            'total_sites' => count(array_unique(array_column($distribution, 'site_host'))),
            'total_accounts' => count($distribution),
            'current_site_accounts_count' => count($currentSiteAccounts),
            'total_current_usage' => array_sum(array_column($distribution, 'current_usage')),
            'total_capacity' => array_sum(array_column($distribution, 'daily_limit')),
            'total_remaining' => array_sum(array_column($distribution, 'remaining_capacity'))
        ]
    ];
    
    $response['summary']['overall_usage_percentage'] = round(
        ($response['summary']['total_current_usage'] / $response['summary']['total_capacity']) * 100, 
        2
    );
    
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => true,
        'message' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT);
} 