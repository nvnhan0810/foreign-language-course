<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\EmailAllowlistService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    public function __construct(private readonly EmailAllowlistService $allowlist) {}

    public function redirect(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'redirect_uri' => ['required', 'url', 'max:2048'],
        ]);

        if (! $this->isValidExtensionRedirect($data['redirect_uri'])) {
            abort(400, 'redirect_uri không hợp lệ.');
        }

        $state = Str::random(48);
        Cache::put($this->stateKey($state), [
            'redirect_uri' => $data['redirect_uri'],
        ], now()->addMinutes(10));

        return Socialite::driver('google')
            ->stateless()
            ->with(['state' => $state])
            ->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        $state = (string) $request->query('state', '');
        $payload = Cache::pull($this->stateKey($state));

        if (! $payload || empty($payload['redirect_uri'])) {
            return $this->redirectWithError(
                $request->query('redirect_uri'),
                'Phiên đăng nhập không hợp lệ hoặc đã hết hạn.'
            );
        }

        $redirectUri = $payload['redirect_uri'];

        try {
            $googleUser = Socialite::driver('google')->stateless()->user();
        } catch (\Throwable) {
            return $this->redirectWithError($redirectUri, 'Đăng nhập Google thất bại.');
        }

        $email = strtolower(trim((string) $googleUser->getEmail()));

        if ($email === '') {
            return $this->redirectWithError($redirectUri, 'Tài khoản Google không có email.');
        }

        if (! $this->allowlist->isAllowed($email)) {
            return $this->redirectWithError(
                $redirectUri,
                'Email chưa được phép sử dụng. Liên hệ quản trị để thêm vào allowlist.'
            );
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

        $token = $user->createToken('flc-extension')->plainTextToken;

        $query = http_build_query([
            'token' => $token,
            'email' => $user->email,
            'name' => $user->name,
        ]);

        return redirect($redirectUri.'?'.$query);
    }

    private function stateKey(string $state): string
    {
        return 'flc_oauth_state:'.$state;
    }

    private function isValidExtensionRedirect(string $uri): bool
    {
        $parsed = parse_url($uri);

        if (! $parsed || empty($parsed['scheme']) || empty($parsed['host'])) {
            return false;
        }

        $host = strtolower($parsed['host']);

        if ($parsed['scheme'] === 'https' && str_ends_with($host, '.chromiumapp.org')) {
            return true;
        }

        if (in_array($host, ['localhost', '127.0.0.1'], true)) {
            return in_array($parsed['scheme'], ['http', 'https'], true);
        }

        return false;
    }

    private function redirectWithError(?string $redirectUri, string $message): RedirectResponse
    {
        if ($redirectUri && $this->isValidExtensionRedirect($redirectUri)) {
            return redirect($redirectUri.'?'.http_build_query([
                'error' => $message,
            ]));
        }

        abort(403, $message);
    }
}
