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
     * Create the API client credentials table.
     */
    public function up(): void
    {
        Schema::create('api_clients', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('client_id')->unique();
            $table->string('client_secret');
            $table->json('abilities');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'client_id']);
            $table->index('user_id');
        });
    }

    /**
     * Drop the API client credentials table.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_clients');
    }
};
