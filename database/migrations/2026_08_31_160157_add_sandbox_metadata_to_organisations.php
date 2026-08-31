<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('organisations', function (Blueprint $table) {
            $table->foreignUuid('sandbox_pair_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->string('sandbox_template')->nullable()->after('sandbox_pair_id');
            $table->unsignedInteger('demo_generation')->default(0)->after('sandbox_template');
            $table->boolean('is_synthetic')->default(false)->after('demo_generation');
            $table->index(['sandbox_pair_id', 'demo_generation']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('organisations', function (Blueprint $table) {
            $table->dropIndex(['sandbox_pair_id', 'demo_generation']);
            $table->dropConstrainedForeignId('sandbox_pair_id');
            $table->dropColumn(['sandbox_template', 'demo_generation', 'is_synthetic']);
        });
    }
};
