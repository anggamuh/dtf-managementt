<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RoleRedirect
{
    public function handle(Request $request, Closure $next)
    {
        if(auth()->check()){

            $user = auth()->user();

            if($user->hasRole('Super Admin')){
                return redirect()->route('dashboard');
            }

            if($user->hasRole('Owner')){
                return redirect()->route('owner.dashboard');
            }

            if($user->hasRole('Admin EPUL')){
                return redirect()->route('epul.dashboard');
            }

            if($user->hasRole('Admin RAPLY')){
                return redirect()->route('raply.dashboard');
            }

        }

        return $next($request);
    }
}