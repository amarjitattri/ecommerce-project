<?php
namespace App\Services\Localshop;

use App\Models\{
    Ipnation,
    LocalShopValue,
    WmbackendUser,
    LocalShopUserIp
};
use App\Models\Order\OrderPaymentDetail;
use App\Models\Order\Order;
use App\Models\Order\OrderLocalShopUser;
use App\Models\Order\OrderLineVariant;
use App\Models\Order\OrderLocalShopOtherThings;
use App\Models\CMS\BikeModel;
use App\Models\CMS\Website\WebsiteInvoice;
use App\Services\Cart\Cart;
use Session;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Crypt;
use Request;
class Localshop {
    private $__cart;
    const UNPROCESSED = 'UP';
    const LOCALSHOPSTATUS = '1';
    
    public function __construct(Cart $cart)
    {
        $this->__cart = $cart;       
    }

    public function localShopeUserexist($adminId)
    {
        $userExist = $this->__checkLocalShopUser($adminId);
        if($userExist)
        {
            $userId = null;
            $params = array(
                            'website_id' => config('wmo_website.website_id'),
                            'admin_id'=>$adminId
            );
            
            $localCart = LocalShopValue::getCartData($params)->get();
            
            if(!$localCart->isEmpty())
            {
                $this->__moveToCartLocalShop($localCart,$userId,$params);
                
            }      
        }
        else
        {
            Session::forget('local_shop_user');
            abort(403, 'Access Denied');
        }        
    }

   /*check user have access or not for localshop */
    private function __checkLocalShopUser($userId)
    {
        $response = false;
        $userIp = Request::ip();
        $isLocalShopUser = WmbackendUser::select('id','is_local_shop','role_id')->where('id',$userId)->first();
        if($isLocalShopUser)
        {
            if($isLocalShopUser->role_id == config('constant.super_admin')) 
            {
                $response = true;
            } 
            else 
            {
                if($isLocalShopUser->is_local_shop)
                {
                    $response = true;
                    $ip = LocalShopUserIp::select('id')->where('user_id',$userId)->first();
                    if($ip) 
                    {
                        $response = LocalShopUserIp::select('id')->where(['user_id'=>$userId,'ip'=>ip2long($userIp)])->first();
                    }
                }    
            }
        }
       
        return $response;
    }
    
    /* move to cart local shop */
    private function __moveToCartLocalShop($localCart,$userId,$params)
    {
        $platFormOrder= LocalShopValue::getOrderData($params)->first()->toArray();
        session(['local_shop_cart'=>$platFormOrder['platformorderid']]);
        foreach ( $localCart as $value) 
        {
            if($value['type']==config('constant.kit_product_type'))
            {
                $kitData = OrderLineVariant::getKitCartData($value['platformorderid'])->get()->toArray();
                foreach($kitData as $kitProduct)
                {
                    $kit = array(array(
                        "prdId" => $kitProduct['varient_id'],
                        "prdCode" => $kitProduct['code'],
                        "quantity" =>$value['quantity'],
                        "description" => $kitProduct['customer_description'],
                        "descriptionType" => ''
                    ));
                }
            }
            else
            {
                $kit='';
            }
            $bikeModel = BikeModel::getBikeModelById($value['model_id'])->toArray();   
            $vehicle_details = $bikeModel['make_assoc']['name'].' '.$bikeModel['name'];
            if(!empty($bikeModel['year_code'])){
                $vehicle_details .= ' '.$bikeModel['year_code']['value'];
            } 
            if(!empty($bikeModel['type_code'])){
                $vehicle_details .= ' '.$bikeModel['type_code']['value'];
            }        
            
            $product = [
                'product_id' => $value['code'],
                'type' => $value['type']
            ];
            $qty = $value['quantity'];
            $options = [
                "product_name" => $value['code'],
                "vechile_name" => $vehicle_details,
                "product_kit_products" => json_encode($kit),
                "assoc_id" => $value['assoc_desc_id'],
                "model_id" => $value['model_id'],
            ];
            $this->__cart->userId($userId);
            $this->__cart->add($product, $qty, $options); 
        }
        if($platFormOrder['promocode'])
        {
            $promoCode = $platFormOrder['promocode'];
            $this->__cart->userId($userId);
            $this->__cart->setPromoCode($promoCode);
            $this->__cart->transformCart();
        }
    }

    /*local shop payment */
    public function localShopPayment($orderData)
    {
        $request = request()->all();
        $order = $orderData['order_data'];

        if((!empty(request()->session()->get('local_shop_user')) || !empty(session()->get('trade_local_shop_user'))) && $request['localShopPayment']){
            $orderPaymentDetails = [
              'platformorderid' => $orderData['platformorderid'],
              'transaction_id' => '',
              'token' =>  '',
              'type' =>  $request['localShopPayment'],
              'data_capture' => '',
              'capture_date' => date('Y-m-d H:i:s'),
              'status' => $request['localShopPayment']=='CASH_ON_DELIVERY' ? config('constant.localshopPaymentStatus_cod') : config('constant.localshopPaymentStatus'),
            ];
            //update payment status
            Order::where('platformorderid', $order['platformorderid'])->update(['order_status' => static::UNPROCESSED]);

            Session::put('payment', array_merge(array('order_id' => $order['order_id']), $orderPaymentDetails));
            OrderPaymentDetail::create($orderPaymentDetails);

            $OrderLocalShopUser = [
              'platformorderid' => $orderData['platformorderid'],
              'admin_id'=>request()->session()->get('local_shop_user')
            ];
            OrderLocalShopUser::create($OrderLocalShopUser);
            $localotherthings = [
              'platformorderid' => $orderData['platformorderid'],
              'order_type'=>$request['localshopOrderType'],
              'notes'=>$request['notes']?$request['notes']:'',
              'ref_order_number'=>isset($request['refNum'])?$request['refNum']:'',
              'reference_notes'=>isset($request['refnotes'])?$request['refnotes']:'',
            ];
            OrderLocalShopOtherThings::create($localotherthings);
            return true;
        }
        else
        {
            return false;
        }  
    }

    public function getRRPrice()
    {
       $websiteRrPrice =  WebsiteInvoice::select('country_id as id')->where('website_id',config('wmo_website.website_id'))->with(['rrPrice'])->first();
       return $websiteRrPrice->rrPrice;
    }

    public function specialOrderSaveBasket($data)
    {
        $userId = $userId = Auth::check() ? Auth::user()->id : null;
        $this->__cart->userId($userId);
        $this->__cart->localShopSpecialCart($data); 
    }


    public function loclashopLogoutBasket($request)
    {
        $request->session()->invalidate();
        Redis::del('user_id');
        $userId = $userId = Auth::check() ? Auth::user()->id : null;
        $this->__cart->userId($userId);
        Session::forget('payment');
        Session::forget('checkout');
        $this->__cart->clearBasket();
        session(['local_shop_logout' => static::LOCALSHOPSTATUS]);
    }

    protected function guard()
    {
        return Auth::guard();
    }
}