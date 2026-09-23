<?php

namespace App\Http\Controllers;

use App\Auth\AuthService;
use App\Auth\Mfa;
use App\Services\R007Api\R007ApiException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function showLogin(Request $request): Response|RedirectResponse
    {
        return $this->staff->check() && ! $this->staff->mfaPending() ? redirect()->route('dashboard') : response()->view('auth.login');
    }

    public function login(Request $request, AuthService $auth): RedirectResponse
    {
        $data = $request->validate(['identifier' => ['required', 'string', 'max:190'], 'password' => ['required', 'string', 'max:500']]);

        try {
            $mfaRequired = $auth->login($data['identifier'], $data['password']);
        } catch (R007ApiException $e) {
            if ($e->isUnauthenticated() || $e->status === 403 || $e->status === 422 || $e->status === 429) {
                throw ValidationException::withMessages(['identifier' => $e->detail ?: $e->title]);
            }
            throw $e;
        }

        $request->session()->regenerate();

        return $mfaRequired ? redirect()->route('mfa') : redirect()->intended(route('dashboard'));
    }

    public function showMfa(Mfa $mfa): Response|RedirectResponse
    {
        if (! $this->staff->check()) {
            return redirect()->route('login');
        }

        return $this->staff->mfaPending() ? response()->view('auth.mfa', ['available' => $mfa->available()]) : redirect()->route('dashboard');
    }

    public function verifyMfa(Request $request, Mfa $mfa): RedirectResponse
    {
        abort_unless($this->staff->check(), 401);
        $data = $request->validate(['code' => ['required', 'digits:6']]);

        if (! $mfa->verify($data['code'])) {
            throw ValidationException::withMessages(['code' => $mfa->available() ? 'That code is not valid.' : 'Two-factor verification is required but the API does not support it yet, so this sign-in is blocked.']);
        }

        $this->staff->setMfaPending(false);

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request, AuthService $auth): RedirectResponse
    {
        $auth->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'Signed out.');
    }
}
