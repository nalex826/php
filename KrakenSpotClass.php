<?php

namespace App\Webhook\Kraken;

/**
 * Class KrakenSpotClass
 * A class for interacting with the Kraken Spot API.
 */
class KrakenSpotClass
{
    // API base URL
    public const API_URL = 'https://api.kraken.com';

    // API version
    public const API_VERSION = '0';

    private $_apiKey;    // API key
    private $_apiSecret; // API secret

    protected $url;     // API base URL
    protected $version; // API version
    protected $curl;    // curl handle

    /**
     * KrakenSpotClass constructor.
     * Initializes the KrakenSpotClass with API key and secret.
     *
     * @param string $apiKey    The API key
     * @param string $apiSecret The API secret
     */
    public function __construct($apiKey, $apiSecret)
    {
        $this->_apiKey    = $apiKey;
        $this->_apiSecret = $apiSecret;
    }

    // Methods for interacting with the Kraken API

    /**
     * Generates the signature for authentication.
     *
     * @param string $endpoint The API endpoint
     * @param string $nonce    The nonce value
     * @param string $postdata The post data
     *
     * @return string The generated signature
     */
    private function generateSignature($endpoint, $nonce, $postdata)
    {
        return hash_hmac('sha512', $endpoint . hash('sha256', $nonce . $postdata, true), base64_decode($this->_apiSecret), true);
    }

    /**
     * Makes a simple request to the Kraken API.
     *
     * @param string $method  The API method
     * @param array  $request The request parameters
     *
     * @return mixed The API response
     */
    private function makeSimpleRequest($method, $request = [])
    {
        $postdata = http_build_query($request, '', '&');
        $endpoint = '/' . self::API_VERSION . $method;

        return $this->curlApi($postdata, null, $endpoint);
    }

    /**
     * Makes a request to the Kraken API with authentication.
     *
     * @param string $method  The API method
     * @param array  $request The request parameters
     *
     * @return mixed The API response
     */
    private function makeRequest($method, $request = [])
    {
        $request['nonce'] = $this->generateNonce();
        $postdata         = http_build_query($request, '', '&');
        $endpoint         = '/' . self::API_VERSION . $method;
        $sign             = $this->generateSignature($endpoint, $request['nonce'], $postdata);

        return $this->curlApi($postdata, $sign, $endpoint);
    }

    /**
     * Generates a nonce value.
     *
     * @return string The generated nonce
     */
    private function generateNonce()
    {
        $nonce = explode(' ', microtime());

        return $nonce[1] . str_pad(substr($nonce[0], 2, 6), 6, '0');
    }

    /**
     * Executes a cURL request to the specified endpoint.
     *
     * @param string      $postdata The post data
     * @param string|null $sign     The authentication signature
     * @param string      $endpoint The API endpoint
     *
     * @return mixed The API response
     */
    private function curlApi($postdata, $sign, $endpoint)
    {
        $curl = curl_init();
        if (! empty($sign)) {
            $header = [
                'API-Key: ' . $this->_apiKey,
                'API-Sign: ' . base64_encode($sign),
            ];
        } else {
            $header = [];
        }
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $postdata);
        curl_setopt($curl, CURLOPT_URL, self::API_URL . $endpoint);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_USERAGENT, 'Kraken PHP API Agent');
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);
        $result = curl_exec($curl);
        if (! $result) {
            echo 'Error:' . curl_error($curl);
            exit('Connection Failure');
        }
        curl_close($curl);

        return $result;
    }

    // Methods for interacting with specific Kraken API endpoints

    /**
     * Gets the account balance.
     *
     * @return mixed The API response
     */
    public function getAccount()
    {
        return $this->makeRequest('/private/Balance');
    }

    /**
     * Gets the price for a given symbol.
     *
     * @param string $symbol The trading symbol
     *
     * @return mixed The API response
     */
    public function getPrice($symbol)
    {
        return $this->makeSimpleRequest('/public/Ticker', ['pair' => $symbol]);
    }

    /**
     * Creates a buy order.
     *
     * @param string $symbol   The trading symbol
     * @param float  $quantity The quantity to buy
     *
     * @return mixed The API response
     */
    public function createOrder($symbol, $quantity)
    {
        return $this->makeRequest('/private/AddOrder', ['pair' => $symbol, 'type' => 'buy', 'ordertype' => 'market', 'volume' => $quantity]);
    }

    /**
     * Closes a sell order.
     *
     * @param string $symbol   The trading symbol
     * @param float  $quantity The quantity to sell
     *
     * @return mixed The API response
     */
    public function closeOrder($symbol, $quantity)
    {
        return $this->makeRequest('/private/AddOrder', ['pair' => $symbol, 'type' => 'sell', 'ordertype' => 'market', 'volume' => $quantity]);
    }

    /**
     * Gets the trade history.
     *
     * @param int|null $startTime The start time of the trade history
     *
     * @return mixed The API response
     */
    public function getHistory($startTime = null)
    {
        $params = ['trades' => true];
        if (! empty($startTime)) {
            $params['start'] = $startTime;
        }

        return $this->makeRequest('/private/TradesHistory', $params);
    }
}
