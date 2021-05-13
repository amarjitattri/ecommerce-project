<?php
namespace App\Services\Cart;

use App\Models\Catalog\DescriptionAssociation;
use App\Models\Catalog\Product\Product;
use App\Models\Catalog\Product\ProductFlatDiscount;
use App\Models\LocalShopValue;
use App\Models\MyAccount\UserWishlist;
use App\Services\Cart\Cache\Contracts\CacheContracts;
use App\Services\Cart\PromoCode;
use App\Services\Checkout\DeliveryMethod;
use App\Services\Product\Contracts\ProductContracts;
use Auth;
use Currency;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Tax;
use App\Http\Traits\CartTrait;

class Cart
{

    private $__cache;
    private $__cart;
    private $__productService;
    private $__moneyOff;
    private $__shippingCharges;

    public $items;
    public $__promocodeService;
    public $cartSubTotal;
    protected $__localShopValue;
    private $__codCharges;
    public $getSubTotalWithDiscount;

    use CartTrait;
    /**
     * CartRepository constructor.
     * @param RedisCache $cache
     */
    public function __construct(CacheContracts $cache, ProductContracts $product, PromoCode $promoCode, LocalShopValue $localShopValue)
    {
        $this->__cache = $cache;
        $this->__productService = $product;
        $this->__promocodeService = $promoCode;
        $this->__localShopValue = $localShopValue;
    }

    public function add($product, int $qty, $options = [])
    {
        $this->__cart = $this->get() ?? null;
        $status = false;
        $productCode = $product['product_id'];
        $productKey = $options['assoc_id'] . $productCode;
        $this->items = isset($this->__cart['products']) ? $this->__cart['products'] : [];
        $time = Carbon::now();
        if ($product['type'] == config('constant.kit_product_type') && isset($options['product_kit_products'])) {
            $productKey = $productKey . '-' . implode('-', array_column(json_decode($options['product_kit_products'], true), 'prdId'));
        }

        if ($this->items && array_key_exists($productKey, $this->items)) {
            $existingQty = $this->items[$productKey]['qty'] ?? 0;
            $qty = $existingQty + $qty;
            $this->items[$productKey]['qty'] = $qty;
            $this->items[$productKey]['updated_at'] = $time;
            $status = true;
        } else {
            $product = $this->__productService->getProductByCode($productCode);
            if ($product['type'] == config('constant.kit_product_type')) {
                //calculate product Kit price
                $options['product_kit_products'] = isset($options['product_kit_products']) ? $options['product_kit_products'] : $product['product_kit_single'];
                $productKitDetails = $this->productKitDetails($product, $options);
                $productKitPrice = $productKitDetails['totalPrice'];
                $productKitWeight = $productKitDetails['totalWeight'];
            }
            $productKitItems = isset($options['product_kit_products']) ? json_decode($options['product_kit_products'], true) : null;
            if (!empty($productKitItems)) {
                # if kit is collection item
                $productCollectionItemData = $this->checkCollectionItem($productKitItems);
                $product['collection_item'] = !empty($productCollectionItemData) ? 1 : 0;
            }
            $product['price'] = $productKitPrice ?? $product['final_price'];
            $product['weight'] = $productKitWeight ?? $product['weight'];
            $product['final_price'] = $product['price'];

            $slugProductCode = $product['variantcontainer'] ? $product['variantcontainer']->code : $productCode;

            $productUrl = $this->productUrl($options, $slugProductCode);

            $product['qty'] = $qty;
            $product['product_name'] = $options['product_name'];
            $product['vechile_name'] = $options['vechile_name'];
            $product['product_kit_items'] = $productKitItems;
            $product['vechicle_details'] = session()->get('user_vehicle_search');
            $product['assoc_id'] = $options['assoc_id'];
            $product['model_id'] = $options['model_id'];
            $product['product_url'] = $productUrl;
            $product['created_at'] = $time;
            $product['updated_at'] = $time;

            $this->items[$productKey] = $product;
            $status = true;
        }
        $specialCartCheck = isset($this->__cart['products']) && strpos(json_encode($this->__cart['products']), 'specialCart') > 0 ? 'specialCart' : '';
        if ($specialCartCheck) {
            $cart['specialCart'] = $specialCartCheck;
        }
        $cart['products'] = $this->items;
        $this->saveBasket($cart);
        return $status;
    }

    public function get()
    {
        return $this->__cache->getAllItems();
    }

    public function setSubTotal($cart)
    {
        $this->cartSubTotal = array_sum(array_map(function ($value) {
            return $value['item_total'];
        }, $cart['products']));
        $this->getSubTotalWithDiscount = round($this->cartSubTotal - $this->__moneyOff, 2);
        if ($this->getSubTotalWithDiscount < 0) {
            $this->getSubTotalWithDiscount = 0;
        }
    }

    public function cartTotal()
    {
        return $this->getSubTotalWithDiscount + $this->__shippingCharges + $this->__codCharges;
    }

    public function transformCart()
    {
        $this->__cart = $this->getCartWithVat() ?? null;
        $cart = [];
        $this->__moneyOff = 0;
        $this->__shippingCharges = 0;
        $this->__codCharges = 0;
        if ((isset($this->__cart['cod_charges']) && !empty($this->__cart['cod_charges']))) {
            $this->__codCharges = $this->__cart['cod_charges']['cod_total'];
            $cart['cod_charges'] = $this->__cart['cod_charges'];
        }

        $freeShipping = 0;
        if ($this->hasCart()) {
            $cartData = $this->__cart;
            $cartData = $this->checkIfWishlist($cartData);
            $productStockData = $this->checkStock($cartData['products']);

            //promocode calculation
            if (isset($cartData['promotion']['promocode'])) {
                $promoCode = $cartData['promotion']['promocode'];
                $promocodeData = $this->__promocodeService->getDiscount($promoCode, $cartData);

                if ($promocodeData['status']) {
                    if ($promocodeData['discount'] == 'FREE_SHIPPING') {
                        $promocodeSuccessStatus = trans('checkout.free_shipping_promo_applied');
                        $discountValue = 0;
                        $freeShipping = 1;
                    } else {
                        $promocodeSuccessStatus = trans('checkout.promocode_applied');
                        $this->__moneyOff = $promocodeData['discount']['total_discount'];
                        $discountValue = $promocodeData['discount']['total_discount'];
                        $freeShipping = 0;
                    }
                    $cartData['promotion']['message'] = $promocodeSuccessStatus;
                    $cartData['promotion']['discount_value'] = $discountValue;
                    $cartData['promotion']['free_shipping'] = $freeShipping;
                } else {
                    $this->setPromoCode(null);
                    $cartData['promotion'] = $promocodeData;
                }

            }

            $product_names = $this->getProductNames($cartData['products']);
            array_walk($cartData['products'], function (&$value) use ($productStockData, $product_names) {
                $value['stock'] = 0;
                if (empty($value['product_stock'])) {
                    $value['product_stock'] = 0;
                }
                $value['eta'] = isset($value['specialCart']) ? $value['eta'] : 0;
                $value['label_color'] = '';
                $value['stock_label'] = '';
                $value['threshold_flag'] = 0;
                $productId = $value['product_id'];

                //check stock for product kit product
                if ($value['type'] == config('constant.kit_product_type')) {
                    $kitData = $this->prepareKitData($value['product_kit_items'], $productStockData);

                    $kitData = Product::kitStockLabels($kitData);
                    $stockLabels = $kitData['stock_labels'];
                    $value['eta'] = $stockLabels['eta'];
                    $value['label_color'] = $stockLabels['labelColor'];
                    $value['stock_label'] = $stockLabels['stockLabel'];
                } else {

                    if ($productStockData && array_key_exists($productId, $productStockData)) {
                        // stock labels start
                        $value['product_stock'] = $productStockData[$productId];
                        $value['stock'] = $productStockData[$productId]['stock'];
                        $stockLabels = Product::stockLabels($value);
                        $value['eta'] = $stockLabels['eta'];
                        $value['label_color'] = $stockLabels['labelColor'];
                        $value['stock_label'] = $stockLabels['stockLabel'];
                        // stock labels end

                    }
                }

                $value['item_total'] = $value['price'] * $value['qty'];

                $value['product_name'] = !empty($product_names[$value['assoc_id']])
                ? $product_names[$value['assoc_id']] : $value['product_name'];

                return $value;
            });
            $this->setSubTotal($cartData);
            if ((!empty(session()->get('local_shop_user'))|| !empty(session()->get('trade_local_shop_user'))) && session()->get('localShopOrderType') == 'service' && $this->getSubTotalWithDiscount == 0 || session()->get('localshopfreeshipping')) {
                $promocodeSuccessStatus = trans('checkout.free_shipping_promo_applied');
                $discountValue = 0;
                $freeShipping = 1;

                $cartData['promotion']['message'] = $promocodeSuccessStatus;
                $cartData['promotion']['discount_value'] = $discountValue;
                $cartData['promotion']['free_shipping'] = $freeShipping;
                $cartData['promotion']['promocode'] = false;
                $cartData['promotion']['status'] = false;
            }

            $this->setSubTotal($cartData);
            //calculate shipping charges
            $shippingVat = 0;

            if (isset($cartData['delivery']) && empty($cartData['delivery']['collection']) && $freeShipping == 0) {
                $this->calculateShippingCharges($cartData);
                $vatPercentage = session('shipping_country_calculation')['country_data']['sr'];
                $shippingVat = round(app('CartHelper')->calculateVatInPrice($vatPercentage, $this->__shippingCharges), 2);
            }

            $cart['sub_total'] = round($this->cartSubTotal, 2);
            $cart['shipping_charges'] = $this->__shippingCharges;
            $cart['total'] = round($this->cartTotal(), 2);
            $cart['count'] = $this->count();
            $cart['vat_total'] = round($this->totalVat($cartData) + $shippingVat, 2);
            $cart['vat_applicable'] = $this->totalVat($cartData) > 0;
            return array_merge($cartData, $cart);
        }
    }

    public function calculateShippingCharges($cartData)
    {
        $params['ids'] = [];
        $internationals = [];
        if (is_array($cartData['delivery']['rule_type'])) {
            foreach ($cartData['delivery']['rule_type'] as $key => $ruletype) {
                if ($ruletype == 1) {
                    $params['ids'][] = $cartData['delivery']['eta_charge_id'][$key];
                }else{
                    $internationals[] = [
                        'eta_charge_id' => $cartData['delivery']['eta_charge_id'][$key], 'shipment_weight' => $cartData['delivery']['shipment_weight'][$key]
                    ];
                }
            }
        }else{
            if ($cartData['delivery']['rule_type'] == 1) {
                $params['ids'][] = $cartData['delivery']['eta_charge_id'];
            }else{
                $internationals[] = [
                    'eta_charge_id' => $cartData['delivery']['eta_charge_id'], 
                    'shipment_weight' => $cartData['delivery']['shipment_weight']
                ];
            }
        }
        $charges = DeliveryMethod::getEtaChargeByIds($params);
        $internationalCharges = DeliveryMethod::getInternationalCharges($internationals);
        $this->__shippingCharges = round(array_sum($charges->pluck('final_charge')->toArray()), 2);
        if (count($params['ids']) != $charges->count()) {
            $this->__shippingCharges = $this->__shippingCharges * 2;
        }
        $this->__shippingCharges = $this->__shippingCharges + $internationalCharges;
    }

    public function transformBasket($basket = null)
    {
        if ($basket == null) {
            $basket = $this->transformCart();
        }
        $newBasketList = [
            'collection_items' => [],
            'delivery_items' => [],
        ];

        if (!empty($basket['products'])) {
            foreach ($basket['products'] as $product) {
                if (!empty($product['collection_item']) && $product['collection_item'] == 1 && !isTradeSite()) {
                    $newBasketList['collection_items'][] = $product;
                } else {
                    $newBasketList['delivery_items'][] = $product;
                }
            }
        }
        return $newBasketList;
    }

    public function saveUserBakset($user)
    {
        $this->__cache->saveUserBakset($user);
    }

    public function update($productCode, $qty)
    {

        $this->__cart = $this->get() ?? null;
        $status = false;
        $cart = $this->__cart;
        $this->items = $cart['products'];
        if ($this->items && array_key_exists($productCode, $this->items)) {
            $this->items[$productCode]['qty'] = $qty;
            $status = true;
        }
        $cart['products'] = $this->items;
        $this->saveBasket($cart);
        return $status;
    }

    public function delete($productCode)
    {

        $this->__cart = $this->get() ?? null;
        $status = false;
        $cart = $this->__cart;
        $this->items = $cart['products'];
        if ($this->items && array_key_exists($productCode, $this->items)) {
            $status = true;
            unset($this->items[$productCode]);
        }
        $cart['products'] = $this->items;
        $this->__cache->saveBasket($cart);
        return $status;
    }

    public function hasCart()
    {
        return (isset($this->get()['products']) && count($this->get()['products']) > 0) ? $this->get() : null;
    }

    public function saveBasket($cart)
    {
        $this->__cache->saveBasket($cart);
    }

    public function setPromoCode($promoCode)
    {
        $this->__cart = $this->get() ?? null;
        $cart = $this->__cart;
        $promoCode = $promoCode ?? null;
        $cart['promotion']['promocode'] = $promoCode;
        $cart['promotion']['status'] = true;
        $this->saveBasket($cart);
        $this->__cart = $this->get() ?? null;
    }

    public function removePromoCode()
    {
        $this->__cart = $this->get() ?? null;
        $cart = $this->__cart;
        $cart['promotion'] = null;
        $this->saveBasket($cart);
        $this->__cart = $this->get() ?? null;
    }

    public function miniCart()
    {
        $cartData = $this->getCartWithVat() ?? null;
        $cart = $cartData['products'];
        $count = count($this->get()['products']);
        $total = 0;

        $cart = collect($cart)->sortByDesc(function ($temp) {
            return $temp['updated_at'];
        })->toArray();
        array_walk($cart, function (&$value) use (&$total) {
            $itemTotal = $value['price'] * $value['qty'];
            $total += $itemTotal;
            $value['item_total'] = round($itemTotal, 2);
            return $value;
        });

        $miniCart['total'] = round($total, 2);
        $miniCart['count'] = $count;
        $miniCart['products'] = array_slice($cart, 0, 2);
        return $miniCart;
    }

    public function setDelivery($delivery)
    {
        $this->__cart = $this->get() ?? null;
        $cart = $this->__cart;
        $cart['delivery'] = $delivery;
        $this->saveBasket($cart);
        $this->__cart = $this->get() ?? null;
    }

    public function userId($userId)
    {
        $this->__cache->getBasketKey($userId);
    }

    public function getCartWithVat()
    {
        $cartData = [];
        if ($this->hasCart()) {
            $cartData = $this->get() ?? null;
            $cartData = Tax::setVatProducts($cartData);
        }

        return $cartData;
    }

    public function localShopSpecialCart($datArr)
    {
        $this->__cart = $this->get() ?? null;
        $this->items = isset($this->__cart['products']) ? $this->__cart['products'] : [];
        $time = Carbon::now();

        $product = array();
        foreach ($datArr['specailArray'] as $orderData)
        {
            $product['product_id'] = '';
            $product["product_code"] = $orderData['partnum'];
            $product["productgroup_id"] = "";
            $product["type"] = config('constant.temp_product');
            $product["category_id"] = '';
            $product["brand_id"] = '';
            $product["primary_image_name"] = "";
            $product["primary_image_link"] = "";
            $product["price"] = $orderData['price'];
            $product["final_price"] = $orderData['price'];
            $product["brand_name"] = "";
            $product["variant_attributes"] = [];
            $product["product_kit_single"] = [];
            $product["attributeset_id"] = "";
            $product["full_description"] = $orderData['description'];
            $product["product_images"] = [];
            $product["product_docs"] = [];
            $product["home_delivery"] = '';
            $product['collection_item'] = 0;
            $product['price'] = $orderData['price'];
            $product['qty'] = $orderData['qty'];
            $product['product_name'] = $orderData['partnum'];
            $product['vechile_name'] = !empty($orderData['otherModel']) ? $orderData['otherModel'] : $orderData['bikeModelName'];
            $product['product_kit_items'] = null;
            $product['vechicle_details'] = [];
            $product['assoc_id'] = "";
            $product['model_id'] = $orderData['bikeModel'];
            $product['other_model'] = $orderData['otherModel'];
            $product['specialCart'] = 'specialCart';
            $product['created_at'] = $time;
            $product['updated_at'] = $time;
            $product['vat'] = $orderData['vat'];
            $product['eta'] = $orderData['days'];
            $product['product_url'] = "";
            $product['frontend_code'] = "";
            $product['frontend_code_label']= "";
            $product['weight']= $orderData['weight'];
            $this->items[$orderData['partnum']] = $product;
        }
        $ordlineProducts = $this->__localShopValue->specialCartOrder($this->items);
        $cart['products'] = $ordlineProducts;
        $cart['sub_total'] = $datArr['totalPrice'];
        $cart['shipping_charges'] = '';
        $cart['total'] = $datArr['totalPrice'];
        $cart['count'] = count($datArr['specailArray']);
        $cart['vat_total'] = $datArr['totalVat'];
        $status = true;
        $cart['specialCart'] = 'specialCart';

        $this->saveBasket($cart);
        return $status;
    }

    public function setCODCharges($codCharges)
    {
        $this->__cart = $this->get() ?? null;
        $cart = $this->__cart;
        $cart['cod_charges'] = $codCharges;
        $this->saveBasket($cart);
        $this->__cart = $this->get() ?? null;
    }

    public function updateProductPrice()
    {
        $this->__cart = $this->get() ?? null;
        if(isset($this->__cart['products']))
        {
            $productIds = array_unique(array_column($this->__cart['products'], 'product_id'));
            $productDetails = $this->__productService->checkPriceByIds($productIds);

            $productDetails = $productDetails->mapWithKeys(function($value) {
                return [$value['product_id'] => [
                    'website_id' => $value['website_id'],
                    'price' => $value['price'],
                    'final_price' => $value['final_price']
                ]];
            });

            $productDetails = $productDetails->toArray();
            $productDetailsIds = array_keys($productDetails);
            $cart = $this->__cart;

            //update price & delete deactivated product
            array_walk($cart['products'], function(&$value) use ($cart, $productDetails, $productDetailsIds){
                if(!isset($value['specialCart']))
                {
                    if ($value['type'] == config('constant.kit_product_type')) {
                        $options['product_kit_products'] = json_encode($value['product_kit_items']);
                        $productKitDetails = $this->productKitDetails($value, $options);
                        $productKitDetails['totalPrice'];
                    } else {
                        if (in_array($value['product_id'], $productDetailsIds)) {
                            $value['price'] = $productDetails[$value['product_id']]['price'];
                            $value['final_price'] = $productDetails[$value['product_id']]['final_price'];
                        } else {
                            unset($cart['products'][$value['product_code']]);
                        }
                    }

                }

            });

            $this->saveBasket($cart);
            $this->__cart = $this->get() ?? null;
        }
    }
}
