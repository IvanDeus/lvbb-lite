<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Tests\TestCase;

class PostTest extends TestCase
{
    public function test_index_lists_newest_first_and_counts_comments(): void
    {
        $older = Post::factory()->create(['title' => 'The older one', 'created_at' => now()->subWeek()]);
        $newer = Post::factory()->create(['title' => 'The newer one', 'created_at' => now()]);
        Comment::factory()->for($newer, 'post')->count(2)->create();

        $this->get('/')
            ->assertOk()
            ->assertViewHas('posts', function ($posts) use ($older, $newer) {
                return $posts->first()->is($newer)
                    && $posts->find($newer->id)->comments_count === 2
                    && $posts->find($older->id)->comments_count === 0;
            });
    }

    public function test_index_truncates_the_body_like_django_truncatechars(): void
    {
        Post::factory()->create(['body' => str_repeat('a', 300)]);

        $this->get('/')
            ->assertOk()
            ->assertSee(str_repeat('a', 239).'…', false)
            ->assertDontSee(str_repeat('a', 240), false);
    }

    public function test_detail_shows_the_whole_post_and_its_comments(): void
    {
        $author = User::factory()->create();
        $post = Post::factory()->for($author, 'author')->create(['body' => str_repeat('b', 300)]);
        Comment::factory()->for($post, 'post')->create(['body' => 'a reply', 'created_at' => now()]);

        $this->get("/post/{$post->id}")
            ->assertOk()
            ->assertSee(str_repeat('b', 300))
            ->assertSee($author->username)
            ->assertSee('a reply')
            ->assertViewHas('canManage', false);
    }

    public function test_guests_are_asked_to_log_in_before_commenting(): void
    {
        $post = Post::factory()->create();

        $this->get("/post/{$post->id}")->assertOk()->assertSee('Log in', false);
        $this->post("/post/{$post->id}", ['body' => 'anonymous'])->assertRedirect('/login');
    }

    public function test_writing_a_post_requires_a_valid_title_and_body(): void
    {
        $this->actingAs(User::factory()->create());
        $before = Post::count();

        $this->post('/post/new', ['title' => '', 'body' => 'text'])->assertSessionHasErrors('title');
        $this->post('/post/new', ['title' => str_repeat('t', 201), 'body' => 'text'])->assertSessionHasErrors('title');
        $this->post('/post/new', ['title' => 'Title', 'body' => ''])->assertSessionHasErrors('body');

        $this->assertSame($before, Post::count());
    }

    public function test_a_new_post_belongs_to_its_author(): void
    {
        $author = User::factory()->create();

        $this->actingAs($author)
            ->post('/post/new', ['title' => 'Board rules', 'body' => 'Be nice.'])
            ->assertRedirect();

        $post = Post::firstWhere('title', 'Board rules');
        $this->assertNotNull($post);
        $this->assertSame($author->id, $post->author_id);
        $this->get("/post/{$post->id}")->assertOk()->assertViewHas('canManage', true);
    }

    public function test_touching_someone_elses_post_redirects_instead_of_403(): void
    {
        $post = Post::factory()->create();
        $stranger = User::factory()->create();

        $this->actingAs($stranger)->get("/post/{$post->id}/edit")->assertRedirect("/post/{$post->id}");
        $this->actingAs($stranger)
            ->post("/post/{$post->id}/edit", ['title' => 'hijacked', 'body' => 'hijacked'])
            ->assertStatus(302)
            ->assertRedirect("/post/{$post->id}");
        $this->actingAs($stranger)
            ->post("/post/{$post->id}/delete")
            ->assertStatus(302)
            ->assertRedirect("/post/{$post->id}");

        $this->assertNotSame('hijacked', $post->fresh()->title);
        $this->assertDatabaseHas('posts', ['id' => $post->id]);
    }

    public function test_staff_may_edit_and_delete_any_post(): void
    {
        $post = Post::factory()->create();
        $admin = User::factory()->staff()->create();

        $this->actingAs($admin)->get("/post/{$post->id}/edit")->assertOk();
        $this->actingAs($admin)
            ->post("/post/{$post->id}/edit", ['title' => 'Corrected', 'body' => 'Edited by staff.'])
            ->assertRedirect("/post/{$post->id}");
        $this->assertDatabaseHas('posts', ['id' => $post->id, 'title' => 'Corrected']);

        $this->actingAs($admin)->post("/post/{$post->id}/delete")->assertRedirect('/');
        $this->assertDatabaseMissing('posts', ['id' => $post->id]);
    }

    public function test_deleting_a_post_takes_its_comments_with_it(): void
    {
        $author = User::factory()->create();
        $post = Post::factory()->for($author, 'author')->create();
        Comment::factory()->for($post, 'post')->count(2)->create();

        $this->actingAs($author)->post("/post/{$post->id}/delete")->assertRedirect('/');

        $this->assertSame(0, Comment::where('post_id', $post->id)->count());
    }

    public function test_comments_are_stored_and_validated(): void
    {
        $post = Post::factory()->create();
        $user = User::factory()->create();

        $this->actingAs($user)->post("/post/{$post->id}", ['body' => 'First!'])->assertRedirect("/post/{$post->id}");
        $this->actingAs($user)->post("/post/{$post->id}", ['body' => ''])->assertSessionHasErrors('body');

        $comment = Comment::firstWhere('post_id', $post->id);
        $this->assertSame('First!', $comment->body);
        $this->assertSame($user->id, $comment->author_id);
        $this->get("/post/{$post->id}")->assertOk()->assertSee('First!');
    }
}
