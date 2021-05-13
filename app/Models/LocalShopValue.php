<?php

namespace App\Models;
use Auth;
use Currency;
use App\Models\User;
use App\Models\Order\{
    Order,
    OrderInvoice,
    OrderLine,
    OrderAddress,
    OrderShipment
};
use App\Models\Catalog\Product\Product;
use App\Models\Order\WebsiteOrderId;
use Session;
use App\Models\Catalog\Attribute;
use App\Models\Catalog\Product\ProductAttribute;

use Illuminate\Database\Eloquent\Model;

class LocalShopValue extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */

    const DRAFTMODE = 2;
    const ACTIVE = 1;
    const INDIV = 1;
    const LABEL = 'Weight';
    const ATT_TYPE = '0';
    protected $fillable = [
        'admin_id', 'platformorderid', 'website_id', 'type',
    ];

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'local_shop_values';

    public function scopeGetCartData($query,$parms=array())
    {
        $query->select('local_shop_values.platformorderid','local_shop_values.website_id','ol.id','ol.product_id','p.type','p.code','ol.quantity','ol.model_id','ol.assoc_desc_id')
        ->leftJoin('order_lines as ol','ol.platformorderid', '=', 'local_shop_values.platformorderid')
        ->leftJoin('products as p','p.id', '=', 'ol.product_id');
        
        if(!empty($parms))
        {
            $query->where(['local_shop_values.admin_id'=>$parms['admin_id'],'local_shop_values.website_id'=>$parms['website_id']]);
        }
       
       return $query;
    }

    public function updateCartOrder($params) {

        $platformorderid = session()->get('local_shop_cart');
        $userInfoLocalShop = session()->get('checkout');
        $cart = $params['cart'];
        $websiteId = config('wmo_website.website_id');
        $currency = session('currency');
        

        //check promocode
        $promocode = $promocodeDiscount = '';
        if (isset($cart['promotion']) && $cart['promotion']['status'] && $cart['promotion']['promocode']) {
            $promocodeDiscount = $cart['promotion']['discount_value'];
            $promocode = $cart['promotion']['promocode'];
        }

         //insert website order id
         $websiteOrderData = ['website_id' => $websiteId, 'token' => $cart['token']];
         $websiteOrderId = WebsiteOrderId::updateOrCreate(['website_id' => $websiteId, 'token' => $cart['token']], $websiteOrderData);
        //user Info
        $shippingAddress = $userInfoLocalShop['user_addresses']['shipping'] ?? [];
        $orderLastName = $shippingAddress['last_name'];
        $orderEmail = $userInfoLocalShop['user_info']['email'];
        $orderFirstName = $shippingAddress['first_name'];
        $orderPhone = $shippingAddress['phone'];
         
        if (empty(session()->get('local_shop_cart'))) {
            $userResult = User::select('id')->where('email', $orderEmail)->get()->first();
            $userId = !empty($userResult) ? $userResult['id'] : '';
            $orderIdPrefix = 'LS';

            $order = [
                'prefix' => $orderIdPrefix,
                'website_id' => $websiteId,
                'order_id' => sprintf('%09d', $websiteOrderId->id),
                'user_id' => $userId,
                'currency_id' => session('currency')['id'],
                'language_id' => session('language')['id'],
                'first_name' => $orderFirstName,
                'last_name' => $orderLastName,
                'promocode' => $promocode,
                'phone' => $orderPhone,
                'email' => $orderEmail,
                'total' => Currency::convertCurrencyRaw($cart['total'], $currency),
                'promocode_discount' => $promocodeDiscount ?? Currency::convertCurrencyRaw($promocodeDiscount, $currency),
                'auth_fraud' => Order::isFraudOrder($params, $userInfoLocalShop),
                'token' => $cart['token'],
                'ip_address' => request()->ip(),
                'order_status' => Order::INCOMPLETE,
                'is_special_order'=>isset($params['cart']['specialCart']) ? 1 : 0,
            ];
            
            $orderData = Order::updateOrCreate(['website_id' => $websiteId, 'token' => $cart['token']], $order);

        }
        else
        {
            $order = [
                'website_id' => $websiteId,
                'promocode' => $promocode,
                'first_name' => $orderFirstName,
                'last_name' => $orderLastName,
                'email' => $orderEmail,
                'phone' => $orderPhone,
                'total' => Currency::convertCurrencyRaw($cart['total'], $currency),
                'promocode_discount' => $promocodeDiscount ?? Currency::convertCurrencyRaw($promocodeDiscount, $currency),
                'auth_fraud' => Order::isFraudOrder($params, $userInfoLocalShop),
                'order_status' => Order::INCOMPLETE,
                'is_special_order'=>isset($params['cart']['specialCart']) ? 1 : 0,
            ];
            $orderData = Order::updateOrCreate(['platformorderid' => $platformorderid], $order);
            
            /* delete old platformorderId realted data */
            OrderShipment::where('platformorderid', $platformorderid)->delete();
            OrderInvoice::where('platformorderid', $platformorderid)->delete();
            OrderLine::where('platformorderid', $platformorderid)->delete();
            OrderAddress::where('platformorderid', $platformorderid)->delete();
            //update orderid
            Session::forget('local_shop_cart');
        }
        
        return [
            'platformorderid' => $orderData->platformorderid,
            'order_id' => $orderData->order_id,
            'prefix' => $orderData->prefix
        ];
    }
    
    public function scopeGetOrderData($query,$parms=array())
    {
        $query->select('local_shop_values.platformorderid','local_shop_values.website_id','o.promocode')
        ->leftJoin('orders as o','o.platformorderid', '=', 'local_shop_values.platformorderid');
        if(!empty($parms))
        {
            $query->where(['local_shop_values.admin_id'=>$parms['admin_id'],'local_shop_values.website_id'=>$parms['website_id']]);
        }
    }
    /* inset temp products*/
    public function specialCartOrder($parms)
    {
        $productData = array();
        $attribute_id = Attribute::select('id')->where('label',static::LABEL)->get()->first();

        foreach($parms as $key=> $order)
        {   
            if(isset($order['specialCart']) && isset($order['product_id']) && empty($order['product_id']))
            {   
                $code =$this->uniqueProductCode('TEMP');
                $productData['type'] = config('constant.temp_product');
                $productData['code'] = $code;
                $productData['customer_description'] = $order['full_description'];
                $productData['created_at'] = $order['created_at'];
                $productData['updated_at'] = $order['updated_at'];
                $productId = Product::insertGetId($productData);
                $order['product_code'] = $code;
                $order['product_id'] = $productId;
                $ordlineProducts[$code] =  $order;
                if(isset($attribute_id['id']))
                {
                    ProductAttribute::insert(['product_id'=>$productId,'attribute_id'=>$attribute_id['id'],'type'=>static::ATT_TYPE,'attribute_value_id'=>$order['weight']]);
                }
            }
            else
            {
                $ordlineProducts[$key] = $order;
            }
        }
        return $ordlineProducts;
    }
    /**  update temp cart products status*/
    public function updateSpecialOrderProducts($parms)
    {
        $productData = array();
        foreach($parms['orderlines'] as $order)
        {   
            if(isset($order['specialCart']) && isset($order['product_id']) && !empty($order['product_id']))
            {   
                $productData['status'] = static::ACTIVE;
                Product::where('id',$order['product_id'])->update($productData);
            }   
        }

    }

    public function uniqueProductCode($type='') {
        //get random number
        $prdcode  = $type.$this->generateRandomNumber(); 
        if ($this->checkProductCodeExists($prdcode) ) {
            return uniqueProductCode();
        }
        return $prdcode;
    }

    /*
    * Method to get random number
    */
    public function generateRandomNumber(){
        $str = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $shuffled = str_shuffle($str);
        $alphpabet = substr($shuffled, 0, 2);

        return $alphpabet.mt_rand(1000, 9999);
    }

    public function checkProductCodeExists($prdcode) {
        return Product::where('code', $prdcode)->exists();
    }
}
