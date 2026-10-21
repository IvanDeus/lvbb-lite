<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Closure;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AvatarTest extends TestCase
{
    /** @var list<string> */
    private array $tempFiles = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Without this every upload below would be written into public/avatars/uploads.
        Storage::fake('avatars');
    }

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $path) {
            @unlink($path);
        }

        parent::tearDown();
    }

    public function test_the_avatars_disk_is_rooted_at_the_public_folder(): void
    {
        // Uploaded avatars are served straight out of the docroot, next to the committed presets,
        // which is why there is no storage:link step in this project.
        $this->assertSame(public_path('avatars'), config('filesystems.disks.avatars.root'));
        $this->assertSame('/avatars', parse_url(config('filesystems.disks.avatars.url'), PHP_URL_PATH));
    }

    public function test_signup_stores_the_picked_preset(): void
    {
        $this->post('/signup', [
            'username' => 'presetpicker',
            'email' => 'preset@example.com',
            'password' => 'secret123',
            'avatar' => 'male-b2.png',
        ])->assertRedirect('/');

        $user = User::firstWhere('username', 'presetpicker');
        $this->assertSame('male-b2.png', $user->avatar);
        Storage::disk('avatars')->assertMissing('uploads/'.$user->id.'.png');
        $this->assertStringEndsWith('/avatars/male-b2.png', $user->avatarUrl());
    }

    public function test_signup_requires_an_avatar(): void
    {
        $this->post('/signup', ['username' => 'noavatar', 'email' => 'a@example.com', 'password' => 'secret123'])
            ->assertSessionHasErrors('avatar');

        $this->assertDatabaseMissing('users', ['username' => 'noavatar']);
    }

    public function test_signup_rejects_an_avatar_that_is_not_one_of_the_four_presets(): void
    {
        foreach (['nope.png', '../../etc/passwd', 'uploads/1.png', 'FEM-A1.PNG', 'fem-a1.png/../x'] as $rejected) {
            $this->post('/signup', [
                'username' => 'forger',
                'email' => 'a@example.com',
                'password' => 'secret123',
                'avatar' => $rejected,
            ])->assertSessionHasErrors('avatar');
        }

        $this->assertDatabaseMissing('users', ['username' => 'forger']);
    }

    public function test_an_upload_is_resized_to_a_hundred_square_png(): void
    {
        $this->post('/signup', [
            'username' => 'uploader',
            'email' => 'upload@example.com',
            'password' => 'secret123',
            'avatar' => 'fem-a1.png',
            'avatar_file' => UploadedFile::fake()->image('portrait.png', 300, 120),
        ])->assertRedirect('/');

        $user = User::firstWhere('username', 'uploader');
        $this->assertSame('uploads/'.$user->id.'.png', $user->avatar);

        $stored = Storage::disk('avatars')->get('uploads/'.$user->id.'.png');
        $this->assertNotNull($stored);

        [$width, $height, $type] = getimagesizefromstring($stored);
        $this->assertSame([100, 100, IMAGETYPE_PNG], [$width, $height, $type]);
        $this->assertStringEndsWith('/avatars/uploads/'.$user->id.'.png', $user->avatarUrl());
    }

    public function test_a_non_square_upload_is_cropped_from_the_centre(): void
    {
        $user = User::factory()->create(['avatar' => 'fem-a1.png']);

        // Only the middle band of a 300x100 image fits inside the centre square that gets cropped.
        $this->actingAs($user)->post('/profile', ['avatar_file' => $this->bandedImage()]);

        $this->assertSame('uploads/'.$user->id.'.png', $user->fresh()->avatar);
        $this->assertSame(
            ['green', 'green', 'green'],
            array_values($this->pixelsOfStoredAvatar($user)),
            'the crop did not start at the centre of the source'
        );
    }

    public function test_uploads_are_re_encoded_in_their_own_format(): void
    {
        foreach ([
            'a.jpg' => IMAGETYPE_JPEG,
            'a.png' => IMAGETYPE_PNG,
            'a.webp' => IMAGETYPE_WEBP,
        ] as $name => $expectedType) {
            $user = User::factory()->create();

            $this->actingAs($user)->post('/profile', [
                'avatar' => 'fem-a1.png',
                'avatar_file' => UploadedFile::fake()->image($name, 240, 240),
            ])->assertRedirect(route('profile'));

            $extension = pathinfo($name, PATHINFO_EXTENSION) === 'jpg' ? 'jpeg' : pathinfo($name, PATHINFO_EXTENSION);
            $this->assertSame('uploads/'.$user->id.'.'.$extension, $user->fresh()->avatar);

            $stored = Storage::disk('avatars')->get('uploads/'.$user->id.'.'.$extension);
            $this->assertSame($expectedType, getimagesizefromstring($stored)[2]);
        }
    }

    public function test_a_transparent_png_keeps_its_alpha_channel(): void
    {
        $user = User::factory()->create(['avatar' => 'fem-a1.png']);

        $this->actingAs($user)->post('/profile', [
            'avatar_file' => $this->image('ghost.png', function (mixed $canvas): void {
                $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
                imagefill($canvas, 0, 0, $transparent);
                imagefilledrectangle($canvas, 80, 80, 120, 120, imagecolorallocatealpha($canvas, 255, 255, 255, 0));
            }, 200, 200, IMAGETYPE_PNG),
        ]);

        $avatar = imagecreatefromstring(Storage::disk('avatars')->get('uploads/'.$user->id.'.png'));
        $this->assertNotFalse($avatar);

        $corner = imagecolorsforindex($avatar, imagecolorat($avatar, 2, 2));
        $centre = imagecolorsforindex($avatar, imagecolorat($avatar, 50, 50));
        imagedestroy($avatar);

        $this->assertSame(127, $corner['alpha'], 'the transparent background was flattened');
        $this->assertSame(0, $centre['alpha']);
    }

    public function test_avif_and_oversized_and_non_image_uploads_are_rejected(): void
    {
        $user = User::factory()->create(['avatar' => 'male-b1.png']);
        $cases = [
            'avif' => $this->image('modern.avif', fn (mixed $canvas) => null, 120, 120, IMAGETYPE_AVIF),
            'oversized' => UploadedFile::fake()->image('huge.png')->size(3000),
            'not an image' => UploadedFile::fake()->create('notes.txt', 4),
        ];

        foreach ($cases as $file) {
            $this->actingAs($user)->post('/profile', ['avatar_file' => $file])
                ->assertSessionHasErrors('avatar_file');
        }

        $this->assertSame('male-b1.png', $user->fresh()->avatar);
        $this->assertEmpty(Storage::disk('avatars')->allFiles('uploads'));
    }

    public function test_a_file_that_sniffs_as_png_but_cannot_be_decoded_is_rejected(): void
    {
        // Signature and IHDR intact, pixel data never written: the mime sniff believes it and
        // getimagesize() reads its header, but there is nothing for GD to decode.
        $canvas = imagecreatetruecolor(120, 120);
        $path = tempnam(sys_get_temp_dir(), 'avatar-test-');
        imagepng($canvas, $path);
        imagedestroy($canvas);
        file_put_contents($path, substr((string) file_get_contents($path), 0, 64));
        $this->tempFiles[] = $path;

        $user = User::factory()->create(['avatar' => 'fem-a1.png']);

        $this->actingAs($user)->post('/profile', ['avatar_file' => new UploadedFile($path, 'broken.png', null, null, true)])
            ->assertSessionHasErrors(['avatar_file' => 'Please upload a readable JPG, PNG or WebP image.']);

        $this->assertSame('fem-a1.png', $user->fresh()->avatar);
        $this->assertEmpty(Storage::disk('avatars')->allFiles('uploads'));
    }

    public function test_the_profile_page_requires_a_login(): void
    {
        $this->get('/profile')->assertRedirect('/login');
        $this->post('/profile', ['avatar' => 'fem-a2.png'])->assertRedirect('/login');
    }

    public function test_the_profile_page_offers_the_four_presets(): void
    {
        $page = $this->actingAs(User::factory()->create())->get('/profile')->assertOk();

        foreach (User::AVATAR_PRESETS as $preset) {
            $page->assertSee('value="'.$preset.'"', false);
        }

        $page->assertSee('name="avatar"', false)
            ->assertSee('enctype="multipart/form-data"', false)
            ->assertSee('name="avatar_file"', false);
    }

    public function test_the_profile_page_shows_the_current_avatar_at_its_full_size(): void
    {
        $user = User::factory()->create(['username' => 'donne', 'avatar' => 'fem-a1.png']);

        $this->actingAs($user)->get('/profile')->assertOk()->assertSee(
            '<img class="avatar-full" src="'.url('/avatars/fem-a1.png').'" alt="donne&#39;s avatar" width="100" height="100">',
            false
        );
    }

    public function test_switching_between_presets_touches_no_file(): void
    {
        $user = User::factory()->create(['avatar' => 'fem-a1.png']);

        $this->actingAs($user)->post('/profile', ['avatar' => 'male-b1.png'])->assertRedirect(route('profile'));

        $this->assertSame('male-b1.png', $user->fresh()->avatar);
        $this->assertEmpty(Storage::disk('avatars')->allFiles('uploads'));
    }

    public function test_uploading_nothing_with_no_preset_keeps_the_current_avatar(): void
    {
        $user = User::factory()->create(['avatar' => 'fem-a2.png']);

        $this->actingAs($user)->post('/profile', [])->assertRedirect(route('profile'));

        $this->assertSame('fem-a2.png', $user->fresh()->avatar);
    }

    public function test_replacing_an_upload_deletes_the_file_it_replaced(): void
    {
        $user = User::factory()->create(['avatar' => 'fem-a1.png']);

        // Somebody else's file, which the cleanup must leave alone.
        Storage::disk('avatars')->put('uploads/999999.png', 'not this user');

        $this->actingAs($user)->post('/profile', ['avatar_file' => UploadedFile::fake()->image('first.png', 200, 200)]);
        Storage::disk('avatars')->assertExists('uploads/'.$user->id.'.png');

        $this->actingAs($user)->post('/profile', ['avatar_file' => UploadedFile::fake()->image('second.webp', 200, 200)]);

        $this->assertSame('uploads/'.$user->id.'.webp', $user->fresh()->avatar);
        Storage::disk('avatars')->assertMissing('uploads/'.$user->id.'.png');
        Storage::disk('avatars')->assertExists('uploads/'.$user->id.'.webp');
        Storage::disk('avatars')->assertExists('uploads/999999.png');
    }

    public function test_picking_a_preset_deletes_an_uploaded_avatar(): void
    {
        $user = User::factory()->create(['avatar' => 'fem-a1.png']);
        $this->actingAs($user)->post('/profile', ['avatar_file' => UploadedFile::fake()->image('gone.png', 200, 200)]);

        $this->actingAs($user)->post('/profile', ['avatar' => 'fem-a1.png']);

        Storage::disk('avatars')->assertMissing('uploads/'.$user->id.'.png');
        $this->assertSame('fem-a1.png', $user->fresh()->avatar);
    }

    public function test_one_user_cannot_delete_another_upload_with_a_forged_value(): void
    {
        $victim = User::factory()->create();
        $this->actingAs($victim)->post('/profile', ['avatar_file' => UploadedFile::fake()->image('mine.png', 200, 200)]);
        Storage::disk('avatars')->assertExists('uploads/'.$victim->id.'.png');

        $attacker = User::factory()->create(['avatar' => 'fem-a1.png']);

        foreach (['uploads/'.$victim->id.'.png', '../fem-a1.png', 'uploads/../uploads/'.$victim->id.'.png'] as $forged) {
            $this->actingAs($attacker)->post('/profile', ['avatar' => $forged])
                ->assertSessionHasErrors('avatar');
        }

        Storage::disk('avatars')->assertExists('uploads/'.$victim->id.'.png');
        $this->assertSame('fem-a1.png', $attacker->fresh()->avatar);
    }

    public function test_avatars_are_rendered_next_to_every_author(): void
    {
        $author = User::factory()->create(['avatar' => 'fem-a2.png']);
        $post = Post::factory()->for($author, 'author')->create();
        Comment::factory()->for($post, 'post')->create(['author_id' => $author->id]);

        $this->get('/')->assertSee('class="avatar"', false)->assertSee('avatars/fem-a2.png', false);
        $this->get('/post/'.$post->id)->assertSee('avatars/fem-a2.png', false);
        $this->actingAs(User::factory()->staff()->create())->get('/admin')->assertSee('avatars/fem-a2.png', false);
        $this->actingAs($author)->get('/profile')->assertSee('avatars/fem-a2.png', false);
    }

    public function test_every_user_row_carries_an_avatar(): void
    {
        $column = collect(Schema::getColumns('users'))->firstWhere('name', 'avatar');
        $this->assertNotFalse($column);
        $this->assertFalse($column['nullable'], 'users.avatar is expected to be NOT NULL');

        // Includes the rows this suite did not create: the seeded accounts and anything
        // board:import-django copied, which is what the migration backfilled.
        $this->assertSame(0, DB::table('users')->whereNull('avatar')->orWhere('avatar', '')->count());
        $this->assertNotEmpty(DB::table('users')->pluck('avatar')->all());
    }

    /**
     * A 300x100 PNG: red in the left third, green in the middle, blue on the right. Only the middle
     * band lies inside the centre square, so a correctly cropped avatar is green all over.
     */
    private function bandedImage(): UploadedFile
    {
        return $this->image('bands.png', function (mixed $canvas, int $width, int $height): void {
            $third = intdiv($width, 3);

            $bands = [
                [0, $third - 1, [255, 0, 0]],
                [$third, $third * 2 - 1, [0, 255, 0]],
                [$third * 2, $width - 1, [0, 0, 255]],
            ];

            foreach ($bands as [$left, $right, [$red, $green, $blue]]) {
                imagefilledrectangle(
                    $canvas,
                    $left,
                    0,
                    $right,
                    $height - 1,
                    imagecolorallocate($canvas, $red, $green, $blue)
                );
            }
        }, 300, 100, IMAGETYPE_PNG);
    }

    /**
     * @return array{top-left: string, centre: string, bottom-right: string}
     */
    private function pixelsOfStoredAvatar(User $user): array
    {
        $avatar = imagecreatefromstring(Storage::disk('avatars')->get('uploads/'.$user->id.'.png'));
        $this->assertNotFalse($avatar);

        $name = fn (int $rgb): string => match (true) {
            ($rgb >> 16 & 0xFF) > 128 => 'red',
            ($rgb >> 8 & 0xFF) > 128 => 'green',
            default => 'blue',
        };

        $samples = [
            'top-left' => $name(imagecolorat($avatar, 2, 2)),
            'centre' => $name(imagecolorat($avatar, 50, 50)),
            'bottom-right' => $name(imagecolorat($avatar, 97, 97)),
        ];

        imagedestroy($avatar);

        return $samples;
    }

    /**
     * UploadedFile::fake()->image() can only draw noise, so anything a test needs to assert about
     * pixels - crop position, alpha, colour - is painted with GD into a temp file instead.
     */
    private function image(string $name, Closure $paint, int $width, int $height, int $type): UploadedFile
    {
        $canvas = imagecreatetruecolor($width, $height);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $paint($canvas, $width, $height);

        $path = tempnam(sys_get_temp_dir(), 'avatar-test-');
        match ($type) {
            IMAGETYPE_PNG => imagepng($canvas, $path),
            IMAGETYPE_WEBP => imagewebp($canvas, $path),
            IMAGETYPE_AVIF => imageavif($canvas, $path),
            default => imagejpeg($canvas, $path),
        };
        imagedestroy($canvas);

        $this->tempFiles[] = $path;

        return new UploadedFile($path, $name, null, null, true);
    }
}
