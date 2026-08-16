<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds the enrolled multi-factor channel (`mfa_method`) to the users table.
     * Null means two-factor is disabled; only `email` is currently supported.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('mfa_method')->nullable()->after('suspended_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('mfa_method');
        });
    }
};
