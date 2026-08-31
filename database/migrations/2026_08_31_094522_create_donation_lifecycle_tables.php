<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fundraising_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->boolean('is_simulated')->default(true);
            $table->timestamps();
            $table->unique(['organisation_id', 'id']);
            $table->unique(['organisation_id', 'slug']);
        });

        Schema::create('donation_funds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->boolean('is_simulated')->default(true);
            $table->timestamps();
            $table->unique(['organisation_id', 'id']);
            $table->unique(['organisation_id', 'slug']);
        });

        Schema::create('donations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('party_id');
            $table->foreignId('fundraising_campaign_id')->nullable();
            $table->foreignId('donation_fund_id');
            $table->string('frequency', 32);
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);
            $table->string('source_code', 64);
            $table->uuid('idempotency_key');
            $table->boolean('is_simulated')->default(true);
            $table->timestamps();
            $table->unique(['organisation_id', 'id']);
            $table->unique(['organisation_id', 'idempotency_key']);
            $table->foreign(['organisation_id', 'party_id'])->references(['organisation_id', 'id'])->on('parties')->restrictOnDelete();
            $table->foreign(['organisation_id', 'fundraising_campaign_id'])->references(['organisation_id', 'id'])->on('fundraising_campaigns')->restrictOnDelete();
            $table->foreign(['organisation_id', 'donation_fund_id'])->references(['organisation_id', 'id'])->on('donation_funds')->restrictOnDelete();
            $table->index(['organisation_id', 'fundraising_campaign_id', 'created_at']);
        });

        Schema::create('recurring_mandates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->uuid('donation_id');
            $table->foreignId('party_id');
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);
            $table->string('interval', 32)->default('monthly');
            $table->string('status', 32)->default('pending');
            $table->unsignedInteger('version')->default(1);
            $table->string('provider_mandate_id')->unique();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->unique(['organisation_id', 'id']);
            $table->unique(['organisation_id', 'donation_id']);
            $table->foreign(['organisation_id', 'donation_id'])->references(['organisation_id', 'id'])->on('donations')->restrictOnDelete();
            $table->foreign(['organisation_id', 'party_id'])->references(['organisation_id', 'id'])->on('parties')->restrictOnDelete();
            $table->index(['organisation_id', 'status']);
        });

        Schema::create('donation_payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->uuid('donation_id');
            $table->uuid('recurring_mandate_id')->nullable();
            $table->unsignedInteger('attempt_number');
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);
            $table->string('status', 32)->default('created');
            $table->unsignedInteger('version')->default(1);
            $table->uuid('idempotency_key');
            $table->string('provider_payment_id')->unique();
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();
            $table->unique(['organisation_id', 'id']);
            $table->unique(['organisation_id', 'idempotency_key']);
            $table->unique(['organisation_id', 'donation_id', 'attempt_number']);
            $table->foreign(['organisation_id', 'donation_id'])->references(['organisation_id', 'id'])->on('donations')->restrictOnDelete();
            $table->foreign(['organisation_id', 'recurring_mandate_id'])->references(['organisation_id', 'id'])->on('recurring_mandates')->restrictOnDelete();
            $table->index(['organisation_id', 'status', 'settled_at']);
        });

        Schema::create('donation_payment_events', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->uuid('donation_payment_id');
            $table->uuid('idempotency_key');
            $table->string('provider_event_id')->unique();
            $table->string('from_status', 32);
            $table->string('to_status', 32);
            $table->timestamp('occurred_at');
            $table->unique(['organisation_id', 'idempotency_key']);
            $table->foreign(['organisation_id', 'donation_payment_id'])->references(['organisation_id', 'id'])->on('donation_payments')->restrictOnDelete();
            $table->index(['organisation_id', 'donation_payment_id', 'occurred_at']);
        });

        Schema::create('recurring_mandate_events', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->uuid('recurring_mandate_id');
            $table->uuid('donation_payment_id')->nullable();
            $table->uuid('idempotency_key');
            $table->string('provider_event_id')->unique();
            $table->string('from_status', 32);
            $table->string('to_status', 32);
            $table->timestamp('occurred_at');
            $table->unique(['organisation_id', 'idempotency_key']);
            $table->foreign(['organisation_id', 'recurring_mandate_id'])->references(['organisation_id', 'id'])->on('recurring_mandates')->restrictOnDelete();
            $table->foreign(['organisation_id', 'donation_payment_id'])->references(['organisation_id', 'id'])->on('donation_payments')->restrictOnDelete();
            $table->index(['organisation_id', 'recurring_mandate_id', 'occurred_at']);
        });

        Schema::create('donation_refunds', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->uuid('donation_payment_id');
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);
            $table->uuid('idempotency_key');
            $table->string('provider_refund_id')->unique();
            $table->timestamp('occurred_at');
            $table->unique(['organisation_id', 'id']);
            $table->unique(['organisation_id', 'idempotency_key']);
            $table->foreign(['organisation_id', 'donation_payment_id'])->references(['organisation_id', 'id'])->on('donation_payments')->restrictOnDelete();
            $table->index(['organisation_id', 'donation_payment_id', 'occurred_at']);
        });

        Schema::create('donation_receipts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->uuid('donation_id');
            $table->uuid('donation_payment_id');
            $table->string('receipt_number')->unique();
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);
            $table->string('marker')->default('Demo—Not a tax receipt');
            $table->timestamp('issued_at');
            $table->unique(['organisation_id', 'id']);
            $table->unique(['organisation_id', 'donation_payment_id']);
            $table->foreign(['organisation_id', 'donation_id'])->references(['organisation_id', 'id'])->on('donations')->restrictOnDelete();
            $table->foreign(['organisation_id', 'donation_payment_id'])->references(['organisation_id', 'id'])->on('donation_payments')->restrictOnDelete();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE donations ADD CONSTRAINT donations_amount_positive CHECK (amount_minor > 0), ADD CONSTRAINT donations_simulated_only CHECK (is_simulated = true)');
            DB::statement("ALTER TABLE donation_payments ADD CONSTRAINT donation_payments_amount_positive CHECK (amount_minor > 0), ADD CONSTRAINT donation_payments_provider_simulated CHECK (left(provider_payment_id, 4) = 'sim_')");
            DB::statement("ALTER TABLE recurring_mandates ADD CONSTRAINT recurring_mandates_amount_positive CHECK (amount_minor > 0), ADD CONSTRAINT recurring_mandates_provider_simulated CHECK (left(provider_mandate_id, 4) = 'sim_')");
            DB::statement("ALTER TABLE donation_refunds ADD CONSTRAINT donation_refunds_amount_positive CHECK (amount_minor > 0), ADD CONSTRAINT donation_refunds_provider_simulated CHECK (left(provider_refund_id, 4) = 'sim_')");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('donation_receipts');
        Schema::dropIfExists('donation_refunds');
        Schema::dropIfExists('recurring_mandate_events');
        Schema::dropIfExists('donation_payment_events');
        Schema::dropIfExists('donation_payments');
        Schema::dropIfExists('recurring_mandates');
        Schema::dropIfExists('donations');
        Schema::dropIfExists('donation_funds');
        Schema::dropIfExists('fundraising_campaigns');
    }
};
