<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Api\WebviewSessionController;
use App\Http\Controllers\Controller;
use App\Models\User;
use Flc\Identity\Application\Query\IsEmailAllowed;
use Flc\Shared\Application\QueryBus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Socialite\Facades\Socialite;

class UserAuthController extends Controller
{
    public function __construct(private readonly QueryBus $queries) {}

    public function showLogin(): Response|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('user.home.lookup');
        }

        return Inertia::render('Auth/Login', [
            'googleUrl' => route('user.auth.google'),
        ]);
    }

    public function redirectGoogle(): RedirectResponse
    {
        return Socialite::driver('google')
            ->redirectUrl(route('user.auth.google.callback'))
            ->redirect();
    }

    public function callbackGoogle(): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')
                ->redirectUrl(route('user.auth.google.callback'))
                ->user();
        } catch (\Throwable) {
            return redirect()->route('user.login')
                ->with('error', 'Google sign-in failed.');
        }

        $email = strtolower(trim((string) $googleUser->getEmail()));

        if ($email === '') {
            return redirect()->route('user.login')
                ->with('error', 'Google account has no email.');
        }

        if (! $this->queries->ask(new IsEmailAllowed($email))) {
            return redirect()->route('user.login')
                ->with('error', 'This email is not allowed. Contact an admin to add it to the allowlist.');
        }

        $user = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $googleUser->getName() ?: Str::before($email, '@'),
                'google_id' => $googleUser->getId(),
                'email_verified_at' => now(),
                'password' => Hash::make(Str::random(64)),
            ]
        );

        if ($googleUser->getId()) {
            $user->google_id = $googleUser->getId();
            $user->save();
        }

        Auth::login($user, remember: true);

        return redirect()->route('user.home.lookup');
    }

    public function logout(): RedirectResponse
    {
        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        $isApp = request()->attributes->get('flc_app') === true
            || request()->cookie('flc_app') === '1'
            || str_contains(request()->userAgent() ?? '', 'FLCApp/');

        if ($isApp) {
            return redirect()->route('user.login', ['flc_logout' => '1'])
                ->with('success', 'Signed out.');
        }

        return redirect()->route('user.login')
            ->with('success', 'Signed out.');
    }

    /**
     * One-time handoff: Sanctum Bearer → Laravel web session for in-app WebView.
     * Google OAuth must NOT run inside the WebView.
     */
    public function webviewHandoff(Request $request): RedirectResponse
    {
        $code = (string) $request->query('code', '');
        if ($code === '') {
            return redirect()->route('user.login')
                ->with('error', 'Missing handoff code.');
        }

        $payload = Cache::pull(WebviewSessionController::cacheKey($code));
        if (! is_array($payload) || empty($payload['user_id'])) {
            return redirect()->route('user.login')
                ->with('error', 'Handoff expired or already used. Sign in again.');
        }

        $user = User::query()->find($payload['user_id']);
        if ($user === null) {
            return redirect()->route('user.login')
                ->with('error', 'User not found.');
        }

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        $next = $payload['next'] ?? null;
        if (is_string($next) && str_starts_with($next, '/') && ! str_starts_with($next, '//')) {
            return redirect()->to($next);
        }

        return redirect()->route('user.home.lookup');
    }
}
