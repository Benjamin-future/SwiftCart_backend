<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureSuperAdmin
{
    public function handle(Request $request, Closure $next)
    {
        $u = $request->user();
        if (!$u || !$u->isSuperAdmin()) {
            abort(403, 'Super Admins only.');
        }
        return $next($request);
    }
}
