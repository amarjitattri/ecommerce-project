<?php
namespace App\Repositories\Order\Interfaces;

interface OrderRepositoryInterface {
    public function save($params);
    
    public function isProductDataMatched($checkoutSession, $cart);
}