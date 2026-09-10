<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the request correlation ID to `auth_audit_logs`.
 *
 * Nullable: rows written by console commands or queue retries outside an HTTP
 * request carry no ID. Join `request_id` to response headers and log lines to
 * trace one call across all three surfaces.
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
            $table->string('request_id', 128)->nullable()->after('id');
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
            $table->dropColumn('request_id');
        });
    }
};
