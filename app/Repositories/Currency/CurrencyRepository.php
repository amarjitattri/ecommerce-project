<?php
namespace App\Repositories\Currency;

use App\Repositories\Currency\Interfaces\CurrencyRepositoryInterface;
use App\Models\CMS\Currency;
use App\Models\CMS\Website;

class CurrencyRepository implements CurrencyRepositoryInterface {
    
    private $__currencyObj;
    public function __construct(Currency $currencyObj)
    {   
        $this->__currencyObj = $currencyObj;
    }

    public function getCurrencyById($id)
    {
        $params = array('id' => $id);
        $currency = $this->__currencyObj->getCurrencyById($params)->first()->toArray();
        
        $exchangeRates = Website::getExchangeCurrency($currency);
        $currency['currency_exchange'] = $exchangeRates;
        return $currency;
    }

    
}