<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('shipping_zones', function (Blueprint $table): void {
            $table->string('zone_type', 20)->default('local')->after('name_en')->index();
            $table->decimal('shipping_fee_min', 12, 2)->nullable()->default(null)->change();
            $table->decimal('shipping_fee_max', 12, 2)->nullable()->change();
            $table->unsignedSmallInteger('delivery_days')->nullable()->default(null)->change();
            $table->foreignId('warehouse_id')->nullable()->change();
        });

        Schema::create('shipping_zone_countries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shipping_zone_id')->constrained('shipping_zones')->cascadeOnDelete();
            $table->foreignId('country_id')->constrained('countries')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['shipping_zone_id', 'country_id']);
            $table->index(['country_id', 'shipping_zone_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_zone_countries');

        Schema::table('shipping_zones', function (Blueprint $table): void {
            $table->dropIndex(['zone_type']);
            $table->string('zone_type', 20)->default('local')->change();
            $table->decimal('shipping_fee_min', 12, 2)->default(0)->change();
            $table->decimal('shipping_fee_max', 12, 2)->change();
            $table->unsignedSmallInteger('delivery_days')->default(1)->change();
            $table->foreignId('warehouse_id')->nullable(false)->change();
        });
    }
};
