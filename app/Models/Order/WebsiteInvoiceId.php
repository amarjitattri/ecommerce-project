<?php

namespace App\Models\Order;

use Illuminate\Database\Eloquent\Model;

class WebsiteInvoiceId extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'website_id', 'token'
    ];
    
}
