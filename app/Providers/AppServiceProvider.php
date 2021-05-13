<?php

namespace App\Providers;

use Braintree_Configuration;
use Illuminate\Http\Request;
use App\Services\Product\Product;
use App\Services\Payment\Braintree;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Redis;
use App\Services\Config\WebsiteConfig;
use App\Services\Cart\Cache\RedisCache;

use App\Services\Search\Providers\Solr;
use Illuminate\Support\ServiceProvider;
use App\Services\Payment\CashOnDelivery;
use App\Services\Product\FeaturedProduct;
use App\Services\Payment\Contracts\PaymentContracts;
use App\Services\Product\Contracts\ProductContracts;
use App\Services\Cart\Cache\Contracts\CacheContracts;

use App\Services\Search\Providers\Contracts\SearchContracts;
use App\Services\Config\Cache\RedisCache as ConfigRedisCache;
use App\Services\Config\Cache\Contracts\CacheContracts as ConfigCacheContracts;
use App\Services\Currency\Providers\Contracts\CurrencyConversionContracts;
use App\Services\Currency\Providers\CurrencyLayer;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(\App\Http\View\Composers\CartComposer::class);
        $this->app->singleton(\App\Services\Config\WebsiteConfig::class);
        $this->app->singleton(\App\Http\View\Composers\ConfigComposer::class);
        $this->app->singleton(\App\Http\View\Composers\ShopAllComposer::class);

        $this->app->singleton(
            ProductContracts::class,
            Product::class
        );
        
        $this->app->singleton(
            CacheContracts::class,
            RedisCache::class
        );
        $this->app->singleton(
            ConfigCacheContracts::class,
            ConfigRedisCache::class
        );
        $this->app->singleton(
            ShopAllCacheContracts::class,
            ShopAll::class
        );

        $this->app->singleton(\App\Services\Product\Stock::class);
        $this->app->singleton(\App\Services\Product\Product::class);
        $this->app->singleton(\App\Services\Cart\Cart::class);

        $this->app->singleton(PaymentContracts::class, function () {
            switch (request()->payment_method) {
                case 'cod':
                    return new CashOnDelivery();
                    break;
                case 'braintree':
                    return new Braintree();
                    break;
                default:
                    return new CashOnDelivery();
            }
        });
       
        $this->app->singleton(SearchContracts::class, function () {
            switch (config('constant.search_type')) {
                case '1':
                    return new Solr();
                    break;
                default:
                    return new Solr();
            }
        });
        $this->app->singleton(\App\Services\Product\FeaturedProduct::class);
        
        $this->app->singleton('currency', function () {
            return new \App\Helpers\Currency;
        });

        $this->app->singleton('tax', function () {
            return new \App\Helpers\Tax;
        });

        Braintree_Configuration::environment(env('BRAINTREE_ENV'));
        Braintree_Configuration::merchantId(env('BRAINTREE_MERCHANT_ID'));
        Braintree_Configuration::publicKey(env('BRAINTREE_PUBLIC_KEY'));
        Braintree_Configuration::privateKey(env('BRAINTREE_PRIVATE_KEY'));

        $this->app->singleton(\App\Services\Checkout\CODFee::class);

        $this->app->singleton(CurrencyConversionContracts::class, function () {
            switch (config('constant.currency.currency_api')) {
                case 'currency_layer':
                    return new CurrencyLayer();
                    break;
                default:
                    return new CurrencyLayer();
            }
        });

        $this->app->singleton('CurrencyConversionAPI', function () {
            return app(\App\Services\Currency\CurrencyConversionAPI::class);
        });

    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot(Request $request)
    {
        // to make debugger true from cookie (will be used in dev mode only)
        if ($request->cookie('debug') == 'staging') {
            config(['app.debug' => true]);
        }
        
        \Route::get('flush-all', function () {
            Redis::flushAll();
            return redirect('/');
        });

        View::composer('*', 'App\Http\View\Composers\ConfigComposer');
        View::composer('*', 'App\Http\View\Composers\CartComposer');
        View::composer('*', 'App\Http\View\Composers\ShopAllComposer');
    }

}
