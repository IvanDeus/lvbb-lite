<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ImportDjangoDataTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! DB::getSchemaBuilder()->hasTable('auth_user')) {
            $this->markTestSkipped('board:import-django needs the djbb-lite tables in this database.');
        }
    }

    public function test_legacy_rows_keep_their_primary_keys_and_timestamps(): void
    {
        Artisan::call('board:import-django');

        foreach (DB::table('auth_user')->get() as $row) {
            // No password comparison: an account that has logged in since the import carries a
            // bcrypt hash, not the pbkdf2 string that was copied over.
            //
            // auth_user has no avatar column, so the importer derives one from the primary key -
            // the same rule the migration used on the rows that were already here.
            $this->assertDatabaseHas('users', [
                'id' => $row->id,
                'username' => $row->username,
                'email' => $row->email,
                'avatar' => User::presetForId((int) $row->id),
                'is_staff' => $row->is_staff,
            ]);
        }

        foreach (DB::table('board_post')->get() as $row) {
            $this->assertDatabaseHas('posts', ['id' => $row->id, 'author_id' => $row->author_id]);

            // Laravel's timestamp() columns are second precision while Django's are microseconds,
            // so the copied value may round by one second. Nothing coarser than that is allowed.
            $stored = Carbon::parse(DB::table('posts')->where('id', $row->id)->value('created_at'));
            $this->assertLessThanOrEqual(1.0, Carbon::parse($row->created_at)->diffInSeconds($stored));
        }

        foreach (DB::table('board_comment')->get() as $row) {
            $this->assertDatabaseHas('comments', ['id' => $row->id, 'post_id' => $row->post_id, 'author_id' => $row->author_id]);
        }
    }

    public function test_rerunning_the_import_does_not_duplicate_or_overwrite(): void
    {
        Artisan::call('board:import-django');
        $counts = [User::count(), Post::count(), Comment::count()];

        $post = Post::firstWhere('id', DB::table('board_post')->value('id'));
        $post?->update(['title' => 'Edited in Laravel']);

        Artisan::call('board:import-django');

        $this->assertSame($counts, [User::count(), Post::count(), Comment::count()]);

        if ($post) {
            $this->assertSame('Edited in Laravel', $post->fresh()->title);
        }
    }
}
