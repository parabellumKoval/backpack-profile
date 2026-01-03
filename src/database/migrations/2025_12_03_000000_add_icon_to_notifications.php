<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ak_notification_events', function (Blueprint $table) {
            $table->string('icon', 32)->nullable()->after('variant');
        });

        Schema::table('ak_notifications', function (Blueprint $table) {
            $table->string('icon', 32)->nullable()->after('variant');
        });
    }

    public function down(): void
    {
        Schema::table('ak_notification_events', function (Blueprint $table) {
            $table->dropColumn('icon');
        });

        Schema::table('ak_notifications', function (Blueprint $table) {
            $table->dropColumn('icon');
        });
    }
};
