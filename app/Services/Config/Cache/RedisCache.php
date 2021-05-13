<?php
namespace App\Services\Config\Cache;

use App\Services\Config\Cache\Contracts\CacheContracts;
use Illuminate\Support\Facades\Redis;

class RedisCache implements CacheContracts {

    const SITE_KEY_PREFIX = 'website';
    const VERSION = 1;

    const LOCALE_KEY_PREFIX = 'locale';
    const GUESTUSER = 'g';
    const COOKIENAME = 'wmolocalesetting';
    
    private $__redisConn;

    public function __construct()
    {   
        $this->__redisConn = Redis::connection('site');
    }
    /**
     * Set Redis cache for website config
     */
    public function setRedisValue($key, $value)
    {
        $this->__redisConn->set($key , json_encode($value));
    }
    /**
     * Get Redis cache for website config
     */
    public function getRedisValue($key)
    {
        return json_decode($this->__redisConn->get($key), true);
    }

    /**
     * delete redis cache
     */
    public function del($key) {
        $this->__redisConn->del($key);
    }

    private function __getCookieLocaleKey($websiteId)
    {
        $minutes = config('cart.cookie_glocale_expiretime');
        $sessioId = static::LOCALE_KEY_PREFIX.session()->getId();
        $browserSavedBaksetKey = isset($_COOKIE[static::COOKIENAME]) ? $_COOKIE[static::COOKIENAME] : null;
        if (!$browserSavedBaksetKey) {
            $browserSavedBaksetKey = $sessioId;
            setcookie(static::COOKIENAME, $sessioId, $minutes, "/", "", config('session.secure'), config('session.http_only'));
        }

        return static::LOCALE_KEY_PREFIX.'_'.static::GUESTUSER.'_'.$websiteId.'_'.$browserSavedBaksetKey.'_'.static::VERSION;
    }

    public function getLocaleKey($websiteId)
    {   
        return $this->__getCookieLocaleKey($websiteId);
    }
}