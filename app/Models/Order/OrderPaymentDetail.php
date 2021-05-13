<?php

namespace App\Models\Order;

use Illuminate\Database\Eloquent\Model;

class OrderPaymentDetail extends Model
{
    protected $fillable = [
        'platformorderid', 'transaction_id', 'token', 'type', 'data_capture', 'capture_date', 'status'
    ];

    /**
     * This used to check if payment already processed
     * but in case back button of user intrupt in going to start again
     */
    public function scopeCheckPaymentExists($query, $val)
    {
        return $query->where(['platformorderid' => $val['platformorderid']])->exists();
    }
}
