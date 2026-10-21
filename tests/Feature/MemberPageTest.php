<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Tests\TestCase;

class MemberPageTest extends TestCase
{
    public function test_anyone_can_see_the_face_and_the_name(): void
    {
        User::factory()->create(['username' => 'donne', 'avatar' => 'fem-a1.png']);

        $this->get('/user/donne')
            ->assertOk()
            ->assertSee('<h2>donne</h2>', false)
            ->assertSee('/avatars/fem-a1.png"', false)
            ->assertSee('width="100" height="100"', false);
    }

    public function test_the_address_is_not_for_public_eyes(): void
    {
        User::factory()->create(['username' => 'donne', 'email' => 'donne@example.com']);

        $this->get('/user/donne')->assertDontSee('donne@example.com');
        $this->actingAs(User::factory()->create())->get('/user/donne')->assertDontSee('donne@example.com');
    }

    public function test_staff_sees_the_address_too(): void
    {
        User::factory()->create(['username' => 'donne', 'email' => 'donne@example.com']);

        $this->actingAs(User::factory()->staff()->create())
            ->get('/user/donne')
            ->assertOk()
            ->assertSee('donne@example.com');
    }

    public function test_every_avatar_on_the_board_links_here(): void
    {
        $writer = User::factory()->create(['username' => 'donne']);
        $post = Post::factory()->create(['author_id' => $writer->id]);
        Comment::factory()->for($post, 'post')->for($writer, 'author')->create();

        $this->get('/')->assertSee('/user/donne"', false);
        $this->get('/post/'.$post->id)->assertSee('/user/donne"', false);
    }

    public function test_a_name_that_is_not_on_the_board_is_not_found(): void
    {
        $this->get('/user/herbert-never-wrote')->assertNotFound();
    }

    public function test_the_lookup_is_case_sensitive_like_djangos(): void
    {
        User::factory()->create(['username' => 'donne']);

        $this->get('/user/Donne')->assertNotFound();
    }
}
