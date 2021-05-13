<?php

namespace App\Models\Catalog\Product;

use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Model;

class ProductFlatDetail extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = "product_flat_details";
    const COLLECTION_ITEM_YES = 1;
    public function scopeGetCollectionItem($query, $argv)
    {
        return $query->select('product_id', 'website_id', 'collection_item')
            ->whereIn('product_id', $argv['product_ids'])
            ->where('collection_item', static::COLLECTION_ITEM_YES)
            ->where('website_id', $argv['website_id']);
    }
    public function scopePrice($query, $argv)
    {
        return $query->select('product_id', 'website_id', 'price','final_price')->where(['product_id' => $argv['product_id'], 'website_id' => $argv['website_id']]);
    }
    public static function getFlatPrice($products, $website_id) {
        return static::select('product_flat_details.product_id', 'product_flat_details.product_code','product_flat_details.website_id', 'price','final_price','stock','shop_status','master_stock','master_shop_status', 'eta', 'collection_item')
                    ->selectTradePrice()
                    ->join('product_flat_stocks','product_flat_details.product_id','=','product_flat_stocks.product_id')
                    ->whereIn('product_flat_details.product_id',$products)
                    ->where(['product_flat_details.website_id' => $website_id,'product_flat_stocks.website_id' => $website_id])
                    ->whereNotNull('price')
                    ->where('price','!=',0)
                    ->orderBy('price','asc')->first();
    }
    public static function getAssocFlatPrice($products, $website_id) {
        $flatPrices = static::select('product_flat_details.product_id','product_flat_details.product_code','product_flat_details.website_id', 'price','final_price','stock','shop_status','master_stock','master_shop_status', 'eta', 'collection_item')
                    ->selectTradePrice()
                    ->join('product_flat_stocks','product_flat_details.product_id','=','product_flat_stocks.product_id')
                    ->whereIn('product_flat_details.product_id',$products)
                    ->where(['product_flat_details.website_id' => $website_id,'product_flat_stocks.website_id' => $website_id])
                    ->whereNotNull('price')
                    ->where('price','!=',0)
                    ->where('shop_status','!=',Product::ON_SHOP_NEVER_STATUS)
                    ->orderBy('price','asc')->get();
        $finalResult = collect();
        if(!empty($flatPrices)){
            foreach($flatPrices as $flatPrice){
                if(Product::emptyFlatTableExit($flatPrice) == 1){
                    continue;
                }else{
                    $finalResult =$flatPrice;
                    break;
                }


            }
        }
        return $finalResult;
    }

    public function scopeGetPrice($query, $argv) {
        return $query->select('product_id', 'website_id', 'price', 'final_price', 'weight')
            ->whereIn('product_id', $argv['product_ids'])
            ->where('website_id', $argv['website_id']);
    }

    public function scopeGetSupplier($query, $argv) {
        return $query->select('product_id', 'supplier_id', 'supplier_part_number', 'is_wemoto_uk')
            ->whereIn('product_id', $argv['product_ids'])
            ->where('website_id', $argv['website_id']);
    }

    public function scopeSelectTradePrice($query) {
        $query->when(Auth::check() && config('wmo_website.type') == config('constant.trade_type'), function ($q) {
            $category_case = '';
            if (!empty(config('constant.trade_category_price_fields')[session('trader.discount_category')])) {
                $category_field =  config('constant.trade_category_price_fields')[session('trader.discount_category')];
                $category_case = 'WHEN ' . $category_field . ' IS NOT NULL AND '. $category_field .' <> "" THEN ' . $category_field;
            }
            if (session('trader.discount_tier')) {
                $tier_field = '(price - ((price - cost_price) / 100) * ' . session('trader.discount_tier') . ')';
                $tier_final_price_case = 'WHEN ' . $tier_field . ' IS NOT NULL AND '. $tier_field .' <> "" THEN ' . $tier_field . ' ELSE final_price';

                $tier_price_case = $tier_final_price_case;
            } else {
                $tier_price_case = 'ELSE final_price';
                $tier_final_price_case = 'ELSE final_price';
            }
            $q->selectRaw('(CASE
                WHEN trade_price IS NOT NULL AND trade_price <> "" THEN trade_price 
                ' . $category_case . ' ' . $tier_price_case . '
            END) as price, 
            (CASE
                WHEN trade_price IS NOT NULL AND trade_price <> "" THEN trade_price 
                ' . $category_case . ' ' . $tier_final_price_case . '
            END) as final_price');
        });
    }

    public function scopeCheckProductExists($query, $argv)
    {
        $query->where([
            'website_id' => $argv['website_id']
        ])
        ->whereIn('product_id', $argv['product_ids']);
    }
}
