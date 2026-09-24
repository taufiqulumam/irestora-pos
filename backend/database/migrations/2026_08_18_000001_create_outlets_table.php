<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outlets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('code')->unique();
            $table->text('address')->nullable();
            // PBJT/PB1 (pajak restoran daerah, BUKAN PPN) - tarif berbeda per Perda daerah, lihat 03-ERD.md §4
            $table->decimal('pb1_rate', 8, 4)->default(0.10);
            $table->decimal('service_charge_rate', 8, 4)->nullable();
            $table->boolean('rounding_enabled')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outlets');
    }
};
