<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\WalletTransaction;
use Illuminate\Http\Request;

class WalletApiController extends Controller
{

    public function balance()
    {
        return response()->json([
            'balance'  => $this->authUser()->wallet_balance,
            'currency' => $this->authTenant()->currency,
        ]);
    }

    public function transactions(Request $request)
    {
        $user = $this->authUser();

        $transactions = WalletTransaction::where('user_id', $user->id)
            ->latest()
            ->paginate(20);

        return response()->json(['transactions' => $transactions]);
    }
}
