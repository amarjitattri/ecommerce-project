<?php

namespace App\Models\Catalog\Product;

use Illuminate\Database\Eloquent\Model;

use App\Models\Catalog\Product\Product;
class ProductFranchiseDetail extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['franchise_id', 'product_id', 'margin_updatedby', 'margin', 'threshold', 'masterpack', 'is_ebay'];

    public function product() {
        $this->belongsTo(Product::class);
    }

    public function scopeGetFranchiseDetails($query, $argv)
    {
        return $query->select('franchise_id', 'product_id', 'margin_updatedby', 'margin', 'threshold', 'masterpack', 'is_ebay')
            ->whereIn('product_id', $argv['product_ids'])
            ->where('franchise_id', $argv['franchise_id']);
    }
}
