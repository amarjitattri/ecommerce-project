<?php

namespace App\Http\Controllers\Auth;

use Session;
use Socialite;
use Carbon\Carbon;
use App\Models\User;
use App\Services\Cart\Cart;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\URL;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Password;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use App\Services\Localshop\Localshop;

class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
     */
    use AuthenticatesUsers;

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    const LOCALSHOPSTATUS = '1';
    protected $redirectTo = '/home';
    private $__cart;
    private $__localshop;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(Cart $cart, Localshop $localshop)
    {
        $this->__cart = $cart;
        $this->__localshop = $localshop;
        $this->middleware('guest')->except('logout');
    }

    protected function signin()
    {
        if(!empty(request()->session()->get('local_shop_user')))
        {
            return redirect('/');
        }
        else
        {
            return view('users.signin');
        }
    }
    public function login(Request $request)
    {
        $this->validateLogin($request);

        // If the class is using the ThrottlesLogins trait, we can automatically throttle
        // the login attempts for this application. We'll key this by the username and
        // the IP address of the client making these requests into this application.
        if (method_exists($this, 'hasTooManyLoginAttempts') &&
            $this->hasTooManyLoginAttempts($request)) {
            $this->fireLockoutEvent($request);

            return $this->sendLockoutResponse($request);
        }
        $responseReturn = back()->withErrors(['email' => __('messages.email_password_error')]);
        $remember = (isset($request->remember) && !empty($request->remember));

        if (Auth::attempt(['email' => $request->email, 'password' => $request->password, 'register_via' => config('constant.wemoto'), 'website_id' => config('wmo_website.website_id')], $remember)) {
            // Prepare User Session Permission Data
            $user = $this->guard()->user();
            if ($user->status != User::STATUS_ACTIVE) {
                $request->session()->invalidate();
                $message = config('wmo_website.type') == User::TRADER_TYPE ? 'messages.account_is_deactivated_trade' : 'messages.account_is_deactivated';
                $responseReturn = redirect()->route('users.login')->withErrors(['email' => __($message)])->withInput($request->input());
            } else if ($user->is_verified == User::NOT_VERIFIED) {
                $request->session()->invalidate();
                $responseReturn = redirect()->route('users.login')->withErrors(['email' => 'Your email has not verified.'])->withInput($request->input());
            } else if ($user->website_id != config('wmo_website.website_id')) {
                $request->session()->invalidate();
                $responseReturn = redirect()->route('users.login')->withErrors(['email' => 'User doesn\'t exists.'])->withInput($request->input());
            } else {
                $date = new Carbon();
                User::where('id', $user->id)
                    ->update([
                        'last_login_at' => $date,
                        'updated_at' => $date,
                    ]);

                return redirect()->intended();
            }
        }

        return $responseReturn;
    }

    /**
     * API for checkout page login ajax
     */
    public function loginApi(Request $request, Response $response)
    {
        $this->validateLogin($request);

        // If the class is using the ThrottlesLogins trait, we can automatically throttle
        // the login attempts for this application. We'll key this by the username and
        // the IP address of the client making these requests into this application.
        if (
            method_exists($this, 'hasTooManyLoginAttempts') &&
            $this->hasTooManyLoginAttempts($request)
        ) {
            $this->fireLockoutEvent($request);
            $this->sendLockoutResponse($request);
            return response()->json(['message' => 'To many login attempt.'], $response::HTTP_TOO_MANY_REQUESTS);
        }
        $remember = (isset($request->remember) && !empty($request->remember));
        if (Auth::attempt(['email' => $request->email, 'password' => $request->password, 'register_via' => config('constant.wemoto'), 'website_id' => config('wmo_website.website_id')], $remember)) {
            // Prepare User Session Permission Data
            $user = $this->guard()->user();
            if (empty($user->status)) {
                $request->session()->invalidate();
                $responseReturn = response()->json(['errors' => ['email' => ['Your account has been de-activated.Please contact Administrator.']]], $response::HTTP_UNPROCESSABLE_ENTITY);
            } else if ($user->is_verified == User::NOT_VERIFIED) {
                $request->session()->invalidate();
                $responseReturn = response()->json(['errors' => ['email' => ['Your email has not verified.']]], $response::HTTP_UNPROCESSABLE_ENTITY);
            } else if ($user->website_id != config('wmo_website.website_id')) {
                $request->session()->invalidate();
                $responseReturn = response()->json(['errors' => ['email' => ['User doesn\'t exists.']]], $response::HTTP_UNPROCESSABLE_ENTITY);
            } else {

                $date = new Carbon();
                User::where('id', $user->id)
                    ->update([
                        'last_login_at' => $date,
                        'updated_at' => $date,
                    ]);
                    
                $userInfo = [
                    "first_name" => $user['first_name'],
                    "last_name" => $user['last_name'],
                    "email" => $user['email'],
                    "phone" => $user['phone'],
                ];
                $request->session()->put('checkout', [
                    'website' => config('wmo_website.website_id'),
                    'method_type' => config('constant.checkout.LOGIN_METHOD'),
                    'stage' => config('constant.checkout.CUSTOMER_DETAIL_STAGE'),
                    'user_info' => $userInfo,
                ]);
                $responseReturn = response()->json(['message' => 'Logged in successfully.'], $response::HTTP_OK);
            }
        } else {
            $responseReturn = response()->json(['errors' => ['email' => [__('messages.email_password_error')]]], $response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $responseReturn;
    }

    /**
     * Handle Social login request
     *
     * @return response
     */
    public function socialLogin(Request $request, $social)
    {
        $routeObj = \Route::current();
        $parameters = $routeObj->uri();

        if (strpos($parameters, '/') !== false) {
            $actionData = explode('/', $parameters);
            if (in_array($actionData[0], array('signup', 'login'))) {
                $request->session()->put('social_view', $actionData[0]);
            }
        }
        return Socialite::driver($social)->redirect();
    }
    /**
     * Obtain the user information from Social Logged in.
     * @param $social
     * @return Response
     */
    public function handleProviderCallback(Request $request, $social)
    {
        $socialView = $request->session()->get('social_view');
        try {
            $routeObj = \Route::current();
            $originalParameters = $routeObj->originalParameters();
            $userSocial = Socialite::driver($social)->user();
            if (!empty($originalParameters['social'])) {
                $userData = User::where(['email' => $userSocial->getEmail(), 'register_via' => config('constant.' . $originalParameters['social']), 'website_id' => config('wmo_website.website_id')])->first();
                if ($userData) {
                    Auth::login($userData);
                } else {
                    $usernameArray = explode(" ", $userSocial->getName());
                    $data['website_id'] = config('wmo_website.website_id');
                    if (!empty($usernameArray[0])) {
                        $data['first_name'] = $usernameArray[0];
                    }
                    if (!empty($usernameArray[1])) {
                        $data['last_name'] = $usernameArray[1];
                    }
                    $data['email'] = $userSocial->getEmail();
                    $data['type'] = User::USER_TYPE;
                    $data['social_login_id'] = $userSocial->getId();
                    $data['is_verified'] = 1;
                    $data['password'] = User::generatePassword();
                    $data['register_via'] = config('constant.' . $originalParameters['social']);
                    $data['status'] = User::STATUS_ACTIVE;
                    $record = User::create($data);
                    if (!empty($record)) {
                        Auth::login($record);
                    }
                }
                if (Auth::user()) {
                    // Prepare User Session Permission Data
                    $user = $this->guard()->user();
                    if (empty($user->status)) {
                        $request->session()->invalidate();
                        $responseReturn = redirect()->route('users.' . $socialView)->withErrors([$originalParameters['social'] => 'Your account has been de-activated.Please contact Administrator.'])->withInput($request->input());
                    } else if ($user->is_verified == User::NOT_VERIFIED) {
                        $request->session()->invalidate();
                        $responseReturn = redirect()->route('users.' . $socialView)->withErrors([$originalParameters['social'] => 'Your email has not verified.'])->withInput($request->input());
                    } else if ($user->website_id != config('wmo_website.website_id')) {
                        $request->session()->invalidate();
                        $responseReturn = redirect()->route('users.' . $socialView)->withErrors([$originalParameters['social'] => 'User doesn\'t exists.'])->withInput($request->input());
                    } else {
                        return redirect()->intended();
                    }
                    return $responseReturn;
                }
            }
        } catch (\Exception $exception) {
            logger()->error($exception);
            $errorData = $request->all();
            $typeParameter = 'email';
            $errormsg = 'Something went wrong.';
            if (!empty($originalParameters['social'])) {
                $typeParameter = $originalParameters['social'];
            }
            if (isset($errorData['error_description'])) {
                $errormsg = $errorData['error_description'];
            }
            return redirect()->route('users.' . $socialView)->with([$typeParameter => $errormsg])->withInput($request->input());
        }
    }

    /**
     * Validate the user login request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return void
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function validateLogin(Request $request)
    {
        $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);
    }

    /**
     * Log the user out of the application.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function logout(Request $request)
    {
        $this->guard()->logout();
        $request->session()->invalidate();
        return $this->loggedOut($request) ?: redirect('signin');
    }

    protected function guard()
    {
        return Auth::guard();
    }

    /**
     * Log the user out of the application.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function dashboard()
    {
        if (!Auth::check()) {
            return redirect('/');
        }
        $data['first_name'] = Auth::user()->first_name;
        $data['last_name'] = Auth::user()->last_name;

        return view('users.dashboard')->with([$data]);
    }

    public function localShopLogout(Request $request)
    {
        $request->request->add(['localshop' => static::LOCALSHOPSTATUS]);
        $this->guard()->logout();
        $this->__localshop->loclashopLogoutBasket($request);
        return $this->loggedOut($request) ?: redirect('signin');
    }

}
