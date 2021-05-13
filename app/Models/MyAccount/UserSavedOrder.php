<?php

namespace App\Models\MyAccount;

use Illuminate\Database\Eloquent\Model;

class UserSavedOrder extends Model
{
    protected $fillable = ['id', 'user_id', 'website_id', 'data', 'order_id', 'updated_at'];

    public function scopeGetSavedOrder($query, $argv)
    {
        return $query->where([
                'user_id' => $argv['user_id'],
                'website_id' => $argv['website_id']
            ]);
    }
}
