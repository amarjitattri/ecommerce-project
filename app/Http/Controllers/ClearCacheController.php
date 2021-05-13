<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use App\Services\Config\Cache\Contracts\CacheContracts;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ClearCacheController extends Controller
{

    public function __construct(CacheContracts $cache)
    {
        $this->__cache = $cache;
    }

    public function clearWebsiteCache()
    {
        $this->__cache->del(config('wmo_website.website_key'));
        $this->__cache->del(config('wmo_website.make_key'));
        $this->__cache->del(config('wmo_website.f_product_key'));
        echo 'Website Cache Cleared';

        //clear session
        session(['currency', null]);
        session(['language', null]);
        return redirect('/');
    }

    public function generateInvoiceOrderidTable()
    {
        $website = new \App\Models\CMS\Website;
        $websites = $website::get();
        
        foreach($websites as $value) {
            $tableName = strtolower($value->website_code).'_invoice_ids';

            // check if table is not already exists
            if (!Schema::hasTable($tableName)) {
                Schema::create($tableName, function (Blueprint $table) {
                    $table->bigIncrements('id');
                    $table->integer('website_id');
                    $table->string('token');
                    $table->timestamps();
                });
                echo $tableName.'table has been successfully created!'. "<br />";
            }
            echo $tableName. 'Given table is already existis.'."<br />";
        }
    }
}
