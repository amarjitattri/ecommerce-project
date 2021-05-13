<?php

namespace App\Models\Catalog\Product;

use Illuminate\Database\Eloquent\Model;
use App\Models\Catalog\Product\Product;

class StockLog extends Model
{
    protected $fillable = [
        'product_id', 'franchise_id', 'start_date', 'end_date'
    ];
 
}