<?php

namespace App\Http\Controllers\Payment;

use Auth;
use Session;
use App\Models\User;
use App\Services\Cart\Cart;
use Illuminate\Http\Request;
use App\Services\Product\Stock;
use App\Models\Order\OrderInvoice;
use App\Http\Controllers\Controller;
use App\Services\Localshop\Localshop;
use App\Models\Trade\TradeOrderDetail;
use App\Models\MyAccount\MessageTarget;
use App\Models\Order\OrderPaymentDetail;
use App\Services\Payment\RoyalShipmentService;
use App\Services\Payment\Contracts\PaymentContracts;
use App\Repositories\Order\Interfaces\OrderRepositoryInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use App\Repositories\Order\Interfaces\TradeOrderRepositoryInterface;
use App\Jobs\ProcessOrderEmailJob;

class PaymentController extends Controller
{
    private $__cart;
    protected $__orderRepo;
    protected $__payment;
    protected $__stockObj;
    protected $__localshop;
    protected $__tradeOrder;
    protected $__orderInvoice;

    const INCOMPLETE = 'IC';
    const UNPROCESSED = 'UP';
    const AWAITING_COLLECTION = 'AC';
    const SHIPPING = 1;
    const BILLING = 2;
    const SAME_AS_SHIPPING = 3;

    public function __construct(Cart $cart, OrderRepositoryInterface $order, TradeOrderRepositoryInterface $tradeOrder, OrderInvoice $orderInvoice)
    {
        $this->__cart = $cart;
        $this->__orderRepo = $order;
        $this->__tradeOrder = $tradeOrder;
        $this->__orderInvoice = $orderInvoice;
    }
    public function process(Request $request, PaymentContracts $payment, Stock $stock, Localshop $localshop)
    {
        //check payment already exists
        $params = [
            'platformorderid' => session('payment.platformorderid')
        ];
        if (OrderPaymentDetail::checkPaymentExists($params)) {
            return redirect('/order/complete');
        }
        $isPayLater = $this->__tradeOrder->isPayLater();
        if ($isPayLater) {
            throw_if(!$this->__tradeOrder->canPayLater(), new NotFoundHttpException());
        }

        
        $this->__payment = $payment;
        $this->__stockObj = $stock;
        $this->__localshop = $localshop;
        $checkoutSession = session('checkout');
        $collectionItemsOnly = $request->session()->get('collection_items_only');
        //check payment stage
        $checkBaksetExists = !$request['payment_method'] && !$this->__cart->hasCart();
        if (!empty(request()->session()->get('local_shop_user')) && $request['localShopPayment']) {
            $checkBaksetExists = !$request['localShopPayment'] && !$this->__cart->hasCart();
        }

        $cartStage = !empty($checkoutSession['stage']) &&
            (
                $checkoutSession['stage'] != config('constant.checkout.DELIVERY_METHOD_STAGE') && (
                $checkoutSession['stage'] != config('constant.checkout.ADDRESS_STAGE') && $collectionItemsOnly
            )
            );

        $userId = Auth::check() ? Auth::user()->id : null;
        $this->__cart->userId($userId);
        $cart = $this->__cart->transformCart();

        $isMatched = $this->__orderRepo->isProductDataMatched($checkoutSession, $cart);
        if ($checkBaksetExists || $cartStage || !$isMatched) {
            return redirect('basket');
        }

        $calculatedOrderLines = $this->__cart->calculateLineDiscount();
        if (!$calculatedOrderLines) {
            return redirect('basket')->with('error', 'Promocode Expired');
        }
        $collectionCheckout = isset($request->collection_checkout) ? $request->collection_checkout : 0;
        $params = [
            'cart' => $cart,
            'orderlines' => $calculatedOrderLines,
            'user_id' => $userId,
            'collection_checkout' => $collectionCheckout,
        ];
        $orderData = $this->__orderRepo->save($params);
        //process payment
        
        if ((!empty(request()->session()->get('local_shop_user')) || !empty(session()->get('trade_local_shop_user'))) && $request['localShopPayment']) {
            $paymentStatus = $this->__localshop->localShopPayment($orderData);
        } else {
            if ($isPayLater) {
                $paymentStatus = true;
                request()->session()->put('checkout.trader.pay_later', TradeOrderDetail::PAY_LATER_YES);

                $order = $orderData['order_data'];
                $orderId = $order['order_id'];
                request()->session()->put('payment', array(
                    'order_id' => $orderId ,
                    'platformorderid' => $order['platformorderid']
                ));
            } else {
                $paymentStatus = $this->__payment->processPayment($orderData);
            }
        }
       
        if ($paymentStatus) {
            //save data in order invoice
            $this->__orderInvoice->saveInvoice($orderData);
            if($checkoutSession['user_info']['email'])
            {
                ProcessOrderEmailJob::dispatch(['email' => $checkoutSession['user_info']['email'], 'orderData' => $orderData])->onQueue('frontend');
            }
            $this->__stockObj->createStockCustomerOrder($orderData);
            //update stock
            $this->__stockObj->updateStock($orderData);
            $this->__stockObj->updatePromoCount($orderData);

            $return = 'order/complete';
        } else {
            $return = 'basket';
        }
        return redirect($return);
    }
}
