<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the refusal reason to `auth_audit_logs`.
 *
 * `outcome` already distinguishes a deliberate refusal from a failed attempt, but
 * it cannot say which rule the client failed. Recording the reason separately lets a
 * forensic query separate a suspended owner from a client still pointed at a human
 * from an orphaned client.
 *
 * The column is scoped to client-credential ineligibility rather than refusal in
 * general, so a null here means "not a client-credential refusal" - never "a refusal
 * we did not classify". Nullable with no index, matching the `outcome` precedent:
 * reason filtering rides the existing event index as a rare forensic query.
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
            $table->string('client_ineligibility_reason')->nullable()->after('outcome');
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
            $table->dropColumn('client_ineligibility_reason');
        });
    }
};
