<?php

namespace App\Models\Order;

use Illuminate\Database\Eloquent\Model;

class OrderLocalShopOtherThings extends Model
{
    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $table = 'order_local_shop_other_things';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'platformorderid', 'order_type','notes','ref_order_number','reference_notes'
    ];

}