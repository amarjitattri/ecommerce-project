<?php

namespace App\Models\Catalog\Product;
use Illuminate\Database\Eloquent\Model;
use App\Models\Catalog\Product\Product;

class ProductFlatDiscount extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['product_id', 'discount', 'website_id','discount_from'];
    protected $table = 'product_flat_discounts';
    

    public function scopeGetDiscount($query, $argv) {
        return $query->select('product_id', 'discount', 'website_id')->where(['product_id' => $argv['product_id'], 'website_id' => $argv['website_id']]);
    }
}
