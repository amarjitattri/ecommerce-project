<?php

namespace App\Http\Traits;

use Illuminate\Database\Eloquent\Model;
use App\Models\{
    WebsitePriceSetting
};
use App\Models\Catalog\{
    DescriptionAssociation,
    Brand
};
use App\Models\Catalog\Product\{
    Product,
    ProductImage,
    ProductModelLink,
    ProductFlatDetail,
    ProductDescription

};
use App\Models\Catalog\Product\ProductKit\{
    ProductKit,
    KitLabel
};
use DB;
use Session;
use App\Models\Catalog\Product\ProductProductgroupWebsite;
const VARIANT_PRODUCT_TYPE = '7';
use App\Models\MyAccount\UserWishlist;
trait ProductTrait{
    public function websiteshopstatus() {
        return $this->belongsTo('App\Models\Catalog\Product\ProductWebsiteDetail','id','product_id');
    }
    public function images() {
        return $this->belongsTo(ProductImage::class,'id','product_id');
    }
    public function productImages() {
        return $this->hasMany(ProductImage::class,'product_id','id');
    }
    public function brands() {
        return $this->belongsTo('App\Models\Catalog\Brand','brand_id','id');
    }
    public function price() {
        return $this->belongsTo('App\Models\Catalog\Product\ProductFlatDetail','id','product_id');
    }
    public function productStock() {
        return $this->belongsTo('App\Models\Catalog\Product\ProductFlatStock','id','product_id');
    }
    public function attributes() {
        return $this->hasMany('App\Models\Catalog\Product\ProductAttribute','product_id','id');
    }
    public function variantattributes() {
        return $this->hasMany('App\Models\Catalog\Product\ProductAttribute','product_id','parent_id');
    }
    public function productvariantattributes() {
        return $this->hasMany('App\Models\Catalog\Product\ProductAttribute','product_id','id');
    }
    public function kitattributes() {
        return $this->hasMany('App\Models\Catalog\Product\ProductKit\ProductKit','product_id','id');
    }
    public function fullDescription(){
        return $this->belongsTo('App\Models\Catalog\Product\ProductDescription','description_id','id');
    }
    public function productDiscount(){
        return $this->belongsTo('App\Models\Catalog\Product\ProductFlatDiscount','id','product_id');
    }
    public function productCategory(){
        return $this->belongsTo('App\Models\Catalog\Category','category_id','id');
    }
    public static function getRRP($website_id){
        return WebsitePriceSetting::where('website_id',$website_id)->first();
    }
    public function productVariant(){
        return $this->belongsTo('App\Models\Catalog\Product\ProductVariant','id','variant_id');
    }

    public function productgroups()
    {
        return $this->hasOne(ProductProductgroupWebsite::class);
    }
    public function productGroupDocuments()
    {
        return $this->hasMany('App\Models\Catalog\Product\ProductGroup\ProductGroupDocument','product_group_id','productgroup_id')->whereWebsiteId(config('wmo_website.website_id'));
    }

    public function productgroupWebsite()
    {
        return $this->hasOne('App\Models\Catalog\Product\ProductgroupWebsite', 'productgroup_id', 'productgroup_id');
    }

    public function productProductgroupWebsite()
    {
        return $this->hasOne('App\Models\Catalog\Product\ProductProductgroupWebsite', 'product_id', 'id');
    }
    public function productReturn()
    {
        return $this->hasMany('App\Models\Order\OrderReturn', 'product_code', 'code');
    }

    /**
     * WM-788 & WM-787
     * Kits lights will depend on color of lights in the products under it.
     * Yellow color light is a temporary state, so actually, yellow is either blue or red.
     * So, if it has 4 products under it:
     * P1 - Green
     * P2 - Red
     * P3 - Blue
     * P4 - Green So, out of blue and red, the one with longest ETA, that color light and ETA will be shown.
     * If they have same ETA, then red light will be shown.
     * Based on selection, when products changes, light will change accordingly.
     *
     * Kit stock will show if all the products are in stock.
     * Kit stock will show Qty of kit that can be accomodated from current stock of products.
     * So, if P1 has 50 qty count 4, can contribute to 10 kitsP2 has 50 Qty, can contribute to 50 kitsP3 has 50 Qty can contribute to 50 kitsP4 has 25 Qty Count 2 can contribute to 12 kits
     * So, kit qty in stock will be 10.
     */
    public static function kitStockLabels($kitData)
    {
        $stockLabel = [
            "stock" => 0,
            "stockLabel" => "",
            "labelColor" => "",
            "eta" => null,
            "available" => true,
        ];
        $kitQty = null;
        if (!empty($kitData['individual_product'])) {
            foreach ($kitData['individual_product'] as &$product) {
                extract(static::kitIndProductLables($product, $kitQty, $stockLabel));
            }
        }
        unset($product);
        if (!empty($kitData['multi_product'])) {
            foreach ($kitData['multi_product'] as &$grp) {
                extract(static::multiProductLables($grp, $kitQty, $stockLabel));
            }
        }
        if ($stockLabel['labelColor'] == 'g') {
            $stockLabel['stockLabel'] = ($kitQty > 4) ? '4+' : $kitQty;
            $stockLabel['stockLabel'] .= ' ' . trans('product.in_stock');
        }
        $kitData['stock_labels'] = $stockLabel;
        return $kitData;
    }

    public static function kitIndProductLables(&$product, $kitQty, $stockLabel)
    {
        if (!empty($product['price'])) {
            // kit can be made by this products available stock( stock/count in kit)

            $canMakeKit = floor($product['price']['stock'] / $product['price']['prd_quantity']);
            // kit can be made by all products available stock(lowest well be selected)
            $kitQty = (is_null($kitQty) || $canMakeKit < $kitQty) ? $canMakeKit : $kitQty;
            $product['price']['stock'] = $canMakeKit;
            $masterCanMakeKit = floor($product['price']['master_stock'] / $product['price']['prd_quantity']);
            $product['price']['master_stock'] = $masterCanMakeKit;
            $stockData = $product['price'];
            $label = static::stockLabelFromStock($stockData);
            $product['stock_labels'] = $label;
            if (empty($stockLabel['stockLabel']) || ($label['eta'] > $stockLabel['eta']) || ($label['eta'] = $stockLabel['eta'] && $label['labelColor'] == 'r')) {
                $kitStockLabel = $stockLabel;
                $stockLabel = $label;
                if(!$kitStockLabel['available']){
                    $stockLabel['available'] = false;
                }
            }
        }
        return compact('kitQty', 'stockLabel');
    }

    public static function multiProductLables(&$grp, $kitQty, $stockLabel)
    {
        array_multisort(array_column($grp, "rrp"), SORT_ASC, $grp);
        foreach ($grp as $key => &$product) {
            if (!empty($product['price']) ) {
                $canMakeKit = floor($product['price']['stock'] / $product['price']['prd_quantity']);
                $product['price']['stock'] = $canMakeKit;
                $masterCanMakeKit = floor($product['price']['master_stock'] / $product['price']['prd_quantity']);
                $product['price']['master_stock'] = $masterCanMakeKit;
                $stockData = $product['price'];
                $label = static::stockLabelFromStock($stockData);
                if(!empty($label['available'])){
                    $kitQty = (is_null($kitQty) || $canMakeKit < $kitQty) ? $canMakeKit : $kitQty;
                }
                $product['stock_labels'] = $label;
                $ifPriorityColor = ($key == 0 && (($label['eta'] > $stockLabel['eta']) || ($label['eta'] = $stockLabel['eta'] && $label['labelColor'] == 'r')));
                if (!empty($label['available']) && empty($product['price']['unavailableProduct']) && (empty($stockLabel['stockLabel']) || $ifPriorityColor)) {
                    $stockLabel = $label;
                }
            }
        }
        return compact('kitQty', 'stockLabel');
    }

    static function stockLabels($product)
    {
        if (!is_array($product) && $product != null) {
            $product = $product->toArray();
        }
        $stockData = $product['product_stock'];

        return static::stockLabelFromStock($stockData);
    }

    public static function stockLabelFromStock($stockData)
    {
        $franchise = config('wmo_website.franchise');
        $isMaster = $franchise['is_master'] ?? false;
        $stock = $stockData['stock'] ?? 0;
        $eta = $stockData['eta'] ?? null;
        $stockLabel = '';
        $labelColor = '';
        $available = !empty($stockData);

        extract(static::stockLabelCommon($stockData));
        if (empty($isMaster) && ($stockData['shop_status'] == Product::ON_SHOP_ALWAYS || $stockData['shop_status'] == Product::ON_SHOP_IN_FRANCHISE)) {
            extract(static::stockLabelNonMasterFranchise($stockData));
        } elseif (!empty($isMaster) && $stockData['shop_status'] == Product::ON_SHOP_ALWAYS) {
            extract(static::stockLabelMasterFranchise($stockData));
        }
        return compact('stock', 'stockLabel', 'labelColor', 'eta', 'available');
    }

    public static function stockLabelCommon($stockData)
    {
        $stock = $stockData['stock'] ?? 0;
        $stockLabel = '';
        $labelColor = '';
        $masterStock = $stockData['master_stock'] ?? 0;
        $masterShopStatus = $stockData['master_shop_status'] ?? 0;
        $available = true;
        if ($stockData['shop_status'] == Product::ON_SHOP_IN_STOCK) {
            if ($stock > 0) {
                // Green, In stock, Qty
                $stockLabel = ($stock > 4) ? '4+' : $stock;
                $stockLabel .= ' ' . trans('product.in_stock');
                $labelColor = 'g';
            } else {
                // Red Add to cart disabled, if Out of stock, this case will come only when user google the product or come on this page from elsewhere.
                $stockLabel = trans('product.out_of_stock');
                $labelColor = 'r';
                $available = false;
            }
            // Yellow - Insufficient, ETA is shown, we still allow user to add to cart, just that whole order fulfillment will happen from ETA stock and not from existing stock.
        } elseif ($stockData['shop_status'] == Product::ON_SHOP_NEVER_STATUS) {
            $stockLabel = trans('product.out_of_stock');
            $labelColor = 'r';
            $available = false;
        } elseif ($stock <= 0 && $masterStock <= 0 && $masterShopStatus != Product::ON_SHOP_ALWAYS) {
            // if user able to find it it should say out of stock with red color
            $stockLabel = trans('product.out_of_stock_enquire_eta');
            $labelColor = 'r';
            $available = false;
        }
        return compact('stockLabel', 'labelColor', 'available');
    }

    public static function stockLabelNonMasterFranchise($stockData)
    {
        $stock = $stockData['stock'] ?? 0;
        $eta = $stockData['eta'] ?? null;
        $masterStock = $stockData['master_stock'] ?? 0;
        $masterShopStatus = $stockData['master_shop_status'] ?? 0;
        $stockLabel = '';
        $labelColor = '';

        if ($stock > 0) {
            // Green, X Qty in stock
            $stockLabel = ($stock > 4) ? '4+' : $stock;
            $stockLabel .= ' ' . trans('product.in_stock');
            $labelColor = 'g';
        } elseif ($stock <= 0 && $masterStock > 0) {
            // Blue, in stock with Master Franchise (European Warehouse) [If WMO is top supplier] ETA Master franchise to Franchise
            $stockLabel = trans('product.in_stock_european_warehouse');
            $stockLabel .= ($eta > 0) ? ' ETA ' . $eta . ' ' . trans('general_frontend.days') : ' ETA ' . trans('general_frontend.today');
            $labelColor = 'b';
        } elseif ($stock <= 0 && $masterStock <= 0 && $masterShopStatus == Product::ON_SHOP_ALWAYS) {
            // Red, if WMO is top supplier When not in stock of franchise as well as Master franchise, but master franchise's prime
            //     website has shop flag of on shop always, then ETA is from supplier to master franchise + from Master franchise to this franchise
            $stockLabel = trans('product.in_stock_european_warehouse');
            $stockLabel .= ($eta > 0) ? ' ETA ' . $eta . ' ' . trans('general_frontend.days') : ' ETA ' . trans('general_frontend.today');
            $labelColor = 'r';
            // Product doesnt appear, if if WMO is top supplier When not in stock of franchise as well as Master franchise, but master franchise's prime website has shop flag of on shop never

            // Red, if top supplier is local supplier, then ETA based on top local supplier
        } elseif ($stock <= 0 && $masterStock <= 0 && $masterShopStatus != Product::ON_SHOP_ALWAYS) {
            // if user able to find it it should say out of stock with red color
            $stockLabel = trans('product.out_of_stock_enquire_eta');
            $labelColor = 'r';
        }

        // Insufficient stock - Yellow, ETA is shown, we still allow user to add to cart, just that whole order fulfillment will happen from ETA stock and not from existing stock.
        return compact('stockLabel', 'labelColor');
    }

    public static function stockLabelMasterFranchise($stockData)
    {
        $stock = $stockData['stock'] ?? 0;
        $eta = $stockData['eta'] ?? null;
        $stockLabel = '';
        $labelColor = '';
        if ($stock > 0) {
            // Green, when in stock and Qty
            $stockLabel = ($stock > 4) ? '4+' : $stock;
            $stockLabel .= ' ' . trans('product.in_stock');
            $labelColor = 'g';
        } else {
            // Red, Out of stock ETA
            $stockLabel = ' ' . trans('product.out_of_stock');
            $stockLabel .= ($eta > 0) ? ' ETA ' . $eta . ' ' . trans('general_frontend.days') : ' ETA ' . trans('general_frontend.today');
            $labelColor = 'r';
        }

        // Insufficient stock - Yellow.
        return compact('stockLabel', 'labelColor');
    }

    static function customerDescription($product){
        if(!empty($product['customer_description'])){
            $customer_description = $product['customer_description'];
        }else{
            $customer_description = !empty($product['price'])?$product['price']['title']:'';
        }
        return $customer_description;
    }
    static function codeLabels($product,$admin_id){
        $product_code_label = trans('product.product_code') . ' :';
        $product_code = $product['code'];
        if(!empty($product['price'])){
            if($product['price']['label_display'] == 1){
                $product_code_label = trans('product.product_code') . ' :';
            }else{
                $product_code_label= trans('product.manufacturer_code') . ' :';
            }

            if(!empty($admin_id)){
                if($product['price']['code_localshop'] == 1){
                    $product_code = $product['price']['product_code'];
                }else{
                    $product_code = $product['price']['manufacture_code']??'';
                }
            }else{
                if($product['price']['code_frontend'] == 1){
                    $product_code= $product['price']['product_code'];
                }else{
                    $product_code= $product['price']['manufacture_code']??'';
                }
            }
        }
        if(empty($product_code) || $product['type']==Product::KIT_PRODUCT_TYPE){
            $product_code = $product['price']['product_code'];
            $product_code_label= trans('product.product_code') . ' :';
        }
        return ['product_code'=>$product_code,'product_code_label'=>$product_code_label];
    }

    public static function availability($stockData)
    {
        $available = true;
        $stock = $stockData['stock'] ?? 0;
        $shopStatus = $stockData['shop_status'] ?? 0;
        $masterStock = $stockData['master_stock'] ?? 0;
        $ifNever = ($shopStatus == Product::ON_SHOP_NEVER_STATUS);
        $ifOutOfStock = ($shopStatus == Product::ON_SHOP_IN_STOCK && $stock <= 0);
        $ifOutOfMasterStock = ($shopStatus == Product::ON_SHOP_IN_FRANCHISE && $stock <= 0 && $masterStock <= 0);
        if ( $ifNever || $ifOutOfStock || $ifOutOfMasterStock) {
            $available = false;
        }
        return $available;
    }

    static function singleBrandProducts($assocBrands,$data){
        $singleProducts = Product::leftJoin('brands as brands', function($query)
                        {
                            $query->on('products.brand_id',  '=', 'brands.id')
                            ->where('brands.status',Brand::STATUS_ACTIVE);
                        })
                        ->whereIn('products.id',$assocBrands->toArray())
                        ->where([
                            'brand_id'=>$data['brand_id']
                            ])
                        ->orderBy('wemoto_preferred')->pluck('products.id','products.code');
        if($singleProducts->isEmpty()){
            $singleProducts = static::singleProducts($assocBrands);
        }
        return $singleProducts;
    }

    static function singleProducts($assocBrands){
        return Product::leftJoin('brands as brands', function($query)
                {
                    $query->on('products.brand_id',  '=', 'brands.id')
                    ->where('brands.status',Brand::STATUS_ACTIVE);
                })
                ->whereIn('products.id',$assocBrands->toArray())
                ->orderBy('wemoto_preferred')->pluck('products.id','products.code');
    }

    /**
     * Product basic details
     */
    public function scopeInactiveBasicDetails($q)
    {
        return $q->with([
                        'price'=>function($q){
                            $q->select('id','product_id','brand','brand_logo','brand_wemoto_preferred','product_code','manufacture_code', 'collection_item','label_display','code_frontend','code_localshop','category','price','final_price','title','full_description','supplier_part_number')
                                    ->where('website_id',config('wmo_website.website_id'))
                                    ->selectTradePrice();
                            },
                        'images'=>function($q){
                            $q->where([
                                        'type'=>ProductImage::PRIMARY_IMAGE_TYPE,
                                        'is_document'=>ProductImage::DOCUMENT_NO
                                    ])
                                    ->orderBy('updated_at','desc')
                                    ->pluck('name','product_id');
                            },
                        'brands'=>function($query){
                            $query->select('brands.id','brands.name','logo','wemoto_preferred')
                                    ->languageJoin()
                                    ->where('status',Brand::STATUS_ACTIVE)
                                    ->orderBy('wemoto_preferred','desc');
                        },
                        'productReturn'=>function($query){
                            $query->select('id','returned_quantity','product_code',DB::raw('sum(returned_quantity) as return_count'))
                            ->groupBy('product_code');
                        },
                        'productDiscount'=>function($q){
                            $q->select('product_id', 'discount', 'website_id')->where(['website_id' => config('wmo_website.website_id')]);
                        },
                        'productGroupDocuments' => function ($rs) {
                            // for language translation
                            $rs->select('productgroup_documents.*')->languageJoin();
                        },
                        'productStock'=>function($q){
                            $q->select('product_id','stock','shop_status', 'master_stock', 'master_shop_status','eta','threshold','location')
                                    ->where('website_id',config('wmo_website.website_id'));
                            },
                    ]
                );
    }

    static function productModelAssoc($modelDetails,$data,$modelProducts){
        //for Association description, Brand
        $assocBrands = ProductModelLink::getproductId([
            'year'=>$modelDetails['year'],
            'assoc_id'=>$data['assoc_id'],
            'bikeModelId'=>$modelDetails['bikeModelId']
        ]);
        $diff= $assocBrands->diff($modelProducts);

        //same product array
        if($diff->isEmpty()){
            $singleProducts = Product::singleProducts($assocBrands);
        }else{
            $singleProducts = Product::singleBrandProducts($assocBrands,$data);
        }

       return $singleProducts;
    }


    public function productdescription()
    {
        return $this->belongsTo(ProductDescription::class,'description_id');
    }

    public function cartImages()
    {
        return $this->belongsTo(ProductImage::class, 'product_id');
    }

    public function productkit()
    {
        return $this->hasMany(ProductKit::class, 'product_id');
    }

    public function userWishlist()
    {
        return $this->hasOne(UserWishlist::class, 'product_id','code');
    }


}
