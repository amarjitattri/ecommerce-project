<?php
namespace App\Http\View\Composers;

use App\Services\Cart\Cart;
use Auth;
use Illuminate\View\View;

class CartComposer
{

    private $__cart;
    private $__miniCart;
    private $__imagePath;

    public function __construct(Cart $cart)
    {
        $this->__cart = $cart;
        $this->__count = 0;
        $this->__total = 0;
        $userId = Auth::check() ? Auth::user()->id : null;
        $this->__cart->userId($userId);
        $this->__imagePath = asset('storage') . '/' . config('wmo_website.website_id') . '/images/productimages/';
        if ($this->__cart->hasCart()) {
            $this->__miniCart = $this->__cart->miniCart() ?? null;

            //currency conversion
            if (count($this->__miniCart)) {
                $this->__miniCart = $this->__cart->cartCurrencyConversion($this->__miniCart, session('currency'));
            }
        }
    }

    /**
     * Bind data to the view.
     *
     * @param  View  $view
     * @return void
     */
    public function compose(View $view)
    {
        $view->with('minicart', $this->__miniCart)
            ->with('imagePath', $this->__imagePath);
    }
}
