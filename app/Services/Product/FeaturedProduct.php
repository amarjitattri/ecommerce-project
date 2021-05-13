<?php
namespace App\Services\Product;

use App\Repositories\Category\Interfaces\ShopAllCategoryRepositoryInterface;
use App\Repositories\Product\Interfaces\ProductRepositoryInterface;
use App\Services\Watermark\WatermarkService;

class FeaturedProduct
{

    private $__productRepo;
    private $__categoryRepository;

    const OFFER_LIMIT = 4;
    const NO_LIMIT = false;

    public function __construct(ShopAllCategoryRepositoryInterface $categoryRepository, ProductRepositoryInterface $productRepo)
    {
        $this->__categoryRepository = $categoryRepository;
        $this->__productRepo = $productRepo;
    }

    public function getfeaturedProducts($product_ids = false, $type='')
    {
        $products = $this->__productRepo->featuredProducts($product_ids,$type);
        $finalData = [];
        if (!empty($products)) {
            $finalData = $this->__categoryRepository->getFeaturedProductDetails($products, $product_ids ? static::NO_LIMIT : static::OFFER_LIMIT);
        }

        if (!empty($finalData)) {
            foreach ($finalData as $val) {
                if (!empty($val['product_image'])) {
                    WatermarkService::processImage($val['product_image']);
                }
            }
        }

        return $finalData;
    }

}
