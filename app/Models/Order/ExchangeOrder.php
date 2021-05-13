<?php

namespace App\Models\Order;

use Illuminate\Database\Eloquent\Model;
use App\Models\CMS\Website\Website;
use Illuminate\Support\Facades\Auth;

class ExchangeOrder extends Model
{

/**
     * The attributes that are mass assignable.
     * @var array
     */
    protected $fillable = [
        'platformorderid','order_notes','promo_code_id','new_amount','exchange_amount','admin_id'
    ];

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = "exchange_orders";

    public function exchangeproducts()
    {
        return $this->hasMany(ExchangeProduct::class, 'exchange_order_id');
    }
}
