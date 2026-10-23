<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Tests\TestCase;

class AdminTest extends TestCase
{
    public function test_the_board_is_closed_to_guests(): void
    {
        $this->get('/admin')->assertRedirect('/login');
        $this->get('/admin/comments')->assertRedirect('/login');
    }

    public function test_a_plain_user_gets_forbidden(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin')->assertForbidden();
        $this->actingAs(User::factory()->create())->get('/admin/comments')->assertForbidden();
    }

    public function test_the_staff_index_lists_posts_with_authors_and_counts(): void
    {
        $post = Post::factory()->create(['title' => 'A row for the admin list']);
        Comment::factory()->for($post, 'post')->count(3)->create();

        $this->actingAs(User::factory()->staff()->create())
            ->get('/admin')
            ->assertOk()
            ->assertSee('A row for the admin list')
            ->assertViewHas('posts', fn ($posts) => $posts->find($post->id)->comments_count === 3);
    }

    public function test_staff_sees_a_posts_comments_and_can_delete_them(): void
    {
        $post = Post::factory()->create(['title' => 'A post with replies']);
        $commenter = User::factory()->create();

        // Stamped two hundred years back because the list is ten to a page from the oldest: a reply
        // dated today sits on the last page of any board that holds more than ten, and which page that
        // is somebody else's data decides. Older than everything the board can hold puts this row on
        // the page the test actually reads.
        $comment = Comment::factory()->for($post, 'post')->for($commenter, 'author')
            ->create(['body' => 'delete me', 'created_at' => now()->subYears(200)]);
        $admin = User::factory()->staff()->create();

        $this->actingAs($admin)->get("/admin/post/{$post->id}")->assertOk()->assertSee('delete me');

        // The comments list mirrors Django's list_display: post, author, created - no body.
        $this->actingAs($admin)->get('/admin/comments')
            ->assertOk()
            ->assertSee('A post with replies')
            ->assertSee($commenter->username);

        $this->actingAs($admin)->post("/admin/comment/{$comment->id}/delete")->assertRedirect('/admin/comments');
        $this->assertDatabaseMissing('comments', ['id' => $comment->id]);

        $this->actingAs($admin)->post("/admin/post/{$post->id}/delete")->assertRedirect('/admin');
        $this->assertDatabaseMissing('posts', ['id' => $post->id]);
    }

    public function test_deleting_from_the_admin_cascades_comments(): void
    {
        $post = Post::factory()->create();
        Comment::factory()->for($post, 'post')->count(2)->create();

        $this->actingAs(User::factory()->staff()->create())
            ->post("/admin/post/{$post->id}/delete")
            ->assertRedirect('/admin');

        $this->assertSame(0, Comment::where('post_id', $post->id)->count());
    }

    public function test_admin_delete_removes_only_the_chosen_post(): void
    {
        $author = User::factory()->create();
        Post::factory()->for($author, 'author')->count(2)->create();

        $this->actingAs(User::factory()->staff()->create())
            ->post('/admin/post/'.Post::where('author_id', $author->id)->value('id').'/delete')
            ->assertRedirect('/admin');

        $this->assertDatabaseHas('users', ['id' => $author->id]);
        $this->assertSame(1, Post::where('author_id', $author->id)->count());
    }

    public function test_the_admin_link_only_shows_for_staff(): void
    {
        $link = 'href="'.route('admin_posts').'"';

        $this->actingAs(User::factory()->create())->get('/')->assertDontSee($link, false);
        $this->actingAs(User::factory()->staff()->create())->get('/')->assertSee($link, false);
    }

    public function test_the_comment_list_shows_ten_rows_oldest_first(): void
    {
        $admin = User::factory()->staff()->create();

        // This list holds every reply on the board, so its size cannot be asserted. Two hundred years
        // back is older than anything it can hold, which puts the first page entirely in these rows'
        // hands - and is only visible there because the order runs from the oldest.
        $stamps = [];

        for ($i = 1; $i <= 11; $i++) {
            $stamps[] = Comment::factory()
                ->for(Post::factory()->create(), 'post')
                ->for($admin, 'author')
                ->create(['created_at' => now()->subYears(200)->addMinutes($i)])
                ->created_at->format('Y-m-d H:i');
        }

        $this->actingAs($admin)->get('/admin/comments')
            ->assertOk()
            ->assertViewHas('comments', fn ($comments) => $comments->perPage() === 10 && $comments->count() === 10)
            ->assertSee($stamps[0], false)
            ->assertSee($stamps[9], false)
            ->assertDontSee($stamps[10], false)
            ->assertSee('page 1 of', false)
            ->assertSee('href="'.route('admin_comments', ['page' => 2]).'"', false);

        $this->actingAs($admin)->get('/admin/comments?page=2')
            ->assertOk()
            ->assertViewHas('comments', fn ($comments) => $comments->currentPage() === 2)
            ->assertSee($stamps[10], false);
    }

    public function test_a_posts_admin_page_pages_its_replies_and_counts_them_all(): void
    {
        $admin = User::factory()->staff()->create();
        $post = Post::factory()->create();
        $replies = [];

        for ($i = 1; $i <= 12; $i++) {
            $replies[] = Comment::factory()->for($post, 'post')->create([
                'body' => 'reply '.str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                'created_at' => now()->subHours(2)->addMinutes($i),
            ]);
        }

        $this->actingAs($admin)->get("/admin/post/{$post->id}")
            ->assertOk()
            ->assertViewHas('comments', fn ($comments) => $comments->count() === 10)
            // The heading is the thread's whole, not the ten rows this page happens to hold.
            ->assertSee('Comments (12)')
            ->assertSee($replies[0]->body)
            ->assertDontSee($replies[11]->body)
            ->assertSee('href="'.route('admin_post', ['post' => $post, 'page' => 2]).'"', false);

        $this->actingAs($admin)->get("/admin/post/{$post->id}?page=2")
            ->assertOk()
            ->assertViewHas('comments', fn ($comments) => $comments->count() === 2 && $comments->first()->is($replies[10]))
            ->assertSee('Comments (12)')
            ->assertSee($replies[11]->body)
            ->assertDontSee($replies[0]->body)
            ->assertSee('page 2 of 2', false);
    }
}
