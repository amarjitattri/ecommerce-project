<?php

namespace App\Models;
use Auth;
use Currency;
use App\Models\User;
use App\Models\Catalog\Product\Product;
use Session;

use Illuminate\Database\Eloquent\Model;

class QuoteOrder extends Model
{
    protected $fillable = [
        'website_id', 'user_id', 'product_id', 'assoc_id','bike_model_id','cat_id','options'
    ];

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'quote_orders';
}