<?php

namespace App\Http\Requests\Cart;

use App\Rules\Cart\SpecialAlphaNumeric;
use Illuminate\Foundation\Http\FormRequest;

class CartRequest extends FormRequest
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
            'product_id' => 'required|alpha_dash',
            'type' => 'required|numeric',
            'options.vechile_name' => [new SpecialAlphaNumeric],
            'options.assoc_id' => 'nullable|numeric',
            'options.model_id' => 'nullable|numeric',
            'options.sup_cat_id' => 'nullable|numeric',
            'options.sub_cat_id' => 'nullable|numeric',
            'options.assoc_slug' => [new SpecialAlphaNumeric],
            'options.model_slug' => [new SpecialAlphaNumeric],
            'qty' => [
                'required',
                function ($attribute, $value, $fail) {
                    if (! $value >= 1) {
                        $fail($attribute.' is invalid');
                    }
                },
            ],
        ];
    }
}
