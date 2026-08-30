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
            $table->timestamp('status_changed_at')->nullable()->after('status');
            $table->timestamp('deletion_scheduled_for')->nullable()->after('status_changed_at');
            $table->timestamp('signed_links_invalidated_at')->nullable()->after('deletion_scheduled_for');
            $table->unsignedBigInteger('access_version')->default(1)->after('signed_links_invalidated_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('organisations', function (Blueprint $table) {
            $table->dropColumn([
                'status_changed_at',
                'deletion_scheduled_for',
                'signed_links_invalidated_at',
                'access_version',
            ]);
        });
    }
};
