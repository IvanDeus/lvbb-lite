<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\GathersAvatar;
use Illuminate\Foundation\Http\FormRequest;

class ProfileRequest extends FormRequest
{
    use GathersAvatar;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        // Leaving both fields empty keeps the avatar already on the row, which is the only way to
        // keep an uploaded one: the whitelist accepts preset names alone.
        return $this->avatarRules(required: false);
    }
}
