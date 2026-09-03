<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use App\Models\Branch;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Share branches and selected branch id with all views for authenticated users
        View::composer('*', function ($view) {
            // Seluruh akun memakai guard web; data cabang global hanya diperlukan
            // layout operasional dan aman untuk user yang memiliki role Spatie.
            if (auth('web')->check()) {
                $employee = auth('web')->user();
                $canAll = $employee->hasAnyRole(['Super Admin', 'Owner']);
                $branches = $canAll ? Branch::orderBy('name')->get() : Branch::whereKey($employee->branch_id)->get();
                $selected = (int) session('selected_branch_id', $employee->branch_id);
                $view->with('globalBranches', $branches)->with('globalSelectedBranchId', $selected);
            }
        });
    }
}
