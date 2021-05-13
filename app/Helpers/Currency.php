<?php

namespace App\Helpers;

use Tax;
use CurrencyConversionAPI;

class Currency
{
    /**
     * Currency Conversion
     */
    public function convertCurrency($value, $currency, $options = [])
    {
        if ($options && count($options) > 0) {
            $productGroupId = $options['productgroup_id'];
            $data = [
                 'productgroup_id' => $productGroupId,
                 'price' => $value
            ];
            $vatValue = Tax::setVat($data);
            $value = $vatValue['price'];
        }

        //get exchange rate from Currency API
        $paramsAPI = [
            'currency_from' => config('wmo_website.base_currency_code'),
            'currency_to' => $currency['code']
        ];
        $exchageRate = CurrencyConversionAPI::getExchangeRate($paramsAPI);
        if ($exchageRate) {
            $value = $value * $exchageRate['exchange_rate'];
        } elseif(isset($currency['currency_exchange']) && count($currency['currency_exchange']) > 0) {
            $value = $value * $currency['currency_exchange']['exchange_rate'];
        }
        
        return $currency['symbol'].sprintf('%01.2f', $value);
    }

    /**
     * Currency Conversion without formatting
     */
    public function convertCurrencyRaw($value, $currency)
    {
        //get exchange rate from Currency API
        $paramsAPI = [
            'currency_from' => config('wmo_website.base_currency_code'),
            'currency_to' => $currency['code']
        ];
        $exchageRate = CurrencyConversionAPI::getExchangeRate($paramsAPI);

        if ($exchageRate) {
            $value = $value * $exchageRate['exchange_rate'];
        } elseif(isset($currency['currency_exchange']) && count($currency['currency_exchange']) > 0) {
            $value = $value * $currency['currency_exchange']['exchange_rate'];
        }
        
        return sprintf('%01.2f', $value);
    }
    /**
     * Change Currency
     */
    public function setCurrency($currency, $id)
    {
        return $currency->getCurrencyById($id);
    }

    /**
     * convert base currency value to prime currency
     */
    public static function convertToPrimeCurrency($val)
    {
        $primeCurrency = config('wmo_website.prime_currency');
        if (count($primeCurrency['currency_exchange']) > 0) {
            $currencyExchangeRate = $primeCurrency['currency_exchange']['exchange_rate'];
            $val = $currencyExchangeRate * $val;
        }
        return round($val, 2);
    }

    /**
     * convert prime currency value to base currency
     */
    public static function convertToBaseCurrency($val)
    {
        if ($val != 0) {
            $primeCurrency = config('wmo_website.prime_currency');
            if (count($primeCurrency['currency_exchange']) > 0 && isset($primeCurrency['reverse_currency_exchange']['exchange_rate'])) {
                $currencyReverseExchangeRates = $primeCurrency['reverse_currency_exchange']['exchange_rate'];
                $val = $currencyReverseExchangeRates * $val;
            }
            return round($val, 2);
        }

        return $val;
        
    }
}
