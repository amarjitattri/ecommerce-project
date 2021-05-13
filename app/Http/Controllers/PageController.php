<?php

namespace App\Http\Controllers;

use App\Models\CMS\Page;
use App\Models\CMS\WebsiteBanner;
use App\Models\CMS\WebsiteCategoriesAssociation;
use Illuminate\Http\Request;

use GuzzleHttp\Client;
class PageController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
    }

    /**
     * Show the application Homepage.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function view($slug, Request $request)
    {
        $data['content'] = Page::getPageContent('url', $slug, config('wmo_website.website_id'));
        if(!$data['content']) {
            abort(404);
        }
        return view('pages.page')->with($data);
    }

    /**
     * Show the application Homepage.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function terms(Request $request)
    {
        return view('pages.terms');

    }

    /**
     * Show the application Thankyou.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function thankyou(Request $request)
    {
        if (\Session::has('message')) {
            return view('pages.thankyou');
        } else {
            abort(config('constant.error_code.403'), 'Access Denied');
        }
    }
 
    
}
