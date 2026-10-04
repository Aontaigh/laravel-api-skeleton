<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the outcome to `auth_audit_logs`.
 *
 * Nullable: rows written before this migration stay null, and every dispatch
 * site now records `succeeded`, `failed`, or `refused` so an audit row always
 * answers whether the recorded activity worked.
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
            $table->string('outcome')->nullable()->after('event');
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
            $table->dropColumn('outcome');
        });
    }
};
