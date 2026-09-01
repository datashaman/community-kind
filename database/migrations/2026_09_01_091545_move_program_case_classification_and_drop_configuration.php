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
            $table->string('case_default_classification', 32)->default('confidential')->after('case_label');
        });

        DB::table('programs')->orderBy('id')->each(function (object $program): void {
            $decodedConfiguration = is_string($program->configuration)
                ? json_decode($program->configuration, true, flags: JSON_THROW_ON_ERROR)
                : (array) $program->configuration;
            if (! is_array($decodedConfiguration)) {
                throw new RuntimeException("Program {$program->id} contains a malformed legacy configuration document.");
            }
            $configuration = $decodedConfiguration;
            $classification = $configuration['case_default_classification'] ?? 'confidential';
            unset($configuration['case_default_classification']);

            if ($configuration !== []) {
                throw new RuntimeException("Program {$program->id} still contains legacy configuration keys: ".implode(', ', array_keys($configuration)));
            }
            if (! in_array($classification, ['confidential', 'highly_restricted'], true)) {
                throw new RuntimeException("Program {$program->id} contains an invalid default case classification.");
            }

            DB::table('programs')->where('id', $program->id)->update([
                'case_default_classification' => $classification,
            ]);
        });

        Schema::table('programs', function (Blueprint $table) {
            $table->dropColumn('configuration');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('programs', function (Blueprint $table) {
            $table->json('configuration')->default('{}');
        });

        DB::table('programs')->orderBy('id')->each(function (object $program): void {
            $configuration = $program->case_default_classification === 'confidential'
                ? []
                : ['case_default_classification' => $program->case_default_classification];
            DB::table('programs')->where('id', $program->id)->update([
                'configuration' => json_encode($configuration, JSON_THROW_ON_ERROR),
            ]);
        });

        Schema::table('programs', function (Blueprint $table) {
            $table->dropColumn('case_default_classification');
        });
    }
};
