<?php
namespace App\Services\Cart\Cache\Contracts;

interface CacheContracts {

    public function saveBasket($basket);
    public function getAllItems();
    public function getBasketKey($userId);
    public function saveUserBakset($user);
    public function set($key, $value, $expireTime);
    public function del($key);
    public function clearBasket();

}