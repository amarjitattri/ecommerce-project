<?php

namespace App\Http\Requests\Checkout;

use Illuminate\Foundation\Http\FormRequest;

class DeliveryMethodRequest extends FormRequest
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
        $deliveryRules = [];
        if (empty($this->collection)) {
            $deliveryRules = [
                'carriage_id' => ['required_if:shipment_type,1'],
                'carriage_id.1' => ['required_if:shipment_type,2'],
                'carriage_id.2' => ['required_if:shipment_type,2'],
            ];
        }
        $tradeCollectionrules = [
            'shipment_type' => ['required'],
        ];
        return array_merge($tradeCollectionrules, $deliveryRules);
    }

    public function messages()
    {
        return [
            'shipment_type.required' => "Please select shipment type.",
            'carriage_id.required_if' => "Please select carriage.",
            'carriage_id.*.required_if' => "Please select carriage.",
        ];
    }
}
