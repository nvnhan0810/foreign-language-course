<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AppSettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function edit(AppSettingService $settings): View
    {
        return view('admin.settings.edit', [
            'allow_all_emails' => $settings->getBool('allow_all_emails'),
            'extension_notice' => $settings->get('extension_notice', ''),
            'app_name' => $settings->get('app_name', 'FLC'),
            'vocab_quiz_push_enabled' => $settings->getBool('vocab_quiz_push_enabled', true),
            'env_allowlist' => config('flc.allowed_emails', []),
            'env_allow_all' => config('flc.allow_all_emails', false),
        ]);
    }

    public function update(Request $request, AppSettingService $settings): RedirectResponse
    {
        $data = $request->validate([
            'allow_all_emails' => ['sometimes', 'boolean'],
            'extension_notice' => ['nullable', 'string', 'max:5000'],
            'app_name' => ['nullable', 'string', 'max:120'],
            'vocab_quiz_push_enabled' => ['sometimes', 'boolean'],
        ]);

        $settings->setMany([
            'allow_all_emails' => $request->boolean('allow_all_emails'),
            'extension_notice' => $data['extension_notice'] ?? '',
            'app_name' => $data['app_name'] ?? 'FLC',
            'vocab_quiz_push_enabled' => $request->boolean('vocab_quiz_push_enabled'),
        ]);

        return redirect()->route('admin.settings.edit')
            ->with('success', 'Đã lưu cài đặt.');
    }
}
