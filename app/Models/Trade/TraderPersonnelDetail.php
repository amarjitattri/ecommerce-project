<?php

namespace App\Models\Trade;

use Illuminate\Database\Eloquent\Model;

class TraderPersonnelDetail extends Model
{
    protected $fillable = [
        'contact_name', 'contact_no', 'email', 'user_id', 'type'
    ];
}
