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
        Schema::create('program_intake_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('program_id');
            $table->string('key', 64);
            $table->string('label', 100);
            $table->string('field_type', 20);
            $table->boolean('is_required')->default(false);
            $table->unsignedSmallInteger('position');
            $table->timestamp('retired_at')->nullable();
            $table->timestamps();

            $table->foreign(['organisation_id', 'program_id'])
                ->references(['organisation_id', 'id'])->on('programs')->cascadeOnDelete();
            $table->unique(['organisation_id', 'id']);
            $table->unique(['organisation_id', 'program_id', 'key']);
            $table->index(['organisation_id', 'program_id', 'retired_at', 'position']);
        });

        Schema::create('program_eligibility_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('program_id');
            $table->string('key', 64);
            $table->string('label', 100);
            $table->boolean('is_required')->default(false);
            $table->unsignedSmallInteger('position');
            $table->timestamp('retired_at')->nullable();
            $table->timestamps();

            $table->foreign(['organisation_id', 'program_id'])
                ->references(['organisation_id', 'id'])->on('programs')->cascadeOnDelete();
            $table->unique(['organisation_id', 'id']);
            $table->unique(['organisation_id', 'program_id', 'key']);
            $table->index(['organisation_id', 'program_id', 'retired_at', 'position']);
        });

        Schema::create('program_risk_flags', function (Blueprint $table) {
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

            $this->backfillDefinitions((int) $program->organisation_id, (int) $program->id, $configuration, 'intake_fields', 'program_intake_fields', function (array $definition): array {
                return [
                    'field_type' => $definition['type'] ?? 'text',
                    'is_required' => (bool) ($definition['required'] ?? false),
                ];
            });
            $this->backfillDefinitions((int) $program->organisation_id, (int) $program->id, $configuration, 'eligibility_fields', 'program_eligibility_questions', fn (array $definition): array => [
                'is_required' => (bool) ($definition['required'] ?? false),
            ]);
            $this->backfillDefinitions((int) $program->organisation_id, (int) $program->id, $configuration, 'risk_flags', 'program_risk_flags');

            unset($configuration['intake_fields'], $configuration['eligibility_fields'], $configuration['risk_flags']);
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
            $configuration['intake_fields'] = DB::table('program_intake_fields')
                ->where('program_id', $program->id)
                ->orderBy('position')
                ->get(['key', 'label', 'field_type', 'is_required'])
                ->map(fn (object $field): array => [
                    'key' => $field->key,
                    'label' => $field->label,
                    'type' => $field->field_type,
                    'required' => (bool) $field->is_required,
                ])->all();
            $configuration['eligibility_fields'] = DB::table('program_eligibility_questions')
                ->where('program_id', $program->id)
                ->orderBy('position')
                ->get(['key', 'label', 'is_required'])
                ->map(fn (object $question): array => [
                    'key' => $question->key,
                    'label' => $question->label,
                    'required' => (bool) $question->is_required,
                ])
                ->all();
            $configuration['risk_flags'] = DB::table('program_risk_flags')
                ->where('program_id', $program->id)
                ->orderBy('position')
                ->get(['key', 'label'])
                ->map(fn (object $flag): array => ['key' => $flag->key, 'label' => $flag->label])
                ->all();

            DB::table('programs')->where('id', $program->id)->update([
                'configuration' => json_encode($configuration, JSON_THROW_ON_ERROR),
            ]);
        });

        Schema::dropIfExists('program_risk_flags');
        Schema::dropIfExists('program_eligibility_questions');
        Schema::dropIfExists('program_intake_fields');
    }

    /**
     * @param  array<string, mixed>  $configuration
     * @param  (callable(array<string, mixed>): array<string, mixed>)|null  $additionalAttributes
     */
    private function backfillDefinitions(int $organisationId, int $programId, array $configuration, string $configurationKey, string $table, ?callable $additionalAttributes = null): void
    {
        $definitions = is_array($configuration[$configurationKey] ?? null) ? $configuration[$configurationKey] : [];

        foreach (array_values($definitions) as $position => $definition) {
            if (! is_array($definition) || ! isset($definition['key'], $definition['label'])) {
                continue;
            }

            DB::table($table)->insert([
                'organisation_id' => $organisationId,
                'program_id' => $programId,
                'key' => $definition['key'],
                'label' => $definition['label'],
                'position' => $position,
                'created_at' => now(),
                'updated_at' => now(),
                ...($additionalAttributes === null ? [] : $additionalAttributes($definition)),
            ]);
        }
    }
};
