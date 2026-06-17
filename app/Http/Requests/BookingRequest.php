<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'court_id' => 'required|exists:courts,id',
            'customer_id' => 'nullable|exists:users,id',
            'booking_date' => 'required|date|after_or_equal:today',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'type' => 'in:online,walk_in,phone',
            'promo_code' => 'nullable|string',
            'notes' => 'nullable|string|max:1000',
            'use_credit' => 'nullable|boolean',
            'payment_method' => 'nullable|in:wallet,court_credit,cash',
        ];
    }
}
