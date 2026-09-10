<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the `system_health_checks` table.
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
        Schema::create('system_health_checks', function (Blueprint $table): void {
            $table->id();
            $table->string('component')->index();
            $table->string('status');
            $table->unsignedInteger('response_time_ms')->nullable();
            /*
             * Capped at 255 characters and never a stack trace - see
             * SystemHealthCheckResult, which bounds every message before it
             * can reach this column or the public status endpoint.
             */
            $table->string('message', 255)->nullable();
            $table->timestamp('checked_at')->index();
            $table->timestamps();

            /*
             * SystemHealthHistoryQuery always filters by component first, then
             * ranges over checked_at (latest-per-component, daily aggregation) -
             * a composite index serves both without a second lookup.
             */
            $table->index(['component', 'checked_at']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('system_health_checks');
    }
};
