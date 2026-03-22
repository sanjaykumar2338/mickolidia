<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureTrialSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $trialUserId = $request->session()->get('trial_user_id');

        if (! Auth::check() && $trialUserId !== null) {
            $user = User::query()->find($trialUserId);

            if ($user !== null) {
                Auth::login($user);
            }
        }

        if (! Auth::check()) {
            return redirect()->route('trial.register');
        }

        if (! $request->session()->has('trial_user_id')) {
            $request->session()->put('trial_user_id', Auth::id());
        }

        return $next($request);
    }
}
