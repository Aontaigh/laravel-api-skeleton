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
     * Store the resolved city and country on the web session registry.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('web_sessions', function (Blueprint $table): void {
            $table->string('location_city')->nullable()->after('user_agent');
            $table->char('location_country', 2)->nullable()->after('location_city');
        });
    }

    /**
     * Remove the resolved city and country from the registry.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('web_sessions', function (Blueprint $table): void {
            $table->dropColumn(['location_city', 'location_country']);
        });
    }
};
