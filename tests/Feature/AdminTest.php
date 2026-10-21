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
        $comment = Comment::factory()->for($post, 'post')->for($commenter, 'author')->create(['body' => 'delete me']);
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
}
