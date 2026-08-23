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
            if (auth()->check()) {
                $canAll = auth()->user()->hasAnyRole(['Super Admin', 'Owner']);
                $branches = $canAll ? Branch::orderBy('name')->get() : Branch::whereKey(auth()->user()->branch_id)->get();
                $selected = (int) session('selected_branch_id', auth()->user()->branch_id);
                $view->with('globalBranches', $branches)->with('globalSelectedBranchId', $selected);
            }
        });
    }
}
