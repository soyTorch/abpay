<?php
class Logger {
    private string $logType;
    private array $requestInfo;
    private PDO $db;
    private ?int $siteId;

    public function __construct(string $logType) {
        $this->logType = $logType;
        $this->db = Database::getInstance();
        $this->siteId = Database::getCurrentSiteId();
        
        $this->requestInfo = [
            'ip' => $this->getClientIP(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            'referer' => $_SERVER['HTTP_REFERER'] ?? 'Direct',
            'request_uri' => $_SERVER['REQUEST_URI'] ?? '/',
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'Unknown'
        ];
    }

    public function log(string $message, bool $includeRequestInfo = false): void {
        $message = $this->sanitizeForDatabase($message);
        $stmt = $this->db->prepare("
            INSERT INTO logs (site_id, type, message, ip, user_agent, referer, request_uri, method)
            VALUES (:site_id, :type, :message, :ip, :user_agent, :referer, :request_uri, :method)
        ");

        $stmt->execute([
            'site_id' => $this->siteId,
            'type' => $this->logType,
            'message' => $message,
            'ip' => $includeRequestInfo ? $this->requestInfo['ip'] : null,
            'user_agent' => $includeRequestInfo ? $this->requestInfo['user_agent'] : null,
            'referer' => $includeRequestInfo ? $this->requestInfo['referer'] : null,
            'request_uri' => $includeRequestInfo ? $this->requestInfo['request_uri'] : null,
            'method' => $includeRequestInfo ? $this->requestInfo['method'] : null
        ]);
    }

    private function getClientIP(): string {
        $headers = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];

        foreach ($headers as $header) {
            if (isset($_SERVER[$header])) {
                $ips = explode(',', $_SERVER[$header]);
                $ip = trim($ips[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return 'Unknown';
    }

    public function logPaymentAttempt(string $orderId, ?array $paypalAccount = null): void {
        $message = sprintf(
            "Intento de pago - Pedido: %s%s",
            $orderId,
            $paypalAccount ? sprintf(
                " - Cuenta PayPal: %s (Account #%d)",
                $paypalAccount['account']['email'],
                $paypalAccount['index'] + 1
            ) : " - Sin cuenta PayPal disponible"
        );
        $this->log($message, true);
    }

    public function logPaymentSuccess(string $orderId, array $paypalAccount): void {
        $message = sprintf(
            "Pago exitoso - Pedido: %s - Cuenta PayPal: %s (Account #%d)",
            $orderId,
            $paypalAccount['email'],
            $paypalAccount['index'] + 1
        );
        $this->log($message, true);
    }

    public function logPaymentError(string $orderId, string $error, ?array $paypalAccount = null): void {
        $message = sprintf(
            "Error en pago - Pedido: %s - Error: %s%s",
            $orderId,
            $error,
            $paypalAccount ? sprintf(
                " - Cuenta PayPal: %s (Account #%d)",
                $paypalAccount['email'],
                $paypalAccount['index'] + 1
            ) : ""
        );
        $this->log($message, true);
    }

    public function logPhpError(int $errorType, string $errorMessage, string $errorFile, int $errorLine, ?array $errorContext = null): void {
        $errorTypes = [
            E_ERROR => 'E_ERROR',
            E_WARNING => 'E_WARNING',
            E_PARSE => 'E_PARSE',
            E_NOTICE => 'E_NOTICE',
            E_CORE_ERROR => 'E_CORE_ERROR',
            E_CORE_WARNING => 'E_CORE_WARNING',
            E_COMPILE_ERROR => 'E_COMPILE_ERROR',
            E_COMPILE_WARNING => 'E_COMPILE_WARNING',
            E_USER_ERROR => 'E_USER_ERROR',
            E_USER_WARNING => 'E_USER_WARNING',
            E_USER_NOTICE => 'E_USER_NOTICE',
            E_STRICT => 'E_STRICT',
            E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
            E_DEPRECATED => 'E_DEPRECATED',
            E_USER_DEPRECATED => 'E_USER_DEPRECATED'
        ];

        $errorTypeName = $errorTypes[$errorType] ?? 'UNKNOWN';
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        array_shift($backtrace); // Remove the current method from trace
        $errorTrace = json_encode($backtrace);

        // Get full URL
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
        $fullUrl = $protocol . "://" . ($_SERVER['HTTP_HOST'] ?? 'unknown') . ($_SERVER['REQUEST_URI'] ?? '');

        // Get order ID from URL parameters or POST data
        $orderId = null;
        if (isset($_GET['id'])) {
            $orderId = base64_decode($_GET['id']);
        } elseif (isset($_POST['id'])) {
            $orderId = base64_decode($_POST['id']);
        }

        // Add order ID and URL to error message if available
        $enhancedMessage = $errorMessage;
        if ($orderId) {
            $enhancedMessage = "[Order ID: {$orderId}] " . $enhancedMessage;
        }
        $enhancedMessage = "[URL: {$fullUrl}] " . $enhancedMessage;

        $stmt = $this->db->prepare("
            INSERT INTO php_errors (
                site_id, error_type, error_message, error_file, error_line,
                error_trace, request_uri, request_method, request_params,
                ip, user_agent
            ) VALUES (
                :site_id, :error_type, :error_message, :error_file, :error_line,
                :error_trace, :request_uri, :request_method, :request_params,
                :ip, :user_agent
            )
        ");

        $stmt->execute([
            'site_id' => $this->siteId,
            'error_type' => $errorTypeName,
            'error_message' => $enhancedMessage,
            'error_file' => $errorFile,
            'error_line' => $errorLine,
            'error_trace' => $errorTrace,
            'request_uri' => $fullUrl,
            'request_method' => $_SERVER['REQUEST_METHOD'] ?? '',
            'request_params' => json_encode([
                'GET' => $_GET,
                'POST' => $_POST,
                'FILES' => $_FILES,
                'order_id' => $orderId
            ]),
            'ip' => $this->getClientIP(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
        ]);

        // Also log to the standard error log for immediate visibility
        error_log(sprintf(
            "[%s] %s in %s on line %d",
            $errorTypeName,
            $enhancedMessage,
            $errorFile,
            $errorLine
        ));
    }

    public function registerErrorHandler(): void {
        set_error_handler(function($errno, $errstr, $errfile, $errline, $errcontext = null) {
            // Don't handle silenced errors
            if (error_reporting() === 0) {
                return false;
            }

            $this->logPhpError($errno, $errstr, $errfile, $errline, $errcontext);

            // Let PHP handle fatal errors
            if ($errno === E_ERROR || $errno === E_CORE_ERROR || $errno === E_COMPILE_ERROR) {
                return false;
            }

            return true;
        });

        set_exception_handler(function($exception) {
            $this->logPhpError(
                E_ERROR,
                $exception->getMessage(),
                $exception->getFile(),
                $exception->getLine()
            );
        });

        register_shutdown_function(function() {
            $error = error_get_last();
            if ($error !== null && ($error['type'] & (E_ERROR | E_CORE_ERROR | E_COMPILE_ERROR))) {
                $this->logPhpError(
                    $error['type'],
                    $error['message'],
                    $error['file'],
                    $error['line']
                );
            }
        });
    }

    private function sanitizeForDatabase(string $value): string {
        // Elimina caracteres fuera del BMP (como emojis) que pueden causar errores en utf8/latin1
        $cleaned = preg_replace('/[\x{10000}-\x{10FFFF}]/u', '?', $value);
        return $cleaned === null ? '' : $cleaned;
    }
} 