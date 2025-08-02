<?php
class WooCommerceAPI {
    private string $apiUrl;
    private string $auth;
    private Logger $logger;

    public function __construct() {
        $config = Database::getConfig('woocommerce');

        $this->apiUrl = $config['api_url'];
        $this->auth = base64_encode(
            $config['consumer_key'] . ':' . 
            $config['consumer_secret']
        );
        $this->logger = new Logger('orders');
    }

    public function request(string $method, string $endpoint, $data = null): array {
        $url = $this->apiUrl . $endpoint;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        if ($method === 'PUT' || $method === 'POST') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            if (!is_null($data)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Basic ' . $this->auth,
            'Content-Type: application/json'
        ]);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $this->logger->log("WC API {$method} {$endpoint} - Status: {$http_code}");
        
        return [$http_code, $response];
    }

    public function getOrder(int $orderId): ?array {
        list($http_code, $response) = $this->request('GET', "/orders/$orderId");
        if ($http_code !== 200) {
            $this->logger->log("Error getting order {$orderId}: HTTP {$http_code}");
            return null;
        }
        return json_decode($response, true);
    }

    public function updateOrderStatus(int $orderId, string $status, array $metadata = []): bool {
        $data = ['status' => $status];
        
        if (!empty($metadata)) {
            $data['meta_data'] = array_map(function($key, $value) {
                return [
                    'key' => $key,
                    'value' => $value
                ];
            }, array_keys($metadata), $metadata);
        }
        
        list($http_code, $response) = $this->request('PUT', "/orders/$orderId", $data);
        return $http_code === 200;
    }

    public function addOrderNote(int $orderId, string $note, bool $isCustomerNote = false): bool {
        $data = [
            'note' => $note,
            'customer_note' => $isCustomerNote
        ];
        
        list($http_code, $response) = $this->request('POST', "/orders/$orderId/notes", $data);
        return $http_code === 201;
    }
} 