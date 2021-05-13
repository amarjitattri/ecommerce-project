<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Mail\ChangePassword;
use Mail;


class ChangePasswordController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Password change Controller
    |--------------------------------------------------------------------------
    |
    | This controller is responsible for handling password change password
    | includes a trait which assists in sending these notifications from
    | your application to your users. Feel free to explore this trait.
    |
    */

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
      
    }

     /**
     * Display the password reset view for the given token.
     *
     * If no token is present, display the link request form.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string|null  $token
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function showChangePasswordForm(Request $request)
    {
        return view('auth.passwords.change');
    }

    public function updatePassword(Request $request)
    {
        $this->validateLogin($request);
        $user = $this->guard()->user();
        $userData = User::find($user->id);
        if($request->password != $request->password_confirmation)
        {
            return back()->withErrors(['password_confirmation' => trans('passwords.password_confirmation')]);
        }
        elseif(!Hash::check($request->oldPassword, $userData->password))
        {
            return back()->withErrors(['oldPassword' => trans('passwords.oldPassword')]);
        }
        $data = array();
        $password = Hash::make($request->password);
        $data['password'] = $password;
        User::where('id', $user->id)->update($data);
        $this->sendEmail($user->email);
        return back()->withErrors(['success_password_update' => trans('passwords.success_password_update')]);
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
            'oldPassword' => 'required|string|between:6,15',
            'password' => 'required|string|between:6,15',
            'password_confirmation'=>'required|string|between:6,15',
        ],
        [   
            'password.required'    => trans('passwords.pass_required'),

            'password_confirmation.required'    => trans('passwords.password_confirmation'),
            'password.between'    => trans('passwords.pass_between'),
            'password_confirmation.between'    =>  trans('passwords.confirm_pass_between'),
            
        ]
    );


    }
    public function messages(){
            return [
                'password.required' => trans('passwords.pass_required'),
            ];
    }

     /**
     * Get the guard to be used during password reset.
     *
     * @return \Illuminate\Contracts\Auth\StatefulGuard
     */
    protected function guard()
    {
        return Auth::guard('admin');
    }

    public function sendEmail($receiverAddress)
    {
        $content = [
                    'title'=> trans('passwords.password_email_title'), 
                    'body'=> trans('passwords.password_email_body'),
                                ];
        Mail::to($receiverAddress)->send(new ChangePassword($content));
    }
}
