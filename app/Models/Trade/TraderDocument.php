<?php

namespace App\Models\Trade;

use Illuminate\Database\Eloquent\Model;

class TraderDocument extends Model
{
    protected $fillable = [
        'document', 'title', 'module_type', 'module_id',
    ];

    const MODULE_SHIPPING_CHANGE = 1;
    const MODULE_REFERENCE = 2;
    const MODULE_TRADER_CHANGE = 3;

}
