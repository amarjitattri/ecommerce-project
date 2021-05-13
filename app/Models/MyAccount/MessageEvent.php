<?php

namespace App\Models\MyAccount;

use Illuminate\Database\Eloquent\Model;

class MessageEvent extends Model
{
    const ACTIVE = 1;
    const ORDER_PLACEMENT = 3;
    const WISHLIST_ORDER_ARRIVED='Wishlist order arrived';
    
    public function scopeGetValidEvents($query)
    {
        return $query->select('id', 'name', 'description')
            ->where('is_optional', static::ACTIVE);
    }
}
