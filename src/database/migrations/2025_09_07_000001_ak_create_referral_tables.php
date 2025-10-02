<?php
// database/migrations/2025_09_08_000001_ak_create_referral_core.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ak_referral_partners', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id')->unique();
            $t->string('ref_code')->unique();
            $t->unsignedBigInteger('parent_id')->nullable()->index();
            $t->json('tree_path')->nullable();
            $t->timestamps();
        });

        Schema::create('ak_reward_events', function (Blueprint $t) {
            $t->id();
            $t->string('trigger');              // alias: store.order_paid, review.published
            $t->string('external_id')->index(); // внешний ID (заказ/отзыв/статья)
            $t->unsignedBigInteger('actor_user_id')->nullable()->index();
            // опциональная «мягкая» полиморфия без FK
            $t->string('subject_type')->nullable();
            $t->string('subject_id')->nullable();
            $t->boolean('is_reversal')->default(false)->index();
            $t->json('payload')->nullable();
            // для работы со статусом
            $t->enum('status',['pending','processing','processed','failed'])->default('pending')->index();
            $t->unsignedSmallInteger('attempts')->default(0);
            $t->timestamp('processed_at')->nullable();
            $t->text('last_error')->nullable();
            $t->unsignedBigInteger('parent_event_id')->nullable()->index();

            $t->timestamp('happened_at')->nullable();
            $t->timestamps();

            $t->unique(['trigger','external_id']); // идемпотентность
            $t->unique(['parent_event_id','is_reversal'], 'ak_rev_once');
        });

        Schema::create('ak_event_counters', function (Blueprint $t) {
            $t->id();
            $t->string('subject_type');
            $t->string('subject_id');
            $t->string('transition'); // alias триггера
            $t->unsignedInteger('version')->default(0);
            $t->timestamps();
            $t->unique(['subject_type','subject_id','transition']);
        });

        Schema::create('ak_rewards', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('event_id')->index();
            $t->unsignedBigInteger('beneficiary_user_id')->index();
            $t->unsignedTinyInteger('level')->nullable(); // null = автор события
            $t->enum('beneficiary_type', ['actor','upline'])->default('actor');

            // универсальные деньги/баллы
            $t->decimal('amount', 20, 6);
            $t->string('currency', 16); // VIVAPOINTS/UAH/CZK/…

            // аудит расчёта
            $t->decimal('base_amount', 20, 6)->nullable();
            $t->string('base_currency', 16)->nullable();
            $t->json('meta')->nullable();

            $t->timestamps();

            $t->foreign('event_id')->references('id')->on('ak_reward_events')->cascadeOnDelete();
            $t->unique(['event_id','beneficiary_user_id','beneficiary_type','level'], 'ak_rr_unique_per_event');
        });

        Schema::create('ak_wallet_balances', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id')->index();
            $t->string('currency', 16)->index();
            $t->decimal('balance', 20, 6)->default(0);
            $t->timestamps();

            $t->unique(['user_id','currency']);
        });

        Schema::create('ak_wallet_ledger', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id')->index();
            $t->enum('type', ['credit','debit','hold','release','capture']);
            $t->decimal('amount', 20, 6);
            $t->string('currency', 16); // универсально

            $t->string('reference_type')->nullable(); // 'order','withdrawal',...
            $t->string('reference_id')->nullable();
            $t->json('meta')->nullable();

            $t->timestamps();
            $t->index(['reference_type','reference_id']);
        });

        Schema::create('ak_withdrawal_requests', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id')->index();

            $t->decimal('amount', 20, 6);  // запрошено списать
            $t->string('currency', 16);    // из какой валюты кошелька списываем

            $t->decimal('wallet_amount', 20, 6)->nullable();
            $t->string('wallet_currency', 16)->nullable();

            $t->enum('status', ['pending','approved','rejected','paid'])->default('pending');
            $t->string('payout_method')->nullable();   // 'bank_transfer','paypal',...
            $t->json('payout_details')->nullable();

            // фиксация конверсии на момент одобрения (если нужно)
            $t->decimal('fx_rate', 20, 10)->nullable();
            $t->string('fx_from', 16)->nullable();
            $t->string('fx_to', 16)->nullable();

            $t->timestamp('approved_at')->nullable();
            $t->timestamp('paid_at')->nullable();
            $t->unsignedBigInteger('approved_by')->nullable();
            $t->unsignedBigInteger('paid_by')->nullable();

            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ak_withdrawal_requests');
        Schema::dropIfExists('ak_wallet_ledger');
        Schema::dropIfExists('ak_wallet_balances');
        Schema::dropIfExists('ak_rewards');
        Schema::dropIfExists('ak_event_counters');
        Schema::dropIfExists('ak_reward_events');
        Schema::dropIfExists('ak_referral_partners');
    }
};
