<?php

namespace App\Models\Trade;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class TraderImpersonate extends Model
{
    
    public function scopeValidateTradeToken($query, $argv)
    {
        return $query->where([
            'token' => $argv['token']
        ]);
    }
}
