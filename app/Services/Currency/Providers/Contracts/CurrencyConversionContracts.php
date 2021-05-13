<?php
namespace App\Services\Currency\Providers\Contracts;

interface CurrencyConversionContracts {
    public function getExchangeRate($data);
}

