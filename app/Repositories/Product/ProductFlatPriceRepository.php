<?php
namespace App\Repositories\Product;

use App\Repositories\Product\Interfaces\ProductPriceRepositoryInterface;

use App\Models\Catalog\Product\{
    Product,
    ProductFlatDetail
};

class ProductFlatPriceRepository implements ProductPriceRepositoryInterface
{
    private $__model;

    public function __construct(ProductFlatDetail $productPrice)
    {
        $this->__model = $productPrice;
    }

    public function getPrice($params)
    {
        $argv = array(
            'product_id' => $params['product_id'],
            'website_id' => $params['website_id']
        );
        
        return $this->__model->price($argv)->selectTradePrice()->first();
    }
}