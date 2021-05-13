<?php
namespace App\Services\Payment\Contracts;

interface PaymentContracts {
    public function processPayment($orderData);
}