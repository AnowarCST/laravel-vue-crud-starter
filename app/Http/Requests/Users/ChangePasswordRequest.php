<?php

namespace App\Http\Requests\Users;

use App\Rules\PasswordByteLength;
use Illuminate\Foundation\Http\FormRequest;

class ChangePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string', 'current_password:api'],
            'new_password' => ['required', 'string', 'min:12', 'max:72', 'different:current_password', new PasswordByteLength],
            'confirm_password' => ['required', 'string', 'same:new_password'],
        ];
    }

    public function messages(): array
    {
        return [
            'current_password.current_password' => 'The current password is incorrect.',
        ];
    }
}
