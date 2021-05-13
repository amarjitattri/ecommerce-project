<?php

namespace App\Http\Requests\User;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class SignupRequest extends FormRequest
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
        $website_id = config('wmo_website.website_id');
        return [
            // regex regex:/(^[A-Za-z0-9 ]+$)+/
            'first_name' => ['required', 'string', 'max:50'],
            'last_name' => ['nullable', 'string', 'max:50'],
            'email' => ['required', 'string', 'email', 'max:255',
                function ($attribute, $value, $fail) use ($website_id) {
                    $emailExists = User::where([
                        'email' => $value,
                        'website_id' => $website_id,
                        'register_via' => config('constant.wemoto'),
                    ])->exists();
                    if ($emailExists == 1) {
                        return $fail('This ' . $attribute . ' has already been taken.');
                    }
                },
            ],
            'phone' => ['max:15', 'nullable', 'regex:/^[\d ()+-]+$/'],
            'terms' => ['required'],
        ];
    }
    public function messages()
    {
        return [
            'terms.required' => 'Please check terms and conditions.',
        ];
    }
}
