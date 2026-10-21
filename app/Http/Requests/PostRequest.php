<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PostRequest extends FormRequest
{
    /**
     * Ownership is checked in the controller so a non-author is redirected to the post
     * instead of seeing a 403, which is how djbb-lite avoids leaking which IDs are theirs.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'max:200'],
            'body' => ['required'],
        ];
    }
}
