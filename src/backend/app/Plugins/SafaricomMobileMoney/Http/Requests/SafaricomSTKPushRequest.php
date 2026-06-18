<?php

namespace App\Plugins\SafaricomMobileMoney\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SafaricomSTKPushRequest extends FormRequest {
    public function authorize(): bool {
        return true;
    }

    public function rules(): array {
        return [
            // Daraja accepts whole numbers only; the service rounds to int
            // before sending.
            'amount' => 'required|numeric|min:1|max:150000',
            // Accept any of the common formats operators type — the service
            // normalises to 2547XXXXXXXX before storing/sending.
            'phone_number' => 'required|string|min:9|max:15',
            // Operator picks Meter or SHS and provides the serial. The
            // customer is derived server-side from the serial+type combo;
            // no customer_id is sent from the form anymore.
            'device_type' => 'required|string|in:meter,shs',
            'device_serial' => 'required|string|min:3|max:100',
            // Stored long-form; we truncate to Daraja's 12/13 char limits
            // server-side before sending the STK Push payload.
            'account_reference' => 'nullable|string|max:50',
            'transaction_desc' => 'nullable|string|max:50',
            'type' => 'nullable|string|in:energy,deferred_payment,down_payment',
        ];
    }

    public function messages(): array {
        return [
            'amount.min' => 'Amount must be at least 1 KES',
            'amount.max' => 'Amount cannot exceed 150,000 KES per STK Push',
        ];
    }
}
