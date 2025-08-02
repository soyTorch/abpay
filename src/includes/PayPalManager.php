<?php
class PayPalManager {
    private Logger $logger;
    private PDO $db;
    private int $siteId;

    public function __construct() {
        $this->logger = new Logger('payment');
        $this->db = Database::getInstance();
        $this->siteId = Database::getCurrentSiteId();
    }

    public function getAvailableAccount(): ?array {
        $stmt = $this->db->prepare("
            WITH site_data AS (
                SELECT :site_id as site_id
            )
            SELECT 
                pa.*,
                COALESCE(pc.count, 0) as current_count
            FROM paypal_accounts pa
            INNER JOIN site_paypal_accounts spa ON 
                spa.paypal_account_id = pa.id AND 
                spa.site_id = (SELECT site_id FROM site_data) AND
                spa.is_active = TRUE
            LEFT JOIN payment_counters pc ON 
                pc.paypal_account_id = pa.id AND 
                pc.site_id = (SELECT site_id FROM site_data) AND
                pc.date = CURRENT_DATE()
            WHERE 
                pa.is_active = TRUE AND
                COALESCE(pc.count, 0) < pa.daily_limit
            ORDER BY current_count ASC, RAND(), pa.id ASC
            LIMIT 1
        ");
        $stmt->execute(['site_id' => $this->siteId]);
        $selectedAccount = $stmt->fetch();

        if (!$selectedAccount) {
            $this->logger->log("No hay cuentas PayPal disponibles en el sitio actual - Todas han alcanzado sus límites diarios", true);
            return null;
        }

        $this->logger->log(sprintf(
            "Cuenta PayPal seleccionada por menor uso: %s - Uso actual: %d/%d",
            $selectedAccount['email'],
            $selectedAccount['current_count'],
            $selectedAccount['daily_limit']
        ), true);

        return [
            'index' => $selectedAccount['id'] - 1, // Mantener compatibilidad con el código existente
            'account' => [
                'client_id' => $selectedAccount['client_id'],
                'email' => $selectedAccount['email'],
                'daily_limit' => $selectedAccount['daily_limit'],
                'currency' => $selectedAccount['currency']
            ],
            'current_count' => $selectedAccount['current_count']
        ];
    }

    public function getGloballyLeastUsedAccount(): ?array {
        $stmt = $this->db->prepare("
            WITH account_usage AS (
                SELECT 
                    pa.id,
                    pa.email,
                    pa.client_id,
                    pa.daily_limit,
                    pa.currency,
                    COALESCE(SUM(pc.count), 0) as total_usage_today
                FROM paypal_accounts pa
                INNER JOIN site_paypal_accounts spa ON spa.paypal_account_id = pa.id AND spa.is_active = TRUE
                INNER JOIN sites s ON s.id = spa.site_id AND s.is_active = TRUE
                LEFT JOIN payment_counters pc ON 
                    pc.paypal_account_id = pa.id AND 
                    pc.site_id = s.id AND 
                    pc.date = CURRENT_DATE()
                WHERE pa.is_active = TRUE
                GROUP BY pa.id, pa.email, pa.client_id, pa.daily_limit, pa.currency
                HAVING total_usage_today < pa.daily_limit
            ),
            best_combinations AS (
                SELECT 
                    au.id,
                    au.email,
                    au.client_id,
                    au.daily_limit,
                    au.currency,
                    au.total_usage_today,
                    spa.site_id as target_site_id,
                    s.host as target_site_host,
                    COALESCE(pc.count, 0) as current_site_usage
                FROM account_usage au
                INNER JOIN site_paypal_accounts spa ON spa.paypal_account_id = au.id AND spa.is_active = TRUE
                INNER JOIN sites s ON s.id = spa.site_id AND s.is_active = TRUE
                LEFT JOIN payment_counters pc ON 
                    pc.paypal_account_id = au.id AND 
                    pc.site_id = spa.site_id AND 
                    pc.date = CURRENT_DATE()
                WHERE COALESCE(pc.count, 0) < au.daily_limit
            )
            SELECT *
            FROM best_combinations
            ORDER BY 
                current_site_usage ASC,    -- Priorizar cuentas con menos uso en el sitio específico
                total_usage_today ASC,     -- Luego por menos uso total global
                RAND(),                    -- Randomizar entre cuentas con mismo uso
                id ASC                     -- Finalmente por ID para consistency
            LIMIT 1
        ");
        
        $stmt->execute();
        $result = $stmt->fetch();
        
        if (!$result) {
            $this->logger->log("No hay cuentas PayPal disponibles globalmente - Todas han alcanzado sus límites diarios", true);
            return null;
        }

        $this->logger->log(sprintf(
            "Cuenta PayPal seleccionada globalmente - Email: %s, Sitio: %s, Uso sitio: %d, Uso global: %d/%d",
            $result['email'],
            $result['target_site_host'],
            $result['current_site_usage'],
            $result['total_usage_today'],
            $result['daily_limit']
        ), true);

        return [
            'account_id' => $result['id'],
            'site_id' => $result['target_site_id'],
            'site_host' => $result['target_site_host'],
            'account' => [
                'client_id' => $result['client_id'],
                'email' => $result['email'],
                'daily_limit' => $result['daily_limit'],
                'currency' => $result['currency']
            ],
            'current_site_usage' => $result['current_site_usage'],
            'total_usage_today' => $result['total_usage_today']
        ];
    }

    public function getDailyPaymentCount(int $accountIndex): int {
        $stmt = $this->db->prepare("
            SELECT COALESCE(count, 0) as count
            FROM payment_counters
            WHERE paypal_account_id = :account_id
            AND site_id = :site_id
            AND date = CURRENT_DATE()
        ");
        $stmt->execute([
            'account_id' => $accountIndex + 1,
            'site_id' => $this->siteId
        ]);
        return (int)($stmt->fetch()['count'] ?? 0);
    }

    public function incrementPaymentCount(int $accountIndex): void {
        $this->db->beginTransaction();
        try {
            // Intentar actualizar el contador existente
            $stmt = $this->db->prepare("
                INSERT INTO payment_counters (site_id, paypal_account_id, date, count)
                VALUES (:site_id, :account_id, CURRENT_DATE(), 1)
                ON DUPLICATE KEY UPDATE count = count + 1
            ");
            $stmt->execute([
                'site_id' => $this->siteId,
                'account_id' => $accountIndex + 1
            ]);
            
            $count = $this->getDailyPaymentCount($accountIndex);
            $account = $this->getAccountInfo($accountIndex);
            
            $this->logger->log(sprintf(
                "Contador de pagos actualizado para cuenta PayPal %s (Account #%d): %d/%d",
                $account['email'],
                $accountIndex + 1,
                $count,
                $account['daily_limit']
            ), true);

            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function getAccountInfo(int $accountIndex): ?array {
        $stmt = $this->db->prepare("
            SELECT pa.* 
            FROM paypal_accounts pa
            INNER JOIN site_paypal_accounts spa ON 
                spa.paypal_account_id = pa.id AND 
                spa.site_id = :site_id AND
                spa.is_active = TRUE
            WHERE pa.id = :id AND pa.is_active = TRUE
        ");
        $stmt->execute([
            'id' => $accountIndex + 1,
            'site_id' => $this->siteId
        ]);
        $account = $stmt->fetch();

        if (!$account) {
            return null;
        }

        return [
            'id' => $account['id'],
            'client_id' => $account['client_id'],
            'email' => $account['email'],
            'daily_limit' => $account['daily_limit'],
            'currency' => $account['currency'],
            'index' => $accountIndex
        ];
    }

    public function getPaymentDistributionReport(): array {
        $stmt = $this->db->prepare("
            SELECT 
                s.host as site_host,
                s.is_matrix,
                pa.id as account_id,
                (pa.id - 1) as account_index,
                pa.email as account_email,
                pa.daily_limit,
                COALESCE(pc.count, 0) as current_usage,
                ROUND((COALESCE(pc.count, 0) / pa.daily_limit) * 100, 2) as usage_percentage,
                (pa.daily_limit - COALESCE(pc.count, 0)) as remaining_capacity
            FROM sites s
            INNER JOIN site_paypal_accounts spa ON spa.site_id = s.id AND spa.is_active = TRUE
            INNER JOIN paypal_accounts pa ON pa.id = spa.paypal_account_id AND pa.is_active = TRUE
            LEFT JOIN payment_counters pc ON 
                pc.site_id = s.id AND 
                pc.paypal_account_id = pa.id AND 
                pc.date = CURRENT_DATE()
            WHERE s.is_active = TRUE
            ORDER BY current_usage DESC, s.host, pa.email
        ");
        
        $stmt->execute();
        $distribution = $stmt->fetchAll();
        
        // Log the distribution for monitoring
        $this->logger->log("=== DISTRIBUCIÓN ACTUAL DE PAGOS ===", true);
        foreach ($distribution as $row) {
            $this->logger->log(sprintf(
                "Sitio: %s%s | ID: %d (Index: %d) | Cuenta: %s | Uso: %d/%d (%s%%) | Restante: %d",
                $row['site_host'],
                $row['is_matrix'] ? ' (MATRIZ)' : '',
                $row['account_id'],
                $row['account_index'],
                $row['account_email'],
                $row['current_usage'],
                $row['daily_limit'],
                $row['usage_percentage'],
                $row['remaining_capacity']
            ), true);
        }
        $this->logger->log("=== FIN DISTRIBUCIÓN ===", true);
        
        return $distribution;
    }
} 