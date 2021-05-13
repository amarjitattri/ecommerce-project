<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Catalog\Product\ProductGroup\ProductGroup;
use App\Models\CMS\{
    NewsCategories,
    Offer,
    Banner,
    News
};
use App\Models\Catalog\Product\Product;
use App\Models\CMS\Currency;

class Website extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'website_code', 'website_name', 'hostname', 'statichost', 'timezone', 'warehousecode', 'status',
    ];
    
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = "websites";


    /**
     * Product Margin with Franchise
     */
    public function productgroups() {
        return $this->belongsToMany(ProductGroup::class, 'productgroup_websites', 'productgroup_id', 'website_id')->withTimestamps();
    }

    /**
     *  Margin with newscategories
     */

    public function newscategories() {
        return $this->belongsToMany(NewsCategories::class, 'news_category_websites', 'news_category_id', 'website_id');
    }

    /**
     *  Margin with Offer
     */
    public function offer() {
        return $this->belongsToMany(Offer::class, 'website_offers', 'offer_id', 'website_id');
    }

    /**
     *  Margin with banner
     */
    public function banner() {
        return $this->belongsToMany(Offer::class, 'website_banners', 'banner_id', 'website_id');
    }

    /**
     *  Margin with news
     */
    public function news() {
        return $this->belongsToMany(News::class, 'website_banners', 'banner_id', 'website_id');
    }

    /**
     * Relationship with product by pivot table
     */
    public function pgproducts() {
        return $this->belongsToMany(Product::class, 'product_productgroup_websites', 'product_id', 'website_id');
    }

    /**
     * Relationship with website currencies by pivot table
     */
    public function currencies() {
        return $this->belongsToMany(Currency::class, 'website_currencies', 'website_id', 'currency_id');
    }
    
}
