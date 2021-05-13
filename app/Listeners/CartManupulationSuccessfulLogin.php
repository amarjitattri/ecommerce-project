<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Login;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use App\Services\Cart\Cart;
use Illuminate\Support\Facades\Redis;
use Auth;

class CartManupulationSuccessfulLogin
{
    private $__cart;

    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct(Cart $cart)
    {
        $this->__cart = $cart;
    }

    /**
     * Handle the event.
     *
     * @param  Login  $event
     * @return void
     */
    public function handle(Login $event)
    {
        $userId = null;
        $this->__cart->userId($userId);
        $this->__cart->saveUserBakset($event->user);
    }
}
