<?php

namespace App\Models\Trade;

use Illuminate\Database\Eloquent\Model;

class TradeOrderDetail extends Model
{

    const TYPE_COLLECTION = 1;
    const TYPE_DELIVERY = 0;
    const PAY_LATER_YES = 1;

    protected $fillable = [
        'platformorderid', 'type', 'order_notes', 'pay_later'
    ];

    public function saveOrderDetails($params) {
        if (isTradeSite()) {
            $checkout_data = request()->session()->get('checkout.trader');
            $data = [
                'type' => $checkout_data['type'],
                'order_notes' => $checkout_data['order_notes']??'',
                'pay_later' => $checkout_data['pay_later'] ?? 0,
            ];
            static::updateOrCreate(['platformorderid' => $params['platformorderid']], $data);
        }
    }

    
}
