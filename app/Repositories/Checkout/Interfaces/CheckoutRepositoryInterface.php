<?php

namespace App\Repositories\Checkout\Interfaces;

interface CheckoutRepositoryInterface
{
    public function getShippingAddresses($userId);

    public function getBillingAddresses($userId);

    public function getAddressById($id);

    public function getCarriages();

    public function getShippingCountries($websiteId);

    public function getBillingCountries();

    public function primeShippingAddress($userId);

    public function primeBillingAddress($userId);

    public function updateOrCreateAddress($where, $data);

    public function ifZipBlocked($address);
}
