<?php
namespace App\Repositories\Cart;

use App\Repositories\Cart\Interfaces\PromoCodeRepositoryInterface;
use App\Models\Catalog\Promocode\Promocode;

class PromoCodeRepository implements PromoCodeRepositoryInterface {
    
    private $__promocodeObj;

    const ACTIVE = 1;
    public function __construct(Promocode $promocodObj)
    {   
        $this->__promocodeObj = $promocodObj;
    }

    public function findByPromoCode($promoCode)
    {
        $params = [
            'promocode' => $promoCode,
            'website_id' => config('wmo_website.website_id'),
            'status' => static::ACTIVE
        ];
        return $this->__promocodeObj->getPromoByCode($params)->first();
    }
}