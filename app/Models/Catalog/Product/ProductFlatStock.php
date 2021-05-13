<?php

namespace App\Models\Catalog\Product;

use Illuminate\Database\Eloquent\Model;

class ProductFlatStock extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = "product_flat_stocks";



    public function scopeGetStock($query, $argv)
    {
        return $query->select('product_id', 'website_id', 'stock', 'shop_status', 'master_stock', 'master_shop_status', 'eta', 'threshold')
            ->whereIn('product_id', $argv['product_ids'])
            ->where('website_id', $argv['website_id']);
    }

    public function scopeGetEta($query, $argv)
    {
        return $query->select('product_id', 'website_id', 'eta')
            ->whereIn('product_id', $argv['product_ids'])
            ->where('website_id', $argv['website_id']);
    }

}
