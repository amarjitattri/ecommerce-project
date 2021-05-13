<?php
namespace App\Services\Cart\Cache;

use App\Services\Cart\Cache\Contracts\CacheContracts;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class RedisCache implements CacheContracts
{
    const BASKET_KEY_PREFIX = 'basket';
    const GUESTUSER = 'g';
    const REGISTERUSER = 'u';
    const VERSION = 2;
    const COOKIENAME = 'wmobasket';

    private $__basketKey;
    private $__redisConn;
    private $__expireTime;
    private $__websiteId;

    public function __construct()
    {
        $this->__websiteId = config('wmo_website.website_id');
        $this->__redisConn = Redis::connection('cart');
    }

    public function saveBasket($basket)
    {
        $this->__setBasket($basket);
    }

    public function getAllItems()
    {
        return json_decode($this->__redisConn->get($this->__basketKey), true);
    }

    public function getBasketKey($userId)
    {
        $expireTime = config('cart.redis_u_expiretime');
        $basketKey = $this->__getCookieBasketKey();
        if ($userId) {
            $expireTime = config('cart.redis_u_expiretime');
            $basketKey = $this->__getRegisterUserBaksetKey($userId);
        }
        $this->__expireTime = $expireTime;
        $this->__basketKey = $basketKey;
    }

    public function saveUserBakset($user)
    {
        $guestItems = $this->getAllItems() ?? null;
        if ($guestItems) {
            $this->__redisConn->del($this->__basketKey);
        }
        $this->__basketKey = $this->__getRegisterUserBaksetKey($user->id);
        $userItems = $this->getAllItems() ?? null;

        $basket = $this->__mergeItems($guestItems, $userItems);
        $this->__expireTime = config('cart.redis_u_expiretime');
        $this->__setBasket($basket);
    }

    private function __getCookieBasketKey()
    {
        $minutes = config('cart.cookie_g_expiretime');
        $sessioId = session()->getId();
        $browserSavedBaksetKey = isset($_COOKIE[static::COOKIENAME]) ? $_COOKIE[static::COOKIENAME] : null;
        if (!$browserSavedBaksetKey) {
            $browserSavedBaksetKey = $sessioId;
            setcookie(static::COOKIENAME, $sessioId, $minutes, "/", "", config('session.secure'), config('session.http_only'));
        }

        return static::BASKET_KEY_PREFIX.'_'.static::GUESTUSER.'_'.$this->__websiteId.'_'.$browserSavedBaksetKey.'_'.static::VERSION;
    }

    private function __getRegisterUserBaksetKey($userId)
    {
        return static::BASKET_KEY_PREFIX.'_'.static::REGISTERUSER.'_'.$this->__websiteId.'_'.$userId.'_'.static::VERSION;
    }

    private function __mergeItems($guestItems, $userItems)
    {
        if ($guestItems && $userItems) {
            $guestPrItems = $guestItems['products'];
            $userPrItems = $userItems['products'];
            foreach ($guestPrItems as $key => $value) {
                if (array_key_exists($key, $userPrItems)) {
                    $existingQty = $userPrItems[$key]['qty'] ?? 0;
                    $qty = $value['qty'] + $existingQty;
                    $userPrItems[$key]['qty'] = $qty;

                    unset($guestPrItems[$key]);
                }
            }
            $basket['products'] = array_merge_recursive($guestPrItems, $userPrItems);
            $basket['promotion'] = isset($guestItems['promotion']) ? $guestItems['promotion'] : null;
        } elseif ($guestItems) {
            $basket = $guestItems;
        } elseif ($userItems) {
            $basket = $userItems;
        } else {
            $basket = array();
        }
        return $basket;
    }

    private function __setBasket($basket)
    {
        $cart = $this->getAllItems();
        if (empty($cart['token'])) {
            $basket['token'] = Str::uuid()->toString();
        } else {
            $basket['token'] = $cart['token'];
        }
        $value = json_encode($basket);
        $key = $this->__basketKey;
        $this->set($key, $value, $this->__expireTime);
    }

    public function set($key, $value, $expireTime)
    {
        $this->__redisConn->set($key, $value);
        $this->__redisConn->expire($key, $expireTime);
    }

    public function del($key)
    {
        $this->__redisConn->del($key);
    }

    public function clearBasket()
    {
        $key = $this->__basketKey;
        $this->del($key);
    }
}
