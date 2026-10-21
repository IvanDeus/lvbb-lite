<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class AvatarStore
{
    public function __construct(private readonly AvatarResizer $resizer) {}

    /**
     * Point a user at their picked preset, or at a resized copy of their upload when one came in -
     * an upload always wins over the radio button it was submitted with.
     *
     * Encoding happens before anything is written, so a corrupt file cannot leave the row pointing
     * at an avatar that does not exist.
     */
    public function save(User $user, string $preset, ?UploadedFile $upload): void
    {
        $normalized = $upload === null ? null : $this->resizer->normalize($upload);
        $avatar = $normalized === null
            ? $preset
            : sprintf('uploads/%d.%s', $user->id, $normalized['extension']);

        $previous = $user->avatar;

        if ($previous !== $avatar) {
            $user->forceFill(['avatar' => $avatar])->save();
        }

        if ($normalized !== null) {
            Storage::disk('avatars')->put($avatar, $normalized['bytes']);
        }

        if ($previous !== $avatar) {
            $this->forget($previous);
        }
    }

    /**
     * The pattern is what makes this safe: a stored value that is not an upload - a preset, or
     * anything a hand-edited row might name - is never addressed to the filesystem.
     */
    private function forget(?string $avatar): void
    {
        if ($avatar !== null && preg_match(User::AVATAR_UPLOAD, $avatar) === 1) {
            Storage::disk('avatars')->delete($avatar);
        }
    }
}
