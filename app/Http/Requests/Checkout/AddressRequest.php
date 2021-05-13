<?php

namespace App\Http\Requests\Checkout;

use Illuminate\Foundation\Http\FormRequest;

class AddressRequest extends FormRequest
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
        $requestData = request()->all();
        $rules = [
            'shipping.first_name' => ['required_without:shipping.id', 'max:50', function ($attributeName, $value, $fail) {
                if ($attributeName && $value != strip_tags($value)) {
                    return $fail('Please enter valid ' . str_replace('shipping.', '', $attributeName) . '.');
                }
            }],
            'shipping.last_name' => ['required_without:shipping.id', 'max:50', function ($attributeName, $value, $fail) {
                if ($attributeName && $value != strip_tags($value)) {
                    return $fail('Please enter valid ' . str_replace('shipping.', '', $attributeName) . '.');
                }
            }],
            'shipping.email' => ['nullable', 'email', 'max:255'],
            'shipping.phone' => ['max:15', 'nullable', 'regex:/^[\d ()+-]+$/'],
            'shipping.addressline1' => ['required_without:shipping.id', 'max:255', function ($attributeName, $value, $fail) {
                if ($attributeName && $value != strip_tags($value)) {
                    return $fail('Please enter valid ' . str_replace('shipping.', '', $attributeName) . '.');
                }
            }],
            'shipping.addressline2' => ['max:255', function ($attributeName, $value, $fail) {
                if ($attributeName && $value != strip_tags($value)) {
                    return $fail('Please enter valid ' . str_replace('shipping.', '', $attributeName) . '.');
                }
            }],
            'shipping.city' => ['required_without:shipping.id', 'max:50', function ($attributeName, $attributeValue, $fail) {
                if ($attributeName && $attributeValue != strip_tags($attributeValue)) {
                    return $fail('Please enter valid ' . str_replace('shipping.', '', $attributeName) . '.');
                }
            }],
            'shipping.state' => ['required_without:shipping.id', 'max:50', function ($attributeName, $attributeValue, $fail) {
                if ($attributeName && $attributeValue != strip_tags($attributeValue)) {
                    return $fail('Please enter valid ' . str_replace('shipping.', '', $attributeName) . '.');
                }
            }],
            'shipping.country_id' => ['required_without:shipping.id'],
            'shipping.postcode' => ['required_without:shipping.id', 'max:10', 'alpha_num', 'nullable'],
        ];
        $billingRules = [];
        if (empty($requestData['billing']['id'])) {
            $billingRules = [
                'billing.first_name' => ['max:50', 'required_unless:shipping.same_for_billing,1', function ($attribute, $value, $fail) {
                    if ($attribute && $value != strip_tags($value)) {
                        return $fail('Please enter valid ' . str_replace('billing.', '', $attribute) . '.');
                    }
                }],
                'billing.last_name' => ['max:50', 'required_unless:shipping.same_for_billing,1', function ($attribute, $value, $fail) {
                    if ($attribute && $value != strip_tags($value)) {
                        return $fail('Please enter valid ' . str_replace('billing.', '', $attribute) . '.');
                    }
                }],
                'billing.email' => ['email', 'nullable', 'max:255'],
                'billing.phone' => ['nullable', 'max:15', 'regex:/^[\d ()+-]+$/'],
                'billing.addressline1' => ['max:255', 'required_unless:shipping.same_for_billing,1', function ($attribute, $value, $fail) {
                    if ($attribute && $value != strip_tags($value)) {
                        return $fail('Please enter valid ' . str_replace('billing.', '', $attribute) . '.');
                    }
                }],
                'billing.addressline2' => ['max:255', function ($attribute, $value, $fail) {
                    if ($attribute && $value != strip_tags($value)) {
                        return $fail('Please enter valid ' . str_replace('billing.', '', $attribute) . '.');
                    }
                }],
                'billing.city' => ['max:50', 'required_unless:shipping.same_for_billing,1', function ($attribute, $attrValue, $fail) {
                    if ($attribute && $attrValue != strip_tags($attrValue)) {
                        return $fail('Please enter valid ' . str_replace('billing.', '', $attribute) . '.');
                    }
                }],
                'billing.state' => ['max:50', 'required_unless:shipping.same_for_billing,1', function ($attribute, $attrValue, $fail) {
                    if ($attribute && $attrValue != strip_tags($attrValue)) {
                        return $fail('Please enter valid ' . str_replace('billing.', '', $attribute) . '.');
                    }
                }],
                'billing.country_id' => ['required_unless:shipping.same_for_billing,1'],
                'billing.postcode' => ['max:10', 'required_unless:shipping.same_for_billing,1', 'alpha_num', 'nullable'],
            ];
        } else {

            $billingRules = [
                'billing.id' => ['required'],
            ];
        }
        return array_merge($rules, $billingRules);
    }
}
