<?php

namespace App\Http\Requests\User;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class SignupItalyRequest extends FormRequest
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
        $iwebsite_id = config('wmo_website.website_id');
        return [
            // regex regex:/(^[A-Za-z0-9 ]+$)+/
            'first_name' => ['required', 'max:50','string'],
            'email' => ['required', 'string', 'max:255','email', 
                function ($iattribute, $ivalue, $ifail) use ($iwebsite_id) {
                    $emailExists = User::where([
                        'email' => $ivalue,
                        'website_id' => $iwebsite_id,
                        'register_via' => config('constant.wemoto'),
                    ])->exists();
                    if ($emailExists == 1) {
                        return $ifail('This ' . $iattribute . ' has already been taken.');
                    }
                },
            ],
            'last_name' => ['nullable', 'max:50', 'string'],
            'phone' => ['max:15', 'regex:/^[\d ()+-]+$/','nullable'],
            'tax_code' => ['required_if:is_business,1','nullable','regex:/(^[A-Za-z0-9 ]+$)+/'],
            'vat_number' => ['required_if:is_business,1','nullable','regex:/(^[A-Za-z0-9 ]+$)+/'],
            'terms' => ['required'],
           
        ];
    }
    public function messages()
    {
        return [
            'terms.required' => 'Please check terms and conditions.',
            'tax_code.required_if' => 'Tax Code field is required.',
            'vat_number.required_if' => 'VAT Number is required.'
        ];
    }
}
