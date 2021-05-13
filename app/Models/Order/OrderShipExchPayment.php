<?php

namespace App\Models\Order;

use Illuminate\Database\Eloquent\Model;

class OrderShipExchPayment extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'type', 'order_exchange_id', 'order_shipment_id', 'transaction_id', 'token', 'payment_type', 'data_capture', 'capture_date', 'status', 'user_id'
    ];
}
