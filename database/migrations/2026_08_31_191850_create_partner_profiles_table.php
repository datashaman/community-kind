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
        Schema::create('partner_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('party_id');
            $table->string('partner_type', 50);
            $table->string('status', 32)->default('active');
            $table->text('relationship_summary');
            $table->timestamp('engaged_at');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->foreign(['organisation_id', 'party_id'])->references(['organisation_id', 'id'])->on('parties')->restrictOnDelete();
            $table->unique(['organisation_id', 'id']);
            $table->unique(['organisation_id', 'party_id']);
            $table->index(['organisation_id', 'status', 'partner_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('partner_profiles');
    }
};
