<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class AdminUsersTest extends TestCase
{
    public function test_the_list_shows_name_avatar_and_email_for_every_writer(): void
    {
        $staff = User::factory()->staff()->create();
        $writers = User::factory()->count(2)->create();

        $page = $this->actingAs($staff)->get('/admin/users')->assertOk();

        foreach ($writers as $person) {
            $page->assertSee('<td>'.$person->username.'</td>', false)
                ->assertSee('<td>'.$person->email.'</td>', false)
                ->assertSee('/avatars/'.$person->avatar.'"', false);
        }
    }

    public function test_the_table_offers_no_way_to_edit_a_row(): void
    {
        User::factory()->create(['username' => 'donne']);

        $html = $this->actingAs(User::factory()->staff()->create())->get('/admin/users')->getContent();
        preg_match('#<table>.*</table>#s', $html, $table);

        // Read-only is the shape of the table: no form to submit, no link into an edit screen that
        // does not exist — only the three columns a staff visitor came to read.
        $this->assertStringNotContainsString('<form', $table[0]);
        $this->assertStringNotContainsString('<a ', $table[0]);
        $this->assertStringNotContainsString('<th></th>', $table[0]);
    }

    public function test_no_route_through_this_admin_writes_anything(): void
    {
        $person = User::factory()->create(['username' => 'donne', 'email' => 'donne@example.com']);
        $this->actingAs(User::factory()->staff()->create());

        $this->post('/admin/users', ['email' => 'forged@example.com'])->assertMethodNotAllowed();
        $this->post('/admin/users/'.$person->id, ['email' => 'forged@example.com'])->assertNotFound();
        $this->delete('/admin/users/'.$person->id)->assertNotFound();

        $this->assertSame('donne@example.com', $person->fresh()->email);
        $this->assertDatabaseHas('users', ['id' => $person->id]);
    }

    public function test_the_users_table_is_linked_from_the_other_admin_pages(): void
    {
        $this->actingAs(User::factory()->staff()->create());

        $this->get('/admin')->assertSee('/admin/users"', false);
        $this->get('/admin/comments')->assertSee('/admin/users"', false);
    }

    public function test_a_plain_user_is_turned_away(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin/users')->assertForbidden();
    }

    public function test_anonymous_never_reaches_it(): void
    {
        $this->get('/admin/users')->assertRedirect('/login');
    }
}
