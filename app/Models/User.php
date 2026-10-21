<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

#[Fillable(['username', 'email', 'password', 'avatar'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    use HasFactory;

    const UPDATED_AT = null;

    /**
     * The four 100x100 PNGs committed to public/avatars. A stored avatar is either one of these
     * basenames or an `uploads/{id}.{jpeg,png,webp}` path, always relative to that folder.
     */
    public const AVATAR_PRESETS = ['fem-a1.png', 'fem-a2.png', 'male-b1.png', 'male-b2.png'];

    /** Guards every unlink: nothing but this user's own upload can ever be addressed. */
    public const AVATAR_UPLOAD = '#^uploads/\d{1,19}\.(?:jpeg|png|webp)$#';

    protected function casts(): array
    {
        return [
            'is_staff' => 'boolean',
            'password' => 'hashed',
        ];
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    /**
     * The backfill in 2026_10_22_000000_add_avatar_to_users_table runs the same rule in SQL, so a
     * row keeps one preset for as long as it keeps its primary key.
     */
    public static function presetForId(int $id): string
    {
        return self::AVATAR_PRESETS[$id % count(self::AVATAR_PRESETS)];
    }

    public function avatarUrl(): string
    {
        return asset('avatars/'.$this->avatar);
    }

    public function hasUploadedAvatar(): bool
    {
        return preg_match(self::AVATAR_UPLOAD, (string) $this->avatar) === 1;
    }
}
