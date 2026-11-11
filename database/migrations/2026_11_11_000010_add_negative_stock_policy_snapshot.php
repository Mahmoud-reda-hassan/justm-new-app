<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table): void {
            $table->boolean('negative_stock_allowed')->default(false)->after('movement_type');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table): void {
            $table->dropColumn('negative_stock_allowed');
        });
    }
};
