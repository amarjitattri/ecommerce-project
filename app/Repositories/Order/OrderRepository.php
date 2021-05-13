<?php
namespace App\Repositories\Order;

use App\Models\Order\Order;
use App\Models\LocalShopValue;
use App\Models\Order\OrderLine;
use App\Models\Order\OrderAddress;
use App\Models\Order\OrderInvoice;
use App\Models\Order\OrderShipment;
use App\Models\Trade\TradeOrderDetail;
use App\Services\Checkout\DeliveryMethod;
use App\Models\Order\GuestDetail;
use App\Repositories\Order\Interfaces\OrderRepositoryInterface;
use Auth;
class OrderRepository implements OrderRepositoryInterface
{

    protected $__order;
    protected $__orderShipment;
    protected $__orderInvoice;
    protected $__orderLine;
    protected $__orderAddress;
    protected $__platformOrderId;
    protected $__localShopValue;
    protected $__tradeOrder;
    protected $__orderGuest;

    public function __construct(
        Order $order,
        OrderShipment $orderShipment,
        OrderInvoice $orderInvoice,
        OrderLine $orderLine,
        OrderAddress $orderAddress,
        LocalShopValue $localShopValue,
        TradeOrderDetail $tradeOrder,
        GuestDetail $orderGuest
    ) {
        $this->__order = $order;
        $this->__orderShipment = $orderShipment;
        $this->__orderInvoice = $orderInvoice;
        $this->__orderLine = $orderLine;
        $this->__orderAddress = $orderAddress;
        $this->__localShopValue = $localShopValue;
        $this->__tradeOrder = $tradeOrder;
        $this->__orderGuest = $orderGuest;
    }

    public function save($params)
    {
        //save data in order table
        if (empty(session()->get('local_shop_cart')) && empty(session()->get('local_shop_user'))) {
            $orderData = $this->__order->saveOrder($params);
        } else {
            $orderData = $this->__localShopValue->updateCartOrder($params);
            if (isset($params['cart']['specialCart'])) {
                $this->__localShopValue->updateSpecialOrderProducts($params);
            }

        }
        $this->__platformOrderId = $orderData['platformorderid'];

        $userInfo =  session()->get('checkout.user_info');
        $webType = config('wmo_website.website_code');
        $userId = Auth::check() ? Auth::user()->id : null;
        if(isset($userInfo['is_business']) && $userInfo['is_business']==1 && $webType=='IT' && $userId==null)
        {
            
            $userGuest = [
                'email' => $userInfo['email'],
                'vat_number' => $userInfo['vat_number'],
                'tax_code' => $userInfo['tax_code'],
                'is_business' => $userInfo['is_business'],
                'order_id' =>   $this->__platformOrderId
            ];
             $this->__orderGuest::create($userGuest);
        }

        $params['platformorderid'] = $this->__platformOrderId;
        $params['order_data'] = $orderData;

        //save data in shipment table
        $orderShipment = $this->__orderShipment->saveShipment($params);
        $params['order_shipments'] = $orderShipment['order_shipments'];
        $params['shipments_products'] = $orderShipment['shipments_products'];

        //save data in order invoice
        $this->__orderInvoice->saveInvoice($params);

        //save data in orderlines table
        $this->__orderLine->saveOrderlines($params);

        //save data in orderaddress table
        if (empty($params['collection_checkout'])) {
            $this->__orderAddress->saveAddress($params);
        }

        // save trade data
        $this->__tradeOrder->saveOrderDetails($params);

        return $params;

    }

    public function isProductDataMatched($checkoutSession, $cart)
    {
        $checkout_products = [];
        if (isset($checkoutSession['delivery_method']['shipments'])) {
            foreach ($checkoutSession['delivery_method']['shipments'] as $row) {
                $checkout_products = array_merge($checkout_products, $row['products']);
            }
        } elseif(isset($checkoutSession['delivery_method']['products'])) {
            $checkout_products = $checkoutSession['delivery_method']['products'];
        }
        if (isset($checkoutSession['collection_shipment']['products'])) {
            $checkout_products = array_merge($checkout_products, $checkoutSession['collection_shipment']['products']);
        }
        
        if (count($checkout_products) !== count($cart['products'])) {
            DeliveryMethod::goToAddressStage();
            return false;
        }

        foreach ($cart['products'] as $key => $product) {
            $cart['products'][$key]['key'] = $key;
        }

        $cart_products = collect(array_values($cart['products']));

        $return = true;
        foreach ($checkout_products as $row) {
            $available_products = $cart_products->where('product_id', $row['product_id'])->where('qty', $row['qty']);
            if($available_products->count() == 0) {
                $return = false;
                break;
            } elseif (!empty($row['product_kit_items'])) {
                $kit_products = $available_products->toArray();
                $isSameKitExist = false;
                foreach ($kit_products as $kit) {
                    if ($row['product_kit_items'] == $kit['product_kit_items']) {
                        $isSameKitExist = true;
                        break;
                    }
                }
                if(!$isSameKitExist) {
                    $return = false;
                    break;
                }
            }
        }
        if (!$return) {
            DeliveryMethod::goToAddressStage();
        }
        return $return;
    }

}
