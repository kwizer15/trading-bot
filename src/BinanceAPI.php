<?php

namespace Kwizer15\TradingBot;

use Kwizer15\TradingBot\Clock\RealClock;
use Kwizer15\TradingBot\Configuration\ApiConfiguration;
use Kwizer15\TradingBot\DTO\Balance;
use Kwizer15\TradingBot\DTO\Order;
use Kwizer15\TradingBot\Utils\Logger;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class BinanceAPI implements BinanceAPIInterface
{
    private const BASE_URL = 'https://api.binance.com/api/';
    private const BASE_URL_TEST = 'https://testnet.binance.vision/api/';

    private string $baseUrl;
    private array $exchangeInfo;
    private LoggerInterface $logger;
    private array $klinesResponses = [];
    private array $clients = [];

    public function __construct(
        private readonly ApiConfiguration $apiConfig,
    ) {
        $this->baseUrl = $this->apiConfig->testMode ? self::BASE_URL_TEST : self::BASE_URL;
        $this->logger = new Logger(new RealClock(), dirname(__DIR__).'/logs/binance_api.log', 'debug');
    }

    /**
     * Récupère les données de marché pour un symbole
     */
    public function getKlines(string $symbol, string $interval = '1h', int $limit = 100, ?int $startTime = null, ?int $endTime = null): array
    {
        $this->prepareKlines($symbol, $interval, $limit, $startTime, $endTime);

        foreach ($this->klinesResponses as $responseSymbol => $response) {
            if ($symbol !== $responseSymbol) {
                continue;
            }

            unset($this->klinesResponses[$symbol]);

            return $this->handleResponse($response);
        }
        throw new \Exception('Pas de klines pour la paire ' . $symbol);
    }

    /**
     * Récupère les informations de compte
     */
    private function getAccount(): array
    {
        $endpoint = 'v3/account';
        return $this->makeRequest($endpoint, [], 'GET', true);
    }

    /**
     * Récupère le solde pour une devise spécifique
     */
    public function getBalance(string $currency): Balance
    {
        $account = $this->getAccount();

        if (isset($account['balances'])) {
            foreach ($account['balances'] as $balance) {
                if ($balance['asset'] === $currency) {
                    return new Balance($balance['free'], $balance['locked']);
                }
            }
        }

        return new Balance();
    }

    /**
     * Crée un ordre d'achat ou de vente
     */
    private function createOrder(string $symbol, string $side, string $type, float $quantity, float $price = null)
    {
        $endpoint = 'v3/order';

        $params = [
            'symbol' => $symbol,
            'side' => $side,        // BUY ou SELL
            'type' => $type,        // LIMIT, MARKET, STOP_LOSS, etc.
            'quantity' => $this->getQuantity($symbol, $quantity),
            'timestamp' => $this->getTimestamp()
        ];

        if ($type === 'LIMIT') {
            $params['timeInForce'] = 'GTC';  // Good Till Canceled
            $params['price'] = $this->getPrice($symbol, $price);
        }

        return $this->makeRequest($endpoint, $params, 'POST', true);
    }

    /**
     * Crée un ordre d'achat market
     */
    public function buyMarket($symbol, $quantity): Order
    {
        $order = $this->createOrder($symbol, 'BUY', 'MARKET', $quantity);
        if (!$order || !isset($order['orderId'])) {
            throw new \Exception("Erreur lors de l’achat de {$symbol}: " . json_encode($order));
        }

        $this->logger->debug('Nouvel ordre d’achat : ' . json_encode($order));
        $price = $order['cummulativeQuoteQty'] / $order['executedQty'];

        return new Order($order['orderId'], $price, $order['executedQty'], 0, $order['transactTime']);
    }

    /**
     * Crée un ordre de vente market
     */
    public function sellMarket($symbol, $quantity): Order
    {
        $order = $this->createOrder($symbol, 'SELL', 'MARKET', $quantity);
        if (!$order || !isset($order['orderId'])) {
            throw new \Exception("Erreur lors de la vente de {$symbol}: " . json_encode($order));
        }

        $this->logger->debug('Nouvel ordre de vente : ' . json_encode($order));
        $price = $order['cummulativeQuoteQty'] / $order['executedQty'];

        return new Order($order['orderId'], $price, $order['executedQty'], 0, $order['transactTime']);
    }

    /**
     * Récupère le prix actuel d'un symbole
     */
    public function getCurrentPrice($symbol): float
    {
        $endpoint = 'v3/ticker/price';
        $params = [
            'symbol' => $symbol
        ];

        $result = $this->makeRequest($endpoint, $params, 'GET', false);

        if (!isset($result['price'])) {
            throw new \Exception("Prix actuel de {$symbol} non disponible");
        }

        return (float) $result['price'];
    }

    public function getCurrentPrices(array $symbols): iterable
    {
        $endpoint = 'v3/ticker/price';
        $param = '["' . implode('","', $symbols) . '"]';
        $params = [
            'symbols' => $param
        ];

        $results = $this->makeRequest($endpoint, $params, 'GET', false);
        $prices = [];
        foreach ($results as $result) {
            yield $result['symbol'] => (float) $result['price'];
        }
    }

    public function prepareKlines(string $symbol, string $interval = '1h', int $limit = 100, ?int $startTime = null, ?int $endTime = null): void
    {
        if (($this->klinesResponses[$symbol] ?? null) instanceof ResponseInterface) {
            return;
        }

        $endpoint = 'v3/klines';
        $params = [
            'symbol' => $symbol,
            'interval' => $interval,
            'limit' => $limit
        ];

        // Ajouter startTime et endTime s'ils sont spécifiés
        if ($startTime !== null) {
            $params['startTime'] = $startTime;
        }

        if ($endTime !== null) {
            $params['endTime'] = $endTime;
        }

        $this->klinesResponses[$symbol] = $this->prepareRequest($endpoint, $params, 'GET', false, self::BASE_URL);
    }

    private function prepareRequest(string $endpoint, array $params = [], string $method = 'GET', bool $auth = false, ?string $baseUrl = null): ResponseInterface
    {
        $baseUrl ??= $this->baseUrl;
        $client = $this->getClient($baseUrl);

        if ($auth) {
            $params['timestamp'] = $this->getTimestamp();
            $signature = $this->generateSignature($params);
            $params['signature'] = $signature;
        }

        $query = http_build_query($params);
        $options = [];

        if ($method === 'GET' && !empty($query)) {
            $options['query'] = $params;
        }

        $options['headers'] = [
            'Content-Type' => 'application/x-www-form-urlencoded',
            'X-MBX-APIKEY' => $this->apiConfig->key,
        ];

        if ($method === 'POST') {
            $options['body'] = $query;
        } elseif ($method === 'DELETE') {
            if (!empty($query)) {
                $options['body'] = $query;
            }
        }

        return $client->request($method, $endpoint, $options);
    }

    public function getClient(string $baseUrl): HttpClientInterface
    {
        $this->clients[$baseUrl] ??= HttpClient::createForBaseUri($baseUrl);

        return $this->clients[$baseUrl];
    }

    /**
     * Récupère tous les ordres ouverts
     */
    private function getOpenOrders($symbol = null)
    {
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
    private function cancelOrder($symbol, $orderId)
    {
        $endpoint = 'v3/order';
        $params = [
            'symbol' => $symbol,
            'orderId' => $orderId,
            'timestamp' => $this->getTimestamp()
        ];

        return $this->makeRequest($endpoint, $params, 'DELETE', true);
    }

    /**
     * Récupère tous les symboles de trading disponibles
     * @param string $baseCurrency Devise de base (ex: USDT, BTC)
     * @return array Liste des symboles disponibles
     */
    public function getAvailableSymbols(string $baseCurrency)
    {
        $result = $this->getExchangeInfo();
        $symbols = [];

        foreach (($result['symbols'] ?? []) as $symbol) {
            // Vérifier si le symbole est actif pour le trading
            if ($symbol['status'] === 'TRADING') {
                // Si une devise de base est spécifiée, filtrer les paires qui se terminent par cette devise
                if ($baseCurrency === null || substr($symbol['symbol'], -strlen($baseCurrency)) === $baseCurrency) {
                    $baseAsset = $symbol['baseAsset'];
                    $quoteAsset = $symbol['quoteAsset'];

                    // Si on a filtré par devise de base, n'ajouter que l'actif de base
                    if ($baseCurrency !== null && $quoteAsset === $baseCurrency) {
                        $symbols[] = $baseAsset;
                    } else {
                        // Sinon ajouter la paire complète
                        $symbols[] = $symbol['symbol'];
                    }
                }
            }
        }

        return $symbols;
    }

    /**
     * Récupère toutes les devises de base disponibles (USDT, BTC, ETH, etc.)
     * @return array Liste des devises de base disponibles
     */
    public function getBaseCurrencies(): array
    {
        $endpoint = 'v3/exchangeInfo';
        $result = $this->makeRequest($endpoint, [], 'GET', false);

        $baseCurrencies = [];

        if (isset($result['symbols'])) {
            foreach ($result['symbols'] as $symbol) {
                if ($symbol['status'] === 'TRADING') {
                    $quoteAsset = $symbol['quoteAsset'];
                    if (!in_array($quoteAsset, $baseCurrencies)) {
                        $baseCurrencies[] = $quoteAsset;
                    }
                }
            }
        }

        // Trier et mettre les principales devises en premier
        $priorityBaseCurrencies = ['USDT', 'BUSD', 'USDC', 'BTC', 'ETH', 'BNB'];
        $otherBaseCurrencies = array_diff($baseCurrencies, $priorityBaseCurrencies);
        sort($otherBaseCurrencies);

        return array_merge($priorityBaseCurrencies, $otherBaseCurrencies);
    }

    private function makeRequest(string $endpoint, array $params = [], string $method = 'GET', bool $auth = false, ?string $baseUrl = null): array
    {
        $response = $this->prepareRequest($endpoint, $params, $method, $auth, $baseUrl);

        return $this->handleResponse($response);
    }

    private function handleResponse(ResponseInterface $response): array
    {
        $httpCode = $response->getStatusCode();
        $decodedResponse = $response->toArray(false);

        if ($httpCode >= 400) {
            $errorMsg = isset($decodedResponse['msg']) ? $decodedResponse['msg'] : 'Erreur API inconnue';
            throw new \Exception('Erreur API Binance: ' . $errorMsg . ' (Code: ' . $httpCode . ')');
        }

        return $decodedResponse;
    }

    /**
     * Génère une signature HMAC SHA256 pour authentifier les requêtes
     */
    private function generateSignature($params): string
    {
        $query = http_build_query($params);
        return hash_hmac('sha256', $query, $this->apiConfig->secret);
    }

    /**
     * Obtient le timestamp actuel en millisecondes
     */
    private function getTimestamp(): int
    {
        return round(microtime(true) * 1000);
    }

    private function getQuantity(string $currentSymbol, float $quantity): string
    {
        $info = $this->getExchangeInfo();
        $lotSize = [];
        foreach ($info['symbols'] as $symbol) {
            if ($symbol['symbol'] !== $currentSymbol) {
                continue;
            }

            foreach ($symbol['filters'] as $filter) {
                if ($filter['filterType'] === 'LOT_SIZE') {
                    $lotSize = $filter;
                    break 2;
                }
            }
        }
        if ([] === $lotSize) {
            throw new \Exception('Pas de lot size pour la paire ' . $currentSymbol);
        }

        $minQty = (float)$lotSize["minQty"];
        $maxQty = (float)$lotSize["maxQty"];
        $stepSize = (float)$lotSize["stepSize"];

        // Vérification si qty est dans les limites
        if ($quantity < $minQty) {
            return $minQty;
        }

        if ($quantity > $maxQty) {
            return $maxQty;
        }

        // Arrondir en fonction du stepSize
        // Formule : floor(qty / stepSize) * stepSize
        $adjustedQty = floor($quantity / $stepSize) * $stepSize;

        // Précision pour éviter les problèmes de nombre à virgule flottante
        $precision = strlen(substr(strrchr($stepSize, "."), 1));

        return round($adjustedQty, $precision);
    }

    private function getPrice(string $currentSymbol, float $price): string
    {
        $info = $this->getExchangeInfo();
        $priceFilter = [];
        foreach ($info['symbols'] as $symbol) {
            if ($symbol['symbol'] !== $currentSymbol) {
                continue;
            }

            foreach ($symbol['filters'] as $filter) {
                if ($filter['filterType'] === 'PRICE_FILTER') {
                    $priceFilter = $filter;
                    break 2;
                }
            }
        }

        if ([] === $priceFilter) {
            throw new \Exception('Pas de price filter pour la paire ' . $currentSymbol);
        }

        $minPrice = (float)$priceFilter["minPrice"];
        $maxPrice = (float)$priceFilter["maxPrice"];
        $tickSize = (float)$priceFilter["tickSize"];

        // Vérification si qty est dans les limites
        if ($price < $minPrice) {
            return $minPrice;
        }

        if ($price > $maxPrice) {
            return $maxPrice;
        }

        // Arrondir en fonction du stepSize
        // Formule : floor(qty / stepSize) * stepSize
        $adjustedPrice = floor($price / $tickSize) * $tickSize;

        // Précision pour éviter les problèmes de nombre à virgule flottante
        $precision = strlen(substr(strrchr($tickSize, "."), 1));

        return round($adjustedPrice, $precision);
    }

    /**
     * @return mixed
     * @throws \Exception
     */
    private function getExchangeInfo(): mixed
    {
        $endpoint = 'v3/exchangeInfo';
        $this->exchangeInfo ??= $this->makeRequest($endpoint, [], 'GET', false);

        return $this->exchangeInfo;
    }
}
