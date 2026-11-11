<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // MySQL/MariaDB may commit each DDL statement independently. Keep this
        // migration safe to retry if a previous attempt stopped midway.
        if (! Schema::hasColumn('shipping_zones', 'zone_type')) {
            Schema::table('shipping_zones', function (Blueprint $table): void {
                $table->string('zone_type', 20)->default('local')->after('name_en')->index();
            });
        }

        Schema::table('shipping_zones', function (Blueprint $table): void {
            $table->decimal('shipping_fee_min', 12, 2)->nullable()->default(null)->change();
            $table->decimal('shipping_fee_max', 12, 2)->nullable()->change();
            $table->unsignedSmallInteger('delivery_days')->nullable()->default(null)->change();
        });

        // Drop and recreate the FK around the nullability change. This avoids
        // engines rejecting MODIFY/CHANGE while the column participates in FK.
        $this->setWarehouseNullable(true);

        DB::table('permissions')->whereIn('key', [
            'shipping_zones.view', 'shipping_zones.create',
            'shipping_zones.update', 'shipping_zones.activate',
        ])->update([
            'description' => DB::raw("CASE key
                WHEN 'shipping_zones.view' THEN 'عرض مناطق الشحن'
                WHEN 'shipping_zones.create' THEN 'إضافة مناطق الشحن'
                WHEN 'shipping_zones.update' THEN 'تعديل مناطق الشحن'
                ELSE 'تفعيل وإيقاف مناطق الشحن' END"),
        ]);

        if (! Schema::hasTable('shipping_zone_countries')) {
            Schema::create('shipping_zone_countries', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('shipping_zone_id')->constrained('shipping_zones')->cascadeOnDelete();
                $table->foreignId('country_id')->constrained('countries')->restrictOnDelete();
                $table->timestamps();
                $table->unique(['shipping_zone_id', 'country_id']);
                $table->index(['country_id', 'shipping_zone_id']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('shipping_zones', 'zone_type')) {
            DB::table('shipping_zones')->where('zone_type', 'international')->delete();
        }

        Schema::dropIfExists('shipping_zone_countries');

        if (Schema::hasColumn('shipping_zones', 'shipping_fee_min')) {
            Schema::table('shipping_zones', function (Blueprint $table): void {
                $table->decimal('shipping_fee_min', 12, 2)->default(0)->change();
                $table->decimal('shipping_fee_max', 12, 2)->change();
                $table->unsignedSmallInteger('delivery_days')->default(1)->change();
            });
        }

        $this->setWarehouseNullable(false);

        if (Schema::hasColumn('shipping_zones', 'zone_type')) {
            Schema::table('shipping_zones', function (Blueprint $table): void {
                $table->dropIndex(['zone_type']);
                $table->dropColumn('zone_type');
            });
        }
    }

    private function setWarehouseNullable(bool $nullable): void
    {
        if (! Schema::hasColumn('shipping_zones', 'warehouse_id')) {
            return;
        }

        // SQLite rebuilds the table for CHANGE and does not support dropping
        // a foreign key by name. Keep its FK in place and let its grammar
        // rebuild the table; MySQL/MariaDB use the explicit drop/restore path.
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            Schema::table('shipping_zones', function (Blueprint $table) use ($nullable): void {
                $table->unsignedBigInteger('warehouse_id')->nullable($nullable)->change();
            });
            return;
        }

        $foreignKey = collect(Schema::getForeignKeys('shipping_zones'))
            ->first(fn (array $key): bool => in_array('warehouse_id', $key['columns'] ?? [], true));

        if ($foreignKey) {
            Schema::table('shipping_zones', function (Blueprint $table) use ($foreignKey): void {
                $table->dropForeign($foreignKey['name']);
            });
        }

        Schema::table('shipping_zones', function (Blueprint $table) use ($nullable): void {
            $table->unsignedBigInteger('warehouse_id')->nullable($nullable)->change();
        });

        $foreignKeys = Schema::getForeignKeys('shipping_zones');
        $hasWarehouseForeignKey = collect($foreignKeys)
            ->contains(fn (array $key): bool => in_array('warehouse_id', $key['columns'] ?? [], true));

        if (! $hasWarehouseForeignKey) {
            Schema::table('shipping_zones', function (Blueprint $table): void {
                $table->foreign('warehouse_id')->references('id')->on('warehouses')->restrictOnDelete();
            });
        }
    }
};
