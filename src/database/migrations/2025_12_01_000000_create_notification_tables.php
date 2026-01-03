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

        Schema::create('ak_notification_events', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->json('name')->nullable();
            $table->string('variant')->default('info');
            $table->string('audience')->default('authenticated');
            $table->string('target_type')->default('personal');
            $table->boolean('is_pinned')->default(false);
            $table->boolean('is_active')->default(true);
            $table->json('title')->nullable();
            $table->json('excerpt')->nullable();
            $table->json('body')->nullable();
            $table->json('meta')->nullable();
            $table->json('options')->nullable();
            $table->timestamps();
        });

        Schema::create('ak_notifications', function (Blueprint $table) use ($userTable) {
            $table->id();
            $table->foreignId('notification_event_id')->nullable()->constrained('ak_notification_events')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained($userTable)->cascadeOnDelete();
            $table->string('kind')->default('manual'); // manual, event, system
            $table->string('target_type')->default('broadcast'); // broadcast, personal
            $table->string('audience')->default('all'); // all, authenticated, guest
            $table->string('variant')->default('info'); // info, success, warning, error
            $table->boolean('is_pinned')->default(false);
            $table->boolean('is_active')->default(true);
            $table->json('title')->nullable();
            $table->json('excerpt')->nullable();
            $table->json('body')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['audience', 'target_type', 'is_active']);
            $table->index(['published_at', 'expires_at']);
        });

        Schema::create('ak_notification_reads', function (Blueprint $table) use ($userTable) {
            $table->id();
            $table->foreignId('notification_id')->constrained('ak_notifications')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained($userTable)->cascadeOnDelete();
            $table->timestamp('read_at')->useCurrent();
            $table->timestamps();

            $table->unique(['notification_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ak_notification_reads');
        Schema::dropIfExists('ak_notifications');
        Schema::dropIfExists('ak_notification_events');
    }
};
