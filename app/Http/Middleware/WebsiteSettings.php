<?php

namespace App\Http\Middleware;

use App\Http\Middleware\B2BMiddleware;
use App\Models\Order\OrderPaymentDetail;
use Closure;
use Illuminate\Support\Facades\Auth;
use Session;
use App\Models\Trade\TraderImpersonate;
use Carbon\Carbon;
class WebsiteSettings
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($req, Closure $next)
    {
        app(\App\Services\Config\WebsiteConfig::class);

        $request = $req->all();
        $bOk = false;
        if (isset($request['ts'])) {
            $this->tradeUserImperosnateLogin($request);
            $bOk = true;
            session(['trade_local_shop_user' => $request['tls']]);
        }

        if (isset($request['ls'])) {

            $this->guard()->logout();
            session()->invalidate();

            if (isset($request['token']) && base64_decode($request['token']) > time() - (config('constant.localshop_time'))) {
                session(['local_shop_user' => $request['ls']]);
            } else {
                Session::forget('local_shop_user');
                abort(403, 'Access Denied');
            }
            $bOk = true;
        }

        if ($bOk) {
            return redirect('/');
        }


        if (!session('currency')) {
            session(['currency' => config('wmo_website.currency')]);
        }

        if (!session('language')) {
            session(['language' => config('wmo_website.language')]);
        }

        app()->setLocale(session('language.languagecode'));

        // if website type is b2b
        if (config('wmo_website.type') == config('constant.trade_type')) {
            if (!session('trader') && Auth::check()) {
                session([
                    'trader' => [
                        'discount_category' => Auth::user()->trader->discount_category ?? null,
                        'discount_tier' => Auth::user()->trader->tierStructure->value ?? null,
                    ],
                ]);
            } elseif(session('trader') && !Auth::check()) {
                session()->forget('trader');
            }

            return app(B2BMiddleware::class)->handle(app('request'), function ($req) use ($next) {
                return $next($req);
            });
        }

        return app(B2CMiddleware::class)->handle(app('request'), function ($req) use ($next) {
            return $next($req);
        });
    }

    protected function guard()
    {
        return Auth::guard();
    }

    protected function tradeUserImperosnateLogin($request)
    {
        $this->guard()->logout();
        session()->invalidate();

        //validate the trade user
        $tradeUser = TraderImpersonate::validateTradeToken(['token' => $request['ts']])->first();
        $compareDate = Carbon::now()->subHours(config('constant.tsm_expire_time'));
        if($tradeUser && $tradeUser->created_at > $compareDate) {
            Auth::loginUsingId($tradeUser->trade_user_id);
        } else {
            abort(403, 'Access Denied');
        }
    }

}
