<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds Team scoping to `users`.
 */
return new class extends Migration
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'team_id')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->foreignId('team_id')->nullable()->after('id')->constrained()->nullOnDelete();
            });
        }

        Schema::table('users', function (Blueprint $table): void {
            /*
             * User list queries are typically scoped by `team_id` first, so each
             * composite index leads with `team_id`. Without one covering the
             * default sort column, ordering by `id` filesorts the whole Team
             * on every request.
             */
            if (! Schema::hasIndex('users', ['team_id', 'name'])) {
                $table->index(['team_id', 'name']);
            }

            if (! Schema::hasIndex('users', ['team_id', 'created_at'])) {
                $table->index(['team_id', 'created_at']);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['team_id', 'name']);
            $table->dropIndex(['team_id', 'created_at']);
            $table->dropConstrainedForeignId('team_id');
        });
    }
};
