<?php

namespace App\Http\Traits;

use Auth;
use Currency;
use App\Models\MyAccount\UserWishlist;
use App\Models\Catalog\DescriptionAssociation;
use App\Models\Catalog\Product\ProductFlatDiscount;

trait CartTrait {

    public function getProductNames($products)
    {
        $assoc_ids = array_values(array_map(function ($row) {
            return $row['assoc_id'];
        }, $products));

        // for language translation
        return DescriptionAssociation::select('association_descriptions.id', 'title')
            ->languageJoin()->whereIn('association_descriptions.id', $assoc_ids)
            ->pluck('title', 'association_descriptions.id')->toArray();
    }

    public function checkIfWishlist($cartData)
    {

        $productCodes = array();
        foreach ($cartData['products'] as $key => $productData) {
            $productCodes[$key] = $productData['product_code'];
            $cartData['products'][$key]['iswishlist'] = '';
        }
        $websiteId = config('wmo_website.website_id');
        $userId = Auth::check() ? Auth::user()->id : null;
        $userWishList = UserWishlist::select('id', 'product_id')->where(['website_id' => $websiteId, 'user_id' => $userId])->whereIn('product_id', $productCodes)->get()->toArray();
        if (count($userWishList) > 0) {
            foreach ($userWishList as $wishQry) {
                if (in_array($wishQry['product_id'], $productCodes)) {
                    $cartKey = array_search($wishQry['product_id'], $productCodes);
                    $cartData['products'][$cartKey]['iswishlist'] = $wishQry['id'];
                }
            }
        }

        return $cartData;
    }

    /**
     * prepare transform kit childs item array as needed by Product::kitStockLabels function
     *
     */
    public function prepareKitData($kitItems, $productStockData)
    {
        $kitData['individual_product'] = [];
        foreach ($kitItems as $item) {
            $preparedItem = $item;
            $preparedItem['prd_quantity'] = $item['quantity'];
            $preparedItem['prdcode'] = $item['prdCode'];
            $kitData['individual_product'][]['price'] = array_merge($preparedItem, $productStockData[$item['prdId']]);
        }
        return $kitData;
    }


    public function checkStock($products)
    {
        foreach ($products as $value) {
            if ($value['type'] == config('constant.kit_product_type')) {
                $productKitsId = array_column($value['product_kit_items'], 'prdId');
                $productIds = isset($productIds) ? array_merge($productIds, $productKitsId) : $productKitsId;
            } else {
                $productIds[] = $value['product_id'];
            }
        }
        return $this->__productService->checkStockByIds($productIds)->keyBy('product_id')->toArray();
    }

    public function checkEta($products)
    {
        $kit = [];
        foreach ($products as $value) {
            if ($value['type'] == config('constant.kit_product_type')) {
                $productKitsId = array_column($value['product_kit_items'], 'prdId');
                $kit[$value['product_id']] = $productKitsId;
                $productIds = isset($productIds) ? array_merge($productIds, $productKitsId) : $productKitsId;
            } else {
                $productIds[] = $value['product_id'];
            }
        }
        $etas = $this->__productService->checkEtaByIds($productIds)->pluck('eta', 'product_id')->toArray();
        foreach ($kit as $kitId => $productKitsId) {
            foreach ($productKitsId as $prdId) {
                if (!empty($etas[$kitId]) && $etas[$kitId] < $etas[$prdId]) {
                    $etas[$kitId] = $etas[$prdId];
                } else {
                    $etas[$kitId] = null;
                }
            }
        }
        return $etas;
    }

    public function checkCollectionItem($products)
    {
        $productIds = [];
        foreach ($products as $value) {
            $productIds[] = $value['prdId'];
        }
        return $this->__productService->checkCollectionItemByIds($productIds)->pluck('collection_item', 'product_id')->toArray();
    }

    public function checkStockForProductKit($value, $productStockData)
    {
        $uniquePrdIds = [];
        foreach ($value['product_kit_items'] as $product) {
            $prd = $product['prdId'];
            if (array_key_exists($prd, $uniquePrdIds)) {
                $uniquePrdIds[$prd] = $uniquePrdIds[$prd] + ($product['quantity'] * $value['qty']);
            } else {
                $uniquePrdIds[$prd] = $product['quantity'] * $value['qty'];
            }
        }

        //check stock
        foreach ($uniquePrdIds as $key => $pval) {
            if ($productStockData[$key]['stock'] >= $pval) {
                $stock = 1;
            } else {
                return 0;
            }
        }
        return $stock;
    }

    public function cartCurrencyConversion($basket, $currency)
    {
        array_walk($basket['products'], function (&$value) use ($currency) {
            $value['price'] = Currency::convertCurrency($value['price'], $currency);
            $value['final_price'] = Currency::convertCurrency($value['final_price'], $currency);
            $value['item_total'] = Currency::convertCurrency($value['item_total'], $currency);
        });

        //need to convert promocode
        if (isset($basket['promotion']) && $basket['promotion']['status'] && $basket['promotion']['promocode']) {
            $basket['promotion']['discount_value'] = Currency::convertCurrency($basket['promotion']['discount_value'], $currency);
        }
        $basket['sub_total'] = isset($basket['sub_total']) ? Currency::convertCurrency($basket['sub_total'], $currency) : null;
        $basket['shipping_charges'] = isset($basket['shipping_charges']) ? Currency::convertCurrency($basket['shipping_charges'], $currency) : null;
        $basket['total'] = Currency::convertCurrency($basket['total'], $currency);
        if (isset($basket['vat_total'])) {
            $basket['vat_total'] = Currency::convertCurrency($basket['vat_total'], $currency);
        }

        return $basket;
    }

    public function totalVat($cart)
    {
        return array_sum(array_map(function ($value) {
            if (isset($value['vat'])) {
                return $value['vat'] * $value['qty'];
            }

        }, $cart['products']));
    }

    
    public function productUrl($options, $productCode)
    {
        $productCode = strtolower($productCode);
        if (empty($options['model_slug']) && empty($options['assoc_slug'])) {
            if (empty($options['assoc_id'])) {
                return productDetailUrl($productCode);
            }
            $assoc_desc = DescriptionAssociation::select('alias')->find($options['assoc_id']);
            $options['model_slug'] = $assoc_desc->alias . '-' . $options['model_id'];
        }

        if (!empty($options['model_slug'])) {
            return productDetailUrl($productCode, false, $options['model_slug']);
        }
        return productDetailUrl($productCode, $options['assoc_slug']);
    }

    public static function checkCartSessionDiff($checkout, $cart)
    {
        $diff = false;
        $cartData = [];
        $sessionData = [];
        // cart array to 1D array
        if (!empty($cart['products'])) {
            foreach ($cart['products'] as $value) {
                $cartData[$value['product_code']] = $value['qty'];
            }
        }
        // collection array to 1D array
        if (!empty($checkout['collection_shipment']['products'])) {
            foreach ($checkout['collection_shipment']['products'] as $value) {
                $sessionData[$value['product_code']] = $value['qty'];
            }
        }
        // multi shipment array to 1D array
        if (!empty($checkout['delivery_method']['shipments']) && is_array($checkout['delivery_method']['shipments'])) {
            foreach ($checkout['delivery_method']['shipments'] as $shipment) {
                foreach ($shipment['products'] as $value) {
                    $sessionData[$value['product_code']] = $value['qty'];
                }
            }
        }
        // single shipment array to 1D array
        if (!empty($checkout['delivery_method']['products'])) {
            foreach ($checkout['delivery_method']['products'] as $value) {
                $sessionData[$value['product_code']] = $value['qty'];
            }
        }
        // now check array difference
        $neqProductCount = count($cartData) != count($sessionData);
        if ($neqProductCount || !empty(array_diff_assoc($cartData, $sessionData))) {
            // product data mistmatch found
            $diff = true;
        }
        return $diff;
    }

    public function productKitDetails($product, $products)
    {
        $productIds = array_unique(array_column(json_decode($products['product_kit_products'], true), 'prdId'));
        $productDetails = $this->__productService->checkPriceByIds($productIds);
        $productPrices = $productDetails->pluck('final_price', 'product_id')->toArray();
        $productData = json_decode($products['product_kit_products'], true);
        $totalPrices = array_sum(array_map(function ($value) use ($productPrices) {
            return $productPrices[$value['prdId']] * $value['quantity'];
        }, $productData));

        //calculateproductKitPrice discount
        $params = [
            'product_id' => $product['product_id'],
            'website_id' => config('wmo_website.website_id'),
        ];

        $discount = ProductFlatDiscount::getDiscount($params)->first();

        if ($discount) {
            $totalPrices = $totalPrices - ($totalPrices * ($discount->discount / 100));
        }

        //  weight of kit
        $productWeights = $productDetails->pluck('weight', 'product_id')->toArray();
        $totalWeight = array_sum(array_map(function ($value) use ($productWeights) {
            return $productWeights[$value['prdId']] * $value['quantity'];
        }, $productData));

        return [
            'totalPrice' => round($totalPrices, 2),
            'totalWeight' => round($totalWeight, 2),
        ];
    }

    public function clearBasket()
    {
        $this->__cache->clearBasket();
    }

    public function calculateLineDiscount()
    {
        $this->__cart = $this->getCartWithVat() ?? null;
        if (!$this->__cart) {
            return false;
        }
        return $this->__promocodeService->calculateLineDiscount($this->__cart);
    }

    public function count()
    {
        $this->__cart = $this->get() ?? null;
        return count($this->__cart['products']);
    }
    
}