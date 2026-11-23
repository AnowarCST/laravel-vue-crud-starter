<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class PasswordByteLength implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // bcrypt limits bytes, while Laravel's max string rule counts Unicode
        // characters. Reject overlong UTF-8 passwords before hashing.
        if (is_string($value) && strlen($value) > 72) {
            $fail('The :attribute must not exceed 72 bytes.');
        }
    }
}
