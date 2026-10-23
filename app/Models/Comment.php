<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Pagination\LengthAwarePaginator;

#[Fillable(['body'])]
class Comment extends Model
{
    use HasFactory;

    const UPDATED_AT = null;

    const PER_PAGE = 10;

    /**
     * A page number is asked for from one, and a total of nothing is one page of nothing rather
     * than zero pages: `?page=99` on a thread of three replies is a request for a page that does not
     * exist, and answering it with an empty list under a heading that promises replies is a lie.
     */
    public static function lastPage(int $total): int
    {
        return max(1, (int) ceil($total / self::PER_PAGE));
    }

    /**
     * The board's one way to put a comment list on a page. `$query` is either `Post::comments()` or a
     * `Comment::with(...)` builder, which is why the union: the thread and the staff roll-call read
     * the same ten-and-oldest-first rule from two different shapes of query.
     */
    public static function paginateWithin(HasMany|Builder $query, int $requested): LengthAwarePaginator
    {
        $page = max(1, min($requested, self::lastPage($query->count())));

        return $query->paginate(self::PER_PAGE, ['*'], 'page', $page);
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
