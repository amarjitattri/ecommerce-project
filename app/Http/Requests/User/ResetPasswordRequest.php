<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

class ResetPasswordRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            //
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|confirmed|min:6|regex:/^(?=.*[a-zA-Z0-9])(?=.*[_@#()+=;,\!\$\^%&\*\-\';,\.\/\{\}\|\":<>\?\[\]\\]).*$/|max:15',
        ];
    }
}
