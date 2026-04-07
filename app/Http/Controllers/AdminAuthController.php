<?php

namespace App\Http\Controllers;

use App\Http\Middleware\AdminBasicAuth;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminAuthController extends Controller
{
    public function create(Request $request): View|RedirectResponse
    {
        if (AdminBasicAuth::isAuthenticated($request)) {
            return redirect()->route('admin.clients.index');
        }

        return view('admin.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $username = (string) config('wolforix.admin_auth.username', 'admin');
        $password = (string) config('wolforix.admin_auth.password', 'wolforix-admin');

        if (! hash_equals($username, (string) $credentials['username']) || ! hash_equals($password, (string) $credentials['password'])) {
            return back()
                ->withErrors([
                    'username' => __('site.admin.login.invalid'),
                ])
                ->onlyInput('username');
        }

        $request->session()->regenerate();
        $request->session()->put(AdminBasicAuth::SESSION_KEY, true);
        $request->session()->put(AdminBasicAuth::USERNAME_KEY, $username);

        $redirectTo = (string) $request->session()->pull('admin.intended', route('admin.clients.index'));

        return redirect()->to($redirectTo);
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->session()->forget([
            AdminBasicAuth::SESSION_KEY,
            AdminBasicAuth::USERNAME_KEY,
            'admin.intended',
        ]);
        $request->session()->regenerateToken();

        return redirect()
            ->route('admin.login')
            ->with('status', __('site.admin.login.logged_out'));
    }
}
