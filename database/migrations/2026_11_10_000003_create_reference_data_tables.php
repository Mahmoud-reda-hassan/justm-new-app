<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('countries', function (Blueprint $table): void {
            $table->id();
            $table->string('name_ar', 100)->unique();
            $table->string('name_en', 100)->unique();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('regions', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100)->unique();
            $table->timestamps();
        });

        Schema::create('governorates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('country_id')->constrained()->restrictOnDelete();
            $table->foreignId('region_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name_ar', 100);
            $table->string('name_en', 100);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->unique(['country_id', 'name_ar']);
            $table->unique(['country_id', 'name_en']);
        });

        Schema::create('cities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('governorate_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name_ar', 120);
            $table->string('name_en', 120);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->unique(['governorate_id', 'name_ar']);
            $table->unique(['governorate_id', 'name_en']);
        });

        Schema::create('companies', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 150)->unique();
            $table->string('phone', 40)->nullable();
            $table->string('mobile', 40)->nullable();
            $table->string('fax', 40)->nullable();
            $table->string('email', 190)->nullable();
            $table->foreignId('city_id')->nullable()->constrained()->nullOnDelete();
            $table->text('address')->nullable();
            $table->string('logo_path')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('branches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('city_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 120);
            $table->boolean('is_main')->default(false);
            $table->string('address', 255)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->unique(['company_id', 'name']);
            $table->index(['company_id', 'is_active']);
        });

        Schema::create('warehouse_types', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 50)->unique();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('warehouses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_type_id')->constrained()->restrictOnDelete();
            $table->string('name', 150);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->unique(['branch_id', 'name']);
            $table->index(['branch_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouses');
        Schema::dropIfExists('warehouse_types');
        Schema::dropIfExists('branches');
        Schema::dropIfExists('companies');
        Schema::dropIfExists('cities');
        Schema::dropIfExists('governorates');
        Schema::dropIfExists('regions');
        Schema::dropIfExists('countries');
    }
};
