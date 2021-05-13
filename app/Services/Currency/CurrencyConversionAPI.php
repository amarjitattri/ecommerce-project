<?php
namespace App\Services\Currency;

use App\Services\Currency\Providers\Contracts\CurrencyConversionContracts;

class CurrencyConversionAPI
{
    protected $currencyObj;
    public function __construct(CurrencyConversionContracts $currencyApi)
    {
        $this->currencyObj = $currencyApi;
    }
    public function getExchangeRate($data)
    {
        return $this->currencyObj->getExchangeRate($data);
    }
}
