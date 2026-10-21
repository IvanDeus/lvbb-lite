<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Every user must carry an avatar, so the column ends up NOT NULL. Existing rows - the
    // seeded accounts and whatever board:import-django copied out of auth_user - are filled
    // first, because Postgres refuses SET NOT NULL while a single null is left.
    //
    // Only `users` is touched; djbb-lite reads auth_user, which has no avatar field.
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('avatar', 255)->nullable();
        });

        // id % 4 makes a row's preset a stable function of its primary key, the same rule as
        // User::presetForId(), so a re-run of the importer cannot disagree with this backfill.
        DB::statement('update users set avatar = '
            ."(array['fem-a1.png','fem-a2.png','male-b1.png','male-b2.png'])[1 + (id % 4)] "
            .'where avatar is null');

        // ->change() always emits "alter column ... type", so the length has to be repeated here
        // or the column is silently re-typed; leaving nullable() off is what produces SET NOT NULL.
        Schema::table('users', function (Blueprint $table) {
            $table->string('avatar', 255)->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('avatar');
        });
    }
};
