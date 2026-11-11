<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table): void {
            $table->string('source_type', 80)->nullable()->after('movement_type');
            $table->unsignedBigInteger('source_id')->nullable()->after('source_type');
            $table->string('idempotency_key', 100)->nullable()->after('source_id');
            $table->char('idempotency_hash', 64)->nullable()->after('idempotency_key');
            $table->foreignId('reversal_of_id')->nullable()->after('posted_at')
                ->constrained('inventory_movements')->restrictOnDelete();

            $table->index(['source_type', 'source_id'], 'inventory_movement_source_lookup');
            $table->unique('idempotency_key', 'inventory_movement_idempotency_unique');
            $table->unique('reversal_of_id', 'inventory_movement_reversal_unique');
        });

        Schema::table('inventory_movement_items', function (Blueprint $table): void {
            $table->decimal('destination_quantity_before', 18, 3)->nullable()->after('quantity_after');
            $table->decimal('destination_quantity_after', 18, 3)->nullable()->after('destination_quantity_before');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_movement_items', function (Blueprint $table): void {
            $table->dropColumn(['destination_quantity_before', 'destination_quantity_after']);
        });

        Schema::table('inventory_movements', function (Blueprint $table): void {
            $table->dropUnique('inventory_movement_reversal_unique');
            $table->dropUnique('inventory_movement_idempotency_unique');
            $table->dropIndex('inventory_movement_source_lookup');
            $table->dropForeign(['reversal_of_id']);
            $table->dropColumn(['reversal_of_id', 'idempotency_hash', 'idempotency_key', 'source_id', 'source_type']);
        });
    }
};
