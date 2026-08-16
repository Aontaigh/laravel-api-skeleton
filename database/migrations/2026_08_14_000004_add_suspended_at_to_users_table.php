<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Add a suspension marker to the users table.
     *
     * A non-null `suspended_at` means the account is suspended and must be
     * turned away at the gate regardless of how it authenticated (Bearer
     * token or cookie session).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('suspended_at')->nullable()->after('is_service_account');
        });
    }

    /**
     * Drop the suspension marker.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('suspended_at');
        });
    }
};
