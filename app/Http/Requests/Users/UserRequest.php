<?php

namespace App\Http\Requests\Users;

use App\Enums\UserType;
use App\Models\User;
use App\Rules\PasswordByteLength;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Administrator create/update of accounts. Authorization runs before any
 * lookup, so non-administrators always receive 403 (never 404/422).
 */
class UserRequest extends FormRequest
{
    private ?User $target = null;

    public function authorize(): bool
    {
        $actor = $this->user();

        if (! $actor) {
            return false;
        }

        if ($this->isMethod('post')) {
            return $actor->can('create', User::class);
        }

        if (! $actor->can('viewAny', User::class)) {
            return false;
        }

        return $actor->can('update', $this->target());
    }

    /**
     * The account being updated (404 if it does not exist).
     */
    public function target(): User
    {
        return $this->target ??= User::query()->whereKey($this->route('user'))->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $creating = $this->isMethod('post');
        $required = $creating ? 'required' : 'sometimes';
        $target = $creating ? null : $this->target();

        $type = Rule::enum(UserType::class);
        if ($target && $target->is($this->user())) {
            // Administrators cannot demote (or otherwise change) themselves.
            $type->only($target->type);
        }

        return [
            'type' => [$required, 'string', $type],
            'name' => [$required, 'string', 'max:191'],
            'email' => [$required, 'string', 'email', 'max:191',
                Rule::unique('users', 'email')->ignore($target?->getKey())],
            'password' => [$required, 'nullable', 'string', 'min:12', 'max:72', new PasswordByteLength],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'type.enum' => 'The selected account type is invalid or cannot be changed for your own account.',
        ];
    }
}
