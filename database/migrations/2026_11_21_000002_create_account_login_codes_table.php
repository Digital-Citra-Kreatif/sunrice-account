<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Emailed sign-in codes: one per user, hashed, tied to the browser that asked for it.
        Schema::create('account_login_codes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->string('code_hash');
            $table->string('origin', 64);
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at')->index();
            $table->timestamps();
        });

        // Sign-in providers linked to a user (Google).
        Schema::create('account_social_accounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('provider', 30);
            $table->string('provider_id');
            $table->string('email')->nullable();
            $table->string('avatar')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'provider_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_social_accounts');
        Schema::dropIfExists('account_login_codes');
    }
};
