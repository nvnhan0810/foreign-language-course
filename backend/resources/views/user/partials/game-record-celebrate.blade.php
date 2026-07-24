@php
    $sessionCorrect = (int) ($sessionCorrect ?? 0);
    $bestCorrect = (int) ($bestCorrect ?? 0);
    $celebrateRecord = $celebrateRecord ?? null;
@endphp

@if (!empty($celebrateRecord))
    <div class="game-record-overlay" data-game-record-celebrate hidden>
        <div class="game-record-burst" aria-hidden="true"></div>
        <div class="game-record-card">
            <p class="game-record-eyebrow">New record</p>
            <p class="game-record-title">Kỷ lục mới!</p>
            <p class="game-record-score">{{ (int) $celebrateRecord }} <span>correct</span></p>
            <p class="game-record-sub">Best run this session so far.</p>
        </div>
    </div>
@endif
