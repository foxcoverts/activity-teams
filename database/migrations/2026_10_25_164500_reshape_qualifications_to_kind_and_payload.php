<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var array<string, string>
     */
    private array $mountainTrainingNames = [
        'ICA' => 'Indoor Climbing Assistant',
        'CWI' => 'Climbing Wall Instructor',
        'CWI Abseil' => 'Climbing Wall Instructor (Abseil)',
        'CWDI' => 'Climbing Wall Development Instructor',
        'RCI' => 'Rock Climbing Instructor',
        'RCDI' => 'Rock Climbing Development Instructor',
        'ML' => 'Mountain Leader',
        'MCI' => 'Mountaineering and Climbing Instructor',
        'WMCI' => 'Winter Mountaineering and Climbing Instructor',
    ];

    /**
     * @var array<string, string>
     */
    private array $kinds = [
        'App\\Models\\ScoutPermit' => 'scout_permit',
        'App\\Models\\MountainTrainingQualification' => 'mountain_training',
        'App\\Models\\GirlguidingQualification' => 'girlguiding',
    ];

    public function up(): void
    {
        if (! Schema::hasColumn('qualifications', 'kind')) {
            Schema::table('qualifications', function (Blueprint $table): void {
                $table->string('kind')->nullable()->after('user_id');
            });
        }

        if (! Schema::hasColumn('qualifications', 'payload')) {
            Schema::table('qualifications', function (Blueprint $table): void {
                $table->json('payload')->nullable()->after('kind');
            });
        }

        if (! Schema::hasColumn('activity_qualification_requirements', 'kind')) {
            Schema::table('activity_qualification_requirements', function (Blueprint $table): void {
                $table->string('kind')->nullable()->after('activity_id');
            });
        }

        if (! Schema::hasColumn('activity_qualification_requirements', 'criteria')) {
            Schema::table('activity_qualification_requirements', function (Blueprint $table): void {
                $table->json('criteria')->nullable()->after('kind');
            });
        }

        $this->migrateQualifications();
        $this->migrateRequirements();

        Schema::table('qualifications', function (Blueprint $table): void {
            $table->dropMorphs('detail');
            $table->string('kind')->nullable(false)->change();
            $table->json('payload')->nullable(false)->change();
            $table->index('kind');
        });

        Schema::table('activity_qualification_requirements', function (Blueprint $table): void {
            $table->dropColumn([
                'detail_type',
                'permit_activity',
                'category',
                'permit_type',
                'award',
                'scheme',
                'level',
            ]);
            $table->string('kind')->nullable(false)->change();
        });

        Schema::dropIfExists('scout_permits');
        Schema::dropIfExists('mountain_training_qualifications');
        Schema::dropIfExists('girlguiding_qualifications');
    }

    public function down(): void
    {
        Schema::create('scout_permits', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('activity');
            $table->string('category')->nullable();
            $table->string('permit_type');
            $table->text('restrictions')->nullable();
            $table->timestamps();
        });

        Schema::create('mountain_training_qualifications', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('award');
            $table->timestamps();
        });

        Schema::create('girlguiding_qualifications', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('scheme');
            $table->string('level');
            $table->timestamps();
        });

        Schema::table('qualifications', function (Blueprint $table): void {
            $table->dropIndex(['kind']);
            $table->ulidMorphs('detail');
        });

        Schema::table('activity_qualification_requirements', function (Blueprint $table): void {
            $table->string('detail_type')->nullable();
            $table->json('permit_activity')->nullable();
            $table->json('category')->nullable();
            $table->json('permit_type')->nullable();
            $table->json('award')->nullable();
            $table->json('scheme')->nullable();
            $table->json('level')->nullable();
        });

        Schema::table('qualifications', function (Blueprint $table): void {
            $table->dropColumn(['kind', 'payload']);
        });

        Schema::table('activity_qualification_requirements', function (Blueprint $table): void {
            $table->dropColumn(['kind', 'criteria']);
        });
    }

    private function migrateQualifications(): void
    {
        foreach (DB::table('qualifications')->orderBy('id')->get() as $qualification) {
            if ($qualification->detail_type === 'App\\Models\\ScoutPermit') {
                $detail = DB::table('scout_permits')->where('id', $qualification->detail_id)->first();

                if ($detail === null || ($detail->activity === 'Archery' && $detail->category === null)) {
                    DB::table('qualifications')->where('id', $qualification->id)->delete();

                    continue;
                }

                DB::table('qualifications')->where('id', $qualification->id)->update([
                    'kind' => 'scout_permit',
                    'payload' => json_encode(array_filter([
                        'activity' => $detail->activity,
                        'category' => $detail->category,
                        'permit_type' => $detail->permit_type,
                        'restrictions' => $detail->restrictions,
                    ], fn (mixed $value): bool => $value !== null && $value !== '')),
                ]);

                continue;
            }

            if ($qualification->detail_type === 'App\\Models\\MountainTrainingQualification') {
                $detail = DB::table('mountain_training_qualifications')->where('id', $qualification->detail_id)->first();

                if ($detail === null) {
                    DB::table('qualifications')->where('id', $qualification->id)->delete();

                    continue;
                }

                DB::table('qualifications')->where('id', $qualification->id)->update([
                    'kind' => 'mountain_training',
                    'payload' => json_encode([
                        'award' => $this->mountainTrainingNames[$detail->award] ?? $detail->award,
                    ]),
                    'expires_on' => null,
                ]);

                continue;
            }

            if ($qualification->detail_type === 'App\\Models\\GirlguidingQualification') {
                $detail = DB::table('girlguiding_qualifications')->where('id', $qualification->detail_id)->first();

                if ($detail === null) {
                    DB::table('qualifications')->where('id', $qualification->id)->delete();

                    continue;
                }

                DB::table('qualifications')->where('id', $qualification->id)->update([
                    'kind' => 'girlguiding',
                    'payload' => json_encode([
                        'scheme' => $detail->scheme === 'climbing' ? 'Climbing' : $detail->scheme,
                        'level' => $detail->level,
                    ]),
                ]);
            }
        }
    }

    private function migrateRequirements(): void
    {
        foreach (DB::table('activity_qualification_requirements')->orderBy('id')->get() as $requirement) {
            $kind = $this->kinds[$requirement->detail_type] ?? null;

            if ($kind === null) {
                DB::table('activity_qualification_requirements')->where('id', $requirement->id)->delete();

                continue;
            }

            $rows = $this->requirementRows($kind, $requirement);

            foreach ($rows as $index => $criteria) {
                $encoded = $criteria === [] ? null : json_encode($criteria);

                if ($index === 0) {
                    DB::table('activity_qualification_requirements')->where('id', $requirement->id)->update([
                        'kind' => $kind,
                        'criteria' => $encoded,
                    ]);

                    continue;
                }

                $alreadySplit = DB::table('activity_qualification_requirements')
                    ->where('activity_id', $requirement->activity_id)
                    ->where('kind', $kind)
                    ->where('criteria', $encoded)
                    ->exists();

                if ($alreadySplit) {
                    continue;
                }

                DB::table('activity_qualification_requirements')->insert([
                    'id' => (string) str()->ulid(),
                    'activity_id' => $requirement->activity_id,
                    'detail_type' => $requirement->detail_type,
                    'kind' => $kind,
                    'criteria' => $encoded,
                    'sort' => $requirement->sort + $index,
                    'created_at' => $requirement->created_at,
                    'updated_at' => $requirement->updated_at,
                ]);
            }
        }
    }

    /**
     * @return list<array<string, string>>
     */
    private function requirementRows(string $kind, object $requirement): array
    {
        $lists = match ($kind) {
            'scout_permit' => [
                'activity' => $this->decodeList($requirement->permit_activity),
                'category' => $this->decodeList($requirement->category),
                'permit_type' => $this->decodeList($requirement->permit_type),
            ],
            'mountain_training' => [
                'award' => array_map(
                    fn (string $award): string => $this->mountainTrainingNames[$award] ?? $award,
                    $this->decodeList($requirement->award),
                ),
            ],
            'girlguiding' => [
                'scheme' => array_map(
                    fn (string $scheme): string => $scheme === 'climbing' ? 'Climbing' : $scheme,
                    $this->decodeList($requirement->scheme),
                ),
                'level' => $this->decodeList($requirement->level),
            ],
            default => [],
        };

        $keys = array_keys(array_filter($lists, fn (array $values): bool => $values !== []));

        if ($keys === []) {
            return [[]];
        }

        $rows = [[]];

        foreach ($keys as $key) {
            $next = [];

            foreach ($rows as $row) {
                foreach ($lists[$key] as $value) {
                    $next[] = [...$row, $key => $value];
                }
            }

            $rows = $next;
        }

        return $rows;
    }

    /**
     * @return list<string>
     */
    private function decodeList(mixed $value): array
    {
        if (blank($value)) {
            return [];
        }

        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, fn (mixed $item): bool => is_string($item) && $item !== ''));
    }
};
