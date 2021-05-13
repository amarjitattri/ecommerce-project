<?php

namespace App\Models\Catalog\Product;

use Illuminate\Database\Eloquent\Model;

class ProductVat extends Model
{
    protected $table = "product_vat";
    protected $fillable = ['product_id','updated_by','vat','website_id'];
}
