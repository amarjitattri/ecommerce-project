<?php
namespace App\Services\Currency;

use Illuminate\Support\Facades\Facade;

class CurrencyConversionAPIFacade extends Facade {
    
    protected static function getFacadeAccessor()
    {
        return 'CurrencyConversionAPI';
    }
}