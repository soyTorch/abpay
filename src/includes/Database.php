<?php
class Database {
    private static ?PDO $instance = null;
    private static ?int $currentSiteId = null;
    private static bool $isMatrix = false;

    public static function getInstance(): PDO {
        if (self::$instance === null) {
            try {
                self::$instance = new PDO(
                    "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                    DB_USER,
                    DB_PASS,
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false
                    ]
                );
            } catch (PDOException $e) {
                error_log("Error de conexión a la base de datos: " . $e->getMessage());
                throw new Exception("Error de conexión a la base de datos");
            }
        }

        return self::$instance;
    }

    public static function getCurrentSiteId(): ?int {
        if (self::$currentSiteId === null) {
            $db = self::getInstance();
            
            $stmt = $db->prepare("
                SELECT id, is_matrix 
                FROM sites 
                WHERE host = :host AND is_active = TRUE
            ");
            $stmt->execute(['host' => SITE_HOST]);
            $result = $stmt->fetch();
            
            if (!$result) {
                // Si el sitio no existe, intentar crearlo
                $stmt = $db->prepare("
                    INSERT INTO sites (host, name, is_matrix) 
                    VALUES (:host, :name, :is_matrix)
                ");
                $stmt->execute([
                    'host' => SITE_HOST,
                    'name' => SITE_NAME,
                    'is_matrix' => IS_MATRIX ? 1 : 0
                ]);
                
                self::$currentSiteId = $db->lastInsertId();
                self::$isMatrix = IS_MATRIX;
            } else {
                self::$currentSiteId = (int)$result['id'];
                self::$isMatrix = (bool)$result['is_matrix'];
            }
        }
        
        return self::$currentSiteId;
    }

    public static function isMatrix(): bool {
        if (self::$currentSiteId === null) {
            self::getCurrentSiteId();
        }
        return self::$isMatrix;
    }

    public static function getAvailableSite(): ?array {
        $db = self::getInstance();
        $currentSiteId = self::getCurrentSiteId();

        // Si no es el sitio matriz, no buscar otros sitios
        if (!self::isMatrix()) {
            return null;
        }

        // Buscar sitios con cuentas disponibles
        $stmt = $db->prepare("
            WITH site_availability AS (
                SELECT 
                    s.id as site_id,
                    s.host,
                    s.priority,
                    COUNT(DISTINCT CASE 
                        WHEN COALESCE(pc.count, 0) < pa.daily_limit THEN pa.id 
                        ELSE NULL 
                    END) as available_accounts
                FROM sites s
                INNER JOIN site_paypal_accounts spa ON spa.site_id = s.id AND spa.is_active = TRUE
                INNER JOIN paypal_accounts pa ON pa.id = spa.paypal_account_id AND pa.is_active = TRUE
                LEFT JOIN payment_counters pc ON 
                    pc.paypal_account_id = pa.id AND 
                    pc.site_id = s.id AND 
                    pc.date = CURRENT_DATE()
                WHERE 
                    s.is_active = TRUE AND 
                    s.is_matrix = FALSE
                GROUP BY s.id, s.host, s.priority
                HAVING available_accounts > 0
            )
            SELECT * FROM site_availability
            ORDER BY RAND()
            LIMIT 1
        ");
        
        $stmt->execute();
        $result = $stmt->fetch();
        
        return $result ?: null;
    }

    public static function getConfig(string $key): ?array {
        $db = self::getInstance();
        $siteId = self::getCurrentSiteId();
        
        // Buscar configuración (primero específica del sitio, luego global)
        $stmt = $db->prepare("
            SELECT value 
            FROM config 
            WHERE `key` = :config_key
            AND (site_id = :site_id OR site_id IS NULL)
            ORDER BY site_id DESC 
            LIMIT 1
        ");
        
        $stmt->execute([
            'config_key' => $key,
            'site_id' => $siteId
        ]);
        $result = $stmt->fetch();
        
        return $result ? json_decode($result['value'], true) : null;
    }

    public static function getSiteWithLeastPaymentsToday(): ?array {
        $db = self::getInstance();
        $stmt = $db->prepare("
            SELECT 
                s.id, 
                s.host, 
                s.is_matrix,
                COALESCE(SUM(pc.count), 0) AS total_payments,
                COUNT(DISTINCT CASE 
                    WHEN COALESCE(pc.count, 0) < pa.daily_limit THEN pa.id 
                    ELSE NULL 
                END) as available_accounts
            FROM sites s
            LEFT JOIN site_paypal_accounts spa ON spa.site_id = s.id AND spa.is_active = TRUE
            LEFT JOIN paypal_accounts pa ON pa.id = spa.paypal_account_id AND pa.is_active = TRUE
            LEFT JOIN payment_counters pc ON pc.site_id = s.id AND pc.paypal_account_id = pa.id AND pc.date = CURRENT_DATE()
            WHERE s.is_active = TRUE
            GROUP BY s.id, s.host, s.is_matrix
            HAVING available_accounts > 0
            ORDER BY available_accounts DESC, total_payments ASC, s.priority ASC, s.id ASC
            LIMIT 1
        ");
        $stmt->execute();
        $site = $stmt->fetch();
        return $site ?: null;
    }

    public static function getBestSiteForPayment(): ?array {
        $db = self::getInstance();
        
        // Buscar el sitio con más cuentas disponibles y menos pagos hoy
        $stmt = $db->prepare("
            WITH site_stats AS (
                SELECT 
                    s.id,
                    s.host,
                    s.is_matrix,
                    s.priority,
                    COALESCE(SUM(pc.count), 0) AS total_payments_today,
                    COUNT(DISTINCT CASE 
                        WHEN COALESCE(pc.count, 0) < pa.daily_limit THEN pa.id 
                        ELSE NULL 
                    END) as available_accounts,
                    COUNT(DISTINCT pa.id) as total_accounts,
                    COALESCE(AVG(pc.count), 0) as avg_account_usage
                FROM sites s
                LEFT JOIN site_paypal_accounts spa ON spa.site_id = s.id AND spa.is_active = TRUE
                LEFT JOIN paypal_accounts pa ON pa.id = spa.paypal_account_id AND pa.is_active = TRUE
                LEFT JOIN payment_counters pc ON 
                    pc.site_id = s.id AND 
                    pc.paypal_account_id = pa.id AND 
                    pc.date = CURRENT_DATE()
                WHERE s.is_active = TRUE
                GROUP BY s.id, s.host, s.is_matrix, s.priority
                HAVING available_accounts > 0
            )
            SELECT *
            FROM site_stats
            ORDER BY 
                available_accounts DESC,           -- Priorizar sitios con más cuentas disponibles
                avg_account_usage ASC,            -- Luego por menor uso promedio de cuentas
                total_payments_today ASC,         -- Luego por menos pagos totales hoy
                priority ASC,                     -- Luego por prioridad del sitio
                id ASC                           -- Finalmente por ID para consistency
            LIMIT 1
        ");
        
        $stmt->execute();
        $result = $stmt->fetch();
        
        return $result ?: null;
    }
} 