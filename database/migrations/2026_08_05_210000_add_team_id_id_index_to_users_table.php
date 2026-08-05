<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a composite index for the default User list sort (`id` ascending)
 * within a Team scope.
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
        if (! Schema::hasIndex('users', ['team_id', 'id'])) {
            Schema::table('users', function (Blueprint $table): void {
                $table->index(['team_id', 'id']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['team_id', 'id']);
        });
    }
};
