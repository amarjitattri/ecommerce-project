<?php

namespace App\Providers;
use Illuminate\Support\ServiceProvider;
use App\Repositories\Cart\CartRepository;
use App\Repositories\Order\OrderRepository;
use App\Repositories\Cart\PromoCodeRepository;
use App\Repositories\Product\ProductRepository;
use App\Repositories\Order\TradeOrderRepository;
use App\Repositories\Checkout\CheckoutRepository;
use App\Repositories\Currency\CurrencyRepository;
use App\Repositories\Catalog\Brand\BrandRepository;
use App\Repositories\Myaccount\MyVehicleRepository;
use App\Repositories\Myaccount\MyCalendarRepository;
use App\Repositories\Cart\Cache\RedisCacheRepository;
use App\Repositories\Myaccount\UserDetailsRepository;
use App\Repositories\Myaccount\OrderHistoryRepository;
use App\Repositories\Category\ShopAllCategoryRepository;
use App\Repositories\Category\VehicleCategoryRepository;
use App\Repositories\Product\ProductFlatPriceRepository;
use App\Repositories\Cart\Interfaces\CartRepositoryInterface;

use App\Repositories\Myaccount\UserEmailPreferenceRepository;
use App\Repositories\Order\Interfaces\OrderRepositoryInterface;
use App\Repositories\Cart\Interfaces\PromoCodeRepositoryInterface;
use App\Repositories\Product\Interfaces\ProductRepositoryInterface;
use App\Repositories\Cart\Cache\Interfaces\CacheRepositoryInterface;
use App\Repositories\Order\Interfaces\TradeOrderRepositoryInterface;
use App\Repositories\Checkout\Interfaces\CheckoutRepositoryInterface;

use App\Repositories\Currency\Interfaces\CurrencyRepositoryInterface;
use App\Repositories\Catalog\Brand\Interfaces\BrandRepositoryInterface;
use App\Repositories\Myaccount\Interfaces\MyVehicleRepositoryInterface;
use App\Repositories\Myaccount\Interfaces\MyCalendarRepositoryInterface;

use App\Repositories\Product\Interfaces\ProductPriceRepositoryInterface;
use App\Repositories\Myaccount\Interfaces\UserDetailsRepositoryInterface;
use App\Repositories\Myaccount\Interfaces\OrderHistoryRepositoryInterface;
use App\Repositories\Category\Interfaces\ShopAllCategoryRepositoryInterface;
use App\Repositories\Category\Interfaces\VehicleCategoryRepositoryInterface;
use App\Repositories\Myaccount\Interfaces\UserEmailPreferenceRepositoryInterface;

class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind(
            VehicleCategoryRepositoryInterface::class,
            VehicleCategoryRepository::class
        );

        $this->app->bind(
            ProductRepositoryInterface::class,
            ProductRepository::class
        );

        $this->app->bind(
            ProductPriceRepositoryInterface::class,
            ProductFlatPriceRepository::class
        );
        $this->app->bind(
            CartRepositoryInterface::class,
            CartRepository::class
        );

        $this->app->bind(
            CacheRepositoryInterface::class,
            RedisCacheRepository::class
        );

        $this->app->bind(
            PromoCodeRepositoryInterface::class,
            PromoCodeRepository::class
        );

        $this->app->bind(
            ShopAllCategoryRepositoryInterface::class,
            ShopAllCategoryRepository::class
        );

        $this->app->bind(
            UserDetailsRepositoryInterface::class,
            UserDetailsRepository::class
        );

        $this->app->bind(
            MyVehicleRepositoryInterface::class,
            MyVehicleRepository::class
        );

        $this->app->bind(
            MyCalendarRepositoryInterface::class,
            MyCalendarRepository::class
        );

        $this->app->bind(
            CurrencyRepositoryInterface::class,
            CurrencyRepository::class
        );

        $this->app->bind(
            UserEmailPreferenceRepositoryInterface::class,
            UserEmailPreferenceRepository::class
        );

        $this->app->bind(
            OrderRepositoryInterface::class,
            OrderRepository::class
        );

        $this->app->bind(
            CheckoutRepositoryInterface::class,
            CheckoutRepository::class
        );

        $this->app->bind(
            OrderHistoryRepositoryInterface::class,
            OrderHistoryRepository::class
        );

        $this->app->bind(
            TradeOrderRepositoryInterface::class,
            TradeOrderRepository::class
        );

        $this->app->bind(
            BrandRepositoryInterface::class,
            BrandRepository::class
        );
    }
    

}
