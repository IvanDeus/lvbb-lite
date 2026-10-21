<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Tests\TestCase;

class FooterStatsTest extends TestCase
{
    public function test_the_panel_counts_members_replies_and_topics(): void
    {
        $this->assertSame($this->expected(), $this->panel());
    }

    public function test_a_new_reply_moves_the_number_immediately(): void
    {
        $post = Post::factory()->create();

        $before = $this->numbers($this->panel());
        Comment::factory()->for($post, 'post')->for($post->author, 'author')->create();
        $after = $this->numbers($this->panel());

        // Counted per request rather than cached, so the reply a visitor just wrote is in the total
        // the next page they open carries.
        $this->assertSame([$before[0], $before[1] + 1, $before[2]], $after);
    }

    public function test_one_of_each_takes_the_singular_and_none_takes_the_plural(): void
    {
        $this->assertSame('1 user &middot; 1 comment &middot; 1 topic', $this->panelOf(1, 1, 1));
        $this->assertSame('0 users &middot; 0 comments &middot; 0 topics', $this->panelOf(0, 0, 0));
        $this->assertSame('2 users &middot; 12 comments &middot; 3 topics', $this->panelOf(2, 12, 3));
    }

    public function test_the_panel_sits_above_the_credit_on_every_page(): void
    {
        $post = Post::factory()->create();
        $this->actingAs($post->author);

        $pages = [
            '/',
            '/post/'.$post->id,
            '/user/'.$post->author->username,
            '/profile',
            '/signup',
            '/login',
            '/post/'.((int) Post::max('id') + 1),
        ];

        foreach ($pages as $url) {
            $source = $this->oneLine($this->get($url)->getContent());

            $this->assertStringContainsString($this->expected(), $source, $url);
            $this->assertLessThan(strpos($source, 'Ivan Deus'), strpos($source, 'class="stats"'), $url);
        }
    }

    /**
     * The footer paragraph with its source line breaks flattened: the template spreads the three
     * counts over three lines, and only the browser sees them as one.
     */
    private function panel(string $url = '/'): string
    {
        preg_match('#<p class="stats">(.*?)</p>#', $this->oneLine($this->get($url)->getContent()), $match);

        return trim($match[1]);
    }

    /** @return array{0: int, 1: int, 2: int} */
    private function numbers(string $panel): array
    {
        preg_match('#(\d+) users? &middot; (\d+) comments? &middot; (\d+) topics?#', $panel, $match);

        return [(int) $match[1], (int) $match[2], (int) $match[3]];
    }

    /** The partial on its own, so the wording can be read at counts this database has moved past. */
    private function panelOf(int $users, int $comments, int $topics): string
    {
        $rendered = view('partials.stats', ['stats' => compact('users', 'comments', 'topics')])->render();

        preg_match('#<p class="stats">(.*?)</p>#', $this->oneLine($rendered), $match);

        return trim($match[1]);
    }

    private function expected(): string
    {
        return User::count().' users &middot; '.Comment::count().' comments &middot; '.Post::count().' topics';
    }

    private function oneLine(string $html): string
    {
        return preg_replace('/\s+/', ' ', $html);
    }
}
