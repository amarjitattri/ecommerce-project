<?php

namespace App\Http\Controllers\Cart;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\Cart\CartRequest;
use App\Models\Order\OrderPaymentDetail;
use App\Services\Product\Contracts\ProductContracts;
use App\Repositories\Product\Interfaces\ProductRepositoryInterface;
use App\Repositories\Myaccount\Interfaces\MyVehicleRepositoryInterface;
use App\Services\Cart\Cart;
use App\Services\Checkout\DeliveryMethod;
use App\Services\Localshop\Localshop;
use App\Services\Watermark\WatermarkService;
use Response;
use Session;
use Auth;
use Illuminate\Support\Str;

class CartController extends Controller
{
    private $__cart;

    /**
     * Cart Controller Consturctor
     * @param Cart $cartRepository
     */
    public function __construct(Cart $cart, ProductRepositoryInterface $productRepository, ProductContracts $product, MyVehicleRepositoryInterface $myVehicleRepository, Localshop $localshop)
    {
        $this->__cart = $cart;
        $this->productRepository = $productRepository;
        $this->__productService = $product;
        $this->myVehicleRepository = $myVehicleRepository;
        $this->__localshop = $localshop;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //check payment already exists
        $params = [
            'platformorderid' => session('payment.platformorderid')
        ];
        if (OrderPaymentDetail::checkPaymentExists($params)) {
            return redirect('/order/complete');
        }
        $userId = Auth::check() ? Auth::user()->id : null;
        $this->__cart->userId($userId);
        $this->__cart->updateProductPrice();
        $this->__cart->setCODCharges(null);
        $basket = $this->__cart->transformCart() ?? [];
        
        //currency conversion
        if ($basket) {
            $basket = $this->__cart->cartCurrencyConversion($basket, session('currency'));
        }

        $relatedProduct=[];
        if (!empty($basket['products'])) {
            $relatedProduct =  $this->__productService->relatedProducts($basket['products'], 'cart');
        }

        $basketPage = 1;
        $invalidPromoCode = '';
        $imagePath = asset('storage').'/'.config('wmo_website.website_id').'/';
        $websiteFranchise = config('wmo_website.franchise');
        $rrPrice = array();
        if (!empty(request()->session()->get('local_shop_user')) || !empty(request()->session()->get('trade_local_shop_user'))) {
            //$bikeModel= $this->myVehicleRepository->getAllBikeModel()
            $rrPrice = $this->__localshop->getRRPrice();
        }
        if (!empty($basket['products'])) {
            foreach ($basket['products'] as $val) {
                if (!empty($val['product_images'][0]['name'])) {
                    WatermarkService::processImage($val['product_images'][0]['name']);
                }
            }
        }
        $qutoeOrder = Session::get('quote') ? Session::get('quote') :'';
        $localshopFreeShipping = Session::get('localshopfreeshipping') ? Session::get('localshopfreeshipping') :'';
        return view('cart.index', compact('basket', 'imagePath', 'invalidPromoCode', 'relatedProduct', 'basketPage', 'websiteFranchise', 'rrPrice', 'qutoeOrder', 'localshopFreeShipping'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(CartRequest $request)
    {
        $product = [
            'product_id' => $request->product_id,
            'type' => $request->type
        ];
        $options = $request->options;

        $userId = Auth::check() ? Auth::user()->id : null;
        $this->__cart->userId($userId);
        $this->__cart->setDelivery(null);
        $status = $this->__cart->add($product, $request->qty, $options);
        $minicart = $this->__cart->miniCart();
        //currency conversion
        if ($minicart) {
            $minicart = $this->__cart->cartCurrencyConversion($minicart, session('currency'));
        }
        $minicartHTML = view('cart.partials.minicart', compact('minicart'))->render();
        return Response::json([
            'success' => $status,
            'cart' => '',
            'minicart' => [
                'html' => $minicartHTML,
                'json' => $minicart
            ]
        ]);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(CartRequest $request, $id)
    {
        $options = $request->options;
        $userId = Auth::check() ? Auth::user()->id : null;

        $this->__cart->userId($userId);

        $this->__cart->setDelivery(null);
        $status = $this->__cart->update($request->product_id, $request->qty, $options);
        return Response::json([
            'success' => $status,
            'cart' => ''
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $userId = Auth::check() ? Auth::user()->id : null;
        $this->__cart->userId($userId);
        $status = $this->__cart->delete($id);
        return Response::json([
            'success' => $status,
            'cart' => ''
        ]);
    }
}
