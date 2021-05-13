<?php

namespace App\Models\Order;

use Illuminate\Database\Eloquent\Model;

class OrderLocalShopUser extends Model
{
    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $table = 'order_local_shop_user';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'platformorderid', 'admin_id'
    ];

}
