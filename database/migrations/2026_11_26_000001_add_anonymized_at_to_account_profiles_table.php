<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // When staff removed the account's personal data (Administration → Accounts).
        Schema::table('account_profiles', function (Blueprint $table) {
            $table->timestamp('anonymized_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('account_profiles', function (Blueprint $table) {
            $table->dropIndex(['anonymized_at']);
            $table->dropColumn('anonymized_at');
        });
    }
};
