<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\BranchContext;
use Illuminate\Http\Request;

class BranchContextController extends Controller
{
    public function __construct(private readonly BranchContext $context) {}

    public function update(Request $request)
    {
        $data = $request->validate([
            'branch_id' => 'nullable|integer',
        ]);

        $this->context->set(isset($data['branch_id']) ? (int) $data['branch_id'] : null);

        return back();
    }
}
