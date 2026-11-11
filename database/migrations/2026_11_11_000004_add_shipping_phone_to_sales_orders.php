<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('sales_orders', function (Blueprint $table): void {
            $table->foreignId('shipping_phone_id')->nullable()->after('shipping_address_id')
                ->constrained('party_phones')->nullOnDelete();
            $table->string('shipping_phone', 40)->nullable()->after('shipping_address');
        });
    }

    public function down(): void
    {
        Schema::table('sales_orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('shipping_phone_id');
            $table->dropColumn('shipping_phone');
        });
    }
};
