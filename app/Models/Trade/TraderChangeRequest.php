<?php

namespace App\Models\Trade;

use Illuminate\Database\Eloquent\Model;

class TraderChangeRequest extends Model
{
    protected $fillable = [
        'field_name', 'field_change', 'reason', 'user_id'
    ];

    
}
