<?php

namespace App\Http\Controllers;

use App\Mail\WelcomeMail;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class AuthController extends Controller
{
    public function create(Request $request): View|RedirectResponse
    {
        if ($request->user() instanceof User) {
            return redirect()->intended(route('home'));
        }

        return view('public.login');
    }

    public function forgotPassword(): View
    {
        return view('public.forgot-password');
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

    public function sendResetLink(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        try {
            $status = Password::sendResetLink([
                'email' => $validated['email'],
            ]);
        } catch (TransportExceptionInterface $exception) {
            Log::warning('Password reset email could not be sent.', [
                'email' => $validated['email'],
                'message' => $exception->getMessage(),
            ]);

            return back()
                ->withInput()
                ->withErrors([
                    'email' => __('site.auth.passwords.status.mailer'),
                ]);
        }

        if ($status !== Password::RESET_LINK_SENT) {
            return back()
                ->withInput()
                ->withErrors([
                    'email' => $this->passwordStatusMessage($status),
                ]);
        }

        return back()->with('status', $this->passwordStatusMessage($status));
    }

    public function resetPasswordForm(Request $request, string $token): View
    {
        return view('public.reset-password', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function resetPassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $status = Password::reset($validated, function (User $user, string $password): void {
            $user->forceFill([
                'password' => $password,
                'remember_token' => Str::random(60),
            ])->save();

            event(new PasswordReset($user));
        });

        if ($status !== Password::PASSWORD_RESET) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors([
                    'email' => $this->passwordStatusMessage($status),
                ]);
        }

        return redirect()
            ->route('login')
            ->with('status', $this->passwordStatusMessage($status));
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

    private function passwordStatusMessage(string $status): string
    {
        $translationKey = 'site.auth.passwords.status.'.Str::afterLast($status, '.');
        $translated = __($translationKey);

        return $translated === $translationKey
            ? $status
            : $translated;
    }
}
