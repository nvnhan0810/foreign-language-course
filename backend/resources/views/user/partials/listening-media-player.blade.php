@if ($media)
    <div class="listening-media-sticky" aria-label="Nghe media">
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
                    Trình duyệt không hỗ trợ phát audio.
                </audio>
            </div>
        @elseif ($media->url && $media->type !== 'youtube')
            <div class="audio-player-bar">
                <p class="audio-player-label">{{ $media->title }}</p>
                <audio controls preload="metadata" src="{{ $media->url }}">
                    Trình duyệt không hỗ trợ phát audio.
                </audio>
            </div>
        @endif
    </div>
@endif
