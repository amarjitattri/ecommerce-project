<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

class B2BMiddleware
{
    protected $excluded_routes = [
        'signup*',
        'register/create',
        'user/savemydetails',
        'user/my-detail',
        'user/add-address',
        'user/saveaddress',
    ];

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        
        foreach ($this->excluded_routes as $route) {
            if ($request->is($route)) {
                abort(404);
            }
        }
        
        return $next($request);
    }
}
