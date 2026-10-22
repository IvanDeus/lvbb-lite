<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

// The one write the roll-call offers: a row's type, flipped by a POST from that row. Its Django twin
// is MemberRoleTests, and both need two staff accounts for the same reason - the acting admin's own
// row is the row that must never offer a button, which is also the shape a real operator clicks.
class MemberRoleTest extends TestCase
{
    private User $keeper;

    private User $writer;

    private User $other;

    protected function setUp(): void
    {
        parent::setUp();

        $this->keeper = User::factory()->staff()->create(['username' => 'keeper']);
        $this->writer = User::factory()->create(['username' => 'donne']);
        $this->other = User::factory()->staff()->create(['username' => 'herbert']);

        $this->actingAs($this->keeper);
    }

    public function test_a_member_is_made_an_admin(): void
    {
        $this->post(route('member_role_store', $this->writer))->assertRedirect('/members');

        $this->assertTrue($this->writer->fresh()->is_staff);
    }

    public function test_an_admin_is_made_a_member(): void
    {
        $this->post(route('member_role_store', $this->other));

        $this->assertFalse($this->other->fresh()->is_staff);
    }

    public function test_the_board_says_which_type_the_person_became(): void
    {
        $this->followingRedirects()->post(route('member_role_store', $this->writer))
            ->assertSee('donne is now an admin.');

        $this->followingRedirects()->post(route('member_role_store', $this->other))
            ->assertSee('herbert is now a member.');
    }

    public function test_the_toggle_moves_is_staff_and_nothing_else(): void
    {
        $before = $this->writer->fresh()->getAttributes();

        $this->post(route('member_role_store', $this->writer));

        $after = $this->writer->fresh()->getAttributes();

        $changed = [];

        foreach ($before as $key => $value) {
            if ($value !== ($after[$key] ?? null)) {
                $changed[] = $key;
            }
        }

        // One column, and it is the one the page prints. Password, e-mail, avatar and the joining day
        // all stay exactly where the signup left them.
        $this->assertSame(['is_staff'], $changed);
        // A board admin is a staff account, never a superuser: Laravel has no such column to flip,
        // which is the strongest form of the rule djbb-lite states for itself.
        $this->assertFalse(Schema::hasColumn('users', 'is_superuser'));
    }

    public function test_a_forged_self_change_is_refused(): void
    {
        $this->post(route('member_role_store', $this->keeper))->assertForbidden();

        $this->assertTrue($this->keeper->fresh()->is_staff);
    }

    public function test_a_writer_is_refused(): void
    {
        $this->actingAs($this->writer);

        $this->post(route('member_role_store', $this->other))->assertForbidden();

        $this->assertTrue($this->other->fresh()->is_staff);
    }

    public function test_a_guest_is_asked_to_log_in(): void
    {
        $this->post('/logout');

        $this->post(route('member_role_store', $this->writer))->assertRedirect('/login');

        $this->assertFalse($this->writer->fresh()->is_staff);
    }

    public function test_a_get_changes_nothing(): void
    {
        $this->get(route('member_role_store', $this->writer))->assertMethodNotAllowed();

        $this->assertFalse($this->writer->fresh()->is_staff);
    }
}
