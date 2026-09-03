<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
class EnsureStaff {
    public function handle(Request $request, Closure $next) {
        abort_unless($request->user()?->hasAnyRole(['Super Admin','Owner','Admin EPUL','Admin RAPLY','Finance','Produksi']), 403);
        return $next($request);
    }
}
