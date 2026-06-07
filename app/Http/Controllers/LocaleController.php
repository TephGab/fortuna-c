<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;

class LocaleController extends Controller
{
    public function switch(Request $request): RedirectResponse
    {
        $request->validate([
            'locale' => 'required|string|in:en,fr,ht',
        ]);

        $locale = $request->locale;

        if ($user = $request->user()) {
            $user->update(['preferred_locale' => $locale]);
        }

        Session::put('locale', $locale);
        App::setLocale($locale);

        return back()->with('success', __('Language changed successfully'));
    }

    public function setGuestLocale(string $locale): RedirectResponse
    {
        if (!in_array($locale, ['en', 'fr', 'ht'])) {
            $locale = 'en';
        }

        Session::put('locale', $locale);
        App::setLocale($locale);

        return back();
    }
}