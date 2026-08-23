<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

trait ResolvesBranch
{
    protected function canAccessAllBranches(): bool
    {
        return auth()->user()->hasAnyRole(['Super Admin', 'Owner']);
    }

    protected function branchId(Request $request): int
    {
        // 1. Query string takes precedence (for switching branches)
        if ($request->has('branch_id')) {
            $request->session()->put('selected_branch_id', (int) $request->input('branch_id'));
        }

        // 2. Fallback to session, then user's default branch
        $id = (int) $request->session()->get('selected_branch_id', auth()->user()->branch_id);

        // 3. Authorization check: non-admin users can only access their own branch
        if (! $this->canAccessAllBranches() && $id !== (int) auth()->user()->branch_id) {
            throw ValidationException::withMessages(['branch_id' => 'Anda tidak dapat mengakses cabang ini.']);
        }

        return $id;
    }

    protected function branches()
    {
        return $this->canAccessAllBranches() 
            ? Branch::orderBy('name')->get() 
            : Branch::whereKey(auth()->user()->branch_id)->get();
    }
}
