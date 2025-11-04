<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ak_profiles', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id')->unique(); // связь с users.id
            // базовые данные
            $t->string('first_name')->nullable();
            $t->string('last_name')->nullable();
            $t->string('full_name')->nullable();
            $t->string('phone')->nullable();
            $t->string('country_code', 2)->nullable(); // ISO2
            $t->string('locale', 8)->default('uk');    // 'uk','ru','en'...
            $t->string('timezone', 64)->nullable();
            $t->date('birthdate')->nullable();
            $t->string('avatar_url')->nullable();
            // рефералка (быстрый доступ, дубль из partners/или кэш)
            $t->string('referral_code', 32)->unique();
            $t->unsignedBigInteger('sponsor_profile_id')->nullable()->index();
            // Персональная скидка
            $t->decimal('discount_percent', 5, 2);
            // нотификации/согласия
            $t->boolean('email_marketing_opt_in')->default(false);
            $t->boolean('sms_marketing_opt_in')->default(false);
            // тех. поля
            $t->json('meta')->nullable();
            $t->timestamps();


            $t->foreign('sponsor_profile_id')
                ->references('id')->on('ak_profiles')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ak_profiles');
    }
};