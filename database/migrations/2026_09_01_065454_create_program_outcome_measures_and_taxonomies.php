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
        Schema::create('program_outcome_measures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('program_id');
            $table->string('key', 64);
            $table->string('label', 100);
            $table->string('unit', 50)->nullable();
            $table->unsignedSmallInteger('position');
            $table->timestamp('retired_at')->nullable();
            $table->timestamps();
            $table->foreign(['organisation_id', 'program_id'])->references(['organisation_id', 'id'])->on('programs')->cascadeOnDelete();
            $table->unique(['organisation_id', 'id']);
            $table->unique(['organisation_id', 'program_id', 'key']);
            $table->index(['organisation_id', 'program_id', 'retired_at', 'position']);
        });

        Schema::create('program_taxonomies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('program_id');
            $table->string('key', 64);
            $table->string('label', 100);
            $table->unsignedSmallInteger('position');
            $table->timestamp('retired_at')->nullable();
            $table->timestamps();
            $table->foreign(['organisation_id', 'program_id'])->references(['organisation_id', 'id'])->on('programs')->cascadeOnDelete();
            $table->unique(['organisation_id', 'id']);
            $table->unique(['organisation_id', 'program_id', 'key']);
            $table->index(['organisation_id', 'program_id', 'retired_at', 'position']);
        });

        Schema::create('program_taxonomy_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('program_taxonomy_id');
            $table->string('key', 64);
            $table->string('label', 100);
            $table->unsignedSmallInteger('position');
            $table->timestamp('retired_at')->nullable();
            $table->timestamps();
            $table->foreign(['organisation_id', 'program_taxonomy_id'])->references(['organisation_id', 'id'])->on('program_taxonomies')->cascadeOnDelete();
            $table->unique(['organisation_id', 'id']);
            $table->unique(['organisation_id', 'program_taxonomy_id', 'key']);
            $table->index(['organisation_id', 'program_taxonomy_id', 'retired_at', 'position']);
        });

        DB::table('programs')->orderBy('id')->each(function (object $program): void {
            $configuration = is_string($program->configuration)
                ? json_decode($program->configuration, true, flags: JSON_THROW_ON_ERROR)
                : (array) $program->configuration;

            foreach (array_values($configuration['outcome_measures'] ?? []) as $position => $measure) {
                if (! is_array($measure) || ! isset($measure['key'], $measure['label'])) {
                    continue;
                }

                DB::table('program_outcome_measures')->insert([
                    'organisation_id' => $program->organisation_id,
                    'program_id' => $program->id,
                    'key' => $measure['key'],
                    'label' => $measure['label'],
                    'unit' => $measure['unit'] ?? null,
                    'position' => $position,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            foreach (array_values($configuration['taxonomies'] ?? []) as $position => $taxonomy) {
                if (! is_array($taxonomy) || ! isset($taxonomy['key'], $taxonomy['label'])) {
                    continue;
                }

                $taxonomyId = DB::table('program_taxonomies')->insertGetId([
                    'organisation_id' => $program->organisation_id,
                    'program_id' => $program->id,
                    'key' => $taxonomy['key'],
                    'label' => $taxonomy['label'],
                    'position' => $position,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $usedKeys = [];

                foreach (array_values($taxonomy['values'] ?? []) as $valuePosition => $label) {
                    if (! is_string($label)) {
                        continue;
                    }

                    $base = Str::of($label)->ascii()->snake()->limit(56, '')->toString() ?: 'value';
                    $key = $base;
                    $suffix = 2;

                    while (in_array($key, $usedKeys, true)) {
                        $key = Str::limit($base, 56, '').'_'.$suffix;
                        $suffix++;
                    }
                    $usedKeys[] = $key;

                    DB::table('program_taxonomy_values')->insert([
                        'organisation_id' => $program->organisation_id,
                        'program_taxonomy_id' => $taxonomyId,
                        'key' => $key,
                        'label' => $label,
                        'position' => $valuePosition,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            unset($configuration['outcome_measures'], $configuration['taxonomies']);
            DB::table('programs')->where('id', $program->id)->update([
                'configuration' => json_encode($configuration, JSON_THROW_ON_ERROR),
            ]);
        });
    }

    public function down(): void
    {
        DB::table('programs')->orderBy('id')->each(function (object $program): void {
            $configuration = is_string($program->configuration)
                ? json_decode($program->configuration, true, flags: JSON_THROW_ON_ERROR)
                : (array) $program->configuration;
            $configuration['outcome_measures'] = DB::table('program_outcome_measures')
                ->where('program_id', $program->id)
                ->orderBy('position')
                ->get(['key', 'label', 'unit'])
                ->map(fn (object $measure): array => ['key' => $measure->key, 'label' => $measure->label, 'unit' => $measure->unit])
                ->all();
            $configuration['taxonomies'] = DB::table('program_taxonomies')
                ->where('program_id', $program->id)
                ->orderBy('position')
                ->get(['id', 'key', 'label'])
                ->map(fn (object $taxonomy): array => [
                    'key' => $taxonomy->key,
                    'label' => $taxonomy->label,
                    'values' => DB::table('program_taxonomy_values')
                        ->where('program_taxonomy_id', $taxonomy->id)
                        ->orderBy('position')
                        ->pluck('label')
                        ->all(),
                ])->all();

            DB::table('programs')->where('id', $program->id)->update([
                'configuration' => json_encode($configuration, JSON_THROW_ON_ERROR),
            ]);
        });

        Schema::dropIfExists('program_taxonomy_values');
        Schema::dropIfExists('program_taxonomies');
        Schema::dropIfExists('program_outcome_measures');
    }
};
