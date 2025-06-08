<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\App as AppFacade;
use Illuminate\Support\Facades\Session;

class SetLocale
{
    public function handle($request, Closure $next)
    {
        $locale = Session::get('locale', config('app.locale'));
        AppFacade::setLocale($locale);

        return $next($request);
    }
}
