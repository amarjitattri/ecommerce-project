<?php

namespace App\Models\Order;

use Illuminate\Database\Eloquent\Model;

class ExchangeProduct extends Model
{
    //

    public function orderline()
    {
        return $this->belongsTo(OrderLine::class, 'order_line_id', 'id');
    }

    public function refund()
    {
        return $this->hasMany(OrderRefundDetail::class, 'module_id', 'exchange_order_id')->where('module_type','=', 7);
    }
}
