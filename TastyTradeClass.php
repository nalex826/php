<?php

namespace App\Webhook\Tastytrade;

use App\Webhook\SignalWebhookHelper;

class TastyTradeClass
{
    private $username;
    private $password;
    private $account;
    private $session_token;
    public $error_token;

    public function __construct($apiKey, $apiSecret, $account)
    {
        $this->username  = $apiKey;
        $this->password  = $apiSecret;
        $this->account   = $account;
        $this->getSessionToken();
    }

    public function getSessionToken()
    {
        $body = [
            'login'       => $this->username,
            'password'    => $this->password,
        ];
        try {
            $resp = $this->makeRequest('POST', '/sessions', json_encode($body), false);
            if (isset($resp['code']) && ($resp['code'] == 201 || $resp['code'] == 200)) {
                $this->session_token = $resp['data']['session-token'] ?? '';
            } else {
                $this->error_token = 'Code Session Error from tastyworks';
            }
        } catch (\GuzzleHttp\Exception\BadResponseException $e) {
            $resp = json_decode($e->getResponse()->getBody()->getContents(), true);
            if (isset($resp['error']['code']) && $resp['error']['code'] == 'invalid_credentials') {
                $key         = SignalWebhookHelper::hash_crypt($this->username, 'e');
                $secret      = SignalWebhookHelper::hash_crypt($this->password, 'e');
                $account     = $this->account;
                \DB::connection('mysqlmain')->table('bot_user_accounts')->where('key_token', $key)->where('secret_token', $secret)->where('acct_number', $account)->update(['active' => 0]);
            }
            $this->error_token = $resp['error']['message'];
        }
    }

    private function makeRequest($method, $endpoint, $postdata = '', $auth = true)
    {
        $client  = new \GuzzleHttp\Client();
        $headers = [
            'Accept'       => 'application/json',
            'Content-Type' => 'application/json',
        ];
        if ($auth) {
            if (empty($this->session_token)) {
                return [];
            }
            $headers['Authorization'] = $this->session_token;
        }
        $body                  = $postdata;
        $request               = new \GuzzleHttp\Psr7\Request($method, 'https://api.tastyworks.com' . $endpoint, $headers, $body);
        $res                   = $client->sendAsync($request, ['connect_timeout' => 10])->wait();
        $statusCode            = $res->getStatusCode();
        $jsonData              = json_decode($res->getBody(), true);
        $jsonData['code']      = $statusCode;
        return $jsonData;
    }

    // Get Account
    public function getStatus()
    {
        if (empty($this->account)) {
            return null;
        }
        return $this->makeRequest('GET', '/accounts/' . $this->account . '/trading-status');
    }

    // Get Balance
    public function getBalance()
    {
        if (empty($this->account)) {
            return null;
        }
        return $this->makeRequest('GET', '/accounts/' . $this->account . '/balances');
    }

    // Get Position
    public function getPosition($params = '')
    {
        if (empty($this->account)) {
            return null;
        }
        return $this->makeRequest('GET', '/accounts/' . $this->account . '/positions', $params);
    }

    // Create Order
    public function initOrder($params = '')
    {
        return $this->makeRequest('POST', '/accounts/' . $this->account . '/orders', $params);
    }

    // Test Create or Close Order
    public function initTestOrder($params = '')
    {
        return $this->makeRequest('POST', '/accounts/' . $this->account . '/orders/dry-run', $params);
    }

    // Get History Order
    public function getHistory($params = '')
    {
        return $this->makeRequest('GET', '/accounts/' . $this->account . '/transactions', $params);
    }
}
