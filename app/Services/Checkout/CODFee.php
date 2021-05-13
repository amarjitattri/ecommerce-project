<?php

namespace App\Services\Checkout;
use Currency;

class CODFee {
    
    public function validateCODMethod($checkoutRepository) {
        $paymentMethods = config('wmo_website.payment_gateways');
        if (isset($paymentMethods['cod'])) {
            //check cod charges enabled for delivery location
            $checkoutData = session()->get('checkout');
            $paymentStages = [
                config('constant.checkout.ADDRESS_STAGE'),
                config('constant.checkout.DELIVERY_METHOD_STAGE'),
                config('constant.checkout.PAYMENT_STAGE'),
            ];
            
            if (!empty($checkoutData) && in_array($checkoutData['stage'], $paymentStages) && isset($checkoutData['user_addresses']['shipping']['country_id'])) {
                $shippingCountry = $checkoutData['user_addresses']['shipping']['country_id'];
                return $checkoutRepository->getCODCharges($shippingCountry);
            }

        }
    }

    public function calculateCODCharges($cartObj, $checkoutRepository) {
        if ($this->validateCODMethod($checkoutRepository)) {
            return $this->currencyConversion($this->calculateChargesByShipments($cartObj, $checkoutRepository));
        } else {
            return false;
        }
        
    }

    public function calculateChargesByShipments($cartObj, $checkoutRepository)
    { 
        $checkoutData = session()->get('checkout');
        if (!empty($checkoutData['delivery_method']) && $checkoutData['delivery_method']['shipment_type'] == 2) {
            ksort($checkoutData['delivery_method']['shipments']);
            foreach($checkoutData['delivery_method']['shipments'] as $key => $value) {
                $shipmentTotal = $this->shipmentTotal($value['products']);
                $shipmentCharges[$key] = [
                    'sub_total' => $shipmentTotal,
                    'product_count' => count($value['products']),
                ];
            }
        } else {
            $shipmentTotal = $this->shipmentTotal($checkoutData['delivery_method']['products']);
            $shipmentCharges[config('constant.checkout.FIRST_SHIPMENT_TYPE')] = [
                'sub_total' => $shipmentTotal,
                'product_count' => count($checkoutData['delivery_method']['products']),
            ];
        }
        
        $shippingCountry = $checkoutData['user_addresses']['shipping']['country_id'];
        $primeCurrency = config('wmo_website.prime_currency');
        $applicable = false;
        if (count($primeCurrency['currency_exchange']) > 0 && isset($primeCurrency['reverse_currency_exchange']['exchange_rate'])) {
            $currencyExchangeRate = $primeCurrency['currency_exchange']['exchange_rate'];
            $currencyReverseExchangeRate = $primeCurrency['reverse_currency_exchange']['exchange_rate'];
            $applicable = true;
        }
        
        foreach($shipmentCharges as $key => $sValue) {

            //convert price into website prime currency
            $shippingValue = $applicable ? $currencyExchangeRate * $sValue['sub_total'] : $sValue['sub_total']; 
            
            $codCharges = $checkoutRepository->getCODChargesByValue($shippingCountry, $shippingValue);
            if (is_null($codCharges)) {
                $codCharges = $checkoutRepository->getCODChargesOrderbyFrom($shippingCountry);
            }
            if (!$codCharges) {
                return false;
            }

            //convert COD charges into GBP
            $shipmentCharges[$key]['fee'] = $applicable ? $currencyReverseExchangeRate * $codCharges['charges'] : $codCharges['charges'];

        }
        
        $totalCodCharges = $this->totalCODCharges($shipmentCharges);
        
        //set cod charges in cart
        $codChargesData = [
            'shipments' => $shipmentCharges,
            'cod_total' => $totalCodCharges,
        ];
        $cartObj->setCODCharges($codChargesData);
        $basket = $cartObj->transformCart() ?? [];
        
        return [
            'shipments' => $shipmentCharges,
            'codTotal' => $totalCodCharges,
            'total' => $basket['total']
        ];
        
        
    }
    public function shipmentTotal($sproducts) {
        return array_sum(array_map(function($value) {
          return $value['item_total'];
      }, $sproducts));
    }

    public function totalCODCharges($shipmentCharges)
    {
        return array_sum(array_map(function($value) {
            return $value['fee'];
        }, $shipmentCharges));
    }

    public function currencyConversion($data)
    {
        $currency = session('currency');
        array_walk($data['shipments'], function(&$value) use ($currency) {
            $value['sub_total'] = Currency::convertCurrency($value['sub_total'], $currency);
            $value['fee'] = Currency::convertCurrency($value['fee'], $currency);
        });
        $data['codTotal'] = Currency::convertCurrency($data['codTotal'], $currency);
        $data['total'] = Currency::convertCurrency($data['total'], $currency);
        return $data;
    }
}