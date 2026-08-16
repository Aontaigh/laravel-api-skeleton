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
     * Run the migrations.
     *
     * Adds a monotonically-increasing session version to the User. Bumping it
     * invalidates every existing web session on the next request, so a
     * credential change or admin force-logout signs the User out everywhere —
     * without depending on the session driver being the database.
     */
    public function up(): void
    {
        Schema::table('users', static function (Blueprint $table): void {
            $table->unsignedBigInteger('session_version')->default(0)->after('suspended_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', static function (Blueprint $table): void {
            $table->dropColumn('session_version');
        });
    }
};
