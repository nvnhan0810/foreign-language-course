@if ($media)
    <div class="listening-media-sticky" aria-label="Listen to media">
        @if ($media->type === 'youtube' && $media->source_id)
            <div class="video-embed video-embed--compact">
                <iframe
                    src="https://www.youtube.com/embed/{{ $media->source_id }}"
                    title="{{ $media->title }}"
                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                    allowfullscreen
                ></iframe>
            </div>
        @elseif ($media->audio_path)
            <div class="audio-player-bar">
                <p class="audio-player-label">{{ $media->title }}</p>
                <audio controls preload="metadata" src="{{ route('user.media.audio', $media) }}">
                    Your browser does not support audio playback.
                </audio>
            </div>
        @elseif ($media->url && $media->type !== 'youtube')
            <div class="audio-player-bar">
                <p class="audio-player-label">{{ $media->title }}</p>
                <audio controls preload="metadata" src="{{ $media->url }}">
                    Your browser does not support audio playback.
                </audio>
            </div>
        @endif
    </div>
@endif
