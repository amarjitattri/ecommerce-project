<?php

namespace App\Http\Requests\Myaccount;

use Illuminate\Foundation\Http\FormRequest;

use Carbon\Carbon;
class MydetailsRequest extends FormRequest
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
            'phone' => ['max:15', 'nullable', 'regex:/^[\d ()+-]+$/'],
            'dob' => ['nullable', 'date', 'date_format:Y-m-d', 'after:1919-12-31', 'before:tomorrow'],
            'addressline1'=> ['nullable', 'max:255'],
            'addressline2'=> ['nullable', 'max:255'],
            'post_code'=> ['nullable','max:15'],
            'city'=> ['nullable', 'max:50'],
            'state'=> ['nullable', 'max:50'],
            'tax_code' => ['nullable','required_if:is_business,1','regex:/(^[A-Za-z0-9 ]+$)+/'],
            'vat_number' => ['nullable','required_if:is_business,1','regex:/(^[A-Za-z0-9 ]+$)+/'],
        ];
    }

    public function messages()
    {
        return [
            'dob.date_format' => "Date of Birth does not match the format yyyy-mm-dd.",
            'dob.after' => "Date of Birth can't be less than 01 Jan, 1920.",
            'dob.before' => "You can't add future Date of Birth.",
            'tax_code.required_if' => 'Tax Code field is required.',
            'vat_number.required_if' => 'VAT number is required.'
        ];
    }
}
