<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            // id BUKAN auto-increment - di-generate di client (UUID) agar aman untuk offline sync
            $table->uuid('id')->primary();
            $table->foreignUuid('outlet_id')->constrained('outlets')->cascadeOnDelete();
            $table->string('order_type'); // dine_in, takeaway - lihat 02-SDD.md §4.7
            $table->foreignUuid('table_id')->nullable()->constrained('tables')->nullOnDelete();
            $table->foreignUuid('shift_id')->constrained('shifts')->cascadeOnDelete();
            $table->foreignUuid('cashier_id')->constrained('users')->restrictOnDelete();
            $table->string('order_number'); // nomor urut per outlet per hari, untuk deteksi gap (FRAUD-04)
            $table->string('status')->default('open'); // open, paid, void, cancelled

            // Breakdown perhitungan - lihat 03-ERD.md §4 untuk formula lengkap
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount_total', 12, 2)->default(0);
            $table->decimal('service_charge_total', 12, 2)->default(0);
            $table->decimal('pb1_total', 12, 2)->default(0); // PBJT/PB1, BUKAN PPN
            $table->decimal('rounding_adjustment', 12, 2)->default(0);
            $table->decimal('grand_total', 12, 2)->default(0);

            $table->string('source')->default('cashier'); // cashier, customer_self_order

            // Kolom untuk offline-first sync - lihat 02-SDD.md §4
            $table->string('device_id');
            $table->timestamp('created_at_client');
            $table->timestamp('synced_at')->nullable();
            $table->string('sync_status')->default('pending'); // pending, synced, conflict

            $table->timestamps();

            $table->unique(['outlet_id', 'order_number']);
            $table->index(['outlet_id', 'created_at']);
            $table->index('sync_status');
        });

        // CHECK constraint - jaring pengaman terakhir agar table_id selalu konsisten
        // dengan order_type, walau ada bug validasi di level aplikasi. Lihat 02-SDD.md §4.7.
        DB::statement(<<<'SQL'
            ALTER TABLE orders
            ADD CONSTRAINT chk_order_type_table_id CHECK (
                (order_type = 'dine_in' AND table_id IS NOT NULL)
                OR (order_type = 'takeaway' AND table_id IS NULL)
            )
        SQL);

        Schema::create('order_batches', function (Blueprint $table) {
            // Satu submit dari cart customer (atau satu kali tambah item oleh kasir) = satu batch
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->constrained('orders')->cascadeOnDelete();
            $table->integer('batch_number');
            $table->string('source')->default('cashier'); // cashier, customer_self_order
            $table->string('status')->default('pending_confirmation'); // pending_confirmation, confirmed, rejected
            $table->timestamp('submitted_at');
            $table->foreignUuid('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();

            $table->unique(['order_id', 'batch_number']);
            $table->index(['order_id', 'status']);
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignUuid('order_batch_id')->constrained('order_batches')->cascadeOnDelete();
            $table->foreignUuid('menu_id')->constrained('menus')->restrictOnDelete();
            // Snapshot - struk yang sudah dicetak tidak boleh berubah nilainya
            $table->string('menu_name_snapshot');
            $table->decimal('price_snapshot', 12, 2);
            $table->integer('qty');
            $table->string('notes')->nullable();
            $table->string('status')->default('active'); // active, voided
            $table->foreignUuid('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('void_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('method'); // cash, qris, card, other
            $table->decimal('amount', 12, 2);
            $table->string('reference_number')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('order_batches');
        Schema::dropIfExists('orders');
    }
};
