<?php

namespace App\Models\MyAccount;

use Illuminate\Database\Eloquent\Model;
use App\Models\Catalog\DescriptionAssociation;
use App\Models\CMS\BikeModel;
use App\Models\Catalog\Product\Product;
use App\Models\Catalog\Product\ProductFlatDetail;
use App\Models\Catalog\Category;

class UserWishlist extends Model
{
    protected $fillable = ['id', 'website_id', 'user_id', 'product_id', 'assoc_id', 'bike_model_id', 'model_year', 'cat_id'];

    public function scopeAllWishlist($query, $argv)
    {
        $websiteId = $argv['website_id'];
        return $query
            ->with(['association',
                'model',
                'product.brands' => function ($q) {
                    $q->select('brands.*')->languageJoin();
                },
                'product.productImages',
                'categroies',
                'detailAssoc'=>function ($q) use ($websiteId) {
                    $q->where('website_id', $websiteId);
                },
            ])
            ->whereHas('detailAssoc', function($query) use ($websiteId){
                $query->where('website_id', $websiteId);
            })
            ->where([
                'user_id' => $argv['user_id'],
                'website_id' => $argv['website_id'],
            ]);
    }
    public function association()
    {
        return $this->belongsTo(DescriptionAssociation::class, 'assoc_id', 'id');
    }

    public function model()
    {
        return $this->belongsTo(BikeModel::class, 'bike_model_id', 'id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id', 'code');
    }
    public function detailAssoc()
    {
        return $this->belongsTo(ProductFlatDetail::class, 'product_id', 'product_code');
    }

    public function categroies()
    {
        return $this->belongsTo(Category::class, 'cat_id');
    }

    public function scopeDeleteWishlist($query, $argv)
    {
        return $query->where([
            'user_id' => $argv['user_id'],
            'website_id' => $argv['website_id'],
            'product_id' => $argv['product_id'],
            'assoc_id' => $argv['assoc_id'],
        ])->delete();
    }
}
