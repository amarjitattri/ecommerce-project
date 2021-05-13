<?php

namespace App\Helpers;

use App\Models\Catalog\ProductgoupVatsetting;

use App\Models\CMS\Website\WebsiteDeliveryLocation;
use App\Models\Country;
use App\Models\CMS\Website\WebsiteInvoice;
class Tax
{
    /**
     * Currency Conversion
     */
    public function setVat($data)
    {
        $countryData = $this->getCountryData();
        if (!empty($countryData)) {
            $countryId = $countryData->id;
            $params = [
                'productgroup_id' => $data['productgroup_id'],
                'website_id' => config('wmo_website.website_id'),
                'country_id' => $countryId,
            ];
            
            $productGroupVatSetting = ProductgoupVatsetting::getProductGroupVat($params)->first();
            if ($productGroupVatSetting) {
                
                $vatValue = $countryData->{$productGroupVatSetting['vat_rate']};
                $data['price'] = round($data['price'] + (($data['price'] / 100) * $vatValue), 2);
            }
            
        }
        return $data;
    }

    public function setVatProducts($data)
    {
        $countryData = $this->getCountryData();
        if(!empty($countryData)) {
            
            $countryId = $countryData->id;
            $productGroupIds = array_unique(array_filter(array_column($data['products'], 'productgroup_id')));

            $params = [
                'productgroup_ids' => $productGroupIds,
                'website_id' => config('wmo_website.website_id'),
                'country_id' => $countryId,
            ];

            $productGroupVatSettings = ProductgoupVatsetting::getVatProductGroups($params)->get()->pluck('vat_rate', 'productgroup_id')->toArray();
            
            array_walk($data['products'], function(&$value) use ($productGroupVatSettings, $countryData) {
                if (isset($productGroupVatSettings[$value['productgroup_id']])) {
                    $vatValue = $countryData->{$productGroupVatSettings[$value['productgroup_id']]};
                    $value['price'] = round($value['price'] + (($value['price'] / 100) * $vatValue), 2);
                    $vat = ($value['final_price'] / 100) * $vatValue;
                    $value['vat'] = round($vat, 2);
                    $value['final_price'] = round($value['final_price'] + $vat, 2);
                }
                
            });
        }
        
        return $data;
    }

    public function shipmentCountryCalculation() {
        $params = [
            'website_id' => config('wmo_website.website_id'),
            'country_id' => session('shipping_country_id')
        ];

        $websiteDeliverLocation = WebsiteDeliveryLocation::getDeliverLocationByCountryId($params)->first();

        //set country data
        //change country id in case of website vat
        $countryId = session('shipping_country_id');
        if ($websiteDeliverLocation['vat_applicable'] == 3) {
            $params = [
                'website_id' => config('wmo_website.website_id')
            ];
            $websiteInvoice = WebsiteInvoice::getWebsiteCountry($params)->first();
            $countryId = $websiteInvoice->country_id;
        }
        if ($websiteDeliverLocation['vat_applicable'] != 1) {
           $countryVat = [
                'shipping_id' => session('shipping_country_id'),
                'country_data' => Country::getCountry(['country_id' => $countryId])->first()
           ]; 
           session(['shipping_country_calculation' => $countryVat]);
           return $countryVat;
        } else {
            return false;
        }

        
    }

    public function getCountryData()
    {
        //set tax for scaler array
        $countryData = config('wmo_website.countryData');
        if (session('shipping_country_id')) {
            if (isset(session('shipping_country_calculation')['shipping_id']) && session('shipping_country_calculation')['shipping_id'] == session('shipping_country_id')) {
                $shipmentCountryCalculation = session('shipping_country_calculation');
            } else {
                $shipmentCountryCalculation = $this->shipmentCountryCalculation();
            }
            $countryData = $shipmentCountryCalculation ? $shipmentCountryCalculation['country_data'] : null;
        }
        return $countryData;
    }

}