<?php

namespace App\Webhook\Bitget;

use bitget\api\mix\MixAccountApi;
use bitget\api\mix\MixMarketApi;
use bitget\api\mix\MixOrderApi;
use bitget\api\mix\MixPositionApi;
use bitget\internal\BitgetRestClient;
use bitget\model\mix\account\SetLeverageReq;
use bitget\model\mix\account\SetMarginModeReq;

class BitGetFutures
{
    private $trys    = 0;
    private $maxTrys = 10;
    private $restClient;
    private $currency;
    public MixAccountApi $mixAccountApi;
    public MixOrderApi $mixOrderApi;
    public MixPositionApi $mixPositionApi;
    public MixMarketApi $mixMarketApi;

    /**
     * BitGetFutures constructor.
     *
     * @param $opts - Options for Bitget API
     */
    public function __construct($opts)
    {
        if (is_object($opts)) {
            $key              = hash_crypt($opts->key_token, 'd');
            $secret           = hash_crypt($opts->secret_token, 'd');
            $passphrase       = hash_crypt($opts->passphrase, 'd');
            $this->currency   = (! empty($opts->currency)) ? $opts->currency : 'USDT';
            $this->restClient = new BitgetRestClient($key, $secret, $passphrase);
        }
    }

    /**
     * Set up Bitget API for trading
     *
     * @param int   $leverage - Leverage value
     * @param array $symbols  - Symbols to set up
     *
     * @return bool - True if setup successful, otherwise false
     */
    public function setUpBitget($leverage, $symbols = [])
    {
        // Check if REST client is initialized
        if (empty($symbols)) {
            return false;
        }
        $this->mixOrderApi = $this->restClient->getMixClient()->getOrderApi();
        $payload           = [
            'direction' => 'close_long',
            'volume'    => 0,
            'symbol'    => 'HBARUSDT_UMCBL',
        ];
        $responds          = $this->closeOrder($payload);
        if (preg_match('/Incorrect permissions/i', $responds)) {
            return false;
        }
        $this->mixAccountApi = $this->restClient->getMixClient()->getAccountApi();
        foreach ($symbols as $symbol) {
            // Retrieve AccountInfo for each symbol
            // Set Leverage
            $setLeverageReq = new SetLeverageReq();
            $setLeverageReq->setLeverage($leverage);
            $setLeverageReq->setSymbol($symbol);
            $setLeverageReq->setMarginCoin($this->currency);
            $setLeverageReq->setHoldSide('short');
            $this->mixAccountApi->setLeverage($setLeverageReq);
            // Set Leverage
            $setLeverageReq->setHoldSide('long');
            $this->mixAccountApi->setLeverage($setLeverageReq);
            // Set MarginMode
            $setMarginModeReq = new SetMarginModeReq();
            $setMarginModeReq->setSymbol($symbol);
            $setMarginModeReq->setMarginCoin($this->currency);
            $setMarginModeReq->setMarginMode('crossed');
            $this->mixAccountApi->setMarginMode($setMarginModeReq);
            sleep(1);
        }

        return true;
    }

    /**
     * Set up Bitget API for getting user account
     *
     * @param array $access  Access levels to retrieve (e.g., history, position).
     * @param array $symbols symbols to retrieve data for
     *
     * @return stdClass|null returns an object containing account information or null if the REST client is not initialized
     *
     * @throws Exception throws an exception if there's an error retrieving account information
     */
    public function getAccount($access = [], $symbols = ['BTCUSDT_UMCBL'])
    {
        // Check if REST client is initialized
        if (empty($this->restClient)) {
            return null;
        }

        // Initialize APIs
        $this->mixAccountApi = $this->restClient->getMixClient()->getAccountApi();

        try {
            // Initialize account object
            $account = new stdClass();

            // Retrieve account balance
            $balance     = $this->mixAccountApi->account('BTC' . $this->currency . '_UMCBL', $this->currency);
            $balanceJson = json_decode($balance);

            // Check if balance retrieval was successful
            if (! empty($balanceJson->msg) && 'success' === $balanceJson->msg) {
                $account->balance = $balanceJson->data;
            } else {
                $account->error = $balanceJson->msg;
            }

            // Retrieve transaction history if requested
            if (in_array('history', $access)) {
                $transactions      = [];
                $collection        = [];
                $startTime         = strtotime('-30 days') * 1000;
                $endTime           = strtotime(date('Y-m-d 23:59:59', time())) * 1000;
                $this->mixOrderApi = $this->restClient->getMixClient()->getOrderApi();
                foreach ($symbols as $symbol) {
                    // Retrieve order history for each symbol
                    $history     = $this->mixOrderApi->history($symbol, $startTime, $endTime, '500', '', 'false');
                    $historyJson = json_decode($history);
                    if (! empty($historyJson->msg) && 'success' === $historyJson->msg) {
                        $collect = $historyJson->data->orderList;
                        if (! empty($collect)) {
                            $collection = array_merge($collect, $collection);
                        }
                    }
                }
                if (! empty($collection)) {
                    foreach ($collection as $order) {
                        $transactions[$order->uTime] = $order;
                    }
                }
                krsort($transactions);
                $account->transactions = array_values($transactions);
            }

            // Retrieve positions if requested
            if (in_array('position', $access)) {
                $positions            = [];
                $this->mixPositionApi = $this->restClient->getMixClient()->getMixPositionApi();
                $position             = $this->mixPositionApi->allPosition('umcbl', $this->currency);
                $positionJson         = json_decode($position);
                if (! empty($positionJson->msg) && 'success' === $positionJson->msg) {
                    $this->mixMarketApi = $this->restClient->getMixClient()->getMarketApi();
                    foreach ($positionJson->data as $pos) {
                        if ($pos->averageOpenPrice > 0) {
                            // Retrieve ticker information for each position
                            $ticker     = $this->mixMarketApi->ticker($pos->symbol);
                            $tickerJson = json_decode($ticker);
                            if (! empty($tickerJson->msg) && 'success' === $tickerJson->msg) {
                                $pos->close = $tickerJson->data->bestAsk;
                            }
                            $positions[$pos->symbol . '_' . $pos->holdSide] = $pos;
                        }
                    }
                }
                $account->position = $positions;
            }

            // Return the account object
            return $account;
        } catch (Exception $e) {
            // Throw an exception if there's an error
            throw new Exception('Error retrieving account information: ' . $e->getMessage());
        }
    }

    /**
     * Retrieves position data for the current user.
     *
     * @return array|null returns an array containing position data or null if the REST client is not initialized
     */
    public function getPosition()
    {
        // Check if REST client is initialized
        if (empty($this->restClient)) {
            return null;
        }

        try {
            $positions            = [];
            $this->mixPositionApi = $this->restClient->getMixClient()->getMixPositionApi();
            $position             = $this->mixPositionApi->allPosition('umcbl', $this->currency);
            $positionJson         = json_decode($position);

            // Check if position retrieval was successful
            if (! empty($positionJson->msg) && 'success' === $positionJson->msg) {
                $this->mixMarketApi = $this->restClient->getMixClient()->getMarketApi();
                foreach ($positionJson->data as $pos) {
                    // Filter out positions with non-positive average open price
                    if ($pos->averageOpenPrice > 0) {
                        $ticker     = $this->mixMarketApi->ticker($pos->symbol);
                        $tickerJson = json_decode($ticker);

                        // Check if ticker retrieval was successful
                        if (! empty($tickerJson->msg) && 'success' === $tickerJson->msg) {
                            $pos->close = $tickerJson->data->bestAsk;
                        }
                        $positions[$pos->symbol . '_' . $pos->holdSide] = $pos;
                    }
                }
            }

            return $positions;
        } catch (HttpException $e) {
            // Handle any exceptions
            echo $e->getMessage();
        }
    }

    /**
     * Retrieves transaction history for specified symbols.
     *
     * @param array $symbols symbols for which transaction history is to be retrieved
     *
     * @return array|null returns an array of transaction history or null if the REST client is not initialized
     */
    public function getTransactions($symbols = ['BTCUSDT_UMCBL'])
    {
        // Check if REST client is initialized
        if (empty($this->restClient)) {
            return null;
        }

        try {
            $transactions      = [];
            $collection        = [];
            $startTime         = strtotime('-30 days') * 1000;
            $endTime           = strtotime(date('Y-m-d 23:59:59', time())) * 1000;
            $this->mixOrderApi = $this->restClient->getMixClient()->getOrderApi();

            // Retrieve transaction history for each symbol
            if (! empty($symbols)) {
                foreach ($symbols as $symbol) {
                    $history     = $this->mixOrderApi->history($symbol, $startTime, $endTime, '500', '', 'false');
                    $historyJson = json_decode($history);

                    // Check if history retrieval was successful
                    if (! empty($historyJson->msg) && 'success' === $historyJson->msg) {
                        $collect = $historyJson->data->orderList;
                        if (! empty($collect)) {
                            $collection = array_merge($collect, $collection);
                        }
                    }
                }
            }

            // Organize transactions by timestamp and return
            if (! empty($collection)) {
                foreach ($collection as $col) {
                    $transactions[$col->uTime] = $col;
                }
            }
            krsort($transactions);

            return array_values($transactions);
        } catch (HttpException $e) {
            // Handle any exceptions
            // echo $e->getMessage();
        }
    }
}
