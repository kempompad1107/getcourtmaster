<?php

namespace App\Http\Controllers;

use App\Models\Tenant;

class TenantPublicController extends Controller
{
    /**
     * Public-facing tenant landing page. The URL customers share / scan to
     * discover the venue before signing up. No auth required.
     */
    public function show(Tenant $tenant)
    {
        abort_if(in_array($tenant->status, ['suspended', 'cancelled'], true), 404);

        $branches = $tenant->branches()
            ->where('is_active', true)
            ->orderByDesc('is_main')
            ->orderBy('name')
            ->get();

        $settings = $tenant->settings ?? [];
        $logoUrl  = file_url($tenant->logo);

        return view('public.tenant-landing', compact('tenant', 'branches', 'settings', 'logoUrl'));
    }
}
