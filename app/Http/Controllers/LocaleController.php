<?php

namespace App\Http\Controllers;

use App\Http\Middleware\SetLocale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LocaleController extends Controller
{
    /**
     * Switch the interface language. Guests keep it in a cookie; signed-in
     * users also get it saved on their account so emails use it too.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'locale' => ['required', 'string', Rule::in(array_keys(config('app.supported_locales')))],
        ]);

        $request->user()?->forceFill(['locale' => $validated['locale']])->save();

        return back()->withCookie(cookie()->forever(SetLocale::COOKIE, $validated['locale']));
    }
}
