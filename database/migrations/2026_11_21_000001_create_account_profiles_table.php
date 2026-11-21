<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A user's site account details. No foreign key: the users table is the app's.
        Schema::create('account_profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->string('phone', 32)->nullable()->index();
            $table->string('locale', 10)->nullable();
            $table->boolean('accepts_marketing')->default(false);
            $table->timestamp('marketing_consent_at')->nullable();
            $table->boolean('password_set')->default(true);
            $table->timestamp('deletion_requested_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_profiles');
    }
};
