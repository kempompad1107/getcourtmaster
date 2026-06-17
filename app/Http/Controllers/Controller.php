<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\User;
use App\Services\BranchContext;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;

abstract class Controller
{
    use AuthorizesRequests;

    protected function authUser(): User
    {
        /** @var User $user */
        $user = Auth::user();
        return $user;
    }

    protected function authTenant(): Tenant
    {
        $tenant = $this->authUser()->tenant;

        abort_if(is_null($tenant), 403, 'No tenant is associated with this account.');

        return $tenant;
    }

    /**
     * Resolve the active branch ID from the topbar context. If no specific
     * branch is selected (owner on "All branches"), short-circuits the
     * request with a friendly redirect — create actions can't pick a
     * branch on the user's behalf when there's no obvious choice.
     */
    protected function requireActiveBranch(string $what = 'record'): int
    {
        $id = app(BranchContext::class)->current();
        if ($id === null) {
            throw new HttpResponseException(
                redirect()->back()->withInput()->with(
                    'error',
                    "Pick a specific branch from the topbar before adding a {$what}. \"All branches\" is a view-only mode."
                )
            );
        }
        return $id;
    }
}
