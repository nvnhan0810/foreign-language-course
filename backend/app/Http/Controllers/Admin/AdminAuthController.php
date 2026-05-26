<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Laravel\Socialite\Facades\Socialite;

class AdminAuthController extends Controller
{
    public function showLogin(): View|RedirectResponse
    {
        $email = session('admin_email');
        if ($email && in_array(strtolower($email), config('flc.admin_emails', []), true)) {
            return redirect()->route('admin.dashboard');
        }

        return view('admin.login');
    }

    public function redirectGoogle(): RedirectResponse
    {
        return Socialite::driver('google')
            ->redirectUrl(route('admin.auth.google.callback'))
            ->redirect();
    }

    public function callbackGoogle(): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')
                ->redirectUrl(route('admin.auth.google.callback'))
                ->user();
        } catch (\Throwable) {
            return redirect()->route('admin.login')
                ->with('error', 'Đăng nhập Google thất bại.');
        }

        $email = strtolower(trim((string) $googleUser->getEmail()));
        $admins = config('flc.admin_emails', []);

        if ($email === '' || ! in_array($email, $admins, true)) {
            return redirect()->route('admin.login')
                ->with('error', 'Email này không có quyền truy cập admin.');
        }

        session([
            'admin_email' => $email,
            'admin_name' => $googleUser->getName() ?: $email,
        ]);

        return redirect()->route('admin.dashboard');
    }

    public function logout(): RedirectResponse
    {
        session()->forget(['admin_email', 'admin_name']);

        return redirect()->route('admin.login')
            ->with('success', 'Đã đăng xuất.');
    }
}
