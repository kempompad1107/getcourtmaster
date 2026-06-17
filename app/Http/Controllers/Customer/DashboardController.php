<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Repositories\Contracts\BookingRepositoryInterface;
use App\Repositories\Contracts\MembershipRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private readonly BookingRepositoryInterface $bookings,
        private readonly MembershipRepositoryInterface $memberships,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();

        $upcoming = $this->bookings->forCustomer($user->id, ['pending', 'confirmed', 'active'])->take(5);
        $past = $this->bookings->forCustomer($user->id, ['completed', 'cancelled'])->take(5);
        $membership = $this->memberships->activeForCustomer($user->id);

        return view('customer.dashboard', compact('user', 'upcoming', 'past', 'membership'));
    }
}
