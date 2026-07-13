<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use Flc\Identity\Application\Query\IsEmailAllowed;
use Flc\Shared\Application\QueryBus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Laravel\Socialite\Facades\Socialite;

class UserAuthController extends Controller
{
    public function __construct(private readonly QueryBus $queries) {}

    public function showLogin(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('user.home.lookup');
        }

        return view('user.login');
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
                ->with('error', 'Đăng nhập Google thất bại.');
        }

        $email = strtolower(trim((string) $googleUser->getEmail()));

        if ($email === '') {
            return redirect()->route('user.login')
                ->with('error', 'Tài khoản Google không có email.');
        }

        if (! $this->queries->ask(new IsEmailAllowed($email))) {
            return redirect()->route('user.login')
                ->with('error', 'Email chưa được phép sử dụng. Liên hệ quản trị để thêm vào allowlist.');
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

        return redirect()->route('user.login')
            ->with('success', 'Đã đăng xuất.');
    }
}
