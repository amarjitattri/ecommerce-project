<?php
namespace App\Services\Currency\Providers;

use App\Services\Currency\Providers\Contracts\CurrencyConversionContracts;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Http;
class CurrencyLayer implements CurrencyConversionContracts
{
    public function getExchangeRate($data)
    {
        $endpoint = config('constant.currency.currency_layer.end_points');
        $access_key = config('constant.currency.currency_layer.access_key');
        $source = $data['currency_from'];
        $currencies = $data['currency_to'];
        $quote = $source.$currencies;
        $url = config('constant.currency.currency_layer.url').$endpoint.'?access_key='.$access_key.'&source='.$source.'&currencies='.$currencies;
        $client = new Client();
        $response = $client->request('GET', $url);
        $exchangeRates = json_decode($response->getBody()->getContents());
        return $exchangeRates->success ? ['exchange_rate' => $exchangeRates->quotes->{$quote}] : null;
    }
    
}