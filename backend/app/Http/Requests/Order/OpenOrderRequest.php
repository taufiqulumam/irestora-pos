<?php

namespace App\Http\Requests\Order;

use App\Models\DiningTable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validasi buka order baru dari Kasir App.
 * Kasir wajib pilih order_type (dine_in/takeaway) SEBELUM pilih meja/menu
 * - lihat 02-SDD.md §4.7.1. Meja yang bisa dipilih HANYA yang berstatus
 * 'available' - meja occupied/reserved tidak boleh dipilih untuk order baru.
 */
class OpenOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'orderType' => ['required', Rule::in(['dine_in', 'takeaway'])],
            // table_id WAJIB untuk dine_in, dan TIDAK BOLEH diisi untuk takeaway
            'tableId'   => [
                Rule::requiredIf(fn () => $this->input('orderType') === 'dine_in'),
                Rule::prohibitedIf(fn () => $this->input('orderType') === 'takeaway'),
                'nullable',
                'uuid',
                function ($attribute, $value, $fail) {
                    if (! $value) {
                        return;
                    }

                    $user = $this->user();
                    $table = DiningTable::whereKey($value)
                        ->when($user?->outlet_id, fn ($query, string $outletId) => $query->where('outlet_id', $outletId))
                        ->first();

                    if (! $table) {
                        $fail('Meja tidak ditemukan.');
                        return;
                    }

                    if ($table->status !== 'available') {
                        $fail("Meja sedang tidak tersedia (status saat ini: {$table->status}).");
                    }
                },
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'tableId.required' => 'Meja wajib dipilih untuk order dine-in.',
            'tableId.prohibited' => 'Order takeaway tidak boleh memiliki meja.',
        ];
    }
}
