<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the interface language for the request, in order of preference:
 * the signed-in user's saved choice, the `locale` cookie set by the language
 * switcher, the browser's Accept-Language header and finally the app default.
 */
class SetLocale
{
    public const COOKIE = 'locale';

    public function handle(Request $request, Closure $next): Response
    {
        App::setLocale(self::resolve($request));

        return $next($request);
    }

    public static function isSupported(mixed $locale): bool
    {
        return is_string($locale) && array_key_exists($locale, config('app.supported_locales'));
    }

    public static function resolve(Request $request): string
    {
        $candidates = [
            $request->user()?->locale,
            $request->cookie(self::COOKIE),
        ];

        foreach ($candidates as $locale) {
            if (is_string($locale) && self::isSupported($locale)) {
                return $locale;
            }
        }

        $supported = array_map(strval(...), array_keys(config('app.supported_locales')));
        $preferred = $request->getPreferredLanguage($supported);

        return self::isSupported($preferred) ? $preferred : (string) config('app.locale');
    }
}
