<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('programs', function (Blueprint $table) {
            $table->string('request_label', 100)->default('Request')->after('slug');
            $table->string('case_label', 100)->default('Case')->after('request_label');
        });

        Schema::create('program_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('program_id');
            $table->string('key', 64);
            $table->string('label', 100);
            $table->unsignedSmallInteger('position');
            $table->timestamp('retired_at')->nullable();
            $table->timestamps();

            $table->foreign(['organisation_id', 'program_id'])
                ->references(['organisation_id', 'id'])->on('programs')->cascadeOnDelete();
            $table->unique(['organisation_id', 'id']);
            $table->unique(['organisation_id', 'program_id', 'key']);
            $table->index(['organisation_id', 'program_id', 'retired_at', 'position']);
        });

        DB::table('programs')->orderBy('id')->each(function (object $program): void {
            $configuration = is_string($program->configuration)
                ? json_decode($program->configuration, true, flags: JSON_THROW_ON_ERROR)
                : (array) $program->configuration;
            $labels = is_array($configuration['labels'] ?? null) ? $configuration['labels'] : [];
            $stages = is_array($configuration['stages'] ?? null) ? $configuration['stages'] : [];

            DB::table('programs')->where('id', $program->id)->update([
                'request_label' => $labels['request'] ?? 'Request',
                'case_label' => $labels['case'] ?? 'Case',
            ]);

            foreach (array_values($stages) as $position => $stage) {
                if (! is_array($stage) || ! isset($stage['key'], $stage['label'])) {
                    continue;
                }

                DB::table('program_stages')->insert([
                    'organisation_id' => $program->organisation_id,
                    'program_id' => $program->id,
                    'key' => $stage['key'],
                    'label' => $stage['label'],
                    'position' => $position,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            unset($configuration['labels'], $configuration['stages']);
            DB::table('programs')->where('id', $program->id)->update([
                'configuration' => json_encode($configuration, JSON_THROW_ON_ERROR),
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('programs')->orderBy('id')->each(function (object $program): void {
            $configuration = is_string($program->configuration)
                ? json_decode($program->configuration, true, flags: JSON_THROW_ON_ERROR)
                : (array) $program->configuration;
            $configuration['labels'] = [
                'request' => $program->request_label,
                'case' => $program->case_label,
            ];
            $configuration['stages'] = DB::table('program_stages')
                ->where('program_id', $program->id)
                ->orderBy('position')
                ->get(['key', 'label'])
                ->map(fn (object $stage): array => ['key' => $stage->key, 'label' => $stage->label])
                ->all();

            DB::table('programs')->where('id', $program->id)->update([
                'configuration' => json_encode($configuration, JSON_THROW_ON_ERROR),
            ]);
        });

        Schema::dropIfExists('program_stages');

        Schema::table('programs', function (Blueprint $table) {
            $table->dropColumn(['request_label', 'case_label']);
        });
    }
};
