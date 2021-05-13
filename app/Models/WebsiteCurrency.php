<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebsiteCurrency extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'franchise_id', 'website_id', 'currency_id',
    ];
    
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = "website_currencies";

}   