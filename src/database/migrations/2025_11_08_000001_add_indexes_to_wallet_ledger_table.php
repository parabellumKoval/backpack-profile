<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ak_wallet_ledger', function (Blueprint $table) {
            // Составной индекс для пользователя и даты создания (основной запрос API)
            $table->index(['user_id', 'created_at'], 'idx_wallet_ledger_user_created');
            
            // Индекс для фильтрации по типу операции
            $table->index(['user_id', 'type', 'created_at'], 'idx_wallet_ledger_user_type_created');
            
            // Индекс для фильтрации по типу ссылки
            $table->index(['user_id', 'reference_type', 'created_at'], 'idx_wallet_ledger_user_ref_type_created');
            
            // Индекс для поиска по ссылке
            $table->index(['reference_type', 'reference_id'], 'idx_wallet_ledger_reference');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ak_wallet_ledger', function (Blueprint $table) {
            $table->dropIndex('idx_wallet_ledger_user_created');
            $table->dropIndex('idx_wallet_ledger_user_type_created');
            $table->dropIndex('idx_wallet_ledger_user_ref_type_created');
            $table->dropIndex('idx_wallet_ledger_reference');
        });
    }
};