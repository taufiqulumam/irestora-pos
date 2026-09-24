<?php

namespace App\Http\Requests\Sync;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncOrdersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'orders' => ['required', 'array', 'min:1'],
            'orders.*.id' => ['required', 'uuid'],
            'orders.*.outletId' => ['required', 'uuid', 'exists:outlets,id'],
            'orders.*.orderType' => ['required', Rule::in(['dine_in', 'takeaway'])],
            'orders.*.tableId' => ['nullable', 'uuid', 'exists:tables,id'],
            'orders.*.shiftId' => ['required', 'uuid', 'exists:shifts,id'],
            'orders.*.orderNumber' => ['required', 'string'],
            'orders.*.cashierId' => ['nullable', 'uuid', 'exists:users,id'],
            'orders.*.deviceId' => ['required', 'string'],
            'orders.*.createdAtClient' => ['required', 'date'],
            'orders.*.subtotal' => ['required', 'numeric', 'min:0'],
            'orders.*.discountTotal' => ['required', 'numeric', 'min:0'],
            'orders.*.serviceChargeTotal' => ['required', 'numeric', 'min:0'],
            'orders.*.pb1Total' => ['required', 'numeric', 'min:0'],
            'orders.*.roundingAdjustment' => ['required', 'numeric'],
            'orders.*.grandTotal' => ['required', 'numeric', 'min:0'],
            'orders.*.items' => ['required', 'array', 'min:1'],
            'orders.*.items.*.menuId' => ['required', 'uuid', 'exists:menus,id'],
            'orders.*.items.*.menuNameSnapshot' => ['required', 'string'],
            'orders.*.items.*.priceSnapshot' => ['required', 'numeric', 'min:0'],
            'orders.*.items.*.qty' => ['required', 'integer', 'min:1'],
            'orders.*.items.*.notes' => ['nullable', 'string'],
            'orders.*.items.*.batchId' => ['nullable', 'uuid'],
            'orders.*.payments' => ['nullable', 'array'],
            'orders.*.payments.*.method' => ['required', Rule::in(['cash', 'qris', 'card', 'other'])],
            'orders.*.payments.*.amount' => ['required', 'numeric', 'min:0'],
            'orders.*.payments.*.referenceNumber' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'orders.required' => 'Data orders wajib diisi',
            'orders.*.id.required' => 'Order ID wajib diisi',
            'orders.*.outletId.required' => 'Outlet ID wajib diisi',
            'orders.*.orderType.required' => 'Tipe order wajib diisi',
            'orders.*.shiftId.required' => 'Shift ID wajib diisi',
            'orders.*.items.required' => 'Item order wajib diisi',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $orders = $this->input('orders', []);
            
            foreach ($orders as $index => $order) {
                // Validate table_id consistency with order_type
                if ($order['orderType'] === 'dine_in' && empty($order['tableId'])) {
                    $validator->errors()->add("orders.$index.tableId", 'Table ID wajib diisi untuk dine_in');
                }
                if ($order['orderType'] === 'takeaway' && ! empty($order['tableId'])) {
                    $validator->errors()->add("orders.$index.tableId", 'Table ID tidak boleh diisi untuk takeaway');
                }
            }
        });
    }
}