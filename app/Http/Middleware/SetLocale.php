<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SetLocale
{
    public function handle(Request $request, Closure $next)
    {
        $locale = $request->cookie('app_locale', 'id');
        app()->setLocale(in_array($locale, ['id', 'en', 'zh-CN'], true) ? $locale : 'id');

        return $next($request);
    }
}
