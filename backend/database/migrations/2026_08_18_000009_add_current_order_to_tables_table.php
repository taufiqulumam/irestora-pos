<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * current_order_id menandai order mana yang sedang membuat meja occupied.
 * Diisi saat order dine-in dibuka (kasir atau self-order), dikosongkan saat
 * kasir menutup meja secara manual - lihat 02-SDD.md §4.7.1.
 *
 * Migration terpisah dari 000005_create_tables_table karena tabel `orders`
 * baru dibuat di migration 000007 (FK harus mengarah ke tabel yang sudah ada).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tables', function (Blueprint $table) {
            $table->foreignUuid('current_order_id')->nullable()->after('status')
                ->constrained('orders')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tables', function (Blueprint $table) {
            $table->dropConstrainedForeignId('current_order_id');
        });
    }
};
