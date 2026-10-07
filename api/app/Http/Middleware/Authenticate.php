<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     */
    protected function redirectTo($request): ?string
    {
        $path = $request->path();

        if ($request->expectsJson() || str_starts_with((string) $path, 'api')) {
            return null;
        }

        if (app('router')->has('login')) {
            return route('login');
        }

        return '/';
    }
}
