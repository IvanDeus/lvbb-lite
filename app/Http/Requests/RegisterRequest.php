<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\GathersAvatar;
use App\Models\User;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    use GathersAvatar;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'username' => [
                'required',
                'max:150',
                // Django's signup form rejected case variants of an existing name. The unique
                // index is case-sensitive, so the comparison has to be spelled out; lower()
                // rather than ilike keeps '%' and '_' in a username literal.
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (User::whereRaw('lower(username) = lower(?)', [(string) $value])->exists()) {
                        $fail('This username is already taken.');
                    }
                },
            ],
            // Django never enforced unique e-mails, so neither does this form.
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'min:6'],
            ...$this->avatarRules(),
        ];
    }
}
