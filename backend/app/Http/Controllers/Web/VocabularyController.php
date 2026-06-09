<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Vocabulary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VocabularyController extends Controller
{
    public function index(Request $request): View
    {
        $items = $request->user()
            ->vocabularies()
            ->with('examples')
            ->orderByDesc('created_at')
            ->get();

        return view('user.vocab', ['items' => $items]);
    }

    public function destroy(Request $request, Vocabulary $vocabulary): RedirectResponse
    {
        if ($vocabulary->user_id !== $request->user()->id) {
            abort(403);
        }

        $vocabulary->delete();

        return redirect()->route('user.home.vocab')
            ->with('success', 'Đã xóa từ.');
    }
}
