<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds actor attribution to `auth_audit_logs`.
 *
 * `user_id` records the affected User (the revoked session's owner, the
 * suspended account). Privileged actions - an admin revoking another User's
 * session, a force-logout sweep - previously had no way to name the
 * administrator who performed them. Nullable: rows written by console
 * commands, queue retries, or system events have no human actor.
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
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('auth_audit_logs', function (Blueprint $table): void {
            $table->foreignId('actor_user_id')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('auth_audit_logs', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('actor_user_id');
        });
    }
};
