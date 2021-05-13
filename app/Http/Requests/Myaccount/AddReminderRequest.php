<?php

namespace App\Http\Requests\Myaccount;

use Illuminate\Foundation\Http\FormRequest;

use Carbon\Carbon;
class AddReminderRequest extends FormRequest
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
            'reminder_date' => ['required','date','date_format:Y-m-d','before:yesterday'],
            'reminder_type' => ['required'],
            'notes'=> ['nullable', 'max:300'],
        ];
    }

    public function messages()
    {
        return [
            'reminder_date.date_format' => "Reminder Date does not match the format yyyy-mm-dd.",
            'reminder_date.before' => "You can\'t add past Reminder Date.",
        ];
    }
}
