<?php

namespace App\Models\Order;

use App\Models\CMS\Currency as CurrencyModel;
use App\Models\Order\OrderAddress;
use App\Models\Order\OrderPaymentDetail;
use App\Models\Order\OrderShipment;
use App\Models\User;
use Auth;
use Carbon\Carbon;
use Currency;
use Illuminate\Database\Eloquent\Model;
use App\Models\Order\WebsiteOrderId;

class Order extends Model
{
    const INCOMPLETE = 'IC';
    const B_TO_C = 'BC';
    const B_TO_B = 'BB';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'platformorderid';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'website_id', 'order_id', 'prefix', 'user_id', 'language_id', 'currency_id', 'promocode', 'first_name', 'last_name', 'email', 'phone', 'total', 'promocode_discount', 'carriage_code', 'carriage_price', 'auth_fraud', 'order_status', 'token','is_special_order', 'ip_address'
    ];

    /**
     * Get the order address.
     */
    public function addresses()
    {
        return $this->hasMany(OrderAddress::class, 'platformorderid', 'platformorderid');
    }

    public function saveOrder($params)
    {
        $userInfo = session()->get('checkout');
        $currency = session('currency');
        $cart = $params['cart'];
        $userId = Auth::check() ? Auth::user()->id : null;
        $websiteId = config('wmo_website.website_id');

        //check promocode
        $promocode = '';
        $promocodeDiscount = '';
        if (isset($cart['promotion']) && $cart['promotion']['status'] && $cart['promotion']['promocode']) {
            $promocode = $cart['promotion']['promocode'];
            $promocodeDiscount = $cart['promotion']['discount_value'];
        }

        //insert website order id
        $websiteOrderData = ['website_id' => $websiteId, 'token' => $cart['token']];
        $websiteOrderId = WebsiteOrderId::updateOrCreate(['website_id' => $websiteId, 'token' => $cart['token']], $websiteOrderData);

        //user Info
        if (!empty($params['collection_checkout'])) {
            $orderFirstName = $userInfo['user_info']['first_name'];
            $orderLastName = $userInfo['user_info']['last_name'];
            $orderPhone = $userInfo['user_info']['phone'];
        } else {
            $orderAdressObj = new Orderaddress();
            $shippingAddress = $userInfo['user_addresses']['shipping'] ?? [];
            $orderFirstName = $shippingAddress['first_name'] ? $orderAdressObj->sanitizeFirstname($shippingAddress['first_name']) : '';
            $orderLastName = $shippingAddress['last_name'] ? $orderAdressObj->sanitizeLastname($shippingAddress['last_name']) : '';
            $orderPhone = $shippingAddress['phone'] ?? '';
        }
        $orderEmail = $userInfo['user_info']['email'];

        $order = [
            'website_id' => $websiteId,
            'order_id' => sprintf('%09d', $websiteOrderId->id),
            'prefix' => isTradeSite() ? static::B_TO_B : static::B_TO_C,
            'user_id' => $userId,
            'language_id' => session('language')['id'],
            'currency_id' => session('currency')['id'],
            'promocode' => $promocode,
            'first_name' => $orderFirstName,
            'last_name' => $orderLastName,
            'email' => $orderEmail,
            'phone' => $orderPhone,
            'total' => Currency::convertCurrencyRaw($cart['total'], $currency),
            'promocode_discount' => $promocodeDiscount ?? Currency::convertCurrencyRaw($promocodeDiscount, $currency),
            'auth_fraud' => static::isFraudOrder($params, $userInfo),
            'order_status' => static::INCOMPLETE,
            'token' => $cart['token'],
            'ip_address' => request()->ip(),
        ];
        
        $orderData = Order::updateOrCreate(['website_id' => $websiteId, 'token' => $cart['token']], $order);

        return [
            'platformorderid' => $orderData->platformorderid,
            'order_id' => $orderData->order_id,
            'prefix' => $orderData->prefix,
        ];
    }

    public function shipments()
    {
        return $this->hasMany(OrderShipment::class, 'platformorderid');
    }

    public function currency()
    {
        return $this->belongsTo(CurrencyModel::class, 'currency_id');
    }
    public function scopeGetOrders($query, $argv)
    {
        return $query->with([
            'shipments' => function ($squery) {
                return $squery->with([
                    'orderlines',
                    'orderlines.product',
                    'orderlines.model' => function ($q) {
                        $q->select('model.*')->languageJoin();
                    },
                    'orderlines.assocation',
                    'orderlines.product.productdescription',
                    'orderlines.product.images',
                    'orderlines.exchangeproduct',
                    'orderlines.exchangeproduct.refund',
                    'orderlines.ordershipmenthistory',
                    'orderlines.returnproduct',
                    'orderlines.returnproduct.refund',
                    'orderlines.refund',
                    'shipmenthistories',
                    'shipmenthistories.shipment',
                    'orderstatus',
                    'orderinvoice'
                ]);
            },
            'currency',
            'orderaddress.deliverylocation.local',
            'paymentmethod',
            'refunds.orderline.product',
            'refunds.orderline.product.productdescription',
            'refunds.orderline.model',
            'refunds.orderline.assocation',
            'refunds.return.orderline.product',
            'refunds.return.orderline.product.productdescription',
            'refunds.return.orderline.model',
            'refunds.return.orderline.assocation',
            'refunds.exchange',
            'refunds.exchange.exchangeproducts',
            'refunds.exchange.exchangeproducts.orderline.product',
            'refunds.exchange.exchangeproducts.orderline.product.productdescription',
            'refunds.exchange.exchangeproducts.orderline.model',
            'refunds.exchange.exchangeproducts.orderline.assocation',
            
        ]);
    }

    public static function isFraudOrder($params, $userInfo)
    {
        $fraud_params = config('wmo_website.fraud_params');
        if (!$fraud_params) {
            return false;
        }

        $is_fraud = false;
        $user_fields = ['first_name', 'last_name', 'country_id', 'email'];
        $detail_diffs = [
            'postcode' => [],
            'last_name' => [],
        ];
        $address = $userInfo['user_addresses'] ?? [];
        $user = [
            'first_name' => [],
            'last_name' => [],
            'email' => [],
            'phone_number' => [],
            'country_id' => [],
        ];

        if (Auth::check()) {
            $user['email'][] = strtolower(Auth::user()->email);
            $order_where = ['user_id' => Auth::user()->id];
        } else {
            $user['email'][] = strtolower($userInfo['user_info']['email']);
            $order_where = ['email' => $userInfo['user_info']['email']];
        }
        $fraud_params['order_where'] = $order_where;

        // collect user's first name, last name, phone, country, email to verify for fraud checks
        foreach (['shipping', 'billing'] as $val) {
            if (isset($address[$val])) {
                foreach ($user_fields as $field) {
                    if ($address[$val][$field]) {
                        $user[$field][] = strtolower($address[$val][$field]);
                    }
                }
                if ($address[$val]['phone']) {
                    $user['phone_number'][] = strtolower($address[$val]['phone']);
                }
                $detail_diffs['postcode'][] = strtolower($address[$val]['postcode']);
                $detail_diffs['last_name'][] = strtolower($address[$val]['last_name']);
            }
        }

        // if user's last name and post code does not match in billing and shipping. consider it auth fraud
        if (empty($address['shipping']['same_for_billing']) || $address['shipping']['same_for_billing'] != 1) {
            $postcode_count = count(array_unique($detail_diffs['postcode']));
            $last_name_count = count(array_unique($detail_diffs['last_name']));
            if ($last_name_count > 1 && $postcode_count > 1) {
                $is_fraud = true;
            }
        }

        if (!$is_fraud) {
            $is_fraud = static::__checkAuthFraud($fraud_params, $params, $user);
        }

        return (int) $is_fraud;
    }

    private static function __checkAuthFraud($fraud_params, $params, $user)
    {
        $is_fraud = false;

        // verify user's first name, last name, phone, country, email for fraud check
        foreach ($user as $key => &$val) {
            $val = array_unique($val);
            if ($fraud_params[$key] && array_intersect($val, explode(',', $fraud_params[$key]))) {
                $is_fraud = true;
                break;
            }
        }

        $products = [];
        foreach ($params['cart']['products'] as $row) {
            if ($row['type'] == config('constant.kit_product_type')) {
                foreach ($row['product_kit_items'] as $kit_product) {
                    $products[] = [
                        'qty' => $kit_product['quantity'] * $row['qty'],
                        'prdcode' => $kit_product['prdCode'],
                    ];
                }
            }
            $products[] = [
                'qty' => $row['type'] == config('constant.kit_product_type') ? 0 : $row['qty'],
                'prdcode' => $row['product_code'],
            ];
        }

        // verify user's number of items for fraud check
        if (!$is_fraud && $fraud_params['item_threshold']
            && array_sum(array_column($products, 'qty')) >= $fraud_params['item_threshold']) {
            $is_fraud = true;
        }

        // verify user's cart products for fraud check
        if (!$is_fraud && $fraud_params['product']) {
            $is_fraud = (bool) array_intersect(
                array_map('strtolower', array_column($products, 'prdcode')),
                explode(',', $fraud_params['product'])
            );
        }

        // verify user's cart total for fraud check
        if (!$is_fraud && $fraud_params['order_threshold']) {
            $orders_count = static::where('created_at', '>=', Carbon::now()->subDay())
                ->where($fraud_params['order_where'])->count();
            if ($orders_count >= $fraud_params['order_threshold']) {
                $is_fraud = true;
            }
        }

        if (!$is_fraud) {
            $is_fraud = static::__checkAuthCountries($fraud_params, $params, $user);
        }

        return $is_fraud;
    }

    private static function __checkAuthCountries($fraud_params, $params, $user)
    {
        $is_fraud = false;
        $countries_checked = false;
        $currency = session('currency');

        // verify user's countries threshold for fraud check
        if ($fraud_params['countries']) {
            foreach ($fraud_params['countries'] as $row) {
                if (in_array($row['country_id'], $user['country_id'])) {
                    if ($row['threshold'] < Currency::convertCurrencyRaw($params['cart']['total'], $currency)) {
                        $is_fraud = true;
                    } else {
                        // if threshold for country has checked, no need to check in union
                        $countries_checked = true;
                    }
                    break;
                }
            }
        }

        // verify user's union threshold for fraud check
        if (!$is_fraud && !$countries_checked && $fraud_params['unions']) {
            foreach ($fraud_params['unions'] as $union) {
                if (array_intersect($user['country_id'], explode(',', $union['countries']))
                    && $union['threshold'] < Currency::convertCurrencyRaw($params['cart']['total'], $currency)) {
                    $is_fraud = true;
                    break;
                }
            }
        }

        return $is_fraud;
    }

    public function orderaddress()
    {
        return $this->hasMany(OrderAddress::class, 'platformorderid');
    }

    public function paymentmethod()
    {
        return $this->belongsTo(OrderPaymentDetail::class, 'platformorderid', 'platformorderid');
    }
    
    public function refunds()
    {
        return $this->hasMany(OrderRefundDetail::class, 'platformorderid', 'platformorderid');
    }
}
