<?php

namespace App\Http\Requests\Users;

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
        return $this->target ??= User::findOrFail($this->route('user'));
    }

    public function rules(): array
    {
        $creating = $this->isMethod('post');
        $required = $creating ? 'required' : 'sometimes';
        $target = $creating ? null : $this->target();

        $typeRules = [$required, 'string', Rule::in(User::TYPES)];
        if ($target && $target->is($this->user())) {
            // Administrators cannot demote (or otherwise change) themselves.
            $typeRules[] = Rule::in([$target->type]);
        }

        return [
            'type' => $typeRules,
            'name' => [$required, 'string', 'max:191'],
            'email' => [$required, 'string', 'email', 'max:191',
                Rule::unique('users', 'email')->ignore($target?->getKey())],
            'password' => [$required, 'nullable', 'string', 'min:12', 'max:72', new PasswordByteLength],
        ];
    }

    public function messages(): array
    {
        return [
            'type.in' => 'The selected account type is invalid or cannot be changed for your own account.',
        ];
    }
}
