<?php
namespace App\Repositories\Cart\Interfaces;

interface PromoCodeRepositoryInterface {
    public function findByPromoCode($promoCode);
}