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
        Schema::create('in_kind_offers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('party_id');
            $table->string('category', 100);
            $table->text('description');
            $table->decimal('quantity', 12, 2);
            $table->string('unit', 50);
            $table->unsignedBigInteger('estimated_value_minor')->nullable();
            $table->string('currency', 3)->nullable();
            $table->string('condition', 100);
            $table->string('status', 32)->default('offered');
            $table->text('fulfilment_outcome')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('offered_at');
            $table->timestamp('fulfilled_at')->nullable();
            $table->foreignId('transitioned_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->foreign(['organisation_id', 'party_id'])->references(['organisation_id', 'id'])->on('parties')->restrictOnDelete();
            $table->unique(['organisation_id', 'id']);
            $table->index(['organisation_id', 'status', 'offered_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('in_kind_offers');
    }
};
