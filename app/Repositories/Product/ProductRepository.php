<?php
namespace App\Repositories\Product;

use App\Models\Catalog\Product\Product;
use App\Models\Catalog\Product\ProductFlatDetail;
use App\Models\Catalog\Product\ProductFlatStock;
use App\Models\Catalog\Product\ProductFranchiseDetail;
use App\Models\Catalog\Product\ProductImage;
use App\Models\Catalog\Product\RelatedProduct;
use App\Models\CMS\Offer;
use App\Repositories\Product\Interfaces\ProductRepositoryInterface;

class ProductRepository implements ProductRepositoryInterface
{

    /**
     * find variant product
     */

    public function findProductVariant($param)
    {
        $q = Product::select('products.id', 'code', 'brand_id', 'products.type', 'customer_description', 'customer_notes', 'system_notes', 'parent_id', 'category_id', 'attributeset_id', 'description_id', 'productgroup_id', 'products.status')
            ->where('code', 'like', "%" . $param['code'] . "%")
            ->customerNotesDescByLanguage();
        if (!empty(session()->get('local_shop_user'))) {
            $q->whereIn('status', [Product::INACTIVE_STATUS, Product::ACTIVE_STATUS]);
        } else {
            $q->where('status', Product::ACTIVE_STATUS);
        }
        return $q->where('parent_id', '!=', Product::IS_PARENT)
            ->orderBy('products.created_at')
            ->basicDetails()
            ->productVariantAssoc()
            ->first();

    }

    /**
     * find active variant product
     */

    public function findActiveProductVariant($param)
    {
        $q = Product::select('products.id', 'code', 'brand_id', 'products.type', 'customer_description', 'customer_notes', 'system_notes', 'parent_id', 'category_id', 'attributeset_id', 'description_id', 'productgroup_id', 'products.status')
            ->customerNotesDescByLanguage()
            ->where([
                'products.id' => $param['product_id'],
            ]);
            if (!empty(session()->get('local_shop_user'))) {
                $q->whereIn('status', [Product::INACTIVE_STATUS, Product::ACTIVE_STATUS]);
            } else {
                $q->where('status', Product::ACTIVE_STATUS);
            }
           return $q->where('parent_id', '!=', Product::IS_PARENT)
            ->orderBy('products.created_at')
            ->basicDetails()
            ->productVariantAssoc()
            ->first();

    }

    /**
     * find active variant product
     */

    public function findProductImages($param)
    {
        $productIds = Product::where('code', 'like', $param['code'] . '%')
            ->where('status', Product::ACTIVE_STATUS)
            ->pluck('id');

        return ProductImage::whereIn('product_id', $productIds)
            ->where([
                'type' => ProductImage::PRIMARY_IMAGE_TYPE,
                'is_document' => ProductImage::DOCUMENT_NO,
            ])
            ->pluck('name', 'product_id');

    }
    /*
     * find active variant product
     */

    public function findProductAllImages($code)
    {
        $productIds = Product::where('code', 'like', $code . '%')
            ->where('status', Product::ACTIVE_STATUS)
            ->pluck('id');

        return $this->getProductImages($productIds);

    }

    /**
     * find variant product
     */

    public function findKitProduct($param)
    {
        $q = Product::select('products.id', 'code', 'brand_id', 'products.type', 'customer_description', 'customer_notes', 'system_notes', 'parent_id', 'category_id', 'attributeset_id', 'description_id', 'productgroup_id', 'products.status')
            ->where('code', 'like', "%" . $param['code'] . "%")
            ->customerNotesDescByLanguage();
        if (!empty(session()->get('local_shop_user'))) {
            $q->whereIn('products.status', [Product::INACTIVE_STATUS, Product::ACTIVE_STATUS]);
        } else {
            $q->where('products.status', Product::ACTIVE_STATUS);
        }
        return $q->basicDetails()
            ->productKitMultiAssoc()
            ->first();

    }

    public function findProductByCode($productCode)
    {
        $argv = ['product_code' => $productCode];
        return Product::findProductByCode($argv)->first();
    }

    public function checkStockByIds($productIds)
    {
        $params = [
            'product_ids' => $productIds,
            'website_id' => config('wmo_website.website_id'),
        ];
        return ProductFlatStock::getStock($params)->get();
    }

    public function getFranchiseDetailsByIds($productIds)
    {
        $params = [
            'product_ids' => $productIds,
            'franchise_id' => config('wmo_website.website_franchise'),
        ];
        return ProductFranchiseDetail::getFranchiseDetails($params)->get();
    }

    public function getSupplierDetailsByIds($productIds)
    {
        $params = [
            'product_ids' => $productIds,
            'website_id' => config('wmo_website.website_id'),
        ];
        return ProductFlatDetail::getSupplier($params)->get();
    }

    public function checkEtaByIds($productIds)
    {
        $params = [
            'product_ids' => $productIds,
            'website_id' => config('wmo_website.website_id'),
        ];
        return ProductFlatStock::getEta($params)->get();
    }

    public function checkCollectionItemByIds($productIds)
    {
        $params = [
            'product_ids' => $productIds,
            'website_id' => config('wmo_website.website_id'),
        ];
        return ProductFlatDetail::getCollectionItem($params)->get();
    }

    public function kitGroupDetails($productCode)
    {
        $params = [
            'product_code' => $productCode,
            'website_id' => config('wmo_website.website_id'),
        ];
        return Product::kitChildGroupDetails($params)->get();
    }


    /**
     * find single product by code
     */

    public function findAllProductByCode($code)
    {
        return Product::findAllProductByCode($code);
    }

    /**
     * find related product by list
     */
    public function relatedProductsList($productIds)
    {
       return app('CommonHelper')->relatedProductsList($productIds);
    }

    /**
     * find active variant product
     */

    public function findProductIds($productCodes)
    {
        return Product::whereIn('code', $productCodes)->pluck('id');
    }
    /**
     * find active variant product
     */

    public function getProductImages($productIds)
    {
        return ProductImage::select('product_images.name', 'product_images.type', 'product_id', 'is_document',
            'mime_type', 'image_link', 'website_id')
            ->whereIn('product_id', $productIds)
            ->when(session('language.languagecode') != 'en', function ($q) {
                // for language translation
                $q->selectRaw('IF(ld.name IS NOT NULL, ld.name, product_images.name) as name')
                ->leftJoin('locale_documents as ld', function ($qr) {
                    $qr->on('ld.type_id', '=', 'product_images.id')
                        // 1 is for doc images
                        ->where('ld.type', 1)
                        ->where('ld.language_id', session('language.id'));
                });
            })
            ->get();
    }

    public function checkPriceByIds($productIds)
    {
        $params = [
            'product_ids' => $productIds,
            'website_id' => config('wmo_website.website_id'),
        ];
        return ProductFlatDetail::getPrice($params)->get();
    }

    public function featuredProducts($product_ids = false, $type='')
    {
        if($type)
        {
            $productIds = Offer::getWebsiteOffers($type);
        }else
        {
            $productIds = $product_ids ?: Offer::getWebsiteOffers($type);
        }
        

        $products = [];
        if ($productIds->count() > 0) {
            $placeholders = implode(',', array_fill(0, count($productIds), '?'));
            $products = Product::select('products.id', 'code', 'products.type', 'category_id', 'brand_id', 'customer_description', 'customer_notes', 'system_notes', 'products.status','productgroup_id')
                ->basicDetails()
                ->productKitMultiAssoc()
                ->with([
                    'variantChild' => function ($q) {$q->select('id', 'parent_id')->where('status', Product::ACTIVE_STATUS);},
                ])
                ->whereIn('products.id', $productIds)
                ->where([
                    'products.status' => Product::ACTIVE_STATUS,
                ])
                ->where('products.type', '!=', Product::VARIANT_PRODUCT_TYPE)
                ->customerNotesDescByLanguage()
                ->orderByRaw("field(products.id,{$placeholders})", $productIds)
                ->distinct('products.id')
                ->get();
        }
        return $products;
    }

}
