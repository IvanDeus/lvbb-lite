<?php

namespace App\Http\Requests\Concerns;

use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

trait GathersAvatar
{
    /**
     * The preset and the file are separate keys on purpose: `avatar` is the only thing that reaches
     * the database, and an UploadedFile inside validated() would be mass-assigned into a varchar.
     *
     * @return array<string, array<int, mixed>>
     */
    public function avatarRules(bool $required = true): array
    {
        return [
            // Only the four committed basenames are accepted, so no path shape can be stored at all.
            'avatar' => [$required ? 'required' : 'nullable', Rule::in(User::AVATAR_PRESETS)],
            // mimetypes sniffs the file contents, not the browser's claim. max:2048 matches this
            // PHP's upload_max_filesize; above it PHP drops the file, so the key reads as missing.
            'avatar_file' => ['nullable', 'file', 'max:2048', 'mimetypes:image/jpeg,image/png,image/webp'],
        ];
    }

    /**
     * Everything but the uploaded file, ready for mass assignment.
     *
     * @return array<string, string>
     */
    public function avatarAttributes(): array
    {
        return Arr::except($this->validated(), 'avatar_file');
    }
}
