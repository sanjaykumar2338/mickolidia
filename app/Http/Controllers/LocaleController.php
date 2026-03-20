<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    public function update(Request $request, string $locale): RedirectResponse
    {
        abort_unless(array_key_exists($locale, config('wolforix.supported_locales', [])), 404);

        $request->session()->put('locale', $locale);

        $redirect = $request->string('redirect')->toString();

        if ($redirect === '') {
            return back();
        }

        return redirect()->to($redirect);
    }
}
