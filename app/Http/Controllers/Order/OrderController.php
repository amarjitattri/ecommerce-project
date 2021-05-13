<?php

namespace App\Http\Controllers\Order;

use Auth;
use Session;

use App\Services\Cart\Cart;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Trade\TradeOrderDetail;
use App\Repositories\Order\Interfaces\TradeOrderRepositoryInterface;

class OrderController extends Controller
{
    private $__cart;
    protected $__tradeOrder;

    public function __construct(Cart $cart, TradeOrderRepositoryInterface $tradeOrder)
    {
        $this->__cart = $cart;
        $this->__tradeOrder = $tradeOrder;
    }
    public function completeOrder() {
        $paymentDetails = Session::get('payment');
        if (!$paymentDetails && (Session::get('checkout.trader.pay_later') != TradeOrderDetail::PAY_LATER_YES || !$this->__tradeOrder->canPayLater())) {
            return redirect('basket');
        }
        
        //clear all session and cache
        $this->clearAll();

        return view('order.index', compact('paymentDetails'));
    }

    public function clearAll()
    {
        $userId = Auth::check() ? Auth::user()->id : null;
        $this->__cart->userId($userId);
        Session::forget('payment');
        Session::forget('checkout');
        $this->__cart->clearBasket();
    }
}
