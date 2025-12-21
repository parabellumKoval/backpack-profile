<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('ak_profiles', function (Blueprint $table) {
            if (!Schema::hasColumn('ak_profiles', 'extras')) {
                $table->json('extras')->nullable()->after('avatar_url');
            }

            if (!Schema::hasColumn('ak_profiles', 'role')) {
                $table->string('role', 64)->nullable()->after('referral_code');
                $table->index('role');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ak_profiles', function (Blueprint $table) {
            if (Schema::hasColumn('ak_profiles', 'role')) {
                $table->dropIndex(['role']);
                $table->dropColumn('role');
            }

            if (Schema::hasColumn('ak_profiles', 'extras')) {
                $table->dropColumn('extras');
            }
        });
    }
};
