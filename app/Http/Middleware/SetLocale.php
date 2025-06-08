<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\App;
use Illuminate\Http\Request;

class SetLocale
{
     public function handle(Request $request, Closure $next)
    {
        $locale = $request->session()->get('locale', config('app.locale'));
        App::setLocale($locale);
        return $next($request);
    }
}