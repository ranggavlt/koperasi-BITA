<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFeatureEnabled
{
    /**
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        abort_unless((bool) config("features.{$feature}", false), 404);

        foreach ((array) config("features.dependencies.{$feature}", []) as $dependency) {
            abort_unless((bool) config("features.{$dependency}", false), 404);
        }

        return $next($request);
    }
}
