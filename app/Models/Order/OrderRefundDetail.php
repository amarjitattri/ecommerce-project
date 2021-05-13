<?php

namespace App\Models\Order;

use Illuminate\Database\Eloquent\Model;

class OrderRefundDetail extends Model
{
    //
    public function orderline()
    {
        return $this->belongsTo(OrderLine::class, 'module_id', 'id');
    }

    public function return()
    {
        return $this->belongsTo(OrderReturn::class, 'module_id', 'id');
    }

    public function exchange()
    {
        return $this->belongsTo(ExchangeOrder::class, 'module_id', 'id');
    }
}
