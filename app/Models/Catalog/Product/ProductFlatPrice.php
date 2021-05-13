<?php

namespace App\Models\Catalog\Product;

use Illuminate\Database\Eloquent\Model;

class ProductFlatPrice extends Model
{
    //
    protected $table = "product_flat_prices";

    public function scopePrice($query, $argv)
    {
        return $query->select('product_id', 'website_id', 'rrp')->where(['product_id' => $argv['product_id'], 'website_id' => $argv['website_id']]);
    }
    public static function getFlatPrice($products, $website_id) {
        return static::select('product_id', 'website_id', 'rrp','final_rrp')
                    ->whereIn('product_id',$products)
                    ->where(['website_id' => $website_id])
                    ->whereNotNull('rrp')
                    ->where('rrp','!=',0)
                    ->where('stock','!=',0)
                    ->orderBy('rrp','asc')->first();
    }

    public function scopeGetStock($query, $argv) {
        return $query->select('product_id', 'website_id', 'stock', 'rrp')
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
