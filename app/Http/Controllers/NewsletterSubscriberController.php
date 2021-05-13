<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CMS\WebsiteCategoriesAssociation;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\URL;
use App\Models\{
  NewsletterSubscribe,
};


class NewsletterSubscriberController extends Controller
{
    const TRUE_RETURN = 1;
    const FALSE_RETURN = 2;
    const FALSE_ERROR = 0;
    
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
    }

    public function index(Request $request)
    {        
       
    }
    /**
     * Show the application Homepage.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function create(Request $request)
    {        
        try
        {
            $data = array();
            $data = $request->only(['email']); 
            $getEmail = NewsletterSubscribe:: where('email',$request->only(['email']))->get()->first();
            
            if(!$getEmail)
            {
                $insert = NewsletterSubscribe::create($data);
                return static::TRUE_RETURN;
            }
            else
            {
                return static::FALSE_RETURN;
            }
            
        }
        catch (\Exception $exception) 
        {
            logger()->error($exception);
            return static::FALSE_ERROR;
        }
        
    }

}
