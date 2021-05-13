<?php
namespace App\Repositories\Product\Interfaces;

interface ProductRepositoryInterface
{
    public function findProductVariant($param);

    public function findActiveProductVariant($param);

    public function findProductImages($param);

    public function findKitProduct($param);

    public function kitGroupDetails($param);

    public function findAllProductByCode($param);
    
    public function findProductAllImages($code);

    public function relatedProductsList($productId);

    public function findProductIds($productCodes);
    
    public function getProductImages($productIds);
    
    public function featuredProducts($product_ids = false);

}