<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Tests\TestCase;

class MemberListTest extends TestCase
{
    /** The three rows this test writes. A populated database holds others, so only these are read back. */
    private const WATCHED = ['keeper', 'donne', 'herbert'];

    private User $keeper;

    private User $writer;

    private User $lurker;

    protected function setUp(): void
    {
        parent::setUp();

        // Fixed days rather than insert order: the ordering is the thing under test, so none of it
        // may depend on how fast this machine ran the three INSERTs.
        $this->keeper = User::factory()->staff()->create(['username' => 'keeper', 'created_at' => now()->subDays(10)]);
        $this->writer = User::factory()->create(['username' => 'donne', 'created_at' => now()->subDays(9)]);
        $this->lurker = User::factory()->create(['username' => 'herbert', 'created_at' => now()->subDays(8)]);

        $this->actingAs($this->keeper);
    }

    public function test_every_writer_is_listed_with_a_face_a_name_and_a_type(): void
    {
        $this->get('/members')
            ->assertOk()
            ->assertSee('<th>Avatar</th><th>Name</th><th>Type</th>', false)
            ->assertSee('<td><a href="'.route('user_detail', 'donne').'">donne</a></td>', false)
            ->assertSee('width="48" height="48"', false)
            ->assertSee('<td>member</td>', false)
            ->assertSee('<td>admin</td>', false);
    }

    public function test_the_list_runs_from_the_most_recent_activity(): void
    {
        $post = Post::factory()->for($this->writer, 'author')->create(['created_at' => now()->subDay()]);
        Comment::factory()->for($post, 'post')->for($this->lurker, 'author')->create(['created_at' => now()]);

        // The reply is the newest act on the board, then the topic it answered, then the day the
        // keeper joined - all they have done here.
        $this->assertSame(['herbert', 'donne', 'keeper'], $this->names());
    }

    public function test_a_writer_who_never_wrote_is_placed_by_their_joining(): void
    {
        Post::factory()->for($this->writer, 'author')->create(['created_at' => now()->subYear()]);

        // Neither silent writer has a row in posts or comments, so their joining - eight and ten
        // days back - is what places them, and both sit above a topic a year old.
        $this->assertSame(['herbert', 'keeper', 'donne'], $this->names());
    }

    public function test_a_writer_is_refused(): void
    {
        $this->actingAs($this->writer)->get('/members')->assertForbidden();
    }

    public function test_a_guest_is_asked_to_log_in(): void
    {
        $this->post('/logout');

        $this->get('/members')->assertRedirect('/login');
    }

    public function test_the_users_figure_links_here_for_staff(): void
    {
        $this->assertStringContainsString('<a href="'.route('member_list').'">', $this->panel());
    }

    public function test_the_users_figure_stays_plain_text_for_a_writer(): void
    {
        $this->actingAs($this->writer);

        $this->assertStringNotContainsString('<a', $this->panel());
    }

    public function test_the_users_figure_stays_plain_text_for_a_guest(): void
    {
        $this->post('/logout');

        $this->assertStringNotContainsString('<a', $this->panel());
    }

    /** @return array<int, string> The watched names, in the order their rows came out. */
    private function names(): array
    {
        preg_match_all('#<td><a href="[^"]+/user/([^"]+)">#', $this->oneLine($this->get('/members')->getContent()), $matches);

        return array_values(array_filter($matches[1], fn (string $name) => in_array($name, self::WATCHED, true)));
    }

    /** The footer paragraph with its source line breaks flattened, as only the browser sees it. */
    private function panel(): string
    {
        preg_match('#<p class="stats">(.*?)</p>#', $this->oneLine($this->get('/')->getContent()), $match);

        return $match[1];
    }

    private function oneLine(string $html): string
    {
        return preg_replace('/\s+/', ' ', $html);
    }
}
