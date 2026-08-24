import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import { playPronunciation, PronounceSource } from '../../resources/js/lib/pronunciation.js';

describe('playPronunciation', () => {
    it('should_play_audio_url_when_available', async () => {
        const played = [];
        const spoken = [];

        const source = await playPronunciation({
            word: 'happy',
            audioUrl: 'https://example.com/happy.mp3',
            playAudio: async (url) => {
                played.push(url);
            },
            speak: (word) => {
                spoken.push(word);
            },
        });

        assert.equal(source, PronounceSource.AUDIO);
        assert.deepEqual(played, ['https://example.com/happy.mp3']);
        assert.deepEqual(spoken, []);
    });

    it('should_fall_back_to_tts_when_audio_play_fails', async () => {
        const spoken = [];

        const source = await playPronunciation({
            word: 'happy',
            audioUrl: 'https://example.com/broken.mp3',
            playAudio: async () => {
                throw new Error('502');
            },
            speak: (word) => {
                spoken.push(word);
            },
        });

        assert.equal(source, PronounceSource.TTS);
        assert.deepEqual(spoken, ['happy']);
    });

    it('should_use_fetched_audio_when_direct_url_missing', async () => {
        const played = [];

        const source = await playPronunciation({
            word: 'happy',
            audioUrl: null,
            fetchAudioUrl: async () => 'https://example.com/fetched.mp3',
            playAudio: async (url) => {
                played.push(url);
            },
            speak: () => {
                throw new Error('should not speak');
            },
        });

        assert.equal(source, PronounceSource.FETCHED_AUDIO);
        assert.deepEqual(played, ['https://example.com/fetched.mp3']);
    });

    it('should_fall_back_to_tts_when_fetch_audio_also_fails', async () => {
        const spoken = [];

        const source = await playPronunciation({
            word: 'outlet',
            audioUrl: 'https://example.com/broken.mp3',
            fetchAudioUrl: async () => 'https://example.com/also-broken.mp3',
            playAudio: async () => {
                throw new Error('fail');
            },
            speak: (word) => {
                spoken.push(word);
            },
        });

        assert.equal(source, PronounceSource.TTS);
        assert.deepEqual(spoken, ['outlet']);
    });

    it('should_return_none_when_no_word_and_no_audio', async () => {
        const source = await playPronunciation({
            word: '',
            audioUrl: null,
            playAudio: async () => {
                throw new Error('unused');
            },
            speak: () => {
                throw new Error('unused');
            },
        });

        assert.equal(source, PronounceSource.NONE);
    });
});
