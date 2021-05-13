<?php

namespace App\Repositories\Checkout;

use App\Models\Country;
use App\Models\Carriage;
use App\Models\UserAddress;
use Illuminate\Support\Facades\DB;
use App\Models\CMS\Website\WebsiteDeliveryLocation;
use App\Repositories\Checkout\Interfaces\CheckoutRepositoryInterface;
use App\Models\CMS\WebsiteCodCharge;

class CheckoutRepository implements CheckoutRepositoryInterface
{
    const ACTIVE_STATUS = '1';
    
    public function getShippingAddresses($userId)
    {
        return UserAddress::select('user_addresses.*')->with(['country'])->whereHas('country', function ($query) {
            $query->where('status', static::ACTIVE_STATUS);
        })->where('user_id', $userId)->where('type', UserAddress::SHIPPING_ADDRESS_TYPE)
        ->tradeAddresses()->get()->keyBy('id')->toArray();
    }

    public function getBillingAddresses($userId)
    {
        return UserAddress::with(['country'])->whereHas('country', function ($query) {
            $query->where('status', static::ACTIVE_STATUS);
        })->where('user_id', $userId)->where('type', UserAddress::BILLING_ADDRESS_TYPE)->get()->keyBy('id')->toArray();
    }

    public function getAddressById($id)
    {
        return UserAddress::with(['country'])->where('id', $id)->first()->toArray();
    }

    public function getCarriages()
    {
        return Carriage::getCarriages();
    }

    public function getShippingCountries($websiteId)
    {
        return WebsiteDeliveryLocation::getShippingCountries($websiteId);
    }

    public function getBillingCountries()
    {
        return Country::getBillingCountries();
    }

    public function primeShippingAddress($userId)
    {
        return UserAddress::where('is_prime', UserAddress::IS_PRIME_YES)->where('type', UserAddress::SHIPPING_ADDRESS_TYPE)->where('user_id', $userId)->first();
    }

    public function primeBillingAddress($userId)
    {
        return UserAddress::where('is_prime', UserAddress::IS_PRIME_YES)->where('type', UserAddress::BILLING_ADDRESS_TYPE)->where('user_id', $userId)->first();
    }

    public function updateOrCreateAddress($where, $data)
    {
        return UserAddress::updateOrCreate($where, $data)->id;
    }

    public function ifZipBlocked($address)
    {
        return UserAddress::ifZipBlocked($address);
    }

    public function getCODCharges($countryId) 
    {
        $params = [
            'website_id' => config('wmo_website.website_id'),
            'country_id' => $countryId
        ];
        return WebsiteCodCharge::getCODCharges($params)->exists();
    }

    public function getCODChargesByValue($countryId, $value)
    {
        $params = [
            'website_id' => config('wmo_website.website_id'),
            'country_id' => $countryId,
            'to' => $value
        ];
        return WebsiteCodCharge::getChargesByValue($params)->first();
    }

    public function getCODChargesOrderbyFrom($countryId) 
    {
        $params = [
            'website_id' => config('wmo_website.website_id'),
            'country_id' => $countryId
        ];
        return WebsiteCodCharge::getCODCharges($params)->orderBy('from', 'DESC')->first();
    }
}
