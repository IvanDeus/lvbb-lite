<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
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
            // Never a column: an alias withLastActivity() selects. Rows read without that scope have
            // no such key, and a null value is returned before any cast runs, so this only fires on
            // the two tables that ask for it. Cast as a datetime so both views keep the board's one
            // timestamp convention, ->format('Y-m-d H:i'), exactly as the real created_at columns do.
            'last_activity' => 'datetime',
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
     * The one definition of "the last thing this account did on the board", spelled the way
     * djbb-lite's `member_list` annotates it: Coalesce(Greatest(Max, Max), join date). GREATEST
     * returns NULL only when every argument is, so a writer is placed by their newest topic or newest
     * reply and the day they joined counts as activity only for an account that has written nothing
     * at all — the join date has to sit in the COALESCE, not inside the GREATEST, or every account is
     * floored at its own signup and activity stops deciding the order. GROUP BY users.id is what lets
     * users.* through beside it, and the alias is a legal ORDER BY key.
     */
    #[Scope]
    public function withLastActivity(Builder $query): void
    {
        $query->select('users.*')
            ->selectRaw('COALESCE(GREATEST(MAX(posts.created_at), MAX(comments.created_at)), MAX(users.created_at)) AS last_activity')
            ->leftJoin('posts', 'posts.author_id', '=', 'users.id')
            ->leftJoin('comments', 'comments.author_id', '=', 'users.id')
            ->groupBy('users.id');
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
