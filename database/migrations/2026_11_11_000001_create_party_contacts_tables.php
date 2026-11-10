<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('party_phones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('party_id')->constrained()->cascadeOnDelete();
            $table->string('number', 40);
            $table->string('type', 20)->default('mobile');
            $table->boolean('is_whatsapp')->default(false);
            $table->boolean('is_telegram')->default(false);
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['party_id', 'is_active', 'is_primary']);
            $table->index('number');
        });

        Schema::create('party_addresses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('party_id')->constrained()->cascadeOnDelete();
            $table->foreignId('city_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 80)->default('المنزل');
            $table->text('address');
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['party_id', 'is_active', 'is_primary']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('party_addresses');
        Schema::dropIfExists('party_phones');
    }
};
