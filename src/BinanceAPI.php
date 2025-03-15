<?php

class BinanceAPI {
    private $apiKey;
    private $apiSecret;
    private $baseUrl;
    private $testMode;

    public function __construct(array $config) {
        $this->apiKey = $config['api']['key'];
        $this->apiSecret = $config['api']['secret'];
        $this->testMode = $config['api']['test_mode'];

        // Utilisez l'URL testnet si en mode test
        if ($this->testMode) {
            $this->baseUrl = 'https://testnet.binance.vision/api/';
        } else {
            $this->baseUrl = 'https://api.binance.com/api/';
        }
    }

    /**
     * Récupère les données de marché pour un symbole
     */
    public function getKlines($symbol, $interval = '1h', $limit = 100) {
        $endpoint = 'v3/klines';
        $params = [
            'symbol' => $symbol,
            'interval' => $interval,
            'limit' => $limit
        ];

        return $this->makeRequest($endpoint, $params, 'GET', false);
    }

    /**
     * Récupère les informations de compte
     */
    public function getAccount() {
        $endpoint = 'v3/account';
        return $this->makeRequest($endpoint, [], 'GET', true);
    }

    /**
     * Récupère le solde pour une devise spécifique
     */
    public function getBalance($currency) {
        $account = $this->getAccount();

        if (isset($account['balances'])) {
            foreach ($account['balances'] as $balance) {
                if ($balance['asset'] === $currency) {
                    return [
                        'free' => floatval($balance['free']),
                        'locked' => floatval($balance['locked'])
                    ];
                }
            }
        }

        return [
            'free' => 0,
            'locked' => 0
        ];
    }

    /**
     * Crée un ordre d'achat ou de vente
     */
    public function createOrder($symbol, $side, $type, $quantity, $price = null) {
        $endpoint = 'v3/order';

        $params = [
            'symbol' => $symbol,
            'side' => $side,        // BUY ou SELL
            'type' => $type,        // LIMIT, MARKET, STOP_LOSS, etc.
            'quantity' => $quantity,
            'timestamp' => $this->getTimestamp()
        ];

        if ($type === 'LIMIT') {
            $params['timeInForce'] = 'GTC';  // Good Till Canceled
            $params['price'] = $price;
        }

        return $this->makeRequest($endpoint, $params, 'POST', true);
    }

    /**
     * Crée un ordre d'achat market
     */
    public function buyMarket($symbol, $quantity) {
        return $this->createOrder($symbol, 'BUY', 'MARKET', $quantity);
    }

    /**
     * Crée un ordre de vente market
     */
    public function sellMarket($symbol, $quantity) {
        return $this->createOrder($symbol, 'SELL', 'MARKET', $quantity);
    }

    /**
     * Récupère le prix actuel d'un symbole
     */
    public function getCurrentPrice($symbol) {
        $endpoint = 'v3/ticker/price';
        $params = [
            'symbol' => $symbol
        ];

        $result = $this->makeRequest($endpoint, $params, 'GET', false);

        if (isset($result['price'])) {
            return floatval($result['price']);
        }

        return null;
    }

    /**
     * Récupère tous les ordres ouverts
     */
    public function getOpenOrders($symbol = null) {
        $endpoint = 'v3/openOrders';
        $params = [];

        if ($symbol) {
            $params['symbol'] = $symbol;
        }

        return $this->makeRequest($endpoint, $params, 'GET', true);
    }

    /**
     * Annule un ordre
     */
    public function cancelOrder($symbol, $orderId) {
        $endpoint = 'v3/order';
        $params = [
            'symbol' => $symbol,
            'orderId' => $orderId,
            'timestamp' => $this->getTimestamp()
        ];

        return $this->makeRequest($endpoint, $params, 'DELETE', true);
    }

    /**
     * Effectue une requête à l'API Binance
     */
    private function makeRequest($endpoint, array $params = [], $method = 'GET', $auth = false) {
        $url = $this->baseUrl . $endpoint;

        if ($auth) {
            $params['timestamp'] = $this->getTimestamp();
            $signature = $this->generateSignature($params);
            $params['signature'] = $signature;
        }

        $query = http_build_query($params);

        if ($method === 'GET' && !empty($query)) {
            $url .= '?' . $query;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $headers = ['Content-Type: application/x-www-form-urlencoded'];

        if ($this->apiKey) {
            $headers[] = 'X-MBX-APIKEY: ' . $this->apiKey;
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $query);
        } elseif ($method === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
            if (!empty($query)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $query);
            }
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            throw new Exception('Erreur Curl: ' . curl_error($ch));
        }

        curl_close($ch);

        $decodedResponse = json_decode($response, true);

        if ($httpCode >= 400) {
            $errorMsg = isset($decodedResponse['msg']) ? $decodedResponse['msg'] : 'Erreur API inconnue';
            throw new Exception('Erreur API Binance: ' . $errorMsg . ' (Code: ' . $httpCode . ')');
        }

        return $decodedResponse;
    }

    /**
     * Génère une signature HMAC SHA256 pour authentifier les requêtes
     */
    private function generateSignature($params) {
        $query = http_build_query($params);
        return hash_hmac('sha256', $query, $this->apiSecret);
    }

    /**
     * Obtient le timestamp actuel en millisecondes
     */
    private function getTimestamp() {
        return round(microtime(true) * 1000);
    }
}
