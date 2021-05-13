<?php

namespace App\Models\Catalog\Product;

use Illuminate\Database\Eloquent\Model;
use App\Models\Catalog\Product\Product;

class ProductInventory extends Model
{
    protected $fillable = ['product_id', 'supplier_id', 'franchise_id', 'location', 'stock_in_out', 'stock', 'stockupdate_date', 'reference'];

    public function product() {
        $this->belongsTo(Product::class);
    }
}
