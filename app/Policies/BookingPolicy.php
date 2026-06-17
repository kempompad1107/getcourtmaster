<?php

namespace App\Policies;

use App\Models\Booking;
use App\Models\User;

class BookingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('bookings.view') || $user->isCustomer();
    }

    public function view(User $user, Booking $booking): bool
    {
        if ($user->isCustomer()) {
            return $booking->customer_id === $user->id;
        }
        return $user->tenant_id === $booking->tenant_id
            && $user->hasPermissionTo('bookings.view');
    }

    public function create(User $user): bool
    {
        return $user->isCustomer() || $user->hasPermissionTo('bookings.create');
    }

    public function update(User $user, Booking $booking): bool
    {
        if ($user->isCustomer()) {
            return $booking->customer_id === $user->id && $booking->isPending();
        }
        return $user->tenant_id === $booking->tenant_id
            && $user->hasPermissionTo('bookings.update');
    }

    public function cancel(User $user, Booking $booking): bool
    {
        if ($user->isCustomer()) {
            return $booking->customer_id === $user->id
                && in_array($booking->status, ['pending', 'confirmed']);
        }
        return $user->tenant_id === $booking->tenant_id
            && $user->hasPermissionTo('bookings.cancel');
    }

    public function manageTimer(User $user, Booking $booking): bool
    {
        return $user->tenant_id === $booking->tenant_id
            && $user->hasPermissionTo('timer.manage');
    }
}
