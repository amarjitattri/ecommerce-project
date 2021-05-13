<?php

namespace App\Models\Order;

use Illuminate\Database\Eloquent\Model;

class GuestDetail extends Model
{
    //
    protected $fillable = [
        'email', 'order_id', 'is_business', 'vat_number', 'tax_code'    ];
}
