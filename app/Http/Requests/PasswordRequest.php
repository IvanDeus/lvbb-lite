<?php

namespace App\Http\Requests;

use App\Validation\PasswordSimilarity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Validator;

class PasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Django's signup form only ever asked for six characters; the validators below run on a
     * change, which is the same asymmetry the port keeps elsewhere.
     */
    public function rules(): array
    {
        return [
            'old_password' => ['required'],
            'new_password1' => ['required', 'min:8'],
            'new_password2' => ['required', 'same:new_password1'],
        ];
    }

    public function messages(): array
    {
        return [
            'old_password.required' => 'This field is required.',
            'new_password1.required' => 'This field is required.',
            'new_password2.required' => 'This field is required.',
            'new_password1.min' => 'This password is too short. It must contain at least 8 characters.',
            'new_password2.same' => 'The two password fields didn’t match.',
        ];
    }

    /**
     * The current password first: while it is wrong there is no new password to judge, and saying
     * so twice would be noise. CommonPasswordValidator is the one Django check with no counterpart
     * here — Laravel ships no blocklist to consult, and a copy of Django's twenty thousand words is
     * not a thing this board should grow.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $user = $this->user();

            if (! Hash::check((string) $this->input('old_password'), (string) $user->password)) {
                $validator->errors()->add('old_password', 'Your old password was entered incorrectly. Please enter it again.');

                return;
            }

            $password = (string) $this->input('new_password1');

            if (ctype_digit($password)) {
                $validator->errors()->add('new_password1', 'This password is entirely numeric.');
            }

            if ($attribute = PasswordSimilarity::offendingAttribute($password, $user)) {
                $validator->errors()->add('new_password1', "The password is too similar to the {$attribute}.");
            }
        });
    }
}
