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
     * Create the auth audit log table.
     */
    public function up(): void
    {
        Schema::create('auth_audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event');
            $table->string('email')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->unsignedBigInteger('personal_access_token_id')->nullable();
            $table->boolean('remember_me')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['event', 'created_at']);
            $table->index('email');
        });
    }

    /**
     * Drop the auth audit log table.
     */
    public function down(): void
    {
        Schema::dropIfExists('auth_audit_logs');
    }
};
