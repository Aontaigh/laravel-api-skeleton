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
     * Link auth audit rows to API clients for client-credentials exchanges.
     */
    public function up(): void
    {
        Schema::table('auth_audit_logs', function (Blueprint $table): void {
            $table->foreignId('api_client_id')
                ->nullable()
                ->after('personal_access_token_id')
                ->constrained('api_clients')
                ->nullOnDelete();

            $table->index(['api_client_id', 'created_at']);
        });
    }

    /**
     * Remove the API client link from auth audit rows.
     */
    public function down(): void
    {
        Schema::table('auth_audit_logs', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('api_client_id');
        });
    }
};
