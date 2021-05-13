<?php
namespace App\Services\Product\Contracts;

interface ProductContracts {
    public function getProductByCode($productCode);
    public function setPrice();
    public function getCategory();
    public function getBrand();
    public function getProductImages($productImages);
    public function getAllProductByCode($bikeModelId,$modelyear,$productCode);
    public function relatedProducts($productId);
    public function getProductSpecifications($productIds,$attributesetId);
}