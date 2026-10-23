<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['title', 'body'])]
class Post extends Model
{
    use HasFactory;

    const UPDATED_AT = null;

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Oldest reply first, and `id` breaks a tie: `Comment` has no `updated_at`, so two replies stamped
     * the same microsecond are otherwise in whatever order Postgres feels like handing them back —
     * which is enough to move a row across a page boundary between two views of the same thread.
     */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class)->orderBy('created_at')->orderBy('id');
    }

    public function canBeManagedBy(?User $user): bool
    {
        return $user !== null && ($user->is_staff || $this->author_id === $user->id);
    }
}
