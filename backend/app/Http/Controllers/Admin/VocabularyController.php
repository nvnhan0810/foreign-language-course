<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Vocabulary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VocabularyController extends Controller
{
    public function index(Request $request): View
    {
        $query = Vocabulary::query()->with('user')->latest();

        if ($search = $request->string('q')->trim()->toString()) {
            $query->where('word', 'like', '%'.$search.'%');
        }

        return view('admin.vocabularies.index', [
            'vocabularies' => $query->paginate(25)->withQueryString(),
            'search' => $search ?? '',
        ]);
    }

    public function edit(Vocabulary $vocabulary): View
    {
        $vocabulary->load(['user', 'examples']);

        return view('admin.vocabularies.edit', compact('vocabulary'));
    }

    public function update(Request $request, Vocabulary $vocabulary): RedirectResponse
    {
        $data = $request->validate([
            'word' => ['required', 'string', 'max:120'],
            'phonetic' => ['nullable', 'string', 'max:120'],
            'meanings_json' => ['required', 'string'],
        ]);

        $meanings = json_decode($data['meanings_json'], true);
        if (! is_array($meanings)) {
            return back()->withErrors(['meanings_json' => 'JSON không hợp lệ.'])->withInput();
        }

        $vocabulary->update([
            'word' => strtolower(trim($data['word'])),
            'phonetic' => $data['phonetic'],
            'meanings' => $meanings,
        ]);

        return redirect()->route('admin.vocabularies.index')
            ->with('success', 'Đã cập nhật từ vựng.');
    }

    public function destroy(Vocabulary $vocabulary): RedirectResponse
    {
        $vocabulary->delete();

        return redirect()->route('admin.vocabularies.index')
            ->with('success', 'Đã xóa từ vựng.');
    }
}
