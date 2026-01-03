<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $userModel = config('backpack.profile.user_model', \App\Models\User::class);
        $userTable = (new $userModel)->getTable();

        Schema::create('ak_notification_archives', function (Blueprint $table) use ($userTable) {
            $table->id();
            $table->foreignId('notification_id')->constrained('ak_notifications')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained($userTable)->cascadeOnDelete();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->unique(['notification_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ak_notification_archives');
    }
};
