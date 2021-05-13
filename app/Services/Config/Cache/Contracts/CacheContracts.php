<?php
namespace App\Services\Config\Cache\Contracts;

interface CacheContracts {

    public function getRedisValue($key);
    public function setRedisValue($key, $value);
    public function del($key);

}