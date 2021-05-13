<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Model;


class LocalShopUserIp extends Model
{
    protected $guard = 'admin';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id', 'ip', 
    ];

    
    
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = "local_shop_user_ip";

}
