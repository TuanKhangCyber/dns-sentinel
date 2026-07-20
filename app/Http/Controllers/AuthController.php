<?php

namespace App\Http\Controllers;

use App\Exceptions\RecaptchaException;
use App\Models\User;
use App\Services\LoginHistoryService;
use App\Services\MembershipService;
use App\Services\RecaptchaService;
use App\Services\SystemSettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function __construct(
        private readonly RecaptchaService $recaptcha,
        private readonly MembershipService $memberships,
        private readonly SystemSettingService $settings,
        private readonly LoginHistoryService $loginHistory,
    ) {}

    public function showLogin(Request $request): View
    {
        $email = strtolower(trim((string) $request->old('email', '')));
        $required = $this->recaptcha->loginRequired($this->loginThrottleKey($email, $request->ip()));

        return view('auth.login', ['recaptcha' => $this->recaptcha->publicConfig('login', $required)]);
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $throttleKey = $this->loginThrottleKey($credentials['email'], $request->ip());
        if (RateLimiter::tooManyAttempts($throttleKey, (int) config('auth.login.max_attempts', 5))) {
            return back()
                ->withErrors(['email' => __('ui.login_throttled', ['seconds' => RateLimiter::availableIn($throttleKey)])])
                ->withInput(['email' => $credentials['email']])
                ->onlyInput('email');
        }

        if ($this->recaptcha->loginRequired($throttleKey)) {
            try {
                $this->recaptcha->verify($request, 'login');
            } catch (RecaptchaException $exception) {
                RateLimiter::hit($throttleKey, (int) config('auth.login.decay_seconds', 60));

                return back()->withErrors(['recaptcha' => $exception->getMessage()])
                    ->with('error_code', $exception->errorCode)
                    ->withInput(['email' => $credentials['email']])->onlyInput('email');
            }
        }

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($throttleKey, (int) config('auth.login.decay_seconds', 60));

            return back()
                ->withErrors(['email' => __('ui.invalid_credentials')])
                ->withInput(['email' => $credentials['email']])
                ->onlyInput('email');
        }

        RateLimiter::clear($throttleKey);
        if (! $request->user()->isActive()) {
            Auth::logout();

            return back()->withErrors(['email' => __('platform.errors.account_suspended')])->onlyInput('email');
        }
        $request->session()->regenerate();
        $this->loginHistory->recordLogin($request->user(), $request);

        return redirect()->intended('/dns');
    }

    public function showRegister(): View
    {
        abort_unless($this->settings->get('registration_enabled', true), 404);

        return view('auth.register', ['recaptcha' => $this->recaptcha->publicConfig('register')]);
    }

    public function register(Request $request): RedirectResponse
    {
        abort_unless($this->settings->get('registration_enabled', true), 404);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        try {
            $this->recaptcha->verify($request, 'register');
        } catch (RecaptchaException $exception) {
            return back()->withErrors(['recaptcha' => $exception->getMessage()])
                ->with('error_code', $exception->errorCode)->withInput($request->except(['password', 'password_confirmation', 'g-recaptcha-response']));
        }

        $user = User::create($validated);
        $this->memberships->provision($user);
        Auth::login($user);
        $request->session()->regenerate();
        $this->loginHistory->recordLogin($user, $request);

        return redirect('/dns');
    }

    public function logout(Request $request): RedirectResponse
    {
        $this->loginHistory->recordLogout($request->user(), $request);
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function loginThrottleKey(string $email, ?string $ip): string
    {
        return 'login:'.hash('sha256', strtolower(trim($email)).'|'.($ip ?? 'unknown'));
    }
}
