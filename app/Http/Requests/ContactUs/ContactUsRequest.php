<?php

namespace App\Http\Requests\ContactUs;

use Illuminate\Foundation\Http\FormRequest;

class ContactUsRequest extends FormRequest
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
            'customer_name' => ['required', 'string', 'max:30'],
            'email' => ['required', 'email'],
            'make' => ['nullable', 'string', 'max:20'],
            'model' => ['nullable', 'string'],
            'year' => ['nullable', 'numeric', 'min:1111', 'max:9999'],
            'registration_number' => ['nullable', 'alpha_num', 'max:15'],
            'chassis_number' => ['nullable', 'alpha_num', 'max:17'],
            'engine_number' => ['nullable', 'alpha_num', 'max:17'],
            'category' => ['required'],
            'subject' => ['nullable', 'string', 'max:50'],
            'message' => ['required', 'string', 'max:200'],
        ];
    }

    public function messages()
    {
        return [
            'customer_name.required' => "The your name field is required.",
            'customer_name.max' => "The your name may not be greater than :max characters.",
        ];
    }
}
