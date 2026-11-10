<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('product_code_counters', function (Blueprint $table): void {
            $table->unsignedTinyInteger('id')->primary();
            $table->string('prefix', 2);
            $table->unsignedBigInteger('last_sequence')->default(0);
            $table->timestamps();
        });

        DB::table('product_code_counters')->insert([
            'id' => 1,
            'prefix' => '29',
            'last_sequence' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('product_code_counters');
    }
};
