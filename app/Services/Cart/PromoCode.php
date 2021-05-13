<?php
namespace App\Services\Cart;

use App\Services\Cart\Cache\Contracts\CacheContracts;
use App\Repositories\Cart\Interfaces\PromoCodeRepositoryInterface;
use App\Models\Order\Order;
use DB;
use Session;
use Illuminate\Support\Facades\Auth;

class PromoCode
{
    const SHIPPING_NO = 2;
    const PERCENTAGEOFF = 1;
    const FLATOFF = 2;
    const HOMEDELIVERY = 1;
    const STORECOLLECTION = 2;
    const FREE_SHIPPING = 'FREE_SHIPPING';

    private $__promoRepo;
    private $__cart;
    private $__promoCodeData;
    private $__getIncludedProduct;

    public $errors;
    public $currencyReverseExchangeRate;

    public function __construct(PromoCodeRepositoryInterface $promoCodeRepo)
    {
        $this->__promoRepo = $promoCodeRepo;
    }

    /**
     * Validate and Apply promocodes to cart
     *
     * @param string $promoCode
     * @return mixed
     */
    public function getDiscount($promoCode, $cart = [])
    {
        $invalidPromocode = [
            'status' => false,
            'errors' => [
                'error_code' => 'INVALID_PROMOCODE',
            ]
        ];
        $this->__cart = $cart;
        $this->__promoCodeData = $this->getPromocodeById($promoCode);
        
        if ($this->__promoCodeData) {

            //check usage of promocode
            $email = null;
            if (Session::get('checkout')) {
                $email = Session::get('checkout')['user_info']['email'];
            } else if (isset(auth()->user()->email)){
                $email = auth()->user()->email;
            }
            if (!$this->checkPromocodeUsage($this->__promoCodeData, $email)) {
                return $invalidPromocode;
            }

            $totalDiscount = 0;
            $this->__getIncludedProduct = $this->getInludedProducts();
            if ($this->__getIncludedProduct && count($this->__getIncludedProduct) > 0) {
                $primeCurrency = config('wmo_website.prime_currency');
                $applicable = false;
                if ($primeCurrency['currency_exchange'] && count($primeCurrency['currency_exchange']) > 0 && isset($primeCurrency['reverse_currency_exchange']['exchange_rate'])) {
                    $currencyExchangeRate = $primeCurrency['currency_exchange']['exchange_rate'];
                    $currencyReverseExchangeRates = $primeCurrency['reverse_currency_exchange']['exchange_rate'];
                    $this->currencyReverseExchangeRate = $currencyReverseExchangeRates;
                    $applicable = true;
                }
                $totalAmt = !empty($this->__getIncludedProduct) ? app('CartHelper')->calculateAmount($this->__getIncludedProduct) : 0;

                //convert amount to prime currency
                $totalAmt = $applicable ? $currencyExchangeRate * $totalAmt : $totalAmt;
                if ($totalAmt >= $this->__promoCodeData->threshold) {
                    if ($this->__promoCodeData->free_shipping == static::SHIPPING_NO) {
                        $totalDiscount = $this->promocodeCalculation();
                        $discountData = [
                            'status' => true,
                            'discount' => [
                                'total_discount' => $totalDiscount
                            ],
                            'discount_type' => $this->__promoCodeData['discount_type']
                        ];
                    } else {
                        $discountData = [
                            'status' => true,
                            'discount' => 'FREE_SHIPPING'
                        ];
                    }
                } else {
                    $discountData = $invalidPromocode;
                }
            } else {
                $discountData = $invalidPromocode;
            }
        } else {
            $discountData = $invalidPromocode;
        }
        return $discountData;
    }

    public function promocodeCalculation()
    {
        $discountVal = 0;
        if ($this->__promoCodeData->discount_type == static::PERCENTAGEOFF) {
            $discountVal = $this->__calculatePercentageOff();
        } elseif ($this->__promoCodeData->discount_type == static::FLATOFF) {
            $discountVal = $this->__promoCodeData->discount_value;
            $discountVal= isset($this->currencyReverseExchangeRate) ? $this->currencyReverseExchangeRate * $discountVal : $discountVal;
            $discountVal = round($discountVal, 2);
        }

        return $discountVal;
    }

    public function getInludedProducts()
    {
        $validProducts = [];
        foreach ($this->__cart['products'] as $key => $value) {
            $categories = $this->__promoCodeData->categories ? $this->__promoCodeData->categories->pluck('id')->toArray() : [];
            $brands = $this->__promoCodeData->brand_include ? explode(',', $this->__promoCodeData->brand_include) : [];
            $includeProducts = $this->__promoCodeData->product_include ? array_map('trim', explode(',', $this->__promoCodeData->product_include)) : [];
            $excludeProducts = $this->__promoCodeData->product_exclude ? array_map('trim', explode(',', $this->__promoCodeData->product_exclude)) : [];

            $onShipping = $this->__checkOnShippingProduct($value);
            if (((!empty($excludeProducts) && !in_array($key, $excludeProducts)) && $onShipping) || empty($excludeProducts)) {
                if ($categories || $brands || $includeProducts) {
                    if (in_array($value['category_id'], $categories) || in_array($value['brand_id'], $brands) || in_array($key, $includeProducts)) {
                        $validProducts[$key] = $value;
                    }
                } else {
                    $validProducts[$key] = $value;
                }
            }
        }
        return $validProducts;
    }

    private function __checkOnShippingProduct($product)
    {
        $onShippingType  = true;
        $condition = (($this->__promoCodeData->on_shipping == static::HOMEDELIVERY && $product['home_delivery'] == 0) ||  ($this->__promoCodeData->on_shipping == static::STORECOLLECTION && $product['collection_item'] == 0));
        if (isset($this->__promoCodeData->on_shipping) &&  $condition) {
            $onShippingType = false;
        }
        return $onShippingType;
    }

    private function __calculatePercentageOff()
    {
        $percentageOff = 0;
        foreach ($this->__getIncludedProduct as $product) {
            $percentageOff += (($product['price'] / 100) * $this->__promoCodeData->discount_value) * $product['qty'];
        }

        return round($percentageOff, 2);
    }

    public function calculateLineDiscount($cart)
    {
        $lineDiscount = $cart['products'];
        $includedProducts = '';
        $discount = '';
        $unitFlatDiscount = '';

        if (isset($cart['promotion']) && $cart['promotion']['status'] && $cart['promotion']['promocode']) {
            $discount = $this->getDiscount($cart['promotion']['promocode'], $cart);
            if (isset($discount['errors']) && $discount['errors']['error_code'] == 'INVALID_PROMOCODE') {
                return false;
            }
            $includedProducts = $this->__getIncludedProduct;
            $cartTotal = array_sum(array_map(function ($value) {
                return $value['price'] * $value['qty'];
            }, $lineDiscount));

            if (isset($discount['discount_type']) && $discount['discount_type'] ==  static::FLATOFF) {
                $unitFlatDiscount = $discount['discount']['total_discount'] / $cartTotal;
            }
        }
        array_walk($lineDiscount, function (&$value, $key) use ($includedProducts, $discount, $cart, $unitFlatDiscount) {
            if (isset($cart['promotion']) && $cart['promotion']['status'] && $cart['promotion']['promocode'] && in_array($key, array_keys($includedProducts))) {
                if (isset($discount['discount_type'])) {
                    if ($discount['discount_type'] ==  static::PERCENTAGEOFF) {
                        $promoDiscount = (($value['price'] / 100) * $this->__promoCodeData->discount_value) * $value['qty'];
                    //$unitPrice = ($value['price'] * $value['qty']) - $promoDiscount
                    } elseif ($discount['discount_type'] ==  static::FLATOFF) {

                        //caluclate Flat off orderline
                        $promoDiscount = round(($value['price'] * $value['qty']) * $unitFlatDiscount, 2);
                    }
                }

                if ($discount['discount'] == static::FREE_SHIPPING) {
                    $promoDiscount = 0;
                }

                $value['promo_applicable'] = 1;
                $value['promo_discount'] = $promoDiscount;
            } else {
                $value['promo_applicable'] = 0;
                $value['promo_discount'] = 0;
            }
        });

        //check discount equal to applied discount
        if (isset($cart['promotion']) && $cart['promotion']['status'] && $cart['promotion']['promocode'] && $discount['discount'] != static::FREE_SHIPPING) {
            $checkTotalDiscount = array_sum(
                array_map(function ($value) {
                    return $value['promo_discount'];
                }, $lineDiscount)
            );
            if ($checkTotalDiscount != $discount['discount']['total_discount']) {
                end($lineDiscount);
                $key = key($lineDiscount);
                $diff = $discount['discount']['total_discount'] - $checkTotalDiscount;
                $lineDiscount[$key]['promo_discount'] = round(($lineDiscount[$key]['promo_discount'] + $diff), 2);
            }
        }
        return $lineDiscount;
    }

    public function calcualteFlatoffOrderline()
    {
        //
    }

    public function checkPromocodeUsage($promoCodeData, $email = null)
    {
        $return = $promoCodeData['max_user'] > $promoCodeData['total_usage'];

        //check user limit
        if (!empty($email)) {
            $userLimit = Order::select('promocode', DB::raw('count(*) as total'))
            ->where(['promocode' => $promoCodeData->promocode, 'email' => $email])
            ->groupBy('promocode')
            ->first();

            if ($userLimit) {
                $userLimit = $userLimit->toArray();
                $return  = $promoCodeData['usage'] > $userLimit['total'];
            }
            
        }
        return $return;
    }

    public function getPromocodeById($promoCode)
    {
        return $this->__promoRepo->findByPromoCode($promoCode);
    }
}
