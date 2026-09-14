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
        $requestBranchId = $request->input('branch_id');

        if ($request->has('branch_id') && $requestBranchId !== null && $requestBranchId !== '') {
            $id = (int) $requestBranchId;

            if ($id !== 0 && ! Branch::whereKey($id)->where('active', true)->exists()) {
                $request->session()->forget('selected_branch_id');
                throw ValidationException::withMessages(['branch_id' => 'Cabang yang dipilih tidak valid atau tidak aktif.']);
            }

            if (! $this->canAccessAllBranches() && $id !== 0 && $id !== (int) auth()->user()->branch_id) {
                throw ValidationException::withMessages(['branch_id' => 'Anda tidak dapat mengakses cabang ini.']);
            }

            $request->session()->put('selected_branch_id', $id);
        }

        $id = (int) $request->session()->get('selected_branch_id', auth()->user()->branch_id ?? 0);

        if ($id !== 0 && ! Branch::whereKey($id)->where('active', true)->exists()) {
            $request->session()->forget('selected_branch_id');
            throw ValidationException::withMessages(['branch_id' => 'Cabang yang dipilih tidak valid atau tidak aktif.']);
        }

        if ($id === 0 && ! $this->canAccessAllBranches()) {
            $request->session()->forget('selected_branch_id');
            throw ValidationException::withMessages(['branch_id' => 'Pilih cabang tertentu sebelum melanjutkan.']);
        }

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
