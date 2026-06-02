<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AllowedEmailEntry;
use App\Models\ListeningAssessment;
use App\Models\MediaItem;
use App\Models\User;
use App\Models\Vocabulary;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        return view('admin.dashboard', [
            'stats' => [
                'users' => User::query()->count(),
                'vocabularies' => Vocabulary::query()->count(),
                'media_items' => MediaItem::query()->count(),
                'listening_assessments' => ListeningAssessment::query()->count(),
                'allowlist' => AllowedEmailEntry::query()->where('is_active', true)->count(),
            ],
        ]);
    }
}
