<?php

namespace App\Models\Order;

use Illuminate\Database\Eloquent\Model;

class ProductInventory extends Model
{
    public function scopeGetstockByIds($query, $argv)
    {
        return $query->select('product_id', 'franchise_id', 'stock')
        ->whereIn('product_id', $argv['product_ids'])
        ->where('franchise_id', $argv['franchise_id']);
    }
}
