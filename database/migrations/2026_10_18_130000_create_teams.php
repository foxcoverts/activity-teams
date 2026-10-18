<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teams', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('team_user', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('team_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->string('role');
            $table->timestamp('left_at')->nullable();
            $table->timestamps();

            $table->unique(['team_id', 'user_id']);
        });

        Schema::create('activity_team', function (Blueprint $table) {
            $table->foreignUlid('activity_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('team_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['activity_id', 'team_id']);
        });

        Schema::create('team_venue', function (Blueprint $table) {
            $table->foreignUlid('team_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('venue_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['team_id', 'venue_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('suspended')->default(false)->after('section');
            $table->boolean('oversight')->default(false)->after('suspended');
            $table->boolean('is_system_admin')->default(false)->after('oversight');
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->foreignUlid('team_id')->nullable()->after('id')->constrained()->restrictOnDelete();
        });

        $this->moveExistingPeopleOntoOneTeam();

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'accreditations']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('guest');
            $table->string('accreditations')->default('');
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('team_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['suspended', 'oversight', 'is_system_admin']);
        });

        Schema::dropIfExists('team_venue');
        Schema::dropIfExists('activity_team');
        Schema::dropIfExists('team_user');
        Schema::dropIfExists('teams');
    }

    private function moveExistingPeopleOntoOneTeam(): void
    {
        $hasPeople = DB::table('users')->exists()
            || DB::table('bookings')->exists()
            || DB::table('activities')->exists()
            || DB::table('venues')->exists();

        if (! $hasPeople) {
            return;
        }

        $now = now();
        $teamId = (string) Str::ulid();

        DB::table('teams')->insert([
            'id' => $teamId,
            'name' => 'Climbing',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('users')->orderBy('id')->each(function (object $user) use ($now, $teamId): void {
            $accreditations = $user->accreditations === '' ? [] : explode(',', (string) $user->accreditations);
            $runsBookings = $user->role === 'team-leader' || in_array('manage-bookings', $accreditations, true);
            $oversightOnly = in_array('manage-users', $accreditations, true) && ! $runsBookings;

            DB::table('users')->where('id', $user->id)->update([
                'suspended' => $user->role === 'suspended',
                'oversight' => $oversightOnly,
                'is_system_admin' => $user->role === 'team-leader',
            ]);

            DB::table('team_user')->insert([
                'id' => (string) Str::ulid(),
                'team_id' => $teamId,
                'user_id' => $user->id,
                'role' => $runsBookings ? 'manager' : 'member',
                'left_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        });

        foreach (DB::table('activities')->pluck('id') as $activityId) {
            DB::table('activity_team')->insert([
                'activity_id' => $activityId,
                'team_id' => $teamId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        foreach (DB::table('venues')->pluck('id') as $venueId) {
            DB::table('team_venue')->insert([
                'team_id' => $teamId,
                'venue_id' => $venueId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        DB::table('bookings')->whereNull('team_id')->update(['team_id' => $teamId]);
    }
};
