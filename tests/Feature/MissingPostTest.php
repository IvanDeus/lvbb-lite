<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Tests\TestCase;

class MissingPostTest extends TestCase
{
    /** A key the table cannot already be holding. */
    private function missingId(): int
    {
        return (int) Post::max('id') + 1_000;
    }

    public function test_a_missing_id_answers_in_the_boards_own_voice(): void
    {
        $this->get('/post/'.$this->missingId())
            ->assertNotFound()
            ->assertSee('No such post', false)
            ->assertSee('That topic does not exist, or it has been deleted.', false);
    }

    public function test_the_page_hands_the_visitor_back_to_the_board_after_two_seconds(): void
    {
        $page = $this->get('/post/'.$this->missingId());

        $page->assertSee('<meta http-equiv="refresh" content="2;url='.route('post_list').'">', false);
        $page->assertSee('href="'.route('post_list').'">Go to the board now</a>', false);
    }

    public function test_a_post_deleted_since_the_link_was_made_takes_the_same_route(): void
    {
        $author = User::factory()->create();
        $post = Post::factory()->create(['author_id' => $author->id, 'title' => 'To His Coy Mistress']);
        $url = '/post/'.$post->id;

        $post->delete();

        $this->get($url)->assertNotFound()->assertSee('No such post', false);
    }

    public function test_an_id_too_large_for_the_column_counts_as_missing(): void
    {
        // PHP saturates the cast at the largest integer bigint holds, so the lookup misses instead
        // of the driver raising out of range and the board answering with a stack trace.
        $this->get('/post/99999999999999999999')
            ->assertNotFound()
            ->assertSee('No such post', false);
    }

    public function test_a_post_that_does_exist_still_renders(): void
    {
        $post = Post::factory()->create(['title' => 'The Annunciation']);

        $this->get('/post/'.$post->id)
            ->assertOk()
            ->assertSee('The Annunciation')
            ->assertDontSee('No such post', false);
    }
}
