<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->header('Accept-Language');
        
        // Extraire la langue principale (ex: 'fr-FR,fr;q=0.9' -> 'fr')
        if ($locale) {
            $locale = substr($locale, 0, 2);
            $supportedLocales = ['en', 'fr'];
            
            if (in_array($locale, $supportedLocales)) {
                app()->setLocale($locale);
            }
        }

        return $next($request);
    }
}
