<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     *
     * This is an API-only backend with no web `login` route, so we never redirect:
     * every unauthenticated request returns a clean 401 JSON response (instead of a
     * 500 from trying to resolve a non-existent `login` route on non-JSON requests).
     *
     * @param  \Illuminate\Http\Request  $request
     * @return string|null
     */
    protected function redirectTo($request)
    {
        return null;
    }
}
