<?php

namespace App\Http\Controllers;

use App\Mail\WelcomeMail;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function create(Request $request): View|RedirectResponse
    {
        if ($request->user() instanceof User) {
            return redirect()->intended(route('home'));
        }

        return view('public.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validateWithBag('login', [
            'login_email' => ['required', 'email', 'max:255'],
            'login_password' => ['required', 'string'],
        ]);

        if (! Auth::attempt([
            'email' => $credentials['login_email'],
            'password' => $credentials['login_password'],
        ], $request->boolean('remember'))) {
            return back()
                ->withInput($request->except('login_password'))
                ->withErrors([
                    'login_email' => __('site.auth.login.invalid'),
                ], 'login');
        }

        $request->session()->regenerate();

        return redirect()->intended(route('home'));
    }

    public function register(Request $request): RedirectResponse
    {
        $validated = $request->validateWithBag('register', [
            'register_name' => ['required', 'string', 'max:120'],
            'register_email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'register_password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = DB::transaction(function () use ($validated): User {
            $user = User::query()->create([
                'name' => $validated['register_name'],
                'email' => $validated['register_email'],
                'password' => $validated['register_password'],
                'status' => 'active',
            ]);

            UserProfile::query()->updateOrCreate([
                'user_id' => $user->id,
            ], [
                'preferred_language' => app()->getLocale(),
            ]);

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();
        rescue(fn () => Mail::to($user->email)->send(new WelcomeMail($user)));

        return redirect()->intended(route('home'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
