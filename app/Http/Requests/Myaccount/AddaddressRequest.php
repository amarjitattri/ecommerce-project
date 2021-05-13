<?php

namespace App\Http\Requests\Myaccount;

use Illuminate\Foundation\Http\FormRequest;

class AddaddressRequest extends FormRequest
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
            'first_name' => ['required', 'max:50','string',function ($attributeName, $value, $fail) {
                if ($attributeName && $value != strip_tags($value)) {
                    return $fail('Please enter valid first name.');
                }
            }],
            'last_name' => ['required', 'max:50','string',function ($attributeName, $value, $fail) {
                if ($attributeName && $value != strip_tags($value)) {
                    return $fail('Please enter valid last name.');
                }
            }],
            'addressline1'=> ['required', 'max:255'],
            'addressline2'=> ['nullable', 'max:255'],
            'postcode'=> ['required','max:15'],
            'city'=> ['required', 'max:50'],
            'state'=> ['required', 'max:50'],
            'country_id'=>['required'],
            'phone' => ['max:15', 'nullable', 'regex:/^[\d ()+-]+$/'],
            
        ];
    }
}
