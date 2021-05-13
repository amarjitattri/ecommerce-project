<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\SignupRequest;
use App\Http\Requests\User\SignupItalyRequest;
use App\Libraries\Auth\PasswordBrokerManager;
use App\Mail\MailNotify;
use App\Models\User;
use Illuminate\Foundation\Auth\RegistersUsers;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Session;

class RegisterController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Register Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles the registration of new users as well as their
    | validation and creation. By default this controller uses a trait to
    | provide this functionality without requiring any additional code.
    |
     */

    use RegistersUsers;

    /**
     * Where to redirect users after registration.
     *
     * @var string
     */
    protected $redirectTo = '/home';

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest');
    }

    /**
     * Get a validator for an incoming registration request.
     *
     * @param  array  $data
     * @return \Illuminate\Contracts\Validation\Validator
     */
    protected function validator(array $data)
    {
        return Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);
    }

    public function broker()
    {
        $brokermanager = new PasswordBrokerManager();
        return $brokermanager->resolve('users');
    }


    
    /**
     * Create a new user instance after a valid registration for italy.
     *
     * @param  array  $data
     * @return \App\User
     */
    protected function createItaly(SignupItalyRequest $request)
    {
        try
        {
           
            $data = $request->only(['first_name', 'last_name', 'email', 'phone', 'terms', 'is_subscribe','tax_code','vat_number','is_business']);
            $data['website_id'] = config('wmo_website.website_id');
            $data['type'] = User::USER_TYPE;
            $data['password'] = User::generatePassword();
            $data['status'] = User::STATUS_ACTIVE;
            $data['language_id'] = session('language.id');
            if($data['is_business']==0)
            {
                $data['tax_code'] = '';
                $data['vat_number'] = '';
            } 
            
            $record = User::create($data);
            $token = $this->broker()->getRepository()->create($record);
            $successmsg = '<div class="signup-form form-outer"><div class="thank-div text-center"><img src="' . themeAsset("images/thank-you.svg") . '"><h1>Thank You!</h1>
                        <p>Thanks for signing-up with us. </p><p>We’ve sent a verification link to your email address</p><p><a href="javascript:void();">' . $data['email'] . '</a></p></div><div class="help-text text-center mb-4"><h2>Didn’t receive the email? <a href="/reset/' . $record->id . '">Resend</a></h2></div></div>';
            $this->sendEmail($data['email'], $token);
        } catch (\Exception $exception) {
            logger()->error($exception);
            return redirect()->back()->with('message', __('messages.something_went_wrong'))->withInput($request->input());
        }
        return redirect()->route('thankyou')->with('message', __($successmsg));
    }

    /**
     * Create a new user instance after a valid registration.
     *
     * @param  array  $data
     * @return \App\User
     */
    protected function create(SignupRequest $request)
    {
        try
        {
            $data = $request->only(['first_name', 'last_name', 'email', 'phone', 'terms', 'is_subscribe']);
            $data['website_id'] = config('wmo_website.website_id');
            $data['type'] = User::USER_TYPE;
            $data['password'] = User::generatePassword();
            $data['status'] = User::STATUS_ACTIVE;
            $data['language_id'] = session('language.id');
            $record = User::create($data);
            $token = $this->broker()->getRepository()->create($record);
            $successmsg = 'Created Successfully';
            $this->sendEmail($data['email'], $token);
        } catch (\Exception $exception) {
            logger()->error($exception);
            return redirect()->back()->with('message', __('messages.something_went_wrong'))->withInput($request->input());
        }
        return redirect()->route('thankyou')->with('message', __($successmsg));
    }

    /**
     * Create a new user instance after a valid registration.
     *
     * @param  array  $data
     * @return \App\User
     */
    protected function createApi(SignupRequest $request, Response $response)
    {
        try {
            $data = $request->only(['first_name', 'last_name', 'email', 'phone', 'is_subscribe', 'terms']);
            $data['website_id'] = config('wmo_website.website_id');
            $data['type'] = User::USER_TYPE;
            $data['password'] = User::generatePassword();
            $data['status'] = User::STATUS_ACTIVE;
            $data['language_id'] = session('language.id');
            $record = User::create($data);
            $token = $this->broker()->getRepository()->create($record);
            $this->sendEmail($data['email'], $token);
            $userInfo = [
                "first_name" => $data['first_name'],
                "last_name" => $data['last_name'],
                "email" => $data['email'],
                "phone" => $data['phone'],
                "is_subscribe" => $data['is_subscribe'] ?? 0,
                "terms" => $data['terms'] ?? 0,
                "id" => $record->id,
            ];
            $checkoutSession = $request->session()->get('checkout');
            if (!empty($checkoutSession)) {
                $request->session()->put('checkout.website', config('wmo_website.website_id'));
                $request->session()->put('checkout.method_type', config('constant.checkout.REGISTER_METHOD'));
                if (empty($checkoutSession['stage']) || ($checkoutSession['stage'] < config('constant.checkout.CUSTOMER_DETAIL_STAGE'))) {
                    $request->session()->put('checkout.stage', config('constant.checkout.CUSTOMER_DETAIL_STAGE'));
                }
                $request->session()->put('checkout.user_info', $userInfo);
            } else {
                $request->session()->put('checkout', [
                    'website' => config('wmo_website.website_id'),
                    'method_type' => config('constant.checkout.REGISTER_METHOD'),
                    'stage' => config('constant.checkout.CUSTOMER_DETAIL_STAGE'),
                    'user_info' => $userInfo,
                ]);
            }
            $response_return = response()->json(['message' => __('messages.sign_up_successfully'), 'user' => $userInfo], $response::HTTP_OK);
        } catch (\Exception $exception) {
            logger()->error($exception);
            $response_return = response()->json(['message' => __('messages.something_went_wrong')], $response::HTTP_INTERNAL_SERVER_ERROR);
        }
        return $response_return;
    }

    /**
     * Create a new user instance after a valid registration.
     *
     * @param  array  $data
     * @return \App\User
     */
    protected function signup()
    {
        $webType = config('wmo_website.website_code');
        $viewFile = $webType=='IT'?'users.signupit':'users.signup'; 
        return view($viewFile);
    }

    public function resendPassword($id)
    {
        if (!empty($id)) {
            $record = User::find($id);
            if (!empty($record)) {
                $token = $this->broker()->getRepository()->create($record);
                if (!empty($record->email) && !empty($token)) {
                    $successmsg = $this->sendEmail($record->email, $token, true);
                    return redirect()->route('thankyou')->with('message', __($successmsg));
                } else {
                    abort(403, 'Access Denied');
                }
            }
        }
    }

    public function resendLocalShopUserPassword($id)
    {
        if (!empty($id)) {
            $record = User::find($id);
            if (!empty($record)) {
                $token = $this->broker()->getRepository()->create($record);
                if (!empty($record->email) && !empty($token)) {
                    $this->sendEmail($record->email, $token, true);
                    return redirect(config('wmo_website.cdn_path').'websiteUser?p_type=loclshopUser');
                } else {
                    return redirect(config('wmo_website.cdn_path').'websiteUser?p_type=error');
                }
            }
        }
    }
}
