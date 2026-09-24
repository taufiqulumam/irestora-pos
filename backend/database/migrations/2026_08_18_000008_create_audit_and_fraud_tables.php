<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('outlet_id')->constrained('outlets')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->restrictOnDelete();
            $table->string('action'); // void_item, apply_discount, edit_price, refund, login, dst
            $table->string('target_type');
            $table->uuid('target_id');
            $table->jsonb('before_value')->nullable();
            $table->jsonb('after_value')->nullable();
            $table->text('reason')->nullable();
            $table->foreignUuid('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('device_id')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at');

            $table->index(['outlet_id', 'created_at']);
            $table->index(['user_id', 'action']);

            // CATATAN: tabel ini sengaja TIDAK punya updated_at / soft delete.
            // Tidak boleh ada endpoint API yang meng-update atau menghapus baris di tabel ini.
        });

        Schema::create('fraud_alerts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('outlet_id')->constrained('outlets')->cascadeOnDelete();
            $table->foreignUuid('shift_id')->nullable()->constrained('shifts')->nullOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('rule_code'); // high_void_rate, high_discount, cash_mismatch, sequence_gap
            $table->string('severity')->default('medium'); // low, medium, high
            $table->jsonb('details')->nullable();
            $table->string('status')->default('open'); // open, reviewed, dismissed
            $table->foreignUuid('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fraud_alerts');
        Schema::dropIfExists('audit_logs');
    }
};
