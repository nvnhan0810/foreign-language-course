<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AllowedEmailEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AllowedEmailController extends Controller
{
    public function index(): View
    {
        return view('admin.allowed-emails.index', [
            'entries' => AllowedEmailEntry::query()->orderByDesc('created_at')->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('admin.allowed-emails.form', [
            'entry' => new AllowedEmailEntry,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        AllowedEmailEntry::query()->create($data);

        return redirect()->route('admin.allowed-emails.index')
            ->with('success', 'Đã thêm email vào allowlist.');
    }

    public function edit(AllowedEmailEntry $allowed_email): View
    {
        return view('admin.allowed-emails.form', [
            'entry' => $allowed_email,
        ]);
    }

    public function update(Request $request, AllowedEmailEntry $allowed_email): RedirectResponse
    {
        $allowed_email->update($this->validated($request));

        return redirect()->route('admin.allowed-emails.index')
            ->with('success', 'Đã cập nhật.');
    }

    public function destroy(AllowedEmailEntry $allowed_email): RedirectResponse
    {
        $allowed_email->delete();

        return redirect()->route('admin.allowed-emails.index')
            ->with('success', 'Đã xóa.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'pattern' => ['required', 'string', 'max:255'],
            'label' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ]) + [
            'is_active' => $request->boolean('is_active'),
        ];
    }
}
