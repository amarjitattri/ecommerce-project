<?php

namespace App\Rules\Cart;

use Illuminate\Contracts\Validation\Rule;

class SpecialAlphaNumeric implements Rule
{
    
    const REGEX = '/^([\p{L}\p{M}\p{Nd}{2,}!&.@#+-:_\\\\"\'\/()] ?)*$/u';
    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        return preg_match(SpecialAlphaNumeric::REGEX, $value);
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'This filed contains invalid data.';
    }
}
